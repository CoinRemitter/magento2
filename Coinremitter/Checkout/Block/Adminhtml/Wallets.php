<?php

namespace Coinremitter\Checkout\Block\Adminhtml;

class Wallets extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * Initialize Imagegallery Images Edit Block.
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_wallets';
        $this->_blockGroup = 'Coinremitter_Checkout';
        $this->_headerText = __('Manage Wallet');

        parent::_construct();

        if ($this->_isAllowedAction('Coinremitter_Checkout::save')) {
            $this->buttonList->update('add', 'label', __('Add Wallet'));
        } else {
            $this->buttonList->remove('add');
        }
    }

    /**
     * Check permission for passed action.
     *
     * @param string $resourceId
     *
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
