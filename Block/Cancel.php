<?php

namespace Coinremitter\Checkout\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
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
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Return a translated message describing the cancellation outcome.
     *
     * This method is intentionally a pure display helper.  All state mutations
     * (Magento order cancellation, coinremitter_orders.order_status update) are
     * performed by Controller\Invoice\Cancel before this block is rendered.
     * Keeping side-effects out of view blocks prevents the unconditional
     * UPDATE order_status=4 bypass (CVE-B) where any page render of the cancel
     * URL could force-expire an order record regardless of authentication.
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
