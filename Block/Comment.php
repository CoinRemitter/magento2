<?php

namespace Coinremitter\Checkout\Block;

class Comment extends \Magento\Framework\View\Element\Template {

   protected $coinremitterPaymentModel;
   protected $apiCall;
   protected $_scopeConfig;

   public function __construct(
     \Magento\Framework\View\Element\Template\Context $context,
     \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
     \Magento\Framework\Registry $registry,
     \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
     \Coinremitter\Checkout\Model\ResourceModel\CoinremitterPayment\CollectionFactory $coinremitterPaymentModel,
     \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
     \Magento\Sales\Model\Service\InvoiceService $invoiceService,
     array $data = []
  ) {
      $this->_coreRegistry = $registry;
      parent::__construct($context, $data);
      $this->apiCall = $apiCall;
      $this->_scopeConfig = $scopeConfig;
      $this->coinremitterPaymentModel = $coinremitterPaymentModel;
      $this->productRepository = $productRepository;
      $this->_invoiceService = $invoiceService;
   }

   public function getOrder() {   
      return $this->_coreRegistry->registry('current_order');
   }
   public function getOrderId()
   {
      return $this->getOrder()->getEntityId();
   }
   public function getInvoice(){
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

      $sql="SELECT coin, address, expire_on, invoice_id, status FROM coinremitter_payment WHERE order_id=".$orderId;
      $result = $connection->fetchAll($sql);

      $coin = $result[0]['coin'];
      $address = $result[0]['address'];
      $expire_on = $result[0]['expire_on'];
      $invoice_id = $result[0]['invoice_id'];
      $order_status = $order->getStatus();
      $payment_status = $result[0]['status'];

      $resultData = array();
      
      if(isset($invoice_id) && $invoice_id != ""){

         $sql="SELECT co.invoice_id, co.crp_amount, co.payment_status, cp.coin, cp.payment_history, cp.invoice_url,cp.base_currancy FROM coinremitter_order as co, coinremitter_payment as cp where co.invoice_id=cp.invoice_id AND co.order_id=".$orderId;
         $result = $connection->fetchAll($sql);
         $orderData = $result[0];

         $resultData['invoice_id'] = $orderData['invoice_id'];
         $resultData['invoice_url'] = $orderData['invoice_url'];
         $resultData['crp_amount'] = $orderData['crp_amount'];
         $resultData['payment_status'] = $orderData['payment_status'];
         $resultData['coin'] = $orderData['coin'];
         $resultData['base_currancy'] = $orderData['base_currancy'];
         $total_paid = 0;
         $resultData['transactions'] = array();
         if($orderData['payment_history'] != ""){
            $transactions = json_decode($orderData['payment_history'],true);
            foreach ($transactions as $transaction) {
               $total_paid += number_format($transaction['amount'],8,'.','');
               $data['transaction_id'] = $transaction['txid'];
               $data['explorer_url'] = $transaction['explorer_url'];
               $data['amount'] =  $transaction['amount'];
               $data['confirmations'] =  $transaction['confirmation'];
               $data['paid_date'] =  $transaction['date'];
               array_push($resultData['transactions'], $data);
            }
         }
         if(strtolower($order_status) == 'canceled'){
            $total_paid = 0.00000000;
         }
         $resultData['btnShow'] = 0;
         if($orderData['coin'] == "DOGE"){
          $resultData['crp_amount'] = number_format($resultData['crp_amount'],5,'.','');
          $resultData['total_paid'] = number_format($total_paid,5,'.','');
          $resultData['total_pending'] = number_format($orderData['crp_amount']-$total_paid,5,'.','');
       }else{
         $resultData['total_paid'] = number_format($total_paid,8,'.','');
         $resultData['total_pending'] = number_format($orderData['crp_amount']-$total_paid,8,'.','');
      }
   } else {
      
      $resultData['btnShow'] = 0;
      if(($payment_status == 0 || $payment_status == 2) && (strtolower($order_status) == 'pending')){
         $resultData['btnShow'] = 1;
         $sql="SELECT * FROM coinremitter_webhook WHERE address='".$address."'";
         $webhookData = $connection->fetchAll($sql);

         $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
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
         if($transactions['flag'] == 1){
            foreach ($transactions['data'] as $transaction) {
               $confirm = $transaction['confirmations'] < 3 ? $transaction['confirmations'] : 3;
               if(in_array($transaction['id'], $transactionsArray)){
                  $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirm." WHERE transaction_id='".$transaction['id']."'";
                  $resultTransaction = $connection->query($sql);
               } else {
                  $webhook_data = [
                     "address" => $transaction['address'],
                     "transaction_id" => $transaction['id'],
                     "txId" => $transaction['txid'],
                     "paid_amount" => $transaction['amount'],
                     "coin" => $transaction['coin_short_name'],
                     "confirmations" => $confirm,
                     "paid_date" => $transaction['date'],
                     "explorer_url" => $transaction['explorer_url'],
                  ];
                  $this->dataInsertDB("coinremitter_webhook",$webhook_data);
               }
            }
         }

         $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
         $resultWebhook = $connection->fetchAll($sql);
         $total_paid = 0;
         $total_paid1 = 0;
         foreach ($resultWebhook as $rowWebhook) {
            $total_paid1 += number_format($rowWebhook['paid_amount'],8,".","");
            if($rowWebhook['confirmations'] >= 3){
               $total_paid += number_format($rowWebhook['paid_amount'],8,".","");
            }
         }
         
         $sql = "SELECT crp_amount,payment_status FROM coinremitter_order WHERE address= '".$address."'";
         $resultAmount = $connection->fetchAll($sql)[0];
         $payment_status = $resultAmount['payment_status'];
         $total_paid = (string)$total_paid;
         $status = 0;
         if($resultAmount['crp_amount'] == $total_paid)
            $status = 1;
         else if($resultAmount['crp_amount'] < $total_paid)
            $status = 3;
         
         if(($status == 1 || $status == 3) && (strtolower($order_status) == 'pending' || strtolower($order_status) == 'complete')){
            $sql = "UPDATE coinremitter_order SET payment_status=$status WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);
            $sql = "UPDATE coinremitter_payment SET status=$status WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);

            if(strtolower($order_status) == 'pending'){
               $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
               $orders = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
               $orderState = $this->getStoreConfig('payment/coinremitter_checkout/order_status');
               $orders->setStatus($orderState);
               $orders->save();
               $this->downloadableOrder($orderId);
               header("Refresh:0");
            }
            $resultData['btnShow'] = 0;
         }
         if($status == 1 || $status == 3)
            $resultData['btnShow'] = 0;

         $date_diff = 0;
         if($expire_on != ""){
            $current = strtotime(date("Y-m-d H:i:s"));
            $expire_on = strtotime($expire_on);
            $date_diff = $expire_on - $current;
         }
         
         if($total_paid1 == 0 && $date_diff < 1 && $payment_status != 4 && $expire_on != ""){
            $sql = "UPDATE coinremitter_order SET payment_status=4 WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);
            $sql = "UPDATE coinremitter_payment SET status=4 WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orders = $objectManager->create('\Magento\Sales\Model\Order') ->load($orderId);
            $orderState = \Magento\Sales\Model\Order::STATE_CANCELED;
            $orders->setStatus($orderState);
            $orders->save();
            header("Refresh:0");
            
         }
         if($payment_status == 4 || $payment_status == 1){
            $resultData['btnShow'] = 0;
         }
      }
      
      $sql="SELECT co.address, co.crp_amount, co.payment_status, cp.coin, cp.payment_history FROM coinremitter_order as co, coinremitter_payment as cp where co.address=cp.address AND co.order_id=".$orderId;
      $result = $connection->fetchAll($sql);
      $orderData = $result[0];

      $resultData['order_id'] = $orderId;
      $resultData['baseurl'] = $this->getBaseUrl();
      $resultData['address'] = $orderData['address'];
      $resultData['crp_amount'] = $orderData['crp_amount'];
      $resultData['payment_status'] = $orderData['payment_status'];
      $resultData['coin'] = $orderData['coin'];

      $resultData['transactions'] = array();
      $total_paid = 0;
      $notconfirm_paid = 0;
      $sql = "SELECT transaction_id, explorer_url, paid_amount, confirmations, txId FROM coinremitter_webhook WHERE address='".$orderData['address']."'";
      $result = $connection->fetchAll($sql);
      
      if($result){
         $webhookData = $result;
         foreach ($webhookData as $webhook) {
            if($webhook['confirmations'] >= 3){
               $total_paid += number_format($webhook['paid_amount'],8,'.','');
            }
            $data = array();
            $data['transaction_id'] = substr($webhook['txId'],0,30)."...";
            $data['explorer_url'] = $webhook['explorer_url'];
            array_push($resultData['transactions'], $data);
         }
      }
      if(strtolower($order_status) == 'canceled'){
         $total_paid = 0.00000000;
      }
      if($orderData['coin'] == "DOGE"){
         $resultData['crp_amount'] = number_format($resultData['crp_amount'],5,'.','');
         $resultData['total_paid'] = number_format($total_paid,5,'.','');
         $resultData['total_pending'] = number_format($orderData['crp_amount']-$total_paid,5,'.','');
      }else{
         $resultData['total_paid'] = number_format($total_paid,8,'.','');
         $resultData['total_pending'] = number_format($orderData['crp_amount']-$total_paid,8,'.','');
      }
   }
   return $resultData;
}

public function getBaseUrl(){

   $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
   $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
   return $storeManager->getStore()->getBaseUrl();

}

public function dataInsertDB($_table, $_data){

   $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
   $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
   $connection = $resource->getConnection();
   $connection->insertForce($_table,$_data);
}

public function CR_get_transactions_by_address($param){

   $api_base_url = $this->apiCall->getApiUrl();
   $data = $param;
   $url =  $api_base_url."/".$data['coin']."/get-transaction-by-address";
   
   $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
   return $res;
}

public function getStoreConfig($_env){
   $_val = $this->_scopeConfig->getValue(
      $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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
   if($isDownloadableType){
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();
      $status = \Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_AVAILABLE;
      $sql = "UPDATE downloadable_link_purchased_item SET status='$status' WHERE purchased_id IN (SELECT purchased_id FROM downloadable_link_purchased WHERE order_id=$orderId)";
      $result = $connection->query($sql);
   }
}
}