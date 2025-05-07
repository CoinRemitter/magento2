<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model;

use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Pay In Store payment method model
 */
class CoinremitterCheckout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_WALLET_CODE = 'coinremitter_checkout';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_WALLET_CODE;


    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    /*protected $_formBlockType = 'Coinremitter\Checkout\Block\Form\Pickpay';

    protected $_infoBlockType = 'Coinremitter\Checkout\Block\Info\Pickpay';*/
    
    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
    */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        /*$additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        $this->getInfoInstance()->setWalletCoin($additionalData->getWalletCoin());
        return $this;*/

        parent::assignData($data);
        $infoInstance = $this->getInfoInstance();
        
        
        if(is_array($data->getData('additional_data')))
        {
            $additionalData = $data->getData('additional_data');
            $pickPayLocation = isset($additionalData['transaction_result'])?$additionalData['transaction_result']:"";
            $infoInstance->setAdditionalInformation('transaction_result', $pickPayLocation);
            $data->setTransactionResult($pickPayLocation);
            $infoInstance->setTransactionResult($pickPayLocation);
        }
        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return true;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return true;
    }
}
