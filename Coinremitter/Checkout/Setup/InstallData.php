<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Coinremitter\Checkout\Setup;

use Magento\Framework\Module\Setup\Migration;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * Customer setup factory
     *
     * @var CustomerSetupFactory
     */
    // private $customerSetupFactory;

    protected $date;

    /**
     * Init
     *
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        // CustomerSetupFactory $customerSetupFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        // $this->customerSetupFactory = $customerSetupFactory;
        $this->date = $date;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

    }
}
