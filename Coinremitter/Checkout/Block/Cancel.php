<?php
namespace Coinremitter\Checkout\Block;
class Cancel extends \Magento\Framework\View\Element\Template
{
	public function __construct(\Magento\Framework\View\Element\Template\Context $context)
	{
		parent::__construct($context);
	}

	public function cancelOrder()
	{
		return __('<h3>Your order has been cancelled successfully.</h3>');
	}
}