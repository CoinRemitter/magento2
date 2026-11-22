<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use Magento\Framework\App\Action\Context;
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

            // Strict ownership check instead of null != null loose comparison.
            if (!$this->isOrderOwner($order, $orderId)) {
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('/');
                return $resultRedirect;
            }

            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "SELECT * FROM `coinremitter_orders` WHERE order_id = ?";
            $coinremitterOrder = $connection->fetchAll($sql, [$orderId]);

            $this->checkoutSession->clearQuote();  //remove qoute info from session

            $orderStatusCode = $this->apiCall->orderStatusCode;
            if (!empty($coinremitterOrder) && ($coinremitterOrder[0]['order_status'] == $orderStatusCode['paid'] || $coinremitterOrder[0]['order_status'] == $orderStatusCode['over_paid'])) {
                $coinremitterOrder = $coinremitterOrder[0];

                if ($order->getQuoteId() > 0) {
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

    /**
     * Strict, null-safe order ownership check.
     *
     * For authenticated users: compares integer customer IDs with strict equality.
     * For guest orders (customer_id IS NULL): verifies the session ownership token that
     * CoinremitterRedirect stores immediately after creating the invoice.  This prevents
     * the null != null PHP loose-equality bypass (CVE-A) where every unauthenticated
     * visitor passes the check for every guest order.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param int|string $orderId
     * @return bool
     */
    private function isOrderOwner($order, $orderId): bool
    {
        $orderCustomerId = $order->getCustomerId();

        if ($orderCustomerId !== null) {
            // Authenticated order: strict integer comparison — no type coercion.
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            $sessionCustomerId = $customerSession->getCustomer()->getId();
            return $sessionCustomerId !== null
                && (int)$orderCustomerId === (int)$sessionCustomerId;
        }

        // Guest order: verify the session token written by CoinremitterRedirect.
        return $this->checkoutSession->getData('coinremitter_order_id_' . $orderId) === true;
    }
}
