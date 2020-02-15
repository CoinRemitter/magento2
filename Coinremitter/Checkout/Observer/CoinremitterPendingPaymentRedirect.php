<?php
namespace Coinremitter\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;
class CoinremitterPendingPaymentRedirect implements ObserverInterface
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

    public function getOrder($_order_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {       
        $order_ids = $observer->getEvent()->getOrderIds();
        $order_id = $order_ids[0];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql2 = "SELECT * FROM coinremitter_order WHERE order_id= '".$order_id."'";
        $result_invoice = $connection->fetchAll($sql2); 
        
        if (!empty($result_invoice) && $result_invoice[0]['payment_status'] == 'pending') {

            $order = $this->getOrder($order_id);
            $coin = $order->getPayment()->getTransactionResult();

            $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
            $result = $connection->fetchAll($sql); 

            if (!empty($result)) {
                
                $wallet_data =$result[0]; 
                $invoice_id = $result_invoice[0]['invoice_id'];

                $postData = [
                    'api_key'=>$wallet_data['api_key'],
                    'password'=>$wallet_data['password'],
                    'invoice_id'=>$invoice_id,
                    'coin'=>$coin
                ];
                $invoice = $this->CR_getInvoice($postData);

                $this->_redirect->redirect($this->_response, $invoice['data']['url']);
            }else{
                $this->_logger->info('Coinremitter_pending_payment_redirection : Data not found on sql query');
                $this->_logger->info('Coinremitter_pending_payment_redirection : '.json_encode($result));
            }
        }/*else{
            $this->_logger->info('Coinremitter_pending_payment_redirection : Invoice data Not Found OR payment_status is not pending');
            $this->_logger->info('Coinremitter_pending_payment_redirection : '.json_encode($result_invoice));
        }*/
        
        
    } //end execute function
    
    
    public function CR_getInvoice($param)
    {   
        $api_base_url = $this->apiCall->getApiUrl();
        $data =$param;
        $url = $api_base_url."/".$data['coin']."/get-invoice";
        $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST,$data);
        return $res;
    }

}
