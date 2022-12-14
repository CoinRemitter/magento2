<?php
namespace Coinremitter\Checkout\Controller\Invoice;

use \Magento\Framework\App\Action\Context;

class Cancel extends \Magento\Framework\App\Action\Action
{
	private $checkoutSession;
    protected $resultPageFactory;
    protected $_logger;
    protected $_appState;
    protected $orderManagement;
    protected $apiCall;

    public function __construct( 
    	\Magento\Framework\App\Action\Context $context,
    	\Magento\Framework\View\Result\PageFactory $resultPageFactory,
    	\Magento\Checkout\Model\Session $checkoutSession,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Framework\App\State $appState,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
        
    )
    {
    	$this->resultPageFactory = $resultPageFactory;
    	$this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_appState = $appState;
        $this->apiCall = $apiCall;
        $this->orderManagement = $orderManagement;
        parent::__construct($context);
    }
    public function execute() {

        $env_mode = $this->_appState->getMode();
        $is_show_cancelled_page = 0;
        if($this->getRequest()->getParam('order_id')) {
            
            $orderId = $this->getRequest()->getParam('order_id');
            $order = $this->getOrder($orderId);
            $order_status = $order->getStatus();
            if($order_status != "canceled"){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                $connection = $resource->getConnection();
                $getOrderId = "SELECT co.*, cp.expire_on, cp.coin FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id= '".$orderId."'";
                $coinremitter_order_detail = $connection->fetchAll($getOrderId);
                if(!empty($coinremitter_order_detail)){
                    $coinremitter_order = $coinremitter_order_detail[0];
                    $coin = $coinremitter_order['coin'];
                    $address = $coinremitter_order['address'];
                    $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
                    $result = $connection->fetchAll($sql);
                    $Wallets = $result[0];
                    $wallet_data = [
                     "api_key" => $Wallets['api_key'],
                     "password" => $Wallets['password'],
                     "coin" => $Wallets['coin'],
                     "address" => $address,
                 ];
                 $transactions = $this->CR_get_transactions_by_address($wallet_data);
                 if(isset($coinremitter_order['payment_status']) && ($coinremitter_order['payment_status'] == 0 || $coinremitter_order['payment_status'] == 4) && $transactions['flag'] == 1 && empty($transactions['data'])){
                    
                    $date_diff = 0;
                    if($coinremitter_order['expire_on'] != ""){
                        $current = strtotime(date("Y-m-d H:i:s"));
                        $expire_on = strtotime($coinremitter_order['expire_on']);
                        $date_diff = $expire_on - $current;

                        if($date_diff < 1){
                            $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
                            $order_status = $order->getStatus();
                            if(strtolower($order_status) == 'pending' || strtolower($order_status) == 'canceled'){
                                $this->orderManagement->cancel($orderId);   
                                    $this->checkoutSession->clearQuote();  //remove qoute info from session
                                    $is_show_cancelled_page = 1;
                                }
                            }
                        }
                    }
                }
            }else{
                $is_show_cancelled_page = 1;
            }
        }
        if($is_show_cancelled_page == 1){
            return $this->resultPageFactory->create();
        }else{
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('/');
            return $resultRedirect;  
        }
        
    }
    
    public function CR_get_transactions_by_address($param){

        $api_base_url = $this->apiCall->getApiUrl();
        $data = $param;
        $url =  $api_base_url."/".$data['coin']."/get-transaction-by-address";
        
        $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
        return $res;
    }

    public function getOrder($_order_id){

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;

    }
}
