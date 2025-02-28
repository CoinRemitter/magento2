<?php

namespace Coinremitter\Checkout\Api\Data;

interface WalletsInterface
{
    const ID = 'id';
    const WALLET_NAME = 'wallet_name';
    const COIN_SYMBOL = 'coin_symbol';
    const COIN_NAME = 'coin_name';
    const API_KEY = 'api_key';
    const PASSWORD = 'password';
    const MINIMUM_INVOICE_AMOUNT = 'minimum_invoice_amount';
    const EXCHANGE_RATE_MULTIPLIER = 'exchange_rate_multiplier';
    const UNIT_FIAT_AMOUNT = 'unit_fiat_amount';
    const BASE_FIAT_SYMBOL = 'base_fiat_symbol';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    public function getId();
    public function getCoinSymbol();
    public function getCoinName();
    public function getWalletName();
    public function getMinimumInvoiceAmount();
    public function getExchangeRateMultiplier();
    public function getUnitFiatAmount();
    public function getBaseFiatSymbol();
    public function getApiKey();
    public function getPassword();
    public function getCreatedAt();
    public function getUpdatedAt();
    public function setId($id);
    public function setWalletName($wallet_name);
    public function setCoinSymbol($coin_symbol);
    public function setCoinName($coin_name);
    public function setApiKey($api_key);
    public function setPassword($password);
    public function setMinimumInvoiceAmount($minimum_invoice_amount);
    public function setExchangeRateMultiplier($exchange_rate_multiplier);
    public function setUnitFiatAmount($unit_fiat_amount);
    public function setBaseFiatSymbol($base_fiat_symbol);
    public function setCreatedAt($created_at);
    public function setUpdatedAt($updated_at);
}
