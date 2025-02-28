<?php

namespace Coinremitter\Checkout\Block;

use Zend\Http\Request;

class Comment extends \Magento\Framework\View\Element\Template
{

   protected $coinremitterPaymentModel;
   protected $apiCall;
   protected $_scopeConfig;
   protected $checkoutSession;
   protected $orderManagement;

   public function __construct(
      \Magento\Framework\View\Element\Template\Context $context,
      \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
      \Magento\Framework\Registry $registry,
      \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
      \Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment\CollectionFactory $coinremitterPaymentModel,
      \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
      \Magento\Sales\Model\Service\InvoiceService $invoiceService,
      \Magento\Sales\Api\OrderManagementInterface $orderManagement,
      \Magento\Checkout\Model\Session $checkoutSession,
      array $data = []
   ) {
      $this->_coreRegistry = $registry;
      parent::__construct($context, $data);
      $this->apiCall = $apiCall;
      $this->_scopeConfig = $scopeConfig;
      $this->checkoutSession = $checkoutSession;
      $this->orderManagement = $orderManagement;
      $this->coinremitterPaymentModel = $coinremitterPaymentModel;
      $this->productRepository = $productRepository;
      $this->_invoiceService = $invoiceService;
   }

   public function getOrder()
   {
      return $this->_coreRegistry->registry('current_order');
   }
   public function getOrderId()
   {
      return $this->getOrder()->getEntityId();
   }
   public function getInvoice()
   {
      $orderId = $this->getOrderId();
      $collection = $this->coinremitterPaymentModel->create();
      $collection->addFieldToFilter('order_id', $orderId);

      return $collection->getFirstItem();
   }

   public function getDetail()
   {
      $order = $this->getorder();
      $orderId = $this->getOrderId();
      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();

      $sql = "SELECT * FROM `coinremitter_orders` WHERE order_id=" . $orderId;
      $coinremitterOrder = $connection->fetchAll($sql);
      if (empty($coinremitterOrder)) {
         return [];
      }

      $coinremitterOrder = $coinremitterOrder[0];
      $coin = $coinremitterOrder['coin_symbol'];
      $address = $coinremitterOrder['payment_address'];
      $expire_on = $coinremitterOrder['expiry_date'];
      $order_status = $order->getStatus();

      $statusCode = $this->apiCall->orderStatusCode;
      $orderStatus = $this->apiCall->orderStatus;

      $resultData = array(
         'order_id' => $orderId,
         'baseurl' => $this->getBaseUrl(),
         'address' => $coinremitterOrder['payment_address'],
         'crp_amount' => $coinremitterOrder['crypto_amount'],
         'payment_status' => $orderStatus[$coinremitterOrder['order_status']],
         'coin' => $coin,
         'total_paid' => number_format($coinremitterOrder['paid_crypto_amount'], 8, '.', ''),
         'total_pending' => number_format($coinremitterOrder['crypto_amount'] - $coinremitterOrder['paid_crypto_amount'], 8, '.', ''),
         'transactions' => array(),
         'btnShow' => 0,
         'created_at' => $coinremitterOrder['created_at'],
         'expiry_date' => $expire_on,
      );

      $sql = "SELECT * FROM `coinremitter_orders` WHERE `order_id` = '$orderId' LIMIT 1";
      $coinremitterOrder = $connection->fetchAll($sql);
      if (empty($coinremitterOrder)) {
         return [];
      }
      $coinremitterOrder = $coinremitterOrder[0];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];

      $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $coin . "'";
      $wallet = $connection->fetchAll($sql);
      if (empty($wallet)) {
         return $resultData;
      }
      $wallet = $wallet[0];
      $credencial = [
         "x-api-key" => $wallet['api_key'],
         "x-api-password" => $wallet['password'],
      ];

      $transactionRes = $this->apiCall->getTransactionByAddress(["address" => $address], $credencial);
      if (!isset($transactionRes) || !$transactionRes['success']) {
         return $resultData;
      }
      $transactionData = $transactionRes['data'];
      $allTrx = $transactionData['transactions'];

