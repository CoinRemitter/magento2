<?php
namespace Coinremitter\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;
class CoinremitterRedirect implements ObserverInterface
{
    protected $checkoutSession;
    protected $resultRedirect;
    protected $url;
    protected $coreRegistry;
    protected $_redirect;
    protected $_response;
    public $orderRepository;
    protected $_invoiceService;
    protected $_transaction;
    public $selectcoin;
    public $apiToken;
    public $network;
    public $api_base_url;
    protected $apiCall;
    protected $_logger;
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\ResultFactory $result,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ActionFlag $actionFlag,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\App\ResponseInterface $response,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Coinremitter\Checkout\Logger\Logger $logger
    ) {

        $this->coreRegistry = $registry;
        $this->_moduleList = $moduleList;
        $this->_scopeConfig = $scopeConfig;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirect = $result;
        $this->_actionFlag = $actionFlag;
        $this->_redirect = $redirect;
        $this->_response = $response;
        $this->orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->cart = $cart;
        $this->apiCall = $apiCall;
        $this->_logger = $logger;
    }

    function CR_Configuration($token,$network){
   
        $this->apiToken = $token;
        if($network == 'test' || $network == null):
            $this->network = $this->CR_getApiHostDev();
        else:
            $this->network = $this->CR_getApiHostProd();
        endif;
        $config = (new \stdClass());
        $config->network = $network;
        $config->token = $token;

        return $config;
        
    }

    function CR_getAPIToken() {
         #verify the ipn
     

         $env = $this->getStoreConfig('payment/coinremitter/coinremitter_endpoint');
         $coinremitter_token = $this->getStoreConfig('payment/coinremitter/coinremitter_devtoken');
        

         if ($env == 'prod'):
             $coinremitter_token = $this->getStoreConfig('payment/coinremitter/coinremitter_prodtoken');
         endif;
         $this->apiToken = $coinremitter_token;
        return $this->apiToken;
    }
    
    function CR_getNetwork() {

        return $this->network;
    }
    
    public function CR_getApiHostDev()
    {
        return 'test.coinremitter.com';
    }
    
    public function CR_getApiHostProd()
    {
        return 'coinremitter.com';
    }
    
    public function CR_getApiPort()
    {
        return 443;
    }
    
    public function CR_getInvoiceURL(){
        return $this->network.'/invoices';
    }
    
    public function CR_Item($config,$item_params){
      
        $_item = (new \stdClass());
        $_item->token =$config->token;
        $_item->endpoint =  $config->network;
        $_item->item_params = $item_params;
       
        if($_item->endpoint == 'test'){
            $_item->invoice_endpoint = 'test.coinremitter.com';
          
        }else{
            $_item->invoice_endpoint = 'coinremitter.com';
        }
        
        
        return $_item;
    }
    function CR_getItem(){
        $this->invoice_endpoint = $this->endpoint.'/invoices';
        $this->buyer_transaction_endpoint = $this->endpoint.'/invoiceData/setBuyerSelectedTransactionCurrency';
        $this->item_params->token = $this->token;
        return ($this->item_params);
     }

     public function CR_Invoice($item){
        $this->item = $item;
        return $item;
        
       
         
     }

     public function CR_checkInvoiceStatus($orderID,$item)
     {
           
         $post_fields = ($item->item_params);
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, 'https://' . $item->invoice_endpoint . '/invoices/' . $post_fields->invoiceID);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         $result = curl_exec($ch);
         curl_close($ch);
         return $result;
     }

    public function CR_getInvoiceData()
    {
        return $this->invoiceData;
    }

    public function CR_getInvoiceDataURL()
    {
        $data = json_decode($this->invoiceData);
        return $data->data->url;
    }

    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $_val;

    }

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
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {       
        //die('redirect');
        $order_ids = $observer->getEvent()->getOrderIds();
        $order_id = $order_ids[0];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql2 = "SELECT * FROM coinremitter_order WHERE order_id= '".$order_id."'";
        $result_invoice = $connection->fetchAll($sql2); 

        if (empty($result_invoice)) {
             
            $this->_actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $level = 1;

            
            $order = $this->getOrder($order_id);
            $order_id_long = $order->getIncrementId();
            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            $cd = $order->getPayment()->getMethodInstance();
            $dd = $order->getPayment()->getMethodInstance()->getCode();
            $selectcoin = $order->getPayment()->getTransactionResult();
            
                
            if ($order->getPayment()->getMethodInstance()->getCode() == 'coinremitter_checkout') {
             
                $order->setState('new', true);
                $order->setStatus('pending', true);
                $order->save();

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                $connection = $resource->getConnection();
                $tableName = $resource->getTableName('coinremitter_wallets');       
                $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$selectcoin."'";
                $result = $connection->fetchAll($sql); 

                if (!empty($result)) {
                    
                    $wallet_data =$result[0]; 

                    #get the environment
                    $env = $this->getStoreConfig('payment/coinremitter/coinremitter_endpoint');
                    $coinremitter_token = $this->getStoreConfig('payment/coinremitter/coinremitter_devtoken');

                    $api_key = $wallet_data['api_key'];
                    $api_password = $wallet_data['password'];

                    $modal = false;
                 
                    $config = $this->CR_Configuration($coinremitter_token,$env);

                    //create an item, should be passed as an object'

                    $params = (new \stdClass());
                    $params->extension_version = $this->getExtensionVersion();
                    $params->price = $order['base_grand_total'];
                    $params->currancy_type = $order['store_currency_code'];

                    #buyer email
                    $customerSession = $objectManager->create('Magento\Customer\Model\Session');

                    $buyerInfo = (new \stdClass());
                    $guest_login = true;
                    if ($customerSession->isLoggedIn()) {
                        $guest_login = false;
                        $buyerInfo->name = $customerSession->getCustomer()->getName();
                        $buyerInfo->email = $customerSession->getCustomer()->getEmail();

                    } else {
                        $buyerInfo->name = $order->getBillingAddress()->getFirstName() . ' ' . $order->getBillingAddress()->getLastName();
                        $buyerInfo->email = $order->getCustomerEmail();
                    }
                    $params->buyer = $buyerInfo;

                    $params->orderId = trim($order_id_long);

                    $success_url = $this->getBaseUrl() . 'coinremitter/invoice/success?order_id='.$order_id;
                    
                    $params->extendedNotifications = true;
                    $params->acceptanceWindow = 1200000;
                    
                    $currency_type = $order['store_currency_code'];
                    $item = $this->CR_Item( $config,$params);
                    $invoice_expiry = $this->getStoreConfig('payment/coinremitter_checkout/invoice_expiry');
                    $invoice_exchange_rate = $this->getStoreConfig('payment/coinremitter_checkout/ex_rate');
                    if($invoice_expiry == 0 || $invoice_expiry == null){
                        $invoice_expiry ='';
                    }
                    if($invoice_exchange_rate == 0 || $invoice_exchange_rate == ''){
                        $invoice_exchange_rate = 1;
                    }
                    $amount = $order['base_grand_total']*$invoice_exchange_rate;
                    $notify_url = $this->getBaseUrl().'wallets/notify';
                    
                    $invoice_data =[
                        'api_key' =>$api_key,
                        'password' => $api_password,
                        'amount' =>$amount,
                        'coin'=> $selectcoin,
                        'notify_url' => $notify_url,
                        'currency' => $currency_type,
                        'expire_time'=>$invoice_expiry,
                        'suceess_url' => $success_url,
                        'currency' => $params->currancy_type,
                        'description' => 'Order Id #'.$params->orderId,    
                    ];
                    $invoice = $this->CR_createInvoice($invoice_data);
                    
                    if(!empty($invoice) && $invoice['flag'] == 1){
                        
                        $invoice_data = $invoice['data'];
                        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                        $connection = $resource->getConnection();
                        $table_name = $resource->getTableName('coinremitter_order');
                        $order_status = $this->getStoreConfig('payment/coinremitter_checkout/order_status');

                        $coin = $invoice_data['coin'];
                        $camount = $invoice_data['total_amount'][$coin] ;
                        $connection->insertForce(
                            $table_name,
                            ['order_id' => $order_id, 'amountusd'=>$invoice_data['usd_amount'],'invoice_id'=>$invoice_data['invoice_id'],'crp_amount'=>$camount,'payment_status'=>strtolower($invoice_data['status'])]
                        );


                        $expire_on = $invoice_data['expire_on'];
                        if($invoice_data['expire_on'] == '' && $invoice_data['expire_on'] == null){
                            $expire_on =null;
                        }

                        $total_amount = json_encode($invoice_data['total_amount']); 
                        $paid_amount = $invoice_data['paid_amount'] ? json_encode($invoice_data['paid_amount']) : '';
                        $payment_history = isset($invoice_data['payment_history']) ? json_encode($invoice_data['payment_history']) : '';
                        $conversion_rate =json_encode($invoice_data['conversion_rate']);

                        //$expire_on = $expire_on;   
                        $table_name = $resource->getTableName('coinremitter_payment');
                        $connection->insertForce(
                            $table_name,
                            [
                                'order_id' => $order_id,
                                'invoice_id'=>$invoice_data['invoice_id'],
                                'invoice_name'=>$invoice_data['name'],
                                'marchant_name'=>'',
                                'total_amount'=>$total_amount,
                                'paid_amount'=>$paid_amount,
                                'base_currancy'=>$invoice_data['base_currency'],
                                'description'=>$invoice_data['description'],
                                'coin'=>$invoice_data['coin'],
                                'payment_history'=> $payment_history,
                                'conversion_rate'=> $conversion_rate,
                                'invoice_url'=>$invoice_data['url'],
                                'status'=>$invoice_data['status'],
                                'expire_on'=>$expire_on,
                                'created_at'=>$invoice_data['invoice_date']
                            ]
                        );

                        $this->_redirect->redirect($this->_response, $invoice_data['url']);
                        
                    }else{
                        $this->_logger->info('Coinremitter_redirect : Something went wrong while creating invoice');
                        $this->_logger->info('Coinremitter_redirect :'.json_encode($invoice));
                    }

                }else{
                    $this->_logger->info('Coinremitter_redirect :Data not found on sql query');
                    $this->_logger->info('Coinremitter_redirect :'.json_encode($result));
                }
                
            }

        }
        
        
    } //end execute function
    public function getExtensionVersion()
    {
        return 'Coinremitter_Checkout_Magento2_1.0.0';

    }
     public function CR_createInvoice($param)
    {
        $api_base_url = $this->apiCall->getApiUrl();
        $data = $param;
        $url =  $api_base_url."/".$data['coin']."/create-invoice";
        
        $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
        return $res;
    }

}
