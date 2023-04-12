<?php

namespace Coinremitter\Checkout\Controller\Invoice;
use Zend\Http\Request;


class Webhook extends \Magento\Framework\App\Action\Action {
  
	protected $apiCall;
   protected $orderManagement;
   protected $_scopeConfig;

   public function __construct(
      \Magento\Framework\App\Action\Context $context,
      \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
      \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
      \Magento\Sales\Api\OrderManagementInterface $orderManagement,
      \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
      \Magento\Sales\Model\Service\InvoiceService $invoiceService
   ){
      
      $this->apiCall = $apiCall;
      $this->orderManagement = $orderManagement;
      $this->_scopeConfig = $scopeConfig;
      parent::__construct($context);
      $this->productRepository = $productRepository;
      $this->_invoiceService = $invoiceService;
   }

   public function execute(){

   	if ($this->getRequest()->getParam('address') && $this->getRequest()->getParam('address') != "" && $this->getRequest()->getParam('coin_short_name') && $this->getRequest()->getParam('coin_short_name') != "" && $this->getRequest()->getParam('type') && $this->getRequest()->getParam('type') == "receive") {

        $address = $this->getRequest()->getParam('address');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql = "SELECT order_id, expire_on FROM coinremitter_payment WHERE address= '".$address."'";
        $result = $connection->fetchAll($sql);
        if($result){

         $coin = $this->getRequest()->getParam('coin_short_name');
         $transactionID = $this->getRequest()->getParam('id');
         $expire_on = $result[0]['expire_on'];
         $order_id = $result[0]['order_id'];
         $order = $this->getOrder($order_id);

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
         $order_status = $order->getStatus();
         $sql = "SELECT crp_amount,payment_status FROM coinremitter_order WHERE address= '".$address."'";
         $resultAmount = $connection->fetchAll($sql)[0];
         $payment_status = $resultAmount['payment_status'];
         $total_paid = (string)$total_paid;

         $date_diff = 0;
         if($expire_on != ""){
            $current = strtotime(date("Y-m-d H:i:s"));
            $expire_on = strtotime($expire_on);
            $date_diff = $expire_on - $current;
         }
         if((string)$total_paid1 == 0 && $date_diff < 1 && $expire_on != "" && strtolower($order_status) == 'pending') {

            $sql = "UPDATE coinremitter_order SET payment_status=4 WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);
            $sql = "UPDATE coinremitter_payment SET status=4 WHERE address= '".$address."'";
            $resultTransaction = $connection->query($sql);
            $this->orderCancel($order_id);
         }

         $sql = "SELECT payment_status FROM coinremitter_order WHERE address= '".$address."'";
         $payment_status = $connection->fetchAll($sql)[0]['payment_status'];
         
         if((strtolower($order_status) == 'pending') && ($payment_status == 0 || $payment_status == 2)){
            
            $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
            $resultWallet = $connection->fetchAll($sql);
            $trans_data = [
               "api_key" => $resultWallet[0]['api_key'],
               "password" => $resultWallet[0]['password'],
               "coin" => $coin,
               "id" => $transactionID,
            ];
            $transaction = $this->CR_get_transaction($trans_data);

            if($transaction['flag'] == 1){
               $confirmations = $transaction['data']['confirmations'];
               $sql = "SELECT * FROM coinremitter_webhook WHERE transaction_id= '".$transactionID."'";
               $resultTransaction = $connection->fetchAll($sql);
               if(!$resultTransaction){
                  $webhook_data = [
                     $transaction['data']['address'],
                     $transaction['data']['id'],
                     $transaction['data']['txid'],
                     $transaction['data']['explorer_url'],
                     $transaction['data']['amount'],
                     $transaction['data']['coin_short_name'],
                     $transaction['data']['confirmations'],
                     $transaction['data']['date'],
                  ];
                  $this->dataInsertDB($webhook_data);
                  $msg = "New Transaction";
                  return $this->getResponse()->setBody($msg);
               } else {
                  $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirmations." WHERE transaction_id='".$transaction['data']['id']."'";
                  $resultTransaction = $connection->query($sql);
                  $msg = "Transaction already exists";
                  return $this->getResponse()->setBody($msg);
               }

               $sql="SELECT * FROM coinremitter_webhook WHERE address='".$address."' AND confirmations < 3";
               $webhookData = $connection->fetchAll($sql);
               if($webhookData){
                  foreach ($webhookData as $webhook) {
                     $wallet_data = [
                        "api_key" => $resultWallet[0]['api_key'],
                        "password" => $resultWallet[0]['password'],
                        "coin" => $coin,
                        "id" => $webhook['transaction_id'],
                     ];
                     $transaction = $this->CR_get_transaction($wallet_data);
                     if($transaction['flag'] == 1){
                        $confirm = $transaction["data"]['confirmations'] < 3 ? $transaction["data"]['confirmations'] : 3;
                        $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirm." WHERE transaction_id='".$webhook['transaction_id']."'";
                        $resultTransaction = $connection->query($sql);
                     }
                  }
               }
               $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
               $resultWebhook = $connection->fetchAll($sql);
               $total_paid = 0;
               foreach ($resultWebhook as $rowWebhook) {
                  if($rowWebhook['confirmations'] >= 3){
                     $total_paid += number_format($rowWebhook['paid_amount'],8,".","");
                  }
               }
               $status = 0;
               if($resultAmount['crp_amount'] == $total_paid)
                  $status = 1;
               else if($resultAmount['crp_amount'] < $total_paid)
                  $status = 3;
               else if($total_paid != 0 && $resultAmount['crp_amount'] > $total_paid)
                  $status = 2;

               if(($status == 1 || $status == 3 || $status == 2) && strtolower($order_status) == 'pending'){
                  $sql = "UPDATE coinremitter_order SET payment_status=$status WHERE address= '".$address."'";
                  $resultTransaction = $connection->query($sql);
                  $sql = "UPDATE coinremitter_payment SET status=$status WHERE address= '".$address."'";
                  $resultTransaction = $connection->query($sql);
                  if($status == 1 || $status == 3)
                     $this->orderConfirm($order_id);
               }

            } else {
               $msg = "Transaction Not Found";
               return $this->getResponse()->setBody($msg);
            }
         }

      } else {
        $msg = "Address Not Found";
        return $this->getResponse()->setBody($msg);
     }
  } else {
   $msg = "Parameter required";
   return $this->getResponse()->setBody($msg);
}
}

public function CR_get_transaction($param){

   $api_base_url = $this->apiCall->getApiUrl();
   $data = $param;
   $url =  $api_base_url."/".$data['coin']."/get-transaction";
   
   $res = $this->apiCall->apiCaller($url, Request::METHOD_POST,$data);
   return $res;
}

public function dataInsertDB($_data){

   $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
   $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
   $connection = $resource->getConnection();
      //$result = $connection->insertForce($_table,$_data);

   $sql = "INSERT INTO coinremitter_webhook SET address=?, transaction_id=?, txId=?, explorer_url=?, paid_amount=?, coin=?, confirmations=?, paid_date=?";
   $connection->query($sql, $_data);
   $lastInsertId = $connection->lastInsertId();

   $sql="SELECT * FROM coinremitter_webhook WHERE transaction_id='".$_data[1]."'";
   $result = $connection->fetchAll($sql);

   if(count($result) > 1){
      $sql="DELETE  FROM coinremitter_webhook WHERE id=$lastInsertId";
      $connection->query($sql);
   }

}

public function getOrder($_order_id){

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