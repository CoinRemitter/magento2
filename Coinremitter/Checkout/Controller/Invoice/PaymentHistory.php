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

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        Api $apiCall
    ) {

        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->apiCall = $apiCall;
        parent::__construct($context);
    }

    public function execute()
    {

        $resultJson = $this->_resultJsonFactory->create();
        $resultPage = $this->_resultPageFactory->create();

        $address = $this->getRequest()->getParam("address");
        $coin = $this->getRequest()->getParam("coin");

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $sql = "SELECT * FROM coinremitter_webhook WHERE address='" . $address . "'";
        $webhookData = $connection->fetchAll($sql);

        $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '" . $coin . "'";
        $result = $connection->fetchAll($sql);
        $Wallets = $result[0];
        $wallet_data = [
            "api_key" => $Wallets['api_key'],
            "password" => $Wallets['password'],
            "coin" => $Wallets['coin'],
            "address" => $address,
        ];

        $transactionsArray = array();
        foreach ($webhookData as $a) {
            $transactionsArray[] = $a['transaction_id'];
        }
        $transactions = $this->CR_get_transactions_by_address($wallet_data);
        if ($transactions['flag'] == 1) {
            foreach ($transactions['data'] as $transaction) {
                $confirm = $transaction['confirmations'] < 3 ? $transaction['confirmations'] : 3;
                if (in_array($transaction['id'], $transactionsArray)) {
                    $sql = "UPDATE coinremitter_webhook SET confirmations=" . $confirm . " WHERE transaction_id='" . $transaction['id'] . "'";
                    $resultTransaction = $connection->query($sql);
                } else {
                    $webhook_data = [
                        $transaction['address'],
                        $transaction['id'],
                        $transaction['txid'],
                        $transaction['explorer_url'],
                        $transaction['amount'],
                        $transaction['coin_short_name'],
                        $confirm,
                        $transaction['date'],
                    ];
                    $this->dataInsertDB($webhook_data);
                }
            }
        }

        $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '" . $address . "'";
        $resultWebhook = $connection->fetchAll($sql);
        $total_paid = 0;
        $total_paid1 = 0;
        foreach ($resultWebhook as $rowWebhook) {
            $total_paid1 += number_format($rowWebhook['paid_amount'], 8, ".", "");
            if ($rowWebhook['confirmations'] >= 3) {
                $total_paid += number_format($rowWebhook['paid_amount'], 8, ".", "");
            }
        }

        $sql = "SELECT crp_amount FROM coinremitter_order WHERE address= '" . $address . "'";
        $resultAmount = $connection->fetchAll($sql)[0];
        $total_paid = (string) $total_paid;
        $status = 0;
        if ($resultAmount['crp_amount'] == $total_paid) {
            $status = 1;
        } else if ($resultAmount['crp_amount'] < $total_paid) {
            $status = 3;
        } else if ($total_paid != 0 && $resultAmount['crp_amount'] > $total_paid) {
            $status = 2;
        }

        if ($status == 1 || $status == 3 || $status == 2) {
            $sql = "UPDATE coinremitter_order SET payment_status=$status WHERE address= '" . $address . "'";
            $resultTransaction = $connection->query($sql);
            $sql = "UPDATE coinremitter_payment SET status=$status WHERE address= '" . $address . "'";
            $resultTransaction = $connection->query($sql);
        }

        $sql = "SELECT co.payment_status,co.crp_amount,cp.coin,co.order_id, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address=cp.address AND co.address= '" . $address . "'";
        $result = $connection->fetchAll($sql);
        $responseData = array();
        if ($result) {
            $orderData = $result[0];
            $date_diff = 0;
            if ($orderData['expire_on'] != "") {
                $current = strtotime(date("Y-m-d H:i:s"));
                $expire_on = strtotime($orderData['expire_on']);
                $date_diff = $expire_on - $current;
            }
            $responseData['expire_on'] = $orderData['expire_on'];
            if ($total_paid1 == 0 && $date_diff < 1 && $orderData['expire_on'] != "") {

                $sql = "UPDATE coinremitter_order SET payment_status=4 WHERE address= '" . $address . "'";
                $resultTransaction = $connection->query($sql);
                $sql = "UPDATE coinremitter_payment SET status=4 WHERE address= '" . $address . "'";
                $resultTransaction = $connection->query($sql);
                $responseData['status'] = "expire";
                $responseData['order_id'] = $orderData['order_id'];

            } else if ($orderData['payment_status'] == 1 || $orderData['payment_status'] == 3) {

                $responseData['status'] = "success";
                $responseData['order_id'] = $orderData['order_id'];

            } else {
                $totalPaid = 0;
                $total = $orderData['crp_amount'];
                $responseData['nopayment'] = 0;
                if ($orderData['expire_on'] != "") {
                    $responseData['expire_on'] = date("M d, Y H:i:s", strtotime($orderData['expire_on']));
                    $responseData['curr'] = date("Y-m-d H:i:s");
                } else {
                    $responseData['nopayment'] = 1;
                }
                $responseData['total'] = $total;
                $sql = "SELECT * FROM coinremitter_webhook WHERE address= '" . $address . "'";
                $resultWebhook = $connection->fetchAll($sql);
                if ($resultWebhook) {
                    $responseData['flag'] = 1;
                    $responseData['data'] = array();
                    $responseData['nopayment'] = 1;
                    foreach ($resultWebhook as $row) {
                        $data = array();
                        $data['transaction'] = $row['transaction_id'];
                        $data['txid'] = substr($row['txId'], 0, 20) . "...";
                        $data['amount'] = $row['paid_amount'];
                        $data['coin'] = $row['coin'];
                        $data['explorer_url'] = $row['explorer_url'];
                        $data['confirmations'] = $row['confirmations'];
                        $data['paid_date'] = date("M d, Y H:i:s", strtotime($row['paid_date']));
                        if ($row['confirmations'] >= 3) {
                            $totalPaid += $row['paid_amount'];
                        }

                        array_push($responseData['data'], $data);
                    }
                } else {
                    $responseData['flag'] = 0;
                    $responseData['msg'] = "No payment history found";
                }
                if ($orderData['coin'] == "DOGE") {
                    $resultData['total'] = number_format($resultData['total'], 5, '.', '');
                    $responseData['totalPaid'] = number_format($totalPaid, 5, '.', '');
                    $responseData['totalPending'] = number_format($total - $totalPaid, 5, '.', '');
                } else {
                    $responseData['totalPaid'] = number_format($totalPaid, 8, '.', '');
                    $responseData['totalPending'] = number_format($total - $totalPaid, 8, '.', '');
                }
                $responseData['coin'] = $orderData['coin'];
            }
        } else {
            $responseData['flag'] = 0;
            $responseData['msg'] = "Address not found";
        }
        $resultJson->setData(['output' => $responseData]);
        return $resultJson;
    }

    public function CR_get_transactions_by_address($param)
    {

        $api_base_url = $this->apiCall->getApiUrl();
        $data = $param;
        $url = $api_base_url . "/" . $data['coin'] . "/get-transaction-by-address";

        $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST, $data);
        return $res;
    }

    public function dataInsertDB($_data)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $sql = "INSERT INTO coinremitter_webhook SET address=?, transaction_id=?, txId=?, explorer_url=?, paid_amount=?, coin=?, confirmations=?, paid_date=?";
        $connection->query($sql, $_data);
        $lastInsertId = $connection->lastInsertId();

        $sql = "SELECT * FROM coinremitter_webhook WHERE transaction_id='" . $_data[1] . "'";
        $result = $connection->fetchAll($sql);

        if (count($result) > 1) {
            $sql = "DELETE  FROM coinremitter_webhook WHERE id=$lastInsertId";
            $connection->query($sql);
        }
    }
}
