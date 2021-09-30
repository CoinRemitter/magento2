<?php
namespace Coinremitter\Checkout\Model\Wallets;

use Coinremitter\Checkout\Model\ResourceModel\Wallets\CollectionFactory;
use Coinremitter\Checkout\Model\Wallets;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $walletCollectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $walletCollectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $walletCollectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        $this->loadedData = array();
        /** @var Contact $contact */
        foreach ($items as $wallet) {
            // our fieldset is called "contact" or this table so that magento can find its datas:
            $this->loadedData[$wallet->getId()]['wallets'] = $wallet->getData();
        }
        return $this->loadedData;

    }
}
