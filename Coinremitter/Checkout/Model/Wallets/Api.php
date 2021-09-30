<?php

namespace Coinremitter\Checkout\Model\Wallets;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\ZendClientFactory;

class Api
{
    protected $_httpClient;
    protected $api_url;
    protected $_debug_logger;
    protected $encryptor;
    private $version = 'v3';
    private $url = 'https://coinremitter.com/api/';

    public function __construct(
        ZendClientFactory $httpClient,
        \Psr\Log\LoggerInterface $debug_logger,
        EncryptorInterface $encryptor
    ) {
        $this->_httpClient = $httpClient;
        $this->api_url = $this->url . $this->version;
        $this->_debug_logger = $debug_logger;
        $this->encryptor = $encryptor;

    }

    public function apiCaller($url, $method, $param = [], $header = null)
    {

        $object_instance = \Magento\Framework\App\ObjectManager::getInstance();
        $moduleInfo = $object_instance->get('Magento\Framework\Module\ModuleList')->getOne('Coinremitter_Checkout');

        $this->_debug_logger->debug('Api Caller Called!!!');
        $moduleVersion = $moduleInfo['setup_version']; //extenstion version
        $userAgent = 'CR@' . $this->version . ',magento checkout@' . $moduleVersion;
        $apiCaller = $this->_httpClient->create();
        $apiCaller->setUri($url);
        $apiCaller->setMethod($method);
        $apiCaller->setHeaders([
            'Content-Type: application/json',
            'Accept: application/json',
            'Key: ' . $header,
            'User-Agent:' . $userAgent,
        ]);
        $apiCaller->setConfig(['timeout' => 120]);
        if ($param && !empty($param)) {
            if (isset($param['password'])) {
                $param['password'] = $this->encryptor->decrypt($param['password']);
            }
            $apiCaller->setParameterPost($param); //or parameter get
        }
        try {
            $this->_debug_logger->debug('api caller : in try');
            $this->_debug_logger->debug('api caller : before api request');
            $res = $apiCaller->request();
            $this->_debug_logger->debug('api caller : after api request');

            if ($res->getStatus() == 200) {
                $res = json_decode($res->getBody(), true);
            } else {
                $res = array('flag' => 0, 'msg' => $res->getMessage() . '. Please check coinremitter.com API URL ');
            }

        } catch (\Exception $e) {
            $this->_debug_logger->debug('api caller : error while api call, in catch now');
            $res = array('flag' => 0, 'msg' => "Couldn't connect to coinremitter.com. Please check your internet connection");
        }
        $this->_debug_logger->debug('Api Response : ' . json_encode($res));
        return $res;
    }
    public function getApiUrl()
    {
        return $this->api_url;
    }
}
