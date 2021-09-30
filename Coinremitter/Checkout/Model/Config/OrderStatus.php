<?php
namespace Coinremitter\Checkout\Model\Config;

class OrderStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Retrieve status options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'pending_payment', 'label' => __('Pending Payment')],
            ['value' => 'processing', 'label' => __('Processing')],
            ['value' => 'holded', 'label' => __('On Hold')],
            ['value' => 'complete', 'label' => __('Complete')],
            ['value' => 'canceled', 'label' => __('Cancelled')],
            ['value' => 'refunded', 'label' => __('Refunded')],
            ['value' => 'closed', 'label' => __('Closed')],
        ];
    }
}
