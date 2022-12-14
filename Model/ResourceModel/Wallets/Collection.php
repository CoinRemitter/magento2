<?php
namespace Coinremitter\Checkout\Model\ResourceModel\Wallets;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'coinremitter_wallets_collection';
    protected $_eventObject = 'wallets_collection';

    /**
     * Define resource model
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Coinremitter\Checkout\Model\Wallets', 'Coinremitter\Checkout\Model\ResourceModel\Wallets');
    }
}
