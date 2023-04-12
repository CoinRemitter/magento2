<?php

namespace Coinremitter\Checkout\Model\Wallets;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;

class Api
{
    protected $_httpClient;
    protected $api_url ;
    protected $_debug_logger;
    protected $encryptor;
    private   $version = 'v3';
    private   $url = 'https://coinremitter.com/api/';

    public function __construct(
        ZendClientFactory $httpClient,
        \Psr\Log\LoggerInterface $debug_logger,
        EncryptorInterface $encryptor
    )
    {
        $this->_httpClient = new Client();
        $this->api_url = $this->url.$this->version;
        $this->_debug_logger = $debug_logger;
        $this->encryptor = $encryptor;
        
    }

    public function apiCaller($url, $method, $param=[], $header = null) {
        
        $object_instance = \Magento\Framework\App\ObjectManager::getInstance();
        $moduleInfo =  $object_instance->get('Magento\Framework\Module\ModuleList')->getOne('Coinremitter_Checkout');

        $this->_debug_logger->debug('Api Caller Called!!!');
        $moduleVersion = $moduleInfo['setup_version']; //extenstion version
        $userAgent = 'CR@' . $this->version . ',magento checkout@'.$moduleVersion;
        // $apiCaller = $this->_httpClient->create();
        $apiCaller = new Request();
        // echo "mee";
        // die;
        $apiCaller->setUri($url);
        $apiCaller->setMethod($method);
        // $apiCaller->setHeaders("");
        // $apiCaller->setConfig(['timeout' => 120]);
        if ($param && !empty($param)) {
            if(isset($param['password'])){
                $param['password'] = $this->encryptor->decrypt($param['password']);
            }
            $apiCaller->getPost()->fromArray($param);
            // $apiCaller->setParameterPost($param); //or parameter get   
        }
        try {
            $this->_debug_logger->debug('api caller : in try');    
            $this->_debug_logger->debug('api caller : before api request');    
            // $res = $apiCaller->request();
            $this->_httpClient->setRequest($apiCaller);
            $this->_httpClient->setHeaders([
                'Key: '. $header,
                'User-Agent:'.$userAgent
            ]);
            $res = $this->_httpClient->send();
            // $res = $response->getBody();
            $this->_debug_logger->debug('api caller : after api request');

            if($res->getStatusCode() == 200){
                $res = json_decode($res->getBody(), true);
                $this->_debug_logger->debug('Api Response : '. json_encode($res));
                if($res['flag'] != 1){
                   $res = array('flag' => 0, 'msg' => 'Oops, something went wrong. Please contact admin.'); 
                }
            }else{
                $res = array('flag' => 0, 'msg' => 'Oops, something went wrong. Please contact admin.');
            }
            
        } catch (\Exception $e) {
            $this->_debug_logger->debug('api caller : error while api call, in catch now');    
            $res = array('flag' => 0, 'msg' => "Couldn't connect to coinremitter.com. Please check your internet connection");
        }
        return $res;
    }
    public function getApiUrl(){
        return $this->api_url;
    }
}