<?php

namespace Coinremitter\Checkout\Block\Adminhtml\Wallets\Edit;
use Coinremitter\Checkout\Model\WalletsFactory;

    class Form extends \Magento\Backend\Block\Widget\Form\Generic
    {
        /**
         * @var \Magento\Store\Model\System\Store
         */
        protected $_systemStore;
        
        /**
         * Core registry
         *
         * @var \Magento\Framework\Registry
         */
        protected $_coreRegistry;
        protected $walletsFactory;
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
            array $data = []
        ) {
            $this->_systemStore = $systemStore;
            $this->_coreRegistry = $registry;
            parent::__construct($context, $registry, $formFactory, $data);
            $this->walletsFactory = $walletsFactory;
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
            $walletsCollection = $wallets->getCollection()->addFieldToSelect('coin');
            $arr = $walletsCollection->getData();
            if ($arr) {
                foreach ($arr as $key => $value) {
                    array_push($walletArr, $value['coin']);
                }   
            }

            $model = $this->_coreRegistry->registry('coinremitter_wallets');

            $coins = \Magento\Framework\App\ObjectManager::getInstance()->get('Coinremitter\Checkout\Model\Config\Source\Coin');

           //Preparing the form here.
            $form = $this->_formFactory->create(
                ['data' => ['id' => 'wallets_form', 'enctype' => 'multipart/form-data', 'action' => $this->getUrl("*/*/save"), 'method' => 'post']]
            );
            // $form->setHtmlIdPrefix('demo_');

            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Wallet Information'), 'class' => 'fieldset-wide']
            );
            if ($model->getId()) {
                $c = $model->getData()['coin'];
                /*if (in_array($c, $walletArr)) {
                    unset($walletArr[array_search($c,$walletArr)]);
                }*/
                $fieldset->addField('id', 'hidden', ['name' => 'id']);
                $fieldset->addField(
                    'coin',
                    'label',
                    [
                        'name' => 'coin', 
                        'label' => __('Coin'), 
                        'title' => __('Coin Field'), 
                        'class'=>'field-coin',
                        'required' => true,
                    ]
                );
            }else{
                $fieldset->addField(
                    'coin',
                    'select',
                    [
                        'name' => 'coin', 
                        'label' => __('Coin'), 
                        'title' => __('Coin Field'), 
                        'required' => true,
                        'values'=>$coins->toOptionArray($walletArr)
                    ]
                );
            }

            $fieldset->addField(
                'api_key',
                'text',
                ['name' => 'api_key', 'label' => __('Api Key'), 'title' => __('Api Key'), 'required' => true]
            );

            $fieldset->addField(
                'password',
                'text',
                ['name' => 'password', 'label' => __('Password'), 'title' => __('Password'), 'required' => true]
            );
            
            $form->setUseContainer(true);
            $form->setValues($model->getData());
            $this->setForm($form);
            return parent::_prepareForm();
        }

    }