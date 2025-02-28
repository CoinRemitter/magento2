<?php

namespace Coinremitter\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;
use Zend\Http\Request;

class CoinremitterAdminOrder implements ObserverInterface
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

      $orderdata = $observer->getData();
      $order_id = $observer->getOrder()->getEntityId();
      $old_order_id = $observer->getOrder()->getRelationParentId();
      $old_order = $this->getOrder($old_order_id);
      $order = $this->getOrder($order_id);

      if ($order->getPayment()->getMethodInstance()->getCode() == 'coinremitter_checkout') {

         $selectcoin = $old_order->getPayment()->getTransactionResult();
         $order->getPayment()->setTransactionResult($selectcoin);
         $order->getPayment()->setAdditionalInformation($old_order->getPayment()->getAdditionalInformation());
         $order->save();
         $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
         $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
         $connection = $resource->getConnection();
         $sql = "SELECT * FROM `coinremitter_orders` WHERE order_id= '" . $order_id . "'";
         $result = $connection->fetchAll($sql);

         if (!$result) {

            $sql = "SELECT * FROM `coinremitter_wallets` WHERE coin_symbol= '" . $selectcoin . "'";
            $result = $connection->fetchAll($sql);

            if (!empty($result)) {
               $wallet = $result[0];


               $credencial = [
                  "x-api-key" => $wallet['api_key'],
                  "x-api-password" => $wallet['password']
               ];

               $newAddress = $this->apiCall->getNewAddress($credencial);

               if ($newAddress['success']) {

                  $currencyCode = $order->getOrderCurrencyCode();
                  $grandTotal = $order->getGrandTotal();
                  // $invoice_exchange_rate = $this->getStoreConfig('payment/coinremitter_checkout/ex_rate');
                  $invoice_exchange_rate = $wallet['exchange_rate_multiplier'];
                  if ($invoice_exchange_rate == 0 || $invoice_exchange_rate == '') {
                     $invoice_exchange_rate = 1;
                  }
                  $amount = $grandTotal * $invoice_exchange_rate;

                  $conversionAmount = [
                     'crypto' => $selectcoin,
                     'fiat' => $currencyCode,
                     'fiat_amount' => $amount
                  ];

                  $currencyConversion = $this->apiCall->getFiatToCryptoRate($conversionAmount);

                  if ($currencyConversion['success']) {

                     $cryptoAmount = $currencyConversion['data'][0]['price'];

                     
                     $invoice_expire = $this->getStoreConfig('payment/coinremitter_checkout/invoice_expiry');
                     if ($invoice_expire == '' || $invoice_expire == null || $invoice_expire == 0) {
                        $expire_on = "";
                     } else {
                        $newtimestamp = strtotime(date('Y-m-d H:i:s') . ' + ' . $invoice_expire . ' minute');
                        $expire_on = date('Y-m-d H:i:s', $newtimestamp);
                     }
                     $db_order = [
                        'order_id' => $order_id,
                        'user_id' => $order->getCustomerId(),
                        'coin_symbol' => $wallet['coin_symbol'],
                        'coin_name' => $wallet['coin_name'],
                        'crypto_amount' => $cryptoAmount,
                        'fiat_symbol' => $currencyCode,
                        'fiat_amount' => $amount,
                        'paid_fiat_amount' => 0,
                        'paid_crypto_amount' => 0,
                        'payment_address' => $newAddress['data']['address'],
                        'qr_code' => $newAddress['data']['qr_code'],
                        'order_status' => 0,
                        'expiry_date' => $expire_on,
                     ];
                     $this->dataInsertDB('coinremitter_orders', $db_order);
                     $redirectUrl = $this->getBaseUrl() . 'coinremitter/invoice/Index/order/' . $order_id;
                     return $this->_redirect->redirect($this->_response, $redirectUrl);
                  } else {
                     $msg = $currencyConversion['msg'];
                  }
               } else {
                  $msg = $newAddress['msg'];
               }
            } else {
               $msg = "No Wallet Found";
            }
            //delete order
            $registry = $objectManager->get('Magento\Framework\Registry');
            $registry->register('isSecureArea', 'true');
            $order->delete();
            $registry->unregister('isSecureArea');

            if (!isset($msg)) {
               $msg = 'Something went wrong';
            }
            $this->_messageManager->addError($msg);
            $cartUrl = $this->_url->getUrl('checkout/cart/index');
            $this->_redirect->redirect($this->_response, $cartUrl);
         } else {
            $status = $result[0]['payment_status'];
            if ($status == 1 || $status == 3) {
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

   public function getOrder($_order_id)
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
      return $order;
   }

   public function getBaseUrl()
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
      return $storeManager->getStore()->getBaseUrl();
   }

   public function getStoreConfig($_env)
   {

      $_val = $this->_scopeConfig->getValue($_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
      return $_val;
   }

   public function dataInsertDB($_table, $_data)
   {

      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
      $connection = $resource->getConnection();
      $connection->insertForce($_table, $_data);
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
