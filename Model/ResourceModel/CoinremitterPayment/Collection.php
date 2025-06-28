<?php

namespace Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'coinremitter_payment_collection';
    protected $_eventObject = 'coinremitter_payment_collection';

    /**
     * Define resource model
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Coinremitter\Checkout\Model\CoinremitterPayment', 'Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment');
    }
}
