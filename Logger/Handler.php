<?php

namespace Coinremitter\Checkout\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/app/code/Coinremitter/Checkout/Logs/Coinremitter.log';
}
