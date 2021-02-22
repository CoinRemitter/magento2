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
            
            //$this->context->logger->debug('Prepare form in edit form called');
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

            
            //$this->_debug_logger->debug('before if in prepareForm');
            if ($model->getId()) {

                $fieldset = $form->addFieldset(
                    'base_fieldset',
                    ['legend' => __('Wallet Information'), 'class' => 'fieldset-wide']
                );

                //$this->_debug_logger->debug('prepareForm : in if');
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
            }else{
                //$this->_debug_logger->debug('prepareForm : in else');
                $values = $coins->toOptionArray($walletArr);

                
                if($values['flag'] == 1){

                    $fieldset = $form->addFieldset(
                        'base_fieldset',
                        ['legend' => __('Wallet Information'), 'class' => 'fieldset-wide']
                    );

                    $fieldset->addField(
                        'coin',
                        'select',
                        [
                            'name' => 'coin', 
                            'label' => __('Coin'), 
                            'title' => __('Coin Field'), 
                            'required' => true,
                            'values'=>$values['data']
                        ]
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

                }else{
                    
                    $fieldset = $form->addFieldset(
                        'base_fieldset',
                        ['legend' => __($values['msg']), 'class' => 'fieldset-wide']
                    );

                }
                
            }
            //$this->_debug_logger->debug('after if in prepareForm');
            $filled_data = $model->getData();
            if($model->getId()){
                $filled_data['password'] = $this->encryptor->decrypt($filled_data['password']);
            }
            $form->setUseContainer(true);
            $form->setValues($filled_data);
            $this->setForm($form);
            return parent::_prepareForm();
        }

    }