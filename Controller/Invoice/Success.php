<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use \Magento\Framework\App\Action\Context;
use Coinremitter\Checkout\Model\Wallets\Api;

class Success extends \Magento\Framework\App\Action\Action
{
    private $checkoutSession;
    protected $resultPageFactory;
    protected $_logger;
    protected $apiCall;
    protected $_appState;
    protected $_scopeConfig;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Framework\App\State $appState,
        Api $apiCall
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_appState = $appState;
        $this->apiCall = $apiCall;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }
    public function execute()
    {
        $env_mode = $this->_appState->getMode();
        if ($this->getRequest()->getParam('order_id') && is_numeric($this->getRequest()->getParam('order_id'))) {

            $orderId = $this->getRequest()->getParam('order_id');
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);

            if ($order->getCustomerId() != $this->getCustomer()) {
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('/');
                return $resultRedirect;
            }

            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "SELECT * FROM `coinremitter_orders` WHERE order_id= '" . $orderId . "'";
            $coinremitterOrder = $connection->fetchAll($sql);

            $this->checkoutSession->clearQuote();  //remove qoute info from session

            $orderStatusCode = $this->apiCall->orderStatusCode;
            if (!empty($coinremitterOrder) && ($coinremitterOrder[0]['order_status'] == $orderStatusCode['paid'] || $coinremitterOrder[0]['order_status'] == $orderStatusCode['over_paid'])) {
                $coinremitterOrder = $coinremitterOrder[0];

                if ($order->getQuoteId() > 0) {
                    // echo "<pre>";
                    // print_r($coinremitterOrder);
                    // die;

                    $email = $order->getCustomerEmail();
                    $realOrderId = $order->getIncrementId();

                    /*set order and qoute data in session*/

                    $this->checkoutSession->setLastOrderId($orderId);
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastRealOrderId($realOrderId);

                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('checkout/onepage/success');
                    return $resultRedirect;
                }
            } else {

                if ($env_mode == 'developer') {
                    $this->_logger->info('Invoice_Success : Invoice Not Found OR Error in getting invoice');
                    $this->_logger->info('Invoice_Success : ' . json_encode($coinremitterOrder));
                }
            }
        }
    }

    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $_val;
    }

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        return $customerSession->getCustomer()->getId();
    }
}
