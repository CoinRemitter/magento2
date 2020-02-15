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

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        Api $apiCall,
        array $components = [],
        array $data = []
    ) {
        $this->apiCall = $apiCall;
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_assetRepo = $assetRepo;
        $this->api_base_url = $this->apiCall->getApiUrl();
    }

    public function prepareDataSource(array $dataSource) {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
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
                }
                $filename = strtolower($items['coin']).'.png';
                $items[$fieldName . '_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/".$filename);
                $items[$fieldName . '_alt'] = $this->getAlt($items) ?: $filename;
                $items[$fieldName . '_orig_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/".$filename);
            }
        }

        return $dataSource;
    }
    protected function getAlt($row) {
       $altField = $this->getData('config/altField') ?: self::ALT_FIELD;
       return isset($row[$altField]) ? $row[$altField] : null;
    }
}