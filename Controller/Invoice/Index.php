<?php

namespace Coinremitter\Checkout\Controller\Invoice;

class Index extends \Magento\Framework\App\Action\Action
{

    /** @var  \Magento\Framework\View\Result\Page */
    protected $resultPageFactory;
    protected $request;
    protected $_orderSession;
    protected $apiCall;
    protected $_scopeConfig;
    protected $_cacheTypeList;
    protected $_cacheFrontendPool;
   
   /**      * @param \Magento\Framework\App\Action\Context $context      */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall,
        \Magento\Checkout\Model\Session $orderSession,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
    ) {
        $this->request = $request;
        $this->_orderSession = $orderSession;
        $this->resultPageFactory = $resultPageFactory;
        $this->apiCall = $apiCall;
        $this->_scopeConfig = $scopeConfig;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        parent::__construct($context);
    }
   /**
    * Blog Index, shows a list of recent blog posts.
    *
    * @return \Magento\Framework\View\Result\PageFactory
    */
   
    public function execute()
    {
      
        $order_id = $this->getRequest()->getParam('order');
        $order = $this->getOrder($order_id);
        try {
            if ($order->getId()) {
                if ($order->getCustomerId() == $this->getCustomer()) {
                    if ($order->getPayment()->getMethodInstance()->getCode() == 'coinremitter_checkout' && $order->getStatus() == "pending") {
                        $resultPage = $this->resultPageFactory->create();
                        $resultPage->getConfig()->getTitle()->prepend(__('Order Invoice #' . $order->getIncrementId()));
                        $block = $resultPage->getLayout()->getBlock('coinremitter_invoice');
                        $block->setData('order_id', $order_id);
                        return $resultPage;
                    }
                }
            }
        } catch (Exception $e) {
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('/');
        return $resultRedirect;
    }

    public function getOrder($_order_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;
    }

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        return $customerSession->getCustomer()->getId();
    }
}
