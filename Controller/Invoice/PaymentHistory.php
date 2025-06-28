<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Coinremitter\Checkout\Model\Wallets\Api;
use Zend\Http\Request;

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
        \Psr\Log\LoggerInterface $logger,
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

        $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address`='" . $address . "'";
        $orderData = $connection->fetchAll($sql);


        if (empty($orderData)) {
            $resultJson->setData(['flag' => 0, 'msg' => 'Invalid address.']);
            return $resultJson;
        }

        $orderData = $orderData[0];
        $order = $this->getOrder($orderData['order_id']);
        if ($order->getCustomerId() != $this->getCustomer()) {
            $resultJson->setData(['flag' => 0, 'msg' => 'Unautorize request.']);
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

        if (empty($orderData['transaction_meta']) && $date_diff < 1 && $expire_on != "") {
            $expireStatus = $this->orderStatusCode['expired'];
            $sql = "UPDATE `coinremitter_orders` SET `order_status`= $expireStatus WHERE `id`= '" . $orderData['id'] . "'";
            $connection->query($sql);
            $returnJson['data']['status'] = $this->orderStatus[$expireStatus];
            $returnJson['data']['status_code'] = $expireStatus;
            $this->orderManagement->cancel($orderData['order_id']);
            $resultJson->setData($returnJson);
            return $resultJson;
        }


        $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`='" . $orderData['coin_symbol'] . "'";
        $walletData = $connection->fetchAll($sql);

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
            if ($totalFiatPaidWithTruncation > $orderData['fiat_amount']) {
                $status = $this->orderStatusCode['paid'];
            }
        }
        $trxMeta = json_encode($trxMeta);
        $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE payment_address='" . $orderData['payment_address'] . "'";
        $connection->query($sql);

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

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        return $customerSession->getCustomer()->getId();
    }
}
