<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Coinremitter\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{

    public const CODE = 'sample_gateway';
    
    protected $_scopeConfig;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $sql = "SELECT * FROM coinremitter_wallets";
        $result = $connection->fetchAll($sql);
        $number_of_wallet = count($result);
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        $grandTotal = $cart->getQuote()->getGrandTotal();
        $subTotal = $cart->getQuote()->getSubtotal();
        $otherTotal = $grandTotal - $subTotal;
        
        $currencysymbol = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $currencyCode = $currencysymbol->getStore()->getCurrentCurrencyCode();
        
        // dd(count($result));
        // echo "<pre>";
        // print_r($cart->getQuote()->getSubtotal());
        // print_r($cart->getQuote()->getShippingRate());
        // print_r($cart->getShippingAddress()->getTaxAmount());
        $api_call = $objectManager->get('\Coinremitter\Checkout\Model\Wallets\Api');
        $validate_wallet = [];
        for ($i = 0; $i < $number_of_wallet; $i++) {
            $orderTotal = ($subTotal * $result[$i]['exchange_rate_multiplier']) + $otherTotal;
            
            if ($result[$i]['base_fiat_symbol'] != $currencyCode) {
                // print_r($result[$i]);
                // die;

                $fiatToCryptoConversionParam = [
                    'crypto' => $result[$i]['coin_symbol'],
                    'fiat' => $result[$i]['base_fiat_symbol'],
                    'fiat_amount' => $result[$i]['minimum_invoice_amount']
                ];
                $fiatToCryptoConversionRes = $api_call->getFiatToCryptoRate($fiatToCryptoConversionParam);
                $cryptoToFiatConversionParam = [
                    'crypto' => $result[$i]['coin_symbol'],
                    'crypto_amount' => $fiatToCryptoConversionRes['data'][0]['price'],
                    'fiat' => $currencyCode
                ];
                $cryptoToFiatConversionRes = $api_call->getCryptoToFiatRate($cryptoToFiatConversionParam);
                
                if ($cryptoToFiatConversionRes['success']) {
                    $minimumInvAmountInFiat = $cryptoToFiatConversionRes['data'][0]['amount'];
                    $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
                    $result[$i]['minimum_invoice_amount'] = $minimumInvAmountInFiat;
                    //update table entry
                    
                    $data = ["minimum_invoice_amount" => $minimumInvAmountInFiat,'base_fiat_symbol' => $currencyCode];
                    $where = ['id' => $result[$i]['id']];
                    $connection->update('coinremitter_wallets', $data, $where);
                }
            }


            $add_param['api_key'] = $result[$i]['api_key'];
            $add_param['password'] = $result[$i]['password'];
            $add_param['coin_symbol'] = $result[$i]['coin_symbol'];
            $add_param["fiat_symbol"] = $currencyCode;
            $add_param['fiat_amount'] = $orderTotal;
            

            $fiatToCryptoConversion = [
                'crypto' => $result[$i]['coin_symbol'],
                'fiat' => $currencyCode,
                'fiat_amount' => $orderTotal
            ];

            // print_r($fiatToCryptoConversion);
            // die;
            $currency_data = $api_call->getFiatToCryptoRate($fiatToCryptoConversion);
            if (!$currency_data['success']) {
                continue;
            }
            // echo '<br>';
            // print_r($orderTotal);
            // echo '<br>';
            // print_r($result[$i]['minimum_invoice_amount']);
            //if cart value is greater than minimum value of coin, than only that coin should display in dropdown
            if ($orderTotal >= $result[$i]['minimum_invoice_amount']) {
                $validate_wallet[$i]['coin_symbol'] = $result[$i]['coin_symbol'];
                $validate_wallet[$i]['id'] = $result[$i]['id'];
                $validate_wallet[$i]['coin_name'] = $result[$i]['coin_name'];
            }
        }
        // echo "<pre>";
        // print_r($validate_wallet);
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
            $_env,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $_val;
    }
}
