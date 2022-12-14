<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model\Ui;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\HTTP\ZendClientFactory;


/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{

    const CODE = 'sample_gateway';
    
    protected $_scopeConfig;
    
    public function __construct( 
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {       
        $this->_scopeConfig = $scopeConfig;
        
    }

    public function getConfig()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('coinremitter_wallets');      

        if($connection->tableColumnExists($tableName, 'is_valid') === false){
            $connection->addColumn('coinremitter_wallets', 'is_valid', array(
                'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable'  => false,
                'length'    => 1,
                'default'   => 1,
                'after'     => 'password', // column name to insert new column after
                'comment'   => '1 on valid wallet else 0'
            ));  
        }

        $sql = "SELECT * FROM coinremitter_wallets WHERE `is_valid` = '1'";
        $result = $connection->fetchAll($sql);
        $number_of_wallet = count($result);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        $total = $cart->getQuote()->getGrandTotal();
        $currencyCode = $cart->getQuote();


        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $currencysymbol = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $currencyCode = $currencysymbol->getStore()->getCurrentCurrencyCode();
        

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $api_call = $objectManager->get('\Coinremitter\Checkout\Model\Wallets\Api');
        $api_url = $api_call->getApiUrl();
        // dd(count($result));
        $validate_wallet = [];
        for($i=0; $i<$number_of_wallet; $i++){

            $add_param['api_key'] = $result[$i]['api_key'];
            $add_param['password'] = $result[$i]['password'];
            $add_param['coin'] = $result[$i]['coin'];
            $add_param["fiat_symbol"] = $currencyCode;
            $add_param['fiat_amount'] = $total * $result[$i]['exchange_rate_multiplier'];
            
            $url = $api_url."/".$result[$i]['coin']."/get-fiat-to-crypto-rate";
            $currency_data = $api_call->apiCaller($url, \Zend_Http_Client::POST,$add_param);
            // dd($res);
            // echo '<pre>';
            // print_r($currency_data);

            //if cart value is greater than minimum value of coin, than only that coin should display in dropdown
            if($currency_data['data']['crypto_amount'] >= $result[$i]['minimum_value']){
                $validate_wallet[$i]['coin'] = $result[$i]['coin'];
                $validate_wallet[$i]['id'] = $result[$i]['id'];
                $validate_wallet[$i]['coin_name'] = $result[$i]['coin_name'];
            }
        }
        // die();
        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => $validate_wallet,
                    'payment_description' => $this->getStoreConfig('payment/coinremitter_checkout/description'),
                    'isWallets' => !empty($validate_wallet) ? true : false
                ]
            ]
        ];
    }
    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $_val;

    }
}
