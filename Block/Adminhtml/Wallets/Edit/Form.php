<?php

namespace Coinremitter\Checkout\Block\Adminhtml\Wallets\Edit;

use Coinremitter\Checkout\Model\WalletsFactory;
use Magento\Framework\Encryption\EncryptorInterface;

class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $_systemStore;

    protected $encryptor;
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
    protected $walletsFactory;
    //protected $_debug_logger;
    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Store\Model\System\Store $systemStore
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        \Coinremitter\Checkout\Model\WalletsFactory $walletsFactory,
        EncryptorInterface $encryptor,
        array $data = []/*,\Psr\Log\LoggerInterface $debug_logger*/
    ) {
        $this->_systemStore = $systemStore;
        $this->_coreRegistry = $registry;
        //$this->_debug_logger = $debug_logger;
        parent::__construct($context, $registry, $formFactory, $data);
        $this->walletsFactory = $walletsFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * Init form
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('wallets_form');
        $this->setTitle(__('Wallet Information'));
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {

        $walletArr = [];
        $wallets = $this->walletsFactory->create();
        $walletsCollection = $wallets->getCollection()->addFieldToSelect('coin_symbol');
        $arr = $walletsCollection->getData();

        if ($arr) {
            foreach ($arr as $key => $value) {
                array_push($walletArr, $value['coin_symbol']);
            }
        }

        $model = $this->_coreRegistry->registry('coinremitter_wallets');

        //Preparing the form here.
        $form = $this->_formFactory->create(
            ['data' => ['id' => 'wallets_form', 'enctype' => 'multipart/form-data', 'action' => $this->getUrl("*/*/save"), 'method' => 'post']]
        );
        if ($model->getId()) {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Wallet Information'), 'class' => 'fieldset-wide']
            );

            $fieldset->addField('id', 'hidden', ['name' => 'id']);
            
            $fieldset->addField(
                'api_key',
                'text',
                ['name' => 'api_key', 'label' => __('Api Key'), 'title' => __('Api Key'), 'required' => true]
            );

            $fieldset->addField(
                'password',
                'password',
                ['name' => 'password', 'label' => __('Password'), 'title' => __('Password'), 'required' => true]
            );
            $fieldset->addField(
                'exchange_rate_multiplier',
                'text',
                ['name' => 'exchange_rate_multiplier', 'label' => __('Exchange rate multiplier'), 'title' => __('Exchange rate multiplier'), 'required' => true]
            );

            $fieldset->addField(
                'minimum_invoice_amount',
                'text',
                ['name' => 'minimum_invoice_amount', 'label' => __('Invoice Minimum value'), 'title' => __('Invoice Minimum value'), 'required' => true]
            );
        } else {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Wallet Information'), 'class' => 'fieldset-wide']
            );

            $fieldset->addField(
                'api_key',
                'text',
                ['name' => 'api_key', 'label' => __('Api Key'), 'title' => __('Api Key'), 'required' => true]
            );

            $fieldset->addField(
                'password',
                'password',
                ['name' => 'password', 'label' => __('Password'), 'title' => __('Password'), 'required' => true]
            );
            $fieldset->addField(
                'exchange_rate_multiplier',
                'text',
                ['name' => 'exchange_rate_multiplier', 'label' => __('Exchange rate multiplier'), 'title' => __('Exchange rate multiplier'), 'required' => true]
            );

            $fieldset->addField(
                'minimum_invoice_amount',
                'text',
                ['name' => 'minimum_invoice_amount', 'label' => __('Invoice minimum value'), 'title' => __('Invoice minimum value'), 'required' => true]
            );
        }

        $filled_data = $model->getData();
        // print_r($filled_data);
        // die;
        if ($model->getId()) {
            $filled_data['password'] = $this->encryptor->decrypt($filled_data['password']);
        }
        $form->setUseContainer(true);
        $form->setValues($filled_data);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
