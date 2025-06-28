<?php

namespace Coinremitter\Checkout\Api\Data;

interface WalletsInterface
{
    public const ID = 'id';
    public const WALLET_NAME = 'wallet_name';
    public const COIN_SYMBOL = 'coin_symbol';
    public const COIN_NAME = 'coin_name';
    public const API_KEY = 'api_key';
    public const PASSWORD = 'password';
    public const MINIMUM_INVOICE_AMOUNT = 'minimum_invoice_amount';
    public const EXCHANGE_RATE_MULTIPLIER = 'exchange_rate_multiplier';
    public const UNIT_FIAT_AMOUNT = 'unit_fiat_amount';
    public const BASE_FIAT_SYMBOL = 'base_fiat_symbol';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get coin symbol
     *
     * @return string|null
     */
    public function getCoinSymbol();

    /**
     * Get coin name
     *
     * @return string|null
     */
    public function getCoinName();

    /**
     * Get wallet name
     *
     * @return string|null
     */
    public function getWalletName();

    /**
     * Get minimum invoice amount
     *
     * @return float|null
     */
    public function getMinimumInvoiceAmount();

    /**
     * Get exchange rate multiplier
     *
     * @return float|null
     */
    public function getExchangeRateMultiplier();

    /**
     * Get unit fiat amount
     *
     * @return float|null
     */
    public function getUnitFiatAmount();

    /**
     * Get base fiat symbol
     *
     * @return string|null
     */
    public function getBaseFiatSymbol();

    /**
     * Get API key
     *
     * @return string|null
     */
    public function getApiKey();

    /**
     * Get password
     *
     * @return string|null
     */
    public function getPassword();

    /**
     * Get created at timestamp
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set ID
     *
     * @param int $id
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setId($id);

    /**
     * Set wallet name
     *
     * @param string $wallet_name
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setWalletName($wallet_name);

    /**
     * Set coin symbol
     *
     * @param string $coin_symbol
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setCoinSymbol($coin_symbol);

    /**
     * Set coin name
     *
     * @param string $coin_name
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setCoinName($coin_name);

    /**
     * Set API key
     *
     * @param string $api_key
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setApiKey($api_key);

    /**
     * Set password
     *
     * @param string $password
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setPassword($password);

    /**
     * Set minimum invoice amount
     *
     * @param float $minimum_invoice_amount
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setMinimumInvoiceAmount($minimum_invoice_amount);

    /**
     * Set exchange rate multiplier
     *
     * @param float $exchange_rate_multiplier
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setExchangeRateMultiplier($exchange_rate_multiplier);

    /**
     * Set unit fiat amount
     *
     * @param float $unit_fiat_amount
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setUnitFiatAmount($unit_fiat_amount);

    /**
     * Set base fiat symbol
     *
     * @param string $base_fiat_symbol
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setBaseFiatSymbol($base_fiat_symbol);

    /**
     * Set created at timestamp
     *
     * @param string $created_at
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setCreatedAt($created_at);

    /**
     * Set updated at timestamp
     *
     * @param string $updated_at
     * @return \Coinremitter\Checkout\Api\Data\WalletsInterface
     */
    public function setUpdatedAt($updated_at);
}
