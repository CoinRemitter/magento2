<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model\Config\Source;

use Coinremitter\Checkout\Model\Wallets\Api;

class Coin implements \Magento\Framework\Option\ArrayInterface
{
    protected $apiCall;
    protected $_debug_logger;

    public function __construct(
        Api $apiCall, \Psr\Log\LoggerInterface $debug_logger
    ) {
        $this->apiCall = $apiCall;
        $this->api_base_url = $this->apiCall->getApiUrl();
        $this->_debug_logger = $debug_logger;
    }

    public function toOptionArray($arr = [])
    {
        $this->_debug_logger->debug('toOptionArray() in coin : arr param : ' . json_encode($arr));
        $coin = [];
        $data = self::getCoin();

        if ($data['flag'] == 1) {
            foreach ($data['data'] as $key => $value) {
                $c['value'] = $value['symbol'];
                $c['label'] = $value['symbol'];
                if ($arr) {
                    if (!in_array($c['value'], $arr)) {
                        array_push($coin, $c);
                    }
                } else {
                    array_push($coin, $c);
                }
            }
            $res = array('flag' => 1, 'data' => $coin);
        } else {
            $res = $data;
        }

        $this->_debug_logger->debug('toOptionArray() in coin : return coin : ' . json_encode($res));
        return $res;
    }

    public function getCoin()
    {
        $url = $this->api_base_url . "/get-coin-rate";
        $data = $this->apiCall->apiCaller($url, \Zend_Http_Client::GET);

        $this->_debug_logger->debug('getCoin() in coin : response of api : ' . json_encode($data));
        return $data;
    }
}
