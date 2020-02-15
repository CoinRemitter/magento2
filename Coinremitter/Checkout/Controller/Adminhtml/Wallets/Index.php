<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

class Index extends \Magento\Backend\App\Action
{
    // protected $walletsFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
        // \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        // $this->walletsFactory = $walletsFactory;
    }

    public function execute()
    {
        /*$allnews = $this->walletsFactory->create();
        $newsCollection = $allnews->getCollection();
        
        echo '<pre>';print_r($newsCollection->getData());*/

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Wallets - Coinremitter')));
        return $resultPage;
    }
}