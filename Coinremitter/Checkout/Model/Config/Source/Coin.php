<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model\Config\Source;

use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;

class Coin implements \Magento\Framework\Option\ArrayInterface
{
	protected $apiCall;

    public function __construct(
        Api $apiCall
    ) {
        $this->apiCall = $apiCall;
        $this->api_base_url = $this->apiCall->getApiUrl();
    }

    public function toOptionArray($arr=[])
    {
		$coin = [];
    	$data = self::getCoin();
		if ($data) {
            foreach ($data as $key => $value) {
                $c['value'] = $value['symbol'];
                $c['label'] = $value['symbol'];
                if ($arr) {
                    if (!in_array($c['value'], $arr)) {
                        array_push($coin, $c);   
                    }
                }else{
                    array_push($coin, $c);
                }
            }    
        }
    	return $coin;
    }

    function getCoin()
    {
        $data = [];
        $url = $this->api_base_url."/get-coin-rate";
        $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::GET);

        if (isset($res['flag']) && $res['flag'] == 1) {
            $data = $res['data'];
        }
        return $data;
    }
}

