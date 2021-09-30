<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{

    const CODE = 'sample_gateway';

    protected $_scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {
        $this->_scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('coinremitter_wallets');

        if ($connection->tableColumnExists($tableName, 'is_valid') === false) {
            $connection->addColumn('coinremitter_wallets', 'is_valid', array(
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => false,
                'length' => 1,
                'default' => 1,
                'after' => 'password', // column name to insert new column after
                'comment' => '1 on valid wallet else 0',
            ));
        }

        $sql = "SELECT `coin`,`coin_name` FROM coinremitter_wallets WHERE `is_valid` = '1'";
        $result = $connection->fetchAll($sql);

        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => $result,
                    'payment_description' => $this->getStoreConfig('payment/coinremitter_checkout/description'),
                    'isWallets' => !empty($result) ? true : false,
                ],
            ],
        ];
    }
    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $_val;

    }
}
