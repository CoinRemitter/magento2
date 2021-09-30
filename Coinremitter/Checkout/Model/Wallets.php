<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Coinremitter\Checkout\Model;
use Coinremitter\Checkout\Api\Data\WalletsInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
/**
 * Pay In Store payment method model
 */
class Wallets extends AbstractModel implements WalletsInterface, IdentityInterface
{

    /**
     * Payment code
     *
     * @var string
     */
    // protected $_code = 'coinremitter';

    /**
     * Availability option
     *
     * @var bool
     */
    // protected $_isOffline = true;

    const CACHE_TAG = 'coinremitter_checkout';

    protected $_cacheTag = 'coinremitter_checkout';

    protected function _construct()
    {
        $this->_init('Coinremitter\Checkout\Model\ResourceModel\Wallets');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    public function getId()
    {
        return parent::getData(self::ID);
    }

    public function getCoin()
    {
        return $this->getData(self::COIN);
    }
    public function getCoinName()
    {
        return $this->getData(self::COIN_NAME);
    }

    public function getName()
    {
        return $this->getData(self::NAME);
    }

    public function getApiKey()
    {
        return $this->getData(self::API_KEY);
    }

    public function getPassword()
    {
        return $this->getData(self::PASSWORD);
    }
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setCoin($coin)
    {
        return $this->setData(self::COIN, $coin);
    }
    public function setCoinName($coinname)
    {
        return $this->setData(self::COIN_NAME, $coinname);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function setApiKey($api_key)
    {
        return $this->setData(self::API_KEY, $api_key);
    }
    public function setPassword($password)
    {
        return $this->setData(self::PASSWORD, $password);
    }
    public function setCreatedAt($created_at)
    {
        return $this->setData(self::CREATED_AT, $created_at);
    }
    public function setUpdatedAt($updated_at)
    {
        return $this->setData(self::UPDATED_AT, $updated_at);
    }
}
