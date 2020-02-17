<?php
namespace Coinremitter\Checkout\Controller\Invoice;

use \Magento\Framework\App\Action\Context;

class Success extends \Magento\Framework\App\Action\Action
{
	private $checkoutSession;
    protected $resultPageFactory;
    protected $_logger;

    public function __construct( 
    	\Magento\Framework\App\Action\Context $context,
    	\Magento\Framework\View\Result\PageFactory $resultPageFactory,
    	\Magento\Checkout\Model\Session $checkoutSession,
        \Coinremitter\Checkout\Logger\Logger $logger
    	)
    {
    	$this->resultPageFactory = $resultPageFactory;
    	$this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        parent::__construct($context);
    }
    public function execute() {

        if (isset($_GET['order_id']) && $_GET['order_id'] != '' && $_GET['order_id'] != 0 && is_numeric($_GET['order_id'])) {
            
            $orderId = $_GET['order_id'];
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $getOrderId = "SELECT * FROM coinremitter_order WHERE order_id= '".$orderId."'";
            $result_invoice = $connection->fetchAll($getOrderId); 
            $this->checkoutSession->clearQuote();  //remove qoute info from session

            if(!empty($result_invoice)){

                $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderId);

                if ($order->getQuoteId() > 0) {

                    $email = $order->getCustomerEmail();
                    $realOrderId = $order->getIncrementId();
                    
                    /*set order and qoute data in session*/

                    $this->checkoutSession->setLastOrderId($orderId);
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastRealOrderId($realOrderId);
                }
            }else{
                $this->_logger->info('Invoice_Success : Invoice Not Found OR Error in getting invoice');
                $this->_logger->info('Invoice_Success : '.json_encode($result_invoice));
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/success');
        return $resultRedirect;
    }
     
    
}