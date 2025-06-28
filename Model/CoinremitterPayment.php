<?php

namespace Coinremitter\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

class CoinremitterPayment extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment');
    }
}
