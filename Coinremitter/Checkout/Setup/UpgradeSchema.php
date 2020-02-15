<?php
namespace Coinremitter\Checkout\Setup;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{   
    /**
     * {@inheritdoc}
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;

        $installer->startSetup();       
        $installer->getConnection()->addColumn(
            $installer->getTable('quote_payment'),
            'transaction_result',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'comment' => 'Wallet Coin Name'
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_payment'),
            'transaction_result',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'comment' => 'Wallet Coin Name'
            ]
        );

        $installer->endSetup();
    }
}