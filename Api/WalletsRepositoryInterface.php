<?php

namespace Coinremitter\Checkout\Api;

interface WalletsRepositoryInterface
{
    /**
     * Save wallet data
     *
     * @param \Coinremitter\Checkout\Api\Data\WalletsInterface $crypto
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function save(\Coinremitter\Checkout\Api\Data\WalletsInterface $crypto);

    /**
     * Get wallet by ID
     *
     * @param int $walletId
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function getById($walletId);

    /**
     * Delete wallet
     *
     * @param \Coinremitter\Checkout\Api\Data\WalletsInterface $crypto
     * @return bool
     */
    public function delete(\Coinremitter\Checkout\Api\Data\WalletsInterface $crypto);

    /**
     * Delete wallet by ID
     *
     * @param int $walletId
     * @return bool
     */
    public function deleteById($walletId);
}
