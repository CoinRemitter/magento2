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

    public function __construct( 
    	\Magento\Framework\App\Action\Context $context,
    	\Magento\Framework\View\Result\PageFactory $resultPageFactory,
    	\Magento\Checkout\Model\Session $checkoutSession,
        \Coinremitter\Checkout\Logger\Logger $logger,
        \Magento\Framework\App\State $appState,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement
        
    	)
    {
    	$this->resultPageFactory = $resultPageFactory;
    	$this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_appState = $appState;
        $this->orderManagement = $orderManagement;
        parent::__construct($context);
    }
    public function execute() {

        $env_mode = $this->_appState->getMode();
        $is_show_cancelled_page = 0;
        if ($this->getRequest()->getParam('order_id') && is_numeric($this->getRequest()->getParam('order_id'))) {
            
            $orderId = $this->getRequest()->getParam('order_id');

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $getOrderId = "SELECT * FROM coinremitter_order WHERE order_id= '".$orderId."'";
            $coinremitter_order_detail = $connection->fetchAll($getOrderId); 
            if(!empty($coinremitter_order_detail)){
                $coinremitter_order = $coinremitter_order_detail[0];
                if(isset($coinremitter_order['payment_status']) && strtolower($coinremitter_order['payment_status']) == 'pending'){
                    $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
                    $order_status = $order->getStatus();
                    if(strtolower($order_status) == 'pending'){
                        $this->orderManagement->cancel($orderId);   
                        $this->checkoutSession->clearQuote();  //remove qoute info from session
                        $is_show_cancelled_page = 1;
                    }
                    
                }
                
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
     
    
}
