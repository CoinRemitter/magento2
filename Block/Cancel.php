<?php

namespace Coinremitter\Checkout\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cancel block class for order cancellation
 */
class Cancel extends Template
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderRepository = $orderRepository;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
    }

    /**
     * Cancel order and return success message
     *
     * @return \Magento\Framework\Phrase
     */
    public function cancelOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        
        if ($orderId && is_numeric($orderId)) {
            try {
                $order = $this->orderRepository->get($orderId);
                $orderIncrementId = $order->getIncrementId();

                // Update order status in custom table using proper prepared statement
                $connection = $this->resourceConnection->getConnection();
                $tableName = $this->resourceConnection->getTableName('coinremitter_orders');
                $connection->update(
                    $tableName,
                    ['order_status' => 4],
                    ['order_id = ?' => $orderId]
                );

                $path = $this->getBaseUrl() . "sales/order/view/order_id/" . $orderId . "/";
                return __(
                    '<h3>Your order <a href="%1">#%2</a> has been cancelled successfully.</h3>',
                    $path,
                    $orderIncrementId
                );
            } catch (\Exception $e) {
                return __('<h3>Error cancelling order. Please try again.</h3>');
            }
        } else {
            return __('<h3>Your order has been cancelled successfully.</h3>');
        }
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }
}
