<?php


namespace Coinremitter\Checkout\Controller\Notify;
use \Magento\Sales\Model\Order;
use \Magento\Framework\App\Action\Context;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Config\ScopeConfigInterface; 
use Zend\Http\Request;
class Index extends \Magento\Framework\App\Action\Action
{
    protected $_scopeConfig;
    protected $api_base_url;
    protected $_logger;
    protected $_appState;
    protected $orderManagement;
    public function __construct( \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Framework\App\State $appState,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement)
    {
        $this->resultPageFactory = $resultPageFactory;        
        $this->_scopeConfig = $scopeConfig;
        $this->apiCall = $apiCall;
        $this->_logger = $logger;
        $this->_appState = $appState;
        $this->orderManagement = $orderManagement;
        parent::__construct($context);
    }
    public function execute()
    {
        $env_mode = $this->_appState->getMode();
        $post = $this->getRequest()->getPostValue();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if($env_mode == 'developer'){
                $this->_logger->info('Notify_Index : Only Post request Allow');
            }
            $error_msg = 'Only POST requests are allowed';
            return $this->getResponse()->setBody($error_msg);
        }
        $post = $this->getRequest()->getPostValue(); 
        if(!isset($post['coin'])){
            if($env_mode == 'developer'){
                $this->_logger->info('Notify_Index : No coin Found');
                $this->_logger->info('Notify_Index : '.json_encode($post));    
            }
            $error_msg = 'No coin found';
            return $this->getResponse()->setBody($error_msg);
        }
        if(!isset($post['invoice_id'])){
            if($env_mode == 'developer'){
                $this->_logger->info('Notify_Index : No invoice id Found');
                $this->_logger->info('Notify_Index : '.json_encode($post));    
            }
            $error_msg = 'No invoice id found';
            return $this->getResponse()->setBody($error_msg);
        }
        
        $coin = $post['coin'];
        $invoice_id = $post['invoice_id'];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('coinremitter_wallets');       
        $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
        $result = $connection->fetchAll($sql); 

        if(!empty($result)){

            $wallet_data =$result[0]; 

            $sql2 = "SELECT * FROM coinremitter_order WHERE invoice_id= '".$invoice_id."'";
            $result_invoice = $connection->fetchAll($sql2); 

            if (!empty($result_invoice)) {
                
                $coinremitter_order =  $result_invoice[0];
                if($coinremitter_order['payment_status'] == 'paid' || $coinremitter_order['payment_status'] == 'over paid'){
                    if($env_mode == 'developer'){
                        $this->_logger->info('Notify_Index : All ready paid payment');
                    }
                    $error_msg = 'Payment is already paid';
                    return $this->getResponse()->setBody($error_msg);

                }
                $orderId = $coinremitter_order['order_id'];
                $postData = [
                    'api_key'=>$wallet_data['api_key'],
                    'password'=>$wallet_data['password'],
                    'invoice_id'=>$invoice_id,
                    'coin'=>$coin
                ];
                $invoice = $this->CR_getInvoice($postData);
                if(!empty($invoice) && $invoice['flag'] ==1){
                    $invoice_data = $invoice['data'];
                    if($invoice_data['status_code'] == 1 || $invoice_data['status_code'] == 3){
                        $order_status = $this->getStoreConfig('payment/coinremitter_checkout/order_status');
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
                        $check_order_status = $order->getStatus();
                        if(strtolower($check_order_status) != 'canceled'){
                            $order->setStatus($order_status);
                            $order->save();   
                        }

                        
                        $data = ["payment_status"=>strtolower($invoice_data['status'])]; // Key_Value Pair
                        $id = $invoice_data['invoice_id'];
                        $where = ['invoice_id = ?' => $id];
                        $tableName = $resource->getTableName("coinremitter_order");
                        $connection->update($tableName, $data, $where);

                        $expire_on = $invoice_data['expire_on'];
                        if($invoice_data['expire_on'] == '' && $invoice_data['expire_on'] == null){
                            $expire_on =null;
                        }

                        $total_amount = json_encode($invoice_data['total_amount']); 
                        $paid_amount = json_encode($invoice_data['paid_amount']);
                        $payment_history =json_encode($invoice_data['payment_history']);
                        $conversion_rate =json_encode($invoice_data['conversion_rate']);
                        $expire_on = $expire_on;   
                        $table_name = $resource->getTableName('coinremitter_payment');
                        $wherePay = ['invoice_id = ?' => $id];
                        $connection->update(
                            $table_name,
                            [
                                'order_id' => $orderId,
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
                            ],
                            $wherePay
                        );

                        if($env_mode == 'developer'){
                            $this->_logger->info('Notify_Index : Invoice Paid');
                            $this->_logger->info('Notify_Index : invoice data >>>'.json_encode($invoice_data));    
                        }
                        
                    }elseif($invoice_data['status_code'] == 4){
                        if(empty($invoice_data['payment_history'])){
                            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                            $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
                            $order_status = $order->getStatus();
                            if(strtolower($order_status) == 'pending'){
                                $this->orderManagement->cancel($orderId);   
                            }

                            //update payment_status in coinremitter_order
                            $data = ["payment_status"=>strtolower($invoice_data['status'])]; // Key_Value Pair
                            $id = $invoice_data['invoice_id'];
                            $where = ['invoice_id = ?' => $id];
                            $tableName = $resource->getTableName("coinremitter_order");
                            $connection->update($tableName, $data, $where);

                            //update status in coinremitter_payment
                            $table_name = $resource->getTableName('coinremitter_payment');
                            $wherePay = ['invoice_id = ?' => $id];
                            $connection->update(
                                $table_name,
                                [
                                    'status'=>$invoice_data['status'],
                                ],
                                $wherePay
                            );
                        }
                        if($env_mode == 'developer'){
                            $this->_logger->info('Notify_Index : invoice_expired');
                            $this->_logger->info('Notify_Index : '.json_encode($invoice_data));
                        }
                    }else{

                        if($env_mode == 'developer'){
                            $this->_logger->info('Notify_Index : Payment does not paid');
                            $this->_logger->info('Notify_Index : '.json_encode($invoice_data));    
                        }
                        
                        $error_msg = 'Payment does not paid';
                        return $this->getResponse()->setBody($error_msg);
                    }

                }else{
                    if($env_mode == 'developer'){
                        $this->_logger->info('Notify_Index : Invoice not found or flog is not 1');
                        $this->_logger->info('Notify_Index : '.json_encode($invoice));    
                    }
                    
                    $error_msg = 'Invoice not found or flag is not 1';
                    return $this->getResponse()->setBody($error_msg);

                }

            }else{

                if($env_mode == 'developer'){
                    $this->_logger->info('Notify_Index : Data not found on sql query');
                    $this->_logger->info('Notify_Index : '.json_encode($result_invoice));
                }
            }

        }else{
            if($env_mode == 'developer'){
                $this->_logger->info('Notify_Index : Data not found on sql query');
                $this->_logger->info('Notify_Index : '.json_encode($result));
            }
        }

    }
    
    public function CR_getInvoice($param)
    {   
        $api_base_url = $this->apiCall->getApiUrl();
        $data =$param;
        $url = $api_base_url."/".$data['coin']."/get-invoice";
        $res = $this->apiCall->apiCaller($url, Request::METHOD_POST,$data);
        return $res;
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
    public function getStoreConfig($_env)
    {
        $_val = $this->_scopeConfig->getValue(
            $_env, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $_val;

    }
}

