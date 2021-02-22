<?php
namespace Coinremitter\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;
class CoinremitterRedirect implements ObserverInterface
{
    protected $_redirect;
    protected $_response;
    protected $apiCall;
    protected $_scopeConfig;
    protected $request;
    protected $_messageManager;
    protected $_url;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\App\ResponseInterface $response,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService
    ) {

         $this->_redirect = $redirect;
         $this->_response = $response;
         $this->_url = $url;
         $this->apiCall = $apiCall;
         $this->_scopeConfig = $scopeConfig;
         $this->request = $request;
         $this->_messageManager = $messageManager;
         $this->productRepository = $productRepository;
         $this->_invoiceService = $invoiceService;
    }
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {       
        

      $order_ids = $observer->getEvent()->getOrderIds();
      $order_id = $order_ids[0];
      $order = $this->getOrder($order_id);

      if ($order->getPayment()->getMethodInstance()->getCode() == 'coinremitter_checkout') {
         
         $selectcoin = $order->getPayment()->getTransactionResult();

         $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
         $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
         $connection = $resource->getConnection();
         $sql = "SELECT * FROM coinremitter_order WHERE order_id= '".$order_id."'";
         $result = $connection->fetchAll($sql);

         if(!$result){

            $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$selectcoin."'";
            $result = $connection->fetchAll($sql);

            if (!empty($result)) {
               $wallet =$result[0];
               $wallet_data = [
                  "api_key" => $wallet['api_key'],
                  "password" => $wallet['password'],
                  "coin" => $selectcoin,
               ];

               $newAddress = $this->CR_get_new_address($wallet_data);

               if($newAddress['flag'] == 1){
                  
                  $currencyCode = $order->getOrderCurrencyCode();
                  $grandTotal = $order->getGrandTotal();
                  $invoice_exchange_rate = $this->getStoreConfig('payment/coinremitter_checkout/ex_rate');
                  if($invoice_exchange_rate == 0 || $invoice_exchange_rate == ''){
                     $invoice_exchange_rate = 1;
                  }
                  $amount = $grandTotal*$invoice_exchange_rate;
                  $conversionAmount = [
                     "api_key" => $wallet['api_key'],
                     "password" => $wallet['password'],
                     "coin" => $selectcoin,
                     "fiat_symbol" => $currencyCode,
                     "fiat_amount" => $amount
                  ];

                  $currencyConversion = $this->CR_get_fiat_to_crypto_rate($conversionAmount);

                  if($currencyConversion['flag'] == 1){

                     $db_order = [
                        'order_id' => $order_id,
                        'address' => $newAddress['data']['address'],
                        'crp_amount' => $currencyConversion['data']['crypto_amount'],
                        'payment_status' => 0,
                        'address_qrcode' => $newAddress['data']['qr_code'],
                     ];
                     $this->dataInsertDB('coinremitter_order',$db_order);

                     $invoice_expire = $this->getStoreConfig('payment/coinremitter_checkout/invoice_expiry');
                     if($invoice_expire == '' || $invoice_expire == null || $invoice_expire == 0){
                        $expire_on ="";
                     }else{
                        $newtimestamp = strtotime(date('Y-m-d H:i:s').' + '.$invoice_expire.' minute');
                        $expire_on = date('Y-m-d H:i:s', $newtimestamp);
                     }
                     $db_payment = [
                        'order_id' => $order_id,
                        'address' => $newAddress['data']['address'],
                        'total_amount' => $amount,
                        'base_currancy' => $currencyCode,
                        'coin' => $selectcoin,
                        'conversion_rate' => '',
                        'status' => 0,
                        'expire_on' => $expire_on,
                        'invoice_name' => $wallet['name'],
                        'description' => 'Order Id #'.$order->getIncrementId(),
                        'created_at' => date('Y-m-d H:i:s')
                     ];
                     $this->dataInsertDB('coinremitter_payment',$db_payment);
                     $redirectUrl = $this->getBaseUrl().'coinremitter/invoice/Index/order/'.$order_id;
                     return $this->_redirect->redirect($this->_response, $redirectUrl);

                  } else {
                     $msg=$currencyConversion['msg'];
                  }

               } else {
                  $msg=$newAddress['msg'];
               }

            } else {
               $msg="No Wallet Found";
            }
            //delete order
            $registry = $objectManager->get('Magento\Framework\Registry');
            $registry->register('isSecureArea','true');
            $order->delete();
            $registry->unregister('isSecureArea');
            
            if(!isset($msg)){
               $msg = 'Something went wrong';
            }
            $this->_messageManager->addError($msg);
            $cartUrl = $this->_url->getUrl('checkout/cart/index');
            $this->_redirect->redirect($this->_response, $cartUrl);
         } else {
            $status = $result[0]['payment_status'];
            if($status == 1 || $status == 3){
               $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
               $orders = $objectManager->create('\Magento\Sales\Model\Order')->load($order_id);
               $orderState = $this->getStoreConfig('payment/coinremitter_checkout/order_status');
               $orders->setStatus($orderState);
               $orders->save();
               $this->downloadableOrder($order_id);
            }
         }
      }
    } //end execute function

   public function getOrder($_order_id){

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
      return $order;

   }

   public function getBaseUrl(){

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
      return $storeManager->getStore()->getBaseUrl();

   }

   public function getStoreConfig($_env){

      $_val = $this->_scopeConfig->getValue($_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
      return $_val;
   }

   public function dataInsertDB($_table, $_data){

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();
      $connection->insertForce($_table,$_data);
   }

   public function CR_get_new_address($param){

      $api_base_url = $this->apiCall->getApiUrl();
      $data = $param;
      $url =  $api_base_url."/".$data['coin']."/get-new-address";
        
      $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
      return $res;
   }
   
   public function CR_get_fiat_to_crypto_rate($param){

      $api_base_url = $this->apiCall->getApiUrl();
      $data = $param;
      $url =  $api_base_url."/".$data['coin']."/get-fiat-to-crypto-rate";
        
      $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
      return $res;
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
