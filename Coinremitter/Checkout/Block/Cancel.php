<?php
namespace Coinremitter\Checkout\Block;

class Cancel extends \Magento\Framework\View\Element\Template
{
    protected $orderRepository;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;

    }

    public function cancelOrder()
    {
        if ($this->getRequest()->getParam('order_id') && is_numeric($this->getRequest()->getParam('order_id'))) {
            $order_id = $this->getRequest()->getParam('order_id');
            $order = $this->orderRepository->get($order_id);
            $orderIncrementId = $order->getIncrementId();

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "UPDATE coinremitter_order SET payment_status=4 WHERE order_id=" . $order_id;
            $resultTransaction = $connection->query($sql);

            $path = $this->getBaseUrl() . "sales/order/view/order_id/" . $order_id . "/";
            return __('<h3>Your order <a href="' . $path . '">#' . $orderIncrementId . '</a> has been cancelled successfully.</h3>');
        } else {
            return __('<h3>Your order has been cancelled successfully.</h3>');
        }
    }

    public function getBaseUrl()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        return $storeManager->getStore()->getBaseUrl();

    }
}
