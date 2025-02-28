<?php
namespace Coinremitter\Checkout\Block\Adminhtml\Wallets;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Initialize cms page edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'Coinremitter_Checkout';
        $this->_controller = 'adminhtml_wallets';

        parent::_construct();

        $this->buttonList->remove('save');
        if ($this->_coreRegistry->registry('coinremitter_wallets')->getId()) {
            // $this->buttonList->update("save", "label", __("Update Wallet"));
            $this->buttonList->add(
                "save",
                [
                    'label'=>__('Update Wallet'),
                    'class' => 'save primary',
                    'data_attribute' => [
                        'mage-init' => ['button' => ['event' => 'save']],
                        'form-role' => 'save',
                    ],
                    'sort_order' => 90,
                    'onclick'=>'newWallet()'
                ],
                10
            );
        }else{

            $this->buttonList->add(
                "save",
                [
                    'label'=>__('Save Wallet'),
                    'class' => 'save primary',
                    'data_attribute' => [
                        'mage-init' => ['button' => ['event' => 'save']],
                        'form-role' => 'save',
                    ],
                    'sort_order' => 90,
                    'onclick'=>'newWallet()'
                ],
                10
            );
            
        }
        $this->_formScripts[] = "

        function newWallet(){
            wallets_form.submit($('wallets_form').action+'back/edit/');
        }
        ";
        $this->buttonList->remove('delete');
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return \Magento\Framework\Phrase
     */
    public function getHeaderText()
    {
        if ($this->_coreRegistry->registry('coinremitter_wallets')->getId()) {
            return __("Edit Wallet '%1'", $this->escapeHtml($this->_coreRegistry->registry('coinremitter_wallets')->getName()));
        } else {
            return __('New Wallet');
        }
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }

    /*public function _getSaveUrl(){
        return $this->getUrl('coinremitter/wallets/save');
    }*/

    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }

        return $this->getUrl('*/*/save');
    }

    /**
     * Prepare form Html. call the phtm file with form.
     *
     * @return string
     */
    public function getFormHtml()
    {
       // get the current form as html content.
        $html = parent::getFormHtml();
        //Append the phtml file after the form content.
        return $html;
    }

    /**
     * Prepare layout
     *
     * @return \Magento\Framework\View\Element\AbstractBlock
     */
    protected function _prepareLayout()
    {

        /*$this->_formScripts[] = "
            require([
                'jquery',
                'mage/mage',
                'knockout'
            ], function ($){
                
            });
               
            ";*/
            return parent::_prepareLayout();
        }
    }