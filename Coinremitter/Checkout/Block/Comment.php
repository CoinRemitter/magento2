<?php

namespace Coinremitter\Checkout\Block;

class Comment extends \Magento\Framework\View\Element\Template {

	protected $coinremitterPaymentModel;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment\CollectionFactory $coinremitterPaymentModel,
         array $data = []
     ) {
     	$this->_coreRegistry = $registry;
     	parent::__construct($context, $data);
     	$this->coinremitterPaymentModel = $coinremitterPaymentModel;
   	}

   	public function getOrder() {   
        return $this->_coreRegistry->registry('current_order');
    }
    public function getOrderId()
    {
        return $this->getOrder()->getEntityId();
    }
   	public function getInvoice(){
   		$orderId = $this->getOrderId();
        $collection = $this->coinremitterPaymentModel->create();
        $collection->addFieldToFilter('order_id', $orderId);
        
        return $collection->getFirstItem();
   	}
}