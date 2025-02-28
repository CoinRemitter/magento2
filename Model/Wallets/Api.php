<?php

namespace Coinremitter\Checkout\Model\Wallets;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Zend\Http\Client;
use Zend\Http\Request;
use Magento\Framework\App\ProductMetadataInterface;


class Api
{
    protected $_httpClient;
    protected $api_url;
    protected $_debug_logger;
    protected $encryptor;
    protected $pluginVersion;
    private   $apiVersion = 'v1';
    private $storeManager;
    private $currencyFactory;
    protected $magentoVersion;
    private   $url = 'https://api.coinremitter.com/';
    public   $orderStatusCode = array(
        'pending' => 0,
        'paid' => 1,
        'under_paid' => 2,
        'over_paid' => 3,
        'expired' => 4,
        'cancelled' => 5,
    );

    public $orderStatus = array('Pending', 'Paid', 'Under Paid', 'Over Paid', 'Expired', 'Cancelled');
    public $truncationValue = 0.5; // in USD

    public function __construct(
        ZendClientFactory $httpClient,
        \Psr\Log\LoggerInterface $debug_logger,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagerObj,
    ) {
        $this->_httpClient = new Client();
        $object = \Magento\Framework\App\ObjectManager::getInstance();
        $this->pluginVersion = $object->get('Magento\Framework\Module\ModuleList')->getOne('Coinremitter_Checkout')['setup_version'];
        $this->api_url = $this->url . $this->apiVersion;
        $this->_debug_logger = $debug_logger;
        $this->encryptor = $encryptor;
        $this->currencyFactory = $currencyFactory;
        $this->storeManager = $storeManagerObj;
        $this->magentoVersion = $productMetadata->getVersion();
    }

    public function getWalletBalance($param = [], $header = [])
    {
        if (!isset($header['x-api-key']) || !isset($header['x-api-password'])) {
            return array('success' => 0, 'msg' => 'API key and password is required');
        }
        $header = [
            'x-api-key:' . $header['x-api-key'],
            'x-api-password:' . $this->encryptor->decrypt($header['x-api-password'])
        ];
        return $this->apiCaller("/wallet/balance", $param, $header);
    }
    public function getNewAddress($param = [], $header = [])
    {
        if (!isset($header['x-api-key']) || !isset($header['x-api-password'])) {
            return array('success' => 0, 'msg' => 'API key and password is required');
        }
        $header = [
            'x-api-key:' . $header['x-api-key'],
            'x-api-password:' . $this->encryptor->decrypt($header['x-api-password'])
        ];
        return $this->apiCaller("/wallet/address/create", $param, $header);
    }
    public function getTransactionByAddress($param = [], $header = [])
    {
        if (!isset($header['x-api-key']) || !isset($header['x-api-password'])) {
            return array('success' => 0, 'msg' => 'API key and password is required');
        }
        $header = [
            'x-api-key:' . $header['x-api-key'],
            'x-api-password:' . $this->encryptor->decrypt($header['x-api-password'])
        ];
        return $this->apiCaller("/wallet/address/transactions", $param, $header);
    }
    public function getTransaction($param = [], $header = [])
    {
        if (!isset($header['x-api-key']) || !isset($header['x-api-password'])) {
            return array('success' => 0, 'msg' => 'API key and password is required');
        }
        $header = [
            'x-api-key:' . $header['x-api-key'],
            'x-api-password:' . $this->encryptor->decrypt($header['x-api-password'])
        ];
        return $this->apiCaller("/wallet/transaction", $param, $header);
    }
    public function checkTransactionExists($transactions, $trx_id)
    {
        foreach ($transactions as $transaction) {
            if ($transaction['txid'] == $trx_id) {
                return $transaction;
            }
        }
        return [];
    }

    public function getCoinData($coin_symbol)
    {
        $apiResponse = $this->apiCaller("/rate/supported-currency");
        if (!$apiResponse['success']) {
            return [];
        }
        foreach ($apiResponse['data'] as $coin) {
            if ($coin['coin_symbol'] == $coin_symbol) {
                return $coin;
            }
        }
        return [];
    }

    public function getFiatToCryptoRate($param)
    {
        return $this->apiCaller("/rate/fiat-to-crypto", $param);
    }
    public function getCryptoToFiatRate($param)
    {
        return $this->apiCaller("/rate/crypto-to-fiat", $param);
    }

    public function prepareReturnTrxData($transactions)
    {
        foreach ($transactions as $trxId => $trx) {
            unset($transactions[$trxId]['wallet_id']);
            unset($transactions[$trxId]['wallet_name']);
            unset($transactions[$trxId]['type']);
            unset($transactions[$trxId]['label']);
            unset($transactions[$trxId]['required_confirmations']);
            unset($transactions[$trxId]['id']);
        }
        return $transactions;
    }

    public function convertPrice($price, $currencyCodeFrom, $currencyCodeTo)
    {
        $rate = $this->currencyFactory->create()->load($currencyCodeFrom)->convert($price, $currencyCodeTo);
        $convertedPrice = $price * $rate;
        return $convertedPrice;
    }

    private function apiCaller($route, $param = [], $header = null)
    {
        $url = $this->api_url . $route;
        $this->_debug_logger->debug('Called API : ' . $url);
        $userAgent = 'Magento@' . $this->magentoVersion . ', crplugin@' . $this->pluginVersion;
        $apiCaller = new Request();
        $apiCaller->setUri($url);
        $apiCaller->setMethod(Request::METHOD_POST);
        if ($param && !empty($param)) {
            $apiCaller->getPost()->fromArray($param);
        }
        try {

            $requestHeader = [
                'User-Agent:' . $userAgent
            ];
            $this->_httpClient->setRequest($apiCaller);
            if (isset($header['x-api-password'])) {
                $header['x-api-password'] = $this->encryptor->decrypt($header['x-api-password']);
            }
            if ($header) {
                array_push($requestHeader, ...$header);
            }
            $this->_httpClient->setHeaders($requestHeader);
            $res = $this->_httpClient->send();

            $statusCode = $res->getStatusCode();
            $this->_debug_logger->debug("URL : " . $route . " :: Status Code : " . $statusCode);

            $apiRes = json_decode($res->getBody(), true);
            $this->_debug_logger->debug('Api Response : ' . json_encode($apiRes));

            if ($statusCode == 200) {
                if ($apiRes['success'] != 1) {
                    $apiRes = array('success' => 0, 'msg' => 'Oops, something went wrong. Please contact admin.');
                }
            } else {
                $apiRes = array('success' => 0, 'msg' => 'Oops, something went wrong. Please contact admin.');
            }
        } catch (\Exception $e) {

            $this->_debug_logger->error($e);
            $this->_debug_logger->error('api caller : error while api call, in catch now');
            $apiRes = array('success' => 0, 'msg' => "Couldn't connect to coinremitter.com. Please check your internet connection");
        }
        return $apiRes;
    }
    public function getApiUrl()
    {
        return $this->api_url;
    }
}
