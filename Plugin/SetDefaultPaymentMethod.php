<?php
namespace Coinremitter\Checkout\Plugin;

use Magento\Sales\Model\AdminOrder\Create;

class SetDefaultPaymentMethod
{
    public function afterGetQuote(Create $subject, $quote)
    {
        $payment = $quote->getPayment();
        $payment->setMethod('coinremitter_checkout');
        return $quote;
    }
}
