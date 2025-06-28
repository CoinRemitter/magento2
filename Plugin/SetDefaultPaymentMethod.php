<?php

namespace Coinremitter\Checkout\Plugin;

use Magento\Sales\Model\AdminOrder\Create;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class SetDefaultPaymentMethod
{
    protected $paymentHelper;
    protected $scopeConfig;
    protected $storeManager;

    public function __construct(
        PaymentHelper $paymentHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    public function afterGetQuote(Create $subject, $quote)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $methods = $this->paymentHelper->getStoreMethods($storeId, $quote);
        $enabledMethods = [];

        foreach ($methods as $method) {
            $code = $method->getCode();
            $isActive = $this->scopeConfig->isSetFlag(
                "payment/{$code}/active",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if ($isActive) {
                $enabledMethods[] = $code;
            }
        }
        if (!empty($enabledMethods)) {
            $quote->getPayment()->setMethod($enabledMethods[0]);
        }

        return $quote;
    }
}
