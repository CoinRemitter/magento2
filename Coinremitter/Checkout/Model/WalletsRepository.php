<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Coinremitter\Checkout\Model;

use Coinremitter\Checkout\Api\Data;
use Coinremitter\Checkout\Api\WalletsRepositoryInterface;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Coinremitter\Checkout\Model\ResourceModel\Wallets as ResourceWallets;
use Coinremitter\Checkout\Model\ResourceModel\Wallets\CollectionFactory as WalletsCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class WalletsRepository implements WalletsRepositoryInterface
{
    protected $resource;

    protected $walletsFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $datawalletsFactory;

    private $storeManager;

    public function __construct(
        ResourceWallets $resource,
        WalletsFactory $walletsFactory,
        Data\WalletsInterfaceFactory $datawalletsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->walletsFactory = $walletsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->datawalletsFactory = $datawalletsFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Coinremitter\Checkout\Api\Data\WalletsInterface $wallet)
    {
        if ($wallet->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $wallet->setStoreId($storeId);
        }
        try {
            $this->resource->save($wallet);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the wallet: %1', $exception->getMessage()),
                $exception
            );
        }
        return $wallet;
    }

    public function getById($walletId)
    {
        $wallet = $this->walletsFactory->create();
        $wallet->load($walletId);
        if (!$wallet->getId()) {
            throw new NoSuchEntityException(__('wallet with id "%1" does not exist.', $walletId));
        }
        return $wallet;
    }

    public function delete(\Coinremitter\Checkout\Api\Data\WalletsInterface $wallet)
    {
        try {
            $this->resource->delete($wallet);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the wallet: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($walletId)
    {
        return $this->delete($this->getById($walletId));
    }
}
