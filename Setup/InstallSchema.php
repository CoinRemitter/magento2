<?php
namespace Coinremitter\Checkout\Setup;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    public function install(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        
        $table = $installer->getConnection()->newTable(
            $installer->getTable('coinremitter_wallets')
        )->addColumn(
            'id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'ID'
        )->addColumn(
            'coin',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Coin Short Name'
        )->addColumn(
            'coin_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            100,
            ['nullable' => false],
            'Coin Full Name'
        )->addColumn(
            'name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Wallet Name'
        )->addColumn(
            'api_key',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'API Key'
        )->addColumn(
            'password',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Wallet Password'
        )->addColumn(
            'exchange_rate_multiplier',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false,'default' => '1'],
            'between 0 to 101'
        )->addColumn(
            'minimum_value',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false,'default' => '0.05'],
            'between 0.01 to 1000000'
        )->addColumn(
            'is_valid',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            1,
            ['nullable' => false,'default' => '1'],
            '1 on valid wallet else 0'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            255,
            ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Created At Date'
        )->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            255,
            ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Updated At Date'
        );
        $installer->getConnection()->createTable($table);



        $table = $installer->getConnection()
        ->newTable($installer->getTable('coinremitter_order'))
        ->addColumn(
            'id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )->addColumn(
            'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'magento orderid'
        )->addColumn(
            'invoice_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Invocie orderid'
        )->addColumn(
            'address',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Invocie Address'
        )->addColumn(
            'amountusd',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'amount in usd'
        )->addColumn(
            'crp_amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'crp amount'
        )->addColumn(
            'address_qrcode',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            "2M",
            ['nullable' => true, 'default' => ''],
            'address qrcode'
        )->addColumn(
            'payment_status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'payment_status'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            255,
            ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Created At Date'
        )->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            255,
            ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Updated At Date'
        );
        $installer->getConnection()->createTable($table);


        $table = $installer->getConnection()
        ->newTable($installer->getTable('coinremitter_payment'))
        ->addColumn(
            'id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'ID'
        )->addColumn(
            'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'magento orderid'
        )->addColumn(
            'invoice_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Invocie orderid'
        )->addColumn(
            'address',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Invocie Address'
        )->addColumn(
            'invoice_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Invoce Name'
        )->addColumn(
            'marchant_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Marchant Name'
        )->addColumn(
            'total_amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Total Amount'
        )->addColumn(
            'paid_amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true, 'default' => ''],
            'Paid Amount'
        )->addColumn(
            'base_currancy',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Base Currancy'
        )->addColumn(
            'description',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Description'
        )->addColumn(
            'coin',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Coin'
        )->addColumn(
            'payment_history',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '2M',
            ['nullable' => true, 'default' => ''],
            'Payment History'
        )->addColumn(
            'conversion_rate',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '2M',
            ['nullable' => true, 'default' => ''],
            'Conversion Rate'
        )->addColumn(
            'invoice_url',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false, 'default' => ''],
            'Invoice Url'
        )->addColumn(
            'status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            100,
            ['nullable' => false],
            'Invoice Status'
        )->addColumn(
            'expire_on',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => true,'default' => ''],
            'Invoice Expire time'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            255,
            ['nullable' => false,'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Invoice Created Date'
        );
        $installer->getConnection()->createTable($table);

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

        $installer->endSetup();
    }
}
