<?php

namespace Coinremitter\Checkout\Controller\Adminhtml\Wallets;

use Magento\Backend\App\Action;
use Coinremitter\Checkout\Model\Wallets;

class Delete extends \Magento\Backend\App\Action
{
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            $title = "";
            try {
                $model = $this->_objectManager->create(\Coinremitter\Checkout\Model\Wallets::class);
                $model->load($id);
                $title = $model->getTitle();
                $model->delete();

                $this->messageManager->addSuccess(__('The wallet has been deleted.'));

                $this->_eventManager->dispatch(
                    'adminhtml_wallets_on_delete',
                    ['title' => $title, 'status' => 'success']
                );
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->_eventManager->dispatch(
                    'adminhtml_wallets_on_delete',
                    ['title' => $title, 'status' => 'fail']
                );

                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('we can not find a wallet to delete'));
        return $resultRedirect->setPath('*/*/');
    }
}
