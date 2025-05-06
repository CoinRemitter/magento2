<?php

namespace Coinremitter\Checkout\Controller\Invoice;

use Zend\Http\Request;


class Webhook extends \Magento\Framework\App\Action\Action
{

   protected $apiCall;
   protected $orderManagement;
   protected $productRepository;
   protected $_scopeConfig;

   public function __construct(
      \Magento\Framework\App\Action\Context $context,
      \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
      \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
      \Magento\Sales\Api\OrderManagementInterface $orderManagement,
      \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
   ) {

      $this->apiCall = $apiCall;
      $this->orderManagement = $orderManagement;
      $this->_scopeConfig = $scopeConfig;
      parent::__construct($context);
      $this->productRepository = $productRepository;
   }

   public function execute()
   {

      $webhookData = $this->getRequest()->getParams();

      if (!isset($webhookData['address']) || !isset($webhookData['coin_symbol']) || !isset($webhookData['type'])) {
         return $this->getResponse()->setBody('Invalid webhook data');
      }
      $address = $webhookData['address'];
      $coin = $webhookData['coin_symbol'];
      $id = $webhookData['id'];

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();


      $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address`= '" . $address . "'";
      $coinremitterOrder = $connection->fetchAll($sql);

      if (empty($coinremitterOrder)) {
         return $this->getResponse()->setBody('Invalid address');
      }
      $coinremitterOrder = $coinremitterOrder[0];

      if ($coinremitterOrder['coin_symbol'] != $coin) {
         return $this->getResponse()->setBody('Invalid coin');
      }

      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $expire_on = $coinremitterOrder['expiry_date'];
      $orderId = $coinremitterOrder['order_id'];
      $order = $this->getOrder($orderId);
      $order_status = $order->getStatus();

      $statusCode = $this->apiCall->orderStatusCode;
      // print_r($coinremitterOrder['order_status']);
      // die;
      if (empty($coinremitterOrder['transaction_meta']) && $coinremitterOrder['order_status'] == $statusCode['pending'] && $expire_on != "") {
         $current = strtotime(date("Y-m-d H:i:s"));
         $expire_on = strtotime($expire_on);
         $date_diff = $expire_on - $current;
         if ($date_diff < 1 && $statusCode['expired'] != $coinremitterOrder['order_status']) {
            $this->orderManagement->cancel($orderId);

            $expiryStatus = $statusCode['expired'];
            $sql = "UPDATE `coinremitter_orders` SET `order_status`= $expiryStatus WHERE `payment_address`= '$address' ";
            $connection->query($sql);
            return $this->getResponse()->setBody('Order expired');
         }
      }

      $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $coin . "'";
      $resultWallet = $connection->fetchAll($sql);
      if (empty($resultWallet)) {
         return 'Wallet not found';
      }

      $wallet = $resultWallet[0];
      $credencial = [
         "x-api-key" => $wallet['api_key'],
         "x-api-password" => $wallet['password'],
      ];
      $transaction = $this->apiCall->getTransaction(['id' => $webhookData['id']], $credencial);
      // echo "<pre>";
      // print_r($transaction);
      // die;

      if (!isset($transaction) || !$transaction['success']) {
         return $this->getResponse()->setBody('Transaction Not Found');
      }
      $transactionData = $transaction['data'];
      if ($transactionData['type'] != 'receive') {
         return $this->getResponse()->setBody('Transaction Type Not Matched');
      }

      if (strtolower($transactionData['address']) != strtolower($coinremitterOrder['payment_address'])) {
         return $this->getResponse()->setBody('Invalid Address');
      }

      $fiat_amount = ($transactionData['amount'] * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'];
      $minFiatAmount = $wallet['minimum_invoice_amount'];
      if ($coinremitterOrder['fiat_symbol'] != 'USD') {
         $minFiatAmount = $wallet['minimum_invoice_amount'];
      }
      $minFiatAmount = number_format($minFiatAmount, 2, '.', '');
      $fiat_amount = number_format($fiat_amount, 2, '.', '');
      // $currency = Currency::getIdByIsoCode($coinremitterOrder['fiat_symbol']);
      // $currency = new Currency($currency);
      // $fiat_amount = Tools::convertPrice($fiat_amount, $currency, false);

      if ($fiat_amount < $minFiatAmount) {
         return $this->getResponse()->setBody('Order amount is less than minimum amount');
      }

      $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address`= '" . $address . "' LIMIT 1";
      $result = $connection->fetchAll($sql);
      if (empty($result)) {
         return $this->getResponse()->setBody('Address Not Found');
      }
      $coinremitterOrder = $result[0];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $transactionInfo = $this->apiCall->checkTransactionExists($coinremitterOrder['transaction_meta'], $transactionData['txid']);
      $trxMeta = $coinremitterOrder['transaction_meta'];
      $total_paid = $coinremitterOrder['paid_crypto_amount'];
      $updateOrderRequired = false;
      if (empty($transactionInfo)) {
         $trxMeta[$transactionData['txid']] = $transactionData;
         if ($transactionData['status_code']) {
            $total_paid += $transactionData['amount'];
         }
         $updateOrderRequired = true;
      }

      if ($trxMeta[$transactionData['txid']]['status_code'] == 0 && $transactionData['confirmations'] >= $transactionData['required_confirmations']) {
         $trxMeta[$transactionData['txid']] = $transactionData;
         $trxMeta[$transactionData['txid']]['status_code'] = 1;
         $total_paid += $transactionData['amount'];
         $updateOrderRequired = true;
      }

      if (!$updateOrderRequired) {
         return $this->getResponse()->setBody('Order Not Updated');
      }

      $truncationValue = $this->apiCall->truncationValue;
      if ($coinremitterOrder['fiat_symbol'] != 'USD') {
         $conversionParam = [
            'crypto' => $coinremitterOrder['coin_symbol'],
            'crypto_amount' => $coinremitterOrder['crypto_amount'],
            'fiat' => 'USD',
         ];
         $cryptoToUsdRate = $this->apiCall->getCryptoToFiatRate($conversionParam);
         if ($cryptoToUsdRate['success']) {
            $usdAmount = $cryptoToUsdRate['data'][0]['amount'];
         }
         $truncationValue = ($coinremitterOrder['fiat_amount'] * $truncationValue) / $usdAmount;
      }
      $truncationValue = number_format($truncationValue, 4, '.', '');
      $total_fiat_paid = number_format(($total_paid * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'], 2, '.', '');
      $totalFiatPaidWithTruncation = $total_fiat_paid + $truncationValue;

      $status = $coinremitterOrder['order_status'];
      if ($total_paid == $coinremitterOrder['crypto_amount']) {
         $status = $statusCode['paid'];
      } else if ($total_paid > $coinremitterOrder['crypto_amount']) {
         $status = $statusCode['over_paid'];
      } else if ($total_paid != 0 && $total_paid < $coinremitterOrder['crypto_amount']) {
         $status = $statusCode['under_paid'];
         if ($totalFiatPaidWithTruncation > $coinremitterOrder['fiat_amount']) {
            $status = $statusCode['paid'];
         }
      }

      if ($coinremitterOrder['order_status'] == $statusCode['expired']) {
         $status = $statusCode['expired'];
      }

      $trxMeta = json_encode($trxMeta);
      $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE `payment_address`='" . $coinremitterOrder['payment_address'] . "'";
      $connection->query($sql);
      if ($status == $statusCode['paid'] || $status == $statusCode['over_paid']) {
         $this->orderConfirm($orderId);
         return $this->getResponse()->setBody('Order paid');
      }
      return $this->getResponse()->setBody('Order Updated');
   }

   public function CR_get_transaction($param)
   {

      $api_base_url = $this->apiCall->getApiUrl();
      $data = $param;
      $url =  $api_base_url . "/" . $data['coin'] . "/get-transaction";

      $res = $this->apiCall->apiCaller($url, Request::METHOD_POST, $data);
      return $res;
   }

   public function dataInsertDB($_data)
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();
      //$result = $connection->insertForce($_table,$_data);

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

   public function getOrder($_order_id)
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
      return $order;
   }

   public function orderConfirm($_order_id)
   {
      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $orders = $objectManager->create('\Magento\Sales\Model\Order')->load($_order_id);
      $orderState = $this->getStoreConfig('payment/coinremitter_checkout/order_status');
      $orders->setStatus($orderState);
      $orders->save();
      $this->downloadableOrder($_order_id);
   }

   public function orderCancel($_order_id)
   {
      $this->orderManagement->cancel($_order_id);
   }

   public function getStoreConfig($_env)
   {
      $_val = $this->_scopeConfig->getValue(
         $_env,
         \Magento\Store\Model\ScopeInterface::SCOPE_STORE
      );
      return $_val;
   }

   public function downloadableOrder($orderId)
   {
      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
      $orderItems = $order->getAllItems();
      $isDownloadableType = null;
      foreach ($orderItems as $item) {
         $product = $this->productRepository->get($item->getSku());
         if ($product->getTypeId() === \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE) {
            $isDownloadableType = true;
            break;
         }
      }
      if ($isDownloadableType) {
         $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
         $connection = $resource->getConnection();
         $status = \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_AVAILABLE;
         $sql = "UPDATE downloadable_link_purchased_item SET status='$status' WHERE purchased_id IN (SELECT purchased_id FROM downloadable_link_purchased WHERE order_id=$orderId)";
         $result = $connection->query($sql);
      }
   }
}
