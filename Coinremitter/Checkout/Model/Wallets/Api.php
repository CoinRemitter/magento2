<?php

namespace Coinremitter\Checkout\Model\Wallets;

use Magento\Framework\HTTP\ZendClientFactory;

class Api
{
    protected $_logger;
    protected $_httpClient;
    protected $api_url ;
    public function __construct(ZendClientFactory $httpClient,\Coinremitter\Checkout\Logger\Logger $logger)
    {
        $this->_httpClient = $httpClient;
        $this->_logger = $logger;
        $this->api_url = 'http://192.168.0.102/coinremitter/public/api';
    }

    public function apiCaller($url, $method, $param=[], $header = null) {
        $this->_logger->info('apiCaller : '.$url);
        $this->_logger->info('param '.json_encode($param));

        $apiCaller = $this->_httpClient->create();
        $apiCaller->setUri($url);
        $apiCaller->setMethod($method);
        $apiCaller->setHeaders([
            'Content-Type: application/json',
            'Accept: application/json',
            'Key: ' . $header,
        ]);
        if ($param && !empty($param)) {
            $apiCaller->setParameterPost($param); //or parameter get   
        }
        $res = $apiCaller->request();
        $res = json_decode($res->getBody(), true);
        $this->_logger->info('apiCaller Response: ');
        $this->_logger->info(json_encode($res));
        return $res;
    }
    public function getApiUrl(){
        return $this->api_url;
    }
}