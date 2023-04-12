<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

use Magento\Backend\App\Action;
use Coinremitter\Checkout\Model\Wallets;
// use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Zend\Http\Request;
class Save extends \Magento\Backend\App\Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var \Coinremitter\Checkout\Model\WalletsFactory
     */
    private $walletsFactory;

    /**
     * @var \Coinremitter\Checkout\Api\WalletsRepositoryInterface
     */
    private $walletsRepository;
    protected $apiCall;
    /**
     * @param Action\Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory
     * @param \Coinremitter\Checkout\Api\WalletsRepositoryInterface $walletsRepository
     */

    protected $encryptor;

    protected $fileDriver;

    public function __construct(
        Action\Context $context,
        // DataPersistorInterface $dataPersistor,
        \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory = null,
        \Coinremitter\Checkout\Api\WalletsRepositoryInterface $walletsRepository = null,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        EncryptorInterface $encryptor
    ) {
        // $this->dataPersistor = $dataPersistor;
        $this->walletsFactory = $walletsFactory
        ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Coinremitter\Checkout\Model\WalletsFactory::class);
        $this->walletsRepository = $walletsRepository
        ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Coinremitter\Checkout\Api\WalletsRepositoryInterface::class);
        $this->apiCall = $apiCall;
        $this->api_base_url = $this->apiCall->getApiUrl();
        $this->fileDriver = $fileDriver;
        $this->encryptor = $encryptor;
        parent::__construct($context);
    }
    
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Coinremitter_Checkout::save');
	}

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (empty($data['id'])) {
                $data['id'] = null;
            }
            $walletArr = [];
            /** @var \Coinremitter\Checkout\Model\Wallets $model */
            $model = $this->walletsFactory->create();

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->walletsRepository->getById($id);
                    $walletData = $model->getData();
                    $data['coin'] = $walletData['coin'];
                    
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This wallet no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }else{
                $walletsCollection = $model->getCollection()->addFieldToSelect('coin');
                $arr = $walletsCollection->getData();
                if ($arr) {
                    foreach ($arr as $key => $value) {
                        array_push($walletArr, $value['coin']);
                    }   
                }
                if (in_array($data['coin'], $walletArr)) {
                    $this->messageManager->addErrorMessage(__($data['coin'].' coin wallet already exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }
            $data['password'] = $this->encryptor->encrypt($data['password']);
            $postData = [
                'api_key'=>$data['api_key'],
                'password'=>$data['password']
            ];
            $url = $this->api_base_url."/".$data['coin']."/get-balance";
            $res = $this->apiCall->apiCaller($url, Request::METHOD_POST,$postData);
            if (isset($res['flag']) && $res['flag'] != 1) {
                $this->messageManager->addErrorMessage(__('Invalid Api key or Password.'));
                return $resultRedirect->setPath('*/*/');
            }
            
            $coinremitter_ex_rate_value = $data['exchange_rate_multiplier'];
      
            if($coinremitter_ex_rate_value == ''){

                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field is required'));
                return $resultRedirect->setPath('*/*/');
                
            }else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)){
                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field is invalid'));
                return $resultRedirect->setPath('*/*/');
                
            }else if($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101){
                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field should be between 0 to 101'));
                return $resultRedirect->setPath('*/*/');
            }

            $minimum_value = $data['minimum_value'];

            $url = $this->api_base_url."/get-coin-rate";
            $rate_ras = $this->apiCall->apiCaller($url, Request::METHOD_GET);
            $coin_price = $rate_ras['data'][$data['coin']]['price'];
            $ten_usd_price = 10 / $coin_price;
           
            if($minimum_value == ''){
                $this->messageManager->addErrorMessage(__('Minimum value field is required'));
                return $resultRedirect->setPath('*/*/');
                
            }else if(!preg_match('/^[0-9]+(\.[0-9]{1,8})?$/', $minimum_value)){
                $this->messageManager->addErrorMessage(__('Invoice Minimum value field is invalid'));
                return $resultRedirect->setPath('*/*/');

            }else if($minimum_value < $ten_usd_price){
                $this->messageManager->addErrorMessage(__('Invoice Minimum value should be greater than '.$ten_usd_price));
                return $resultRedirect->setPath('*/*/');
            }



            $data['name'] = $res['data']['wallet_name'];
            $data['coin_name'] = $res['data']['coin_name'];
            $model->setData($data);

            $this->_eventManager->dispatch(
                'coinremitter_wallets_prepare_save',
                ['wallets' => $model, 'request' => $this->getRequest()]
            );

            try {
                $this->walletsRepository->save($model);

                /*download coin image if not exists*/
                $filename = strtolower($data['coin']).'.png';
                $coin_image_path =  $this->getRootPath().'/app/code/Coinremitter/Checkout/view/adminhtml/web/images/'.$data['coin']."/".$filename;
                if(!$this->fileDriver->isExists($coin_image_path)){                       
                    mkdir($this->getRootPath().'/app/code/Coinremitter/Checkout/view/adminhtml/web/images/'.$data['coin'],0777);
                    $url = "https://coinremitter.com/assets/img/home-coin/coin/".$filename;
                    if (getimagesize($url)) {
                        copy($url,$coin_image_path);
                    }
                }
                $this->messageManager->addSuccessMessage(__('Wallet saved successfully done.'));
                // $this->getDataPersistor()->clear('coinremitter_wallets');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {}
            $this->messageManager->addSuccessMessage(__('Wallet saved successfully done.'));
            return $resultRedirect->setPath('*/*/');
        }
        return $resultRedirect->setPath('*/*/');
    }

    public function getRootPath()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        return $directory->getRoot();
    }
}
