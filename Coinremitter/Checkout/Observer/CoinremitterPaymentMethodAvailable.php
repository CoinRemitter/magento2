<?php
 
namespace Coinremitter\Checkout\Observer;
 
use Magento\Framework\Event\ObserverInterface;
 
 
class CoinremitterPaymentMethodAvailable implements ObserverInterface
{
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig 
    ) {
       
        $this->_scopeConfig = $scopeConfig;
       

    }
    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $_val;

    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        if($observer->getEvent()->getMethodInstance()->getCode()=="coinremitter_checkout"){
            $env = $this->getStoreConfig('payment/coinremitter/coinremitter_endpoint');
            $coinremitter_token = $this->getStoreConfig('payment/coinremitter/coinremitter_devtoken');
           
        }
  
    }
}
