<?php
namespace Coinremitter\Checkout\Api\Data;
interface WalletsInterface
{
    const ID = 'id';
    const COIN = 'coin';
    const COIN_NAME = 'coin_name';
    const NAME = 'name';
    const API_KEY = 'api_key';
    const PASSWORD = 'password';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    public function getId();
    public function getCoin();
    public function getCoinName();
    public function getName();
    public function getApiKey();
    public function getPassword();
    public function getCreatedAt();
    public function getUpdatedAt();
    public function setId($id);
    public function setCoin($coin);
    public function setCoinName($coinname);
    public function setName($name);
    public function setApiKey($api_key);
    public function setPassword($password);
    public function setCreatedAt($created_at);
    public function setUpdatedAt($updated_at);
}