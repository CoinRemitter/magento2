<?php

namespace Coinremitter\Checkout\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;

class CoinremitterPayment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('coinremitter_payment', 'id');
    }
}
