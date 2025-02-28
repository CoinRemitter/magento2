<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use \Magento\Framework\App\Action\Context;
use Zend\Http\Request;


class Cancel extends \Magento\Framework\App\Action\Action
{
    private $checkoutSession;
    protected $resultPageFactory;
    protected $_logger;
    protected $_appState;
    protected $orderManagement;
    protected $apiCall;
    protected $messageManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Framework\App\State $appState,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_appState = $appState;
        $this->apiCall = $apiCall;
        $this->orderManagement = $orderManagement;
        $this->messageManager = $messageManager;
        parent::__construct($context);
    }
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('/');
        // return $this->resultPageFactory->create();
        if (!$this->getRequest()->getParam('order_id')) {
            return $resultRedirect;
        }

        $orderId = $this->getRequest()->getParam('order_id');
        $order = $this->getOrder($orderId);

        if ($order->getCustomerId() != $this->getCustomer()) {
            return $resultRedirect;
        }

        $order_status = $order->getStatus();

        if ($order_status == "canceled") {
            return $this->resultPageFactory->create();
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $getOrderId = "SELECT * FROM `coinremitter_orders` WHERE `order_id`= '" . $orderId . "'";
        $coinremitterOrder = $connection->fetchAll($getOrderId);

        if (empty($coinremitterOrder)) {
            return $resultRedirect;
        }

        $coinremitterOrder = $coinremitterOrder[0];

        if (!isset($coinremitterOrder['expiry_date']) || $coinremitterOrder['expiry_date'] == '') {
            return $resultRedirect;
        }

        $date_diff = 0;
        $current = strtotime(date("Y-m-d H:i:s"));
        $expire_on = strtotime($coinremitterOrder['expiry_date']);
        $date_diff = $expire_on - $current;
        if ($date_diff > 0) {
            return $resultRedirect;
        }


        $coin = $coinremitterOrder['coin_symbol'];
        $address = $coinremitterOrder['payment_address'];
        $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $coin . "'";
        $wallet = $connection->fetchAll($sql);
        if (empty($wallet)) {
            return $resultRedirect;
        }
        $wallet = $wallet[0];
        $credencial = [
            "x-api-key" => $wallet['api_key'],
            "x-api-password" => $wallet['password'],
        ];
        $transactions = $this->apiCall->getTransactionByAddress(['address' => $address], $credencial);
        if (!$transactions || !$transactions['success']) {
            return $resultRedirect;
        }
        if (!empty($transactions['data']['transactions'])) {
            return $resultRedirect;
        }
        if ($coinremitterOrder['order_status'] != $this->apiCall->orderStatusCode['pending']) {
            return $resultRedirect;
        }

        if (strtolower($order_status) == 'pending' || strtolower($order_status) == 'canceled') {
            $this->orderManagement->cancel($orderId);
            $this->checkoutSession->clearQuote();
        }
        return $this->resultPageFactory->create();
    }


    public function getOrder($_order_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;
    }

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        return $customerSession->getCustomer()->getId();
    }
}
