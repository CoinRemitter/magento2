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
                    $data['coin_symbol'] = $walletData['coin_symbol'];
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This wallet no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            } else {
                $walletsCollection = $model->getCollection()->addFieldToSelect('coin_symbol');
                $arr = $walletsCollection->getData();
                if ($arr) {
                    foreach ($arr as $key => $value) {
                        array_push($walletArr, $value['coin_symbol']);
                    }
                }
                // if (in_array($data['coin'], $walletArr)) {
                //     $this->messageManager->addErrorMessage(__($data['coin'].' coin wallet already exists.'));
                //     return $resultRedirect->setPath('*/*/');
                // }
            }
            $data['password'] = $this->encryptor->encrypt($data['password']);
            $credencial = [
                'x-api-key' => $data['api_key'],
                'x-api-password' => $data['password']
            ];
            $walletData = $this->apiCall->getWalletBalance([], $credencial);
            if ($walletData['success'] != 1) {
                $this->messageManager->addErrorMessage(__('Invalid Api key or Password.'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }
            $walletData = $walletData['data'];
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $walletData['coin_symbol'] . "'";

            if ($data['id'] != null) {
                $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $walletData['coin_symbol'] . "' AND `id` != '" . $data['id'] . "'";
            }

            $checkDuplicateWallet = $connection->fetchAll($sql);
            if (!empty($checkDuplicateWallet)) {
                $this->messageManager->addErrorMessage(__('Wallet with this coin already exists.'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }

            $coinremitter_ex_rate_value = $data['exchange_rate_multiplier'];

            if ($coinremitter_ex_rate_value == '') {

                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field is required'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            } else if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)) {
                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field is invalid'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            } else if ($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101) {
                $this->messageManager->addErrorMessage(__('Exchange rate multiplier field should be between 0 to 101'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }

            $minimum_value = $data['minimum_invoice_amount'];

            if ($minimum_value == '') {
                $this->messageManager->addErrorMessage(__('Minimum value field is required'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            } else if (!preg_match('/^[0-9]+(\.[0-9]{1,8})?$/', $minimum_value)) {
                $this->messageManager->addErrorMessage(__('Invoice Minimum value field is invalid'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }
            
            $coinRate = $this->apiCall->getCoinData($walletData['coin_symbol']);
            if (empty($coinRate)) {
                $this->messageManager->addErrorMessage(__('Invalid api key and password.'));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }


            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $currencysymbol = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
            $baseFiatCurrency = $currencysymbol->getStore()->getCurrentCurrencyCode();


            $unit_fiat_amount = $coinRate['price_in_usd'];
            if (strtoupper($baseFiatCurrency) != 'USD') {
                $conversionParam = array(
                    'crypto' => $walletData['coin_symbol'],
                    'crypto_amount' => 1,
                    'fiat' => $baseFiatCurrency
                );

                $convertionRes = $this->apiCall->getCryptoToFiatRate($conversionParam);
                if (!$convertionRes['success']) {
                    $this->messageManager->addErrorMessage(__('Opps, something might wrong!'));
                    $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
                }

                $unit_fiat_amount = $convertionRes['data'][0]['amount'];
            }

            $minimumInvAmountInFiat = $walletData['minimum_deposit_amount'] * $unit_fiat_amount;
            $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');

            if ($minimum_value < $minimumInvAmountInFiat) {
                $this->messageManager->addErrorMessage(__('Minimum value should be greater than or equal to ' . $minimumInvAmountInFiat . ' ' . $baseFiatCurrency));
                $resultRedirect->setRefererUrl('*/*/');
                return $resultRedirect;
            }

            $createWalletData = array(
                'wallet_name' => $walletData['wallet_name'],
                'coin_symbol' => $walletData['coin_symbol'],
                'coin_name' => $walletData['coin'],
                'api_key' => $data['api_key'],
                'password' => $data['password'],
                'exchange_rate_multiplier' => $coinremitter_ex_rate_value,
                'minimum_invoice_amount' => $minimum_value,
                'unit_fiat_amount' => $unit_fiat_amount,
                'base_fiat_symbol' => $baseFiatCurrency,
            );
            
            if ($data['id'] != null) {
                $createWalletData['id'] = $data['id'];
            }
            $model->setData($createWalletData);

            $this->_eventManager->dispatch(
                'coinremitter_wallets_prepare_save',
                ['wallets' => $model, 'request' => $this->getRequest()]
            );

            try {
                $this->walletsRepository->save($model);
                
                /*download coin image if not exists*/
                $filename = strtoupper($walletData['coin_symbol']) . '.png';
                $coin_image_path =  $this->getRootPath() . '/view/adminhtml/web/images/'. $filename;
                if (!$this->fileDriver->isExists($coin_image_path)) {
                    $url = "https://coinremitter.com/assets/img/coins/32x32/" . $walletData['coin_symbol'] . '.png';
                    if (getimagesize($url)) {
                        copy($url, $coin_image_path);
                    }
                }
                $this->messageManager->addSuccessMessage(__('Wallet saved successfully.'));
                // $this->getDataPersistor()->clear('coinremitter_wallets');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?: $e);
            } catch (\Exception $e) {

                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the wallet.'));
                return $resultRedirect->setPath('*/*/');
            }
            $this->messageManager->addSuccessMessage(__('Wallet saved successfully.'));
            return $resultRedirect->setPath('*/*/');
        }
        return $resultRedirect->setPath('*/*/');
    }

    public function getRootPath()
    {
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Module\Dir\Reader');
        return $directory->getModuleDir('', 'Coinremitter_Checkout');
    }
}
