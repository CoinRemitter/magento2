<?php

namespace Coinremitter\Checkout\Ui\Component\Listing\Column;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\HTTP\ZendClientFactory;

class WalletsFields extends Column {

    const NAME = 'thumbnail';
    const ALT_FIELD = 'name';

    protected $apiCall;
    protected $_assetRepo;
    protected $fileDriver;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        Api $apiCall,
        array $components = [],
        array $data = []
    ) {
        $this->apiCall = $apiCall;
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_assetRepo = $assetRepo;
        $this->api_base_url = $this->apiCall->getApiUrl();
        $this->resource = $resource;
        $this->fileDriver = $fileDriver;
    }

    public function prepareDataSource(array $dataSource) {
        if (isset($dataSource['data']['items'])) {

            //check if 'is_valid' field is not found, add field
            $connection  = $this->resource->getConnection();
            $tableName = $connection->getTableName("coinremitter_wallets");
            if($connection->tableColumnExists($tableName, 'is_valid') === false){
                $connection->addColumn('coinremitter_wallets', 'is_valid', array(
                    'type'      => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable'  => false,
                    'length'    => 1,
                    'default'   => '1',
                    'after'     => 'password', // column name to insert new column after
                    'comment'   => '1 on valid wallet else 0'
                ));  
            }
            foreach ($dataSource['data']['items'] as &$items) {
                $items['balance'] = 0;

                $url = $this->api_base_url."/".strtoupper($items['coin'])."/get-balance";
                $params = [
                    'api_key'      => $items['api_key'],
                    'password'   => $items['password'],
                ];

                $res = $this->apiCall->apiCaller($url, \Zend_Http_Client::POST, $params);
                if (isset($res['flag']) && $res['flag'] == 1) {
                    $items['balance'] = $res['data']['balance'];
                    $is_valid = 1;
                }else{
                    $items['balance'] = '<style>.wallet_balance.message:before{left:0 }</style><span class="message message-warning wallet_balance" style="background: none;" title="Invalid API key or password. Please check credential again."></span>';
                    $is_valid = 0;
                }

                //update table entry
                $data = ["is_valid"=> $is_valid ];
                $where = ['id = ?' => $items['id']];
                $connection->update($tableName, $data, $where);


                $filename = strtolower($items['coin']).'.png';
                $coin_image_path =  $this->getRootPath().'/app/code/Coinremitter/Checkout/view/adminhtml/web/images/'.$items['coin']."/".$filename;
                if(!$this->fileDriver->isExists($coin_image_path)){
                    $items['logo_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/wallet_default.png");
                    $items['logo_orig_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/wallet_default.png");
                } else {
                    $items['logo_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/".$items['coin']."/".$filename);
                    $items['logo_orig_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/".$items['coin']."/".$filename);
                }
                
                $items['logo_alt'] = $this->getAlt($items) ?: $filename;
                

            }
        }

        return $dataSource;
    }
    protected function getAlt($row) {
       $altField = $this->getData('config/altField') ?: self::ALT_FIELD;
       return isset($row[$altField]) ? $row[$altField] : null;
    }

    public function getRootPath()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        return $directory->getRoot();
    }
}