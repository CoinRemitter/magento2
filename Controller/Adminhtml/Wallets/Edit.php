<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

use Magento\Backend\App\Action;
// use Coinremitter\Checkout\Model\Wallets;

class Edit extends \Magento\Backend\App\Action
{
    
    protected $_coreRegistry;

    /**
     * @var \Coinremitter\Checkout\Model\WalletsFactory
     */
    private $resultPageFactory;

    /**
     * @param Action\Context $context
     * @param \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory
     * @param \Coinremitter\Checkout\Api\WalletsRepositoryInterface $walletsRepository
     */
    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $_coreRegistry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $_coreRegistry;
        parent::__construct($context);
    }
    
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _initAction()
	{
		$resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Coinremitter_Checkout::coinremitter_wallets')
        ->addBreadcrumb(__('Coinremitter'),__('Coinremitter'))
        ->addBreadcrumb(__('Manage All Wallets'),__('Manage All Wallets'));
        return $resultPage;
    }

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create(\Coinremitter\Checkout\Model\Wallets::class);

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This wallet no longer exists.'));

                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
        $this->_coreRegistry->register('coinremitter_wallets',$model);

        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Wallet') : __('Add Wallet'),
            $id ? __('Edit Wallet') : __('Add Wallet')
        );
        $resultPage->getConfig()->getTitle()->prepend((__('Wallets')));
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? (__('Edit Wallet')) : (__('Add Wallet')));
        return $resultPage;
    }
}