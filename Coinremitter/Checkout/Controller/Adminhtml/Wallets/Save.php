<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

use Magento\Backend\App\Action;
use Coinremitter\Checkout\Model\Wallets;
// use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;

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
    public function __construct(
        Action\Context $context,
        // DataPersistorInterface $dataPersistor,
        \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory = null,
        \Coinremitter\Checkout\Api\WalletsRepositoryInterface $walletsRepository = null,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall
    ) {
        // $this->dataPersistor = $dataPersistor;
        $this->walletsFactory = $walletsFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Coinremitter\Checkout\Model\WalletsFactory::class);
        $this->walletsRepository = $walletsRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Coinremitter\Checkout\Api\WalletsRepositoryInterface::class);
        $this->apiCall = $apiCall;
        $this->api_base_url = $this->apiCall->getApiUrl();
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
                    $this->messageManager->addErrorMessage(__('This news no longer exists.'));
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

            $postData = [
                'api_key'=>$data['api_key'],
                'password'=>$data['password']
            ];
            $url = $this->api_base_url."/".$data['coin']."/get-balance";
            $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$postData);

            if (isset($res['flag']) && $res['flag'] != 1) {
                $this->messageManager->addErrorMessage(__('Invalid Api key or Password.'));
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
                $this->messageManager->addSuccessMessage(__('Wallet saved successfully done.'));
                // $this->getDataPersistor()->clear('coinremitter_wallets');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the news.'));
            }
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}