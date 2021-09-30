<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

class Index extends \Magento\Backend\App\Action
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $webhook_msg = "For all these wallets, add this " . $this->getBaseUrl() . "coinremitter/invoice/webhook URL in the Webhook URL field of your Coinremitter wallet's General Settings.";
        $this->messageManager->addWarningMessage(__($webhook_msg));
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Wallets - Coinremitter')));
        return $resultPage;
    }

    public function getBaseUrl()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        return $storeManager->getStore()->getBaseUrl();

    }
}
