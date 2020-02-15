<?php
namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;
use Coinremitter\Checkout\Model\Wallets as Wallets;

class NewAction extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Backend\Model\View\Result\Forward
     */
    protected $resultForwardFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Coinremitter_Checkout::save');
	}

    /**
     * Forward to edit
     *
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Forward $resultForward */
        $resultForward = $this->resultForwardFactory->create();
        return $resultForward->forward('edit');

        // $this->_view->loadLayout();
        // $this->_view->renderLayout();

        // $contactDatas = $this->getRequest()->getParam('wallet');
        // if(is_array($contactDatas)) {
        //     $contact = $this->_objectManager->create(Wallets::class);
        //     $contact->setData($contactDatas)->save();
        //     $resultRedirect = $this->resultRedirectFactory->create();
        //     return $resultRedirect->setPath('*/*/index');
        // }
    }
}
?>