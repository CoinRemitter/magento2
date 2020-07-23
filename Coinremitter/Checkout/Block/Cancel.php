<?php
namespace Coinremitter\Checkout\Block;
class Cancel extends \Magento\Framework\View\Element\Template
{
	protected $orderRepository;
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository
		)
	{
		parent::__construct($context);
		$this->orderRepository = $orderRepository;

	}

	public function cancelOrder()
	{	
		if ($this->getRequest()->getParam('order_id') && is_numeric($this->getRequest()->getParam('order_id'))) {
			$order_id = $this->getRequest()->getParam('order_id');
			$order = $this->orderRepository->get($order_id);
			$orderIncrementId = $order->getIncrementId();
			return __('<h3>Your order #'.$orderIncrementId.' has been cancelled successfully.</h3>');	
		}else{
			return __('<h3>Your order has been cancelled successfully.</h3>');	
		}
		
	}
}