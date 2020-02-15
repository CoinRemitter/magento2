<?php
namespace Coinremitter\Checkout\Block\Adminhtml\Orderedit\Tab;

class View extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    protected $_template = 'tab/view/myorderinfo.phtml';
    protected $payment;
    protected $coinremitterPaymentModel;
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment\CollectionFactory $coinremitterPaymentModel,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
        $this->coinremitterPaymentModel = $coinremitterPaymentModel;
    }
    public function getOrder()
    {   
        return $this->_coreRegistry->registry('current_order');
    }
    public function getOrderId()
    {
        return $this->getOrder()->getEntityId();
    }
    public function getOrderIncrementId()
    {
        return $this->getOrder()->getIncrementId();
    }
    public function getTabLabel()
    {
        return __('Payment Detail (Coinremitter)');
    }
    public function getTabTitle()
    {
        return __('Payment Detail (Coinremitter)');
    }
    public function getInvoice(){
        $orderId = $this->getOrderId();
        $collection = $this->coinremitterPaymentModel->create();
        $collection->addFieldToFilter('order_id', $orderId);
        
        return $collection->getFirstItem();
    }
    public function canShowTab()
    {
        if ($this->getInvoice()->getInvoiceId()) {
            return true;    
        }else{
            return false;
        }
    }
    public function isHidden()
    {
        if ($this->getInvoice()->getInvoiceId()) {
            return false;    
        }else{
            return true;
        }
    }
}