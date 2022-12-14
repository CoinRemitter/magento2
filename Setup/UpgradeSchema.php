<?php
namespace Coinremitter\Checkout\Setup;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{   

    protected $_debug_logger;

    public function __construct(\Psr\Log\LoggerInterface $debug_logger)
    {
        $this->_debug_logger = $debug_logger;
    }
    /**
     * {@inheritdoc}
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $this->_debug_logger->debug('Upgrade Schema Called!!!');
        $installer = $setup;

        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.3', '<')) {

            $installer->getConnection()->addColumn(
                $installer->getTable('coinremitter_order'),
                'address',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => true, 'default' => ''],
                    'comment' => 'Address',
                    'after' => 'invoice_id'
                ]
            );
            $this->_debug_logger->debug('Add Address Field!!!');
            $installer->getConnection()->addColumn(
                $installer->getTable('coinremitter_order'),
                'address_qrcode',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => true, 'default' => ''],
                    'comment' => 'QR Code Image',
                    'after' => 'address'
                ]
            );

            $this->_debug_logger->debug('Add exchange rate field');
            $installer->getConnection()->addColumn(
                $installer->getTable('coinremitter_wallets'),
                'exchange_rate_multiplier',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => false, 'default' => '1'],
                    'comment' => 'between 0 to 101',
                    'after' => 'exchange_rate_multiplier'
                ]
            );

            $this->_debug_logger->debug('Add minimum invoice value field');
            $installer->getConnection()->addColumn(
                $installer->getTable('coinremitter_wallets'),
                'minimum_value',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => false, 'default' => '0.05'],
                    'comment' => 'between 0.01 to 1000000',
                    'after' => 'minimum_value'
                ]
            );


            $this->_debug_logger->debug('Add Qr Code Field!!!');
            $installer->getConnection()->addColumn(
                $installer->getTable('coinremitter_payment'),
                'address',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => true, 'default' => ''],
                    'comment' => 'Address',
                    'after' => 'invoice_id'
                ]
            );
            $installer->getConnection()->changeColumn(
                $installer->getTable('coinremitter_payment'),
                'expire_on',
                'expire_on',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    ['nullable' => true, 'default' => ''],
                    'comment' => 'Expire Time',
                    'after' => 'status'
                ]
            );
            $installer->getConnection()->changeColumn(
                $installer->getTable('coinremitter_payment'),
                'created_at',
                'created_at',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                    'length' => '',
                    ['nullable' => true, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                    'comment' => 'Invoice Created Date',
                    'after' => 'expire_on'
                ]
            );

            $this->_debug_logger->debug('Add Webhook Start!!!');
            $table = $installer->getConnection()
            ->newTable($installer->getTable('coinremitter_webhook'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )->addColumn(
                'address',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'wallet address'
            )->addColumn(
                'transaction_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'transaction id'
            )->addColumn(
                'txId',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Blockchain id'
            )->addColumn(
                'explorer_url',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Explorer Url'
            )->addColumn(
                'paid_amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Paid Amount'
            )->addColumn(
                'coin',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Coin'
            )->addColumn(
                'confirmations',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                255,
                ['nullable' => false, 'default' => 0],
                'Transaction confirmations'
            )->addColumn(
                'paid_date',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                255,
                ['nullable' => false],
                'Payment Paid Date'
            )->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                255,
                ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Invoice Created Date'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                255,
                ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Invoice Updated Date'
            );

            $installer->getConnection()->createTable($table);
            $this->_debug_logger->debug('Add webhook End!!!');
        }

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
        $this->_debug_logger->debug('Upgrade Schema Ended!!!');
    }
}