      $date_diff = 0;
      if (empty($coinremitterOrder['transaction_meta']) && $expire_on != "") {
         $current = strtotime(date("Y-m-d H:i:s"));
         $expire_on = strtotime($expire_on);
         $date_diff = $expire_on - $current;
         if ($date_diff < 1 && $statusCode['expired'] != $coinremitterOrder['order_status']) {

            $this->orderManagement->cancel($orderId);
            $this->checkoutSession->clearQuote();

            $expiryStatus = $statusCode['expired'];
            $total_paid = $transactionData['confirm_amount'];
            $total_fiat_paid = number_format(($total_paid * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'], 2, '.', '');
            $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $transactionData['confirm_amount'] . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $expiryStatus . ",`transaction_meta`='" . json_encode($allTrx) . "' WHERE payment_address='" . $coinremitterOrder['payment_address'] . "'";
            $connection->query($sql);
            $resultData['payment_status'] = $orderStatus[$expiryStatus];
            return $resultData;
         }
      }



      $updateOrderRequired = false;
      $trxMeta = $coinremitterOrder['transaction_meta'];
      $total_paid = 0;
      $resultData['transactions'] = $this->apiCall->prepareReturnTrxData($trxMeta);
      foreach ($allTrx as $trx) {
         if (isset($trx['type']) && $trx['type'] == 'receive') {

            $fiat_amount = ($trx['amount'] * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'];
            $minFiatAmount = $wallet['minimum_invoice_amount'];
            if ($coinremitterOrder['fiat_symbol'] != 'USD') {
               $minFiatAmount = $wallet['minimum_invoice_amount'];
            }
            $minFiatAmount = number_format($minFiatAmount, 2, '.', '');
            $fiat_amount = number_format($fiat_amount, 2, '.', '');
            // $currency = Currency::getIdByIsoCode($coinremitterOrder['fiat_symbol']);
            // $currency = new Currency($currency);
            // print_r($minFiatAmount);
            // die;
            // $fiat_amount = Tools::convertPrice($fiat_amount,$currency,false);
            if ($fiat_amount < $minFiatAmount) {
               continue;
            }


            $transactionInfo = $this->apiCall->checkTransactionExists($coinremitterOrder['transaction_meta'], $trx['txid']);
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
      }


      if (!$updateOrderRequired) {
         return $resultData;
      }

      $truncationValue = $this->apiCall->truncationValue;
      if ($coinremitterOrder['fiat_symbol'] != 'USD') {
         $conversionParam = [
            'crypto' => $coinremitterOrder['coin_symbol'],
            'crypto_amount' => $coinremitterOrder['crypto_amount'],
            'fiat' => 'USD',
         ];
         $cryptoToUsdRate = $this->apiCall->getCryptoToFiatRate($conversionParam);
         if($cryptoToUsdRate['success']){
            $usdAmount = $cryptoToUsdRate['data'][0]['amount'];
         }
         $truncationValue = ($coinremitterOrder['fiat_amount'] * $truncationValue)/$usdAmount;
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


      $trxMeta = json_encode($trxMeta);
      if ($coinremitterOrder['order_status'] == $statusCode['expired']) {
         $this->orderManagement->cancel($orderId);
         $this->checkoutSession->clearQuote();
         $status = $statusCode['expired'];
      }
      if ($status == $statusCode['paid'] || $status == $statusCode['over_paid']) {
         $this->orderConfirm($orderId);
      }
      $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE payment_address='" . $coinremitterOrder['payment_address'] . "'";
      $connection->query($sql);

      $resultData['btnShow'] = 0;
      if ($status == $statusCode['under_paid'] || $status == $statusCode['pending']) {
         $resultData['btnShow'] = 1;
      }

      $resultData['payment_status'] = $orderStatus[$status];
      $resultData['total_paid'] = number_format($total_paid, 8, '.', '');
      $resultData['total_pending'] = number_format($coinremitterOrder['crypto_amount'] - $total_paid, 8, '.', '');
      $resultData['transactions'] = $this->apiCall->prepareReturnTrxData(json_decode($trxMeta, true));
      return $resultData;
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

   public function getBaseUrl()
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
      return $storeManager->getStore()->getBaseUrl();
   }

   public function CR_get_transactions_by_address($param)
   {

      $api_base_url = $this->apiCall->getApiUrl();
      $data = $param;
      $url =  $api_base_url . "/" . $data['coin'] . "/get-transaction-by-address";

      $res = $this->apiCall->apiCaller($url, Request::METHOD_POST, $data);
      return $res;
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

