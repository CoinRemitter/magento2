<?php

/**
 * Created by PhpStorm.
 * User: kunj
 * Date: 22/5/18
 * Time: 6:29 PM
 */

namespace Coinremitter\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;

class QuoteToOrderObserver implements ObserverInterface
{
    protected $cart;

    public function __construct(
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->cart = $cart;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $quotePayment = $this->cart->getQuote()->getPayment();
        $payment = $observer->getEvent()->getOrder()->getPayment();
        if ($quotePayment->getMethod() === \Coinremitter\Checkout\Model\CoinremitterCheckout::PAYMENT_METHOD_WALLET_CODE) {
            if (!empty($quotePayment->getTransactionResult())) {
                $payment->setTransactionResult($quotePayment->getTransactionResult());
            }
        }
    }
}
