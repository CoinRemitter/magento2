<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Coinremitter\Checkout\Model\Wallets\Api;

class PaymentHistory extends Action
{

    protected $_resultPageFactory;
    protected $_resultJsonFactory;
    protected $apiCall;
    protected $orderStatusCode;
    protected $orderStatus;
    protected $orderManagement;
    protected $_logger;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        Api $apiCall
    ) {

        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->apiCall = $apiCall;
        $this->_logger = $logger;
        $this->orderStatusCode = $apiCall->orderStatusCode;
        $this->orderStatus = $apiCall->orderStatus;
        $this->orderManagement = $orderManagement;
        parent::__construct($context);
    }

    public function execute()
    {

        $resultJson = $this->_resultJsonFactory->create();

        $address = $this->getRequest()->getParam("address");

        if ($address == '') {
            $resultJson->setData(['flag' => 0, 'msg' => 'Address is required.']);
            return $resultJson;
        }


        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address` = ?";
        $orderData = $connection->fetchAll($sql, [$address]);


        if (empty($orderData)) {
            $resultJson->setData(['flag' => 0, 'msg' => 'Invalid address.']);
            return $resultJson;
        }

        $orderData = $orderData[0];
        $order = $this->getOrder($orderData['order_id']);

        // Strict ownership check instead of null != null loose comparison.
        if (!$this->isOrderOwner($order, $orderData['order_id'])) {
            $this->_logger->info('PaymentHistory : Unauthorized access attempt for order ' . $orderData['order_id']);
            $resultJson->setData(['flag' => 0, 'msg' => 'Unauthorize request.']);
            return $resultJson;
        }

        $orderData['transaction_meta'] = $orderData['transaction_meta'] ? json_decode($orderData['transaction_meta'], true) : [];
        $orderStatus = $orderData['order_status'];

        $expire_on = $orderData['expiry_date'];
        $date_diff = 0;
        if ($expire_on != "") {
            $current = strtotime(date("Y-m-d H:i:s"));
            $expire_on = strtotime($expire_on);
            $date_diff = $expire_on - $current;
            $expire_on = date("Y-m-d H:i:s", $expire_on);
        }
        $returnJson = [
            'flag' => 1,
            'msg' => 'success',
            'data' => [
                "order_id" => $orderData['order_id'],
                "now_time" => date('Y-m-d, H:i:s'),
                "coin_symbol" => $orderData['coin_symbol'],
                "status" => $this->apiCall->orderStatus[$orderStatus],
                "status_code" => $orderStatus,
                "transactions" => $this->apiCall->prepareReturnTrxData($orderData['transaction_meta']),
                "paid_amount" => $orderData['paid_crypto_amount'],
                "pending_amount" => number_format($orderData['crypto_amount'] - $orderData['paid_crypto_amount'], 8, '.', ''),
                "expire_on" => $expire_on,
            ]
        ];

        // Check Coinremitter API for in-flight transactions BEFORE marking
        // expired and cancelling.  Previously the code only checked the local DB
        // (transaction_meta), which could be empty even when a payment had already been
        // broadcast on-chain, causing premature cancellation of a pending payment.
        if (empty($orderData['transaction_meta']) && $date_diff < 1 && $expire_on != "") {
            $this->_logger->info('PaymentHistory : Order #' . $orderData['order_id'] . ' reached expiry date (' . $expire_on . '). Checking Coinremitter API for on-chain transactions...');

            // Fetch the wallet credentials needed to query the API.
            $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol` = ?";
            $walletDataForExpiry = $connection->fetchAll($sql, [$orderData['coin_symbol']]);

            $hasOnChainTransactions = false;
            $txCount = 0;
            if (!empty($walletDataForExpiry)) {
                $walletForExpiry = $walletDataForExpiry[0];
                $credentialsForExpiry = [
                    "x-api-key"      => $walletForExpiry['api_key'],
                    "x-api-password" => $walletForExpiry['password'],
                ];
                $apiTxRes = $this->apiCall->getTransactionByAddress(
                    ['address' => $address],
                    $credentialsForExpiry
                );
                if ($apiTxRes && $apiTxRes['success'] && !empty($apiTxRes['data']['transactions'])) {
                    $hasOnChainTransactions = true;
                    $txCount = count($apiTxRes['data']['transactions']);
                }
            }

            if ($hasOnChainTransactions) {
                $this->_logger->info('PaymentHistory : Order #' . $orderData['order_id'] . ' has ' . $txCount . ' on-chain transaction(s) via API. Skipping premature cancellation.');
            } else {
                // No transactions found on-chain either — safe to expire.
                $this->_logger->info('PaymentHistory : Order #' . $orderData['order_id'] . ' has 0 on-chain transactions via API. Cancelling order and setting status to expired.');
                $expireStatus = $this->orderStatusCode['expired'];
                $sql = "UPDATE `coinremitter_orders` SET `order_status` = ? WHERE `id` = ?";
                $connection->query($sql, [$expireStatus, $orderData['id']]);
                $returnJson['data']['status'] = $this->orderStatus[$expireStatus];
                $returnJson['data']['status_code'] = $expireStatus;
                $this->orderManagement->cancel($orderData['order_id']);
                $resultJson->setData($returnJson);
                return $resultJson;
            }
        }


        $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol` = ?";
        $walletData = $connection->fetchAll($sql, [$orderData['coin_symbol']]);

        if (empty($walletData)) {
            $resultJson->setData($returnJson);
            return $resultJson;
        }

        $walletData = $walletData[0];

        $credencials = [
            "x-api-key" => $walletData['api_key'],
            "x-api-password" => $walletData['password']
        ];

        $transactionsRes = $this->apiCall->getTransactionByAddress(['address' => $address], $credencials);

        if (!isset($transactionsRes) || !$transactionsRes['success']) {
            $resultJson->setData($returnJson);
            return $resultJson;
        }


        $trxMeta = $orderData['transaction_meta'];
        $allTrx = $transactionsRes['data']['transactions'];
        $updateOrderRequired = false;
        $total_paid = 0;
        foreach ($allTrx as $trx) {
            if ($trx['type'] != 'receive') {
                continue;
            }
            $fiat_amount = ($trx['amount'] * $orderData['fiat_amount']) / $orderData['crypto_amount'];
            $fiat_amount = number_format($fiat_amount, 2, '.', '');
            $minFiatAmount = $walletData['minimum_invoice_amount'];
            if ($fiat_amount < $minFiatAmount) {
                continue;
            }

            $transactionInfo = $this->apiCall->checkTransactionExists($orderData['transaction_meta'], $trx['txid']);
            if (empty($transactionInfo)) {
                $updateOrderRequired = true;
                $trxMeta[$trx['txid']] = $trx;
            } else {
                if ($transactionInfo['status_code'] != $trx['status_code']) {
                    $trxMeta[$trx['txid']] = $trx;
                    $updateOrderRequired = true;
                }
            }
            if ($trx['status_code'] == 1) {
                $total_paid += $trx['amount'];
            }
        }

        if (!$updateOrderRequired) {
            $resultJson->setData($returnJson);
            return $resultJson;
        }

        $truncationValue = $this->apiCall->truncationValue;
        if ($orderData['fiat_symbol'] != 'USD') {
            $conversionParam = [
                'crypto' => $orderData['coin_symbol'],
                'crypto_amount' => $orderData['crypto_amount'],
                'fiat' => 'USD',
            ];
            $cryptoToUsdRate = $this->apiCall->getCryptoToFiatRate($conversionParam);
            if ($cryptoToUsdRate['success']) {
                $usdAmount = $cryptoToUsdRate['data'][0]['amount'];
            }
            $truncationValue = ($orderData['fiat_amount'] * $truncationValue) / $usdAmount;
        }
        $truncationValue = number_format($truncationValue, 4, '.', '');
        $total_fiat_paid = number_format(($total_paid * $orderData['fiat_amount']) / $orderData['crypto_amount'], 2, '.', '');
        $totalFiatPaidWithTruncation = $total_fiat_paid + $truncationValue;

        $status = $orderStatus;
        if ($total_paid == $orderData['crypto_amount']) {
            $status = $this->orderStatusCode['paid'];
        } elseif ($total_paid > $orderData['crypto_amount']) {
            $status = $this->orderStatusCode['over_paid'];
        } elseif ($total_paid != 0 && $total_paid < $orderData['crypto_amount']) {
            $status = $this->orderStatusCode['under_paid'];
            if ($totalFiatPaidWithTruncation >= $orderData['fiat_amount']) {
                $status = $this->orderStatusCode['paid'];
            }
        }
        $trxMeta = json_encode($trxMeta);
        $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount` = ?, `paid_fiat_amount` = ?, `order_status` = ?, `transaction_meta` = ? WHERE payment_address = ?";
        $connection->query($sql, [$total_paid, $total_fiat_paid, $status, $trxMeta, $orderData['payment_address']]);

        $returnJson['data']['status'] = $this->orderStatus[$status];
        $returnJson['data']['status_code'] = $status;
        $returnJson['data']['transactions'] = $this->apiCall->prepareReturnTrxData(json_decode($trxMeta, true));
        $returnJson['data']['paid_amount'] = $total_paid;
        $returnJson['data']['pending_amount'] = number_format($orderData['crypto_amount'] - $total_paid, 8, '.', '');
        $resultJson->setData($returnJson);
        return $resultJson;
    }

    public function getOrder($_order_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;
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
            $isOwner = ($sessionCustomerId !== null && (int)$orderCustomerId === (int)$sessionCustomerId);

            return $isOwner;
        }

        // Guest order: verify the session token written by CoinremitterRedirect.
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
        $hasToken = ($checkoutSession->getData('coinremitter_order_id_' . $orderId) === true);

        return $hasToken;
    }
}
