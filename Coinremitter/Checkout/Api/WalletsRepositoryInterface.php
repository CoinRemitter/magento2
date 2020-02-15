<?php


namespace Coinremitter\Checkout\Api;

interface WalletsRepositoryInterface
{

    /**
     * POST for modal api
     * @param string $param
     * @return string
     */
    public function save(\Coinremitter\Checkout\Api\Data\WalletsInterface $crypto);

    public function getById($walletId);

    public function delete(\Coinremitter\Checkout\Api\Data\WalletsInterface $crypto);
    
    public function deleteById($walletId);
}
