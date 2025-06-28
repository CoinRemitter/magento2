<?php

namespace Coinremitter\Checkout\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Coinremitter\Checkout\Model\Wallets\Api;
use Magento\Framework\Module\Dir\Reader;

class WalletsFields extends Column
{

    public const NAME = 'thumbnail';
    public const ALT_FIELD = 'name';

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
        Reader $moduleDirReader,
        array $components = [],
        array $data = []
    ) {
        $this->apiCall = $apiCall;
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_assetRepo = $assetRepo;
        $this->api_base_url = $this->apiCall->getApiUrl();
        $this->resource = $resource;
        $this->fileDriver = $fileDriver;
        $this->moduleDirReader = $moduleDirReader;
    }

    public function prepareDataSource(array $dataSource)
    {

        if (isset($dataSource['data']['items'])) {
            $connection  = $this->resource->getConnection();
            $tableName = $connection->getTableName("coinremitter_wallets");

            foreach ($dataSource['data']['items'] as &$items) {
                $items['balance'] = 0;
                $credencials = [
                    'x-api-key'      => $items['api_key'],
                    'x-api-password'   => $items['password'],
                ];

                $walletData = $this->apiCall->getWalletBalance([], $credencials);

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $currencysymbol = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
                $baseFiatCurrency = $currencysymbol->getStore()->getCurrentCurrencyCode();


                if ($baseFiatCurrency != $items['base_fiat_symbol']) {
                    $fiatToCryptoConversionParam = [
                        'crypto' => $items['coin_symbol'],
                        'fiat' => $items['base_fiat_symbol'],
                        'fiat_amount' => $items['minimum_invoice_amount']
                    ];
                    $fiatToCryptoConversionRes = $this->apiCall->getFiatToCryptoRate($fiatToCryptoConversionParam);
                    $cryptoToFiatConversionParam = [
                        'crypto' => $items['coin_symbol'],
                        'crypto_amount' => $fiatToCryptoConversionRes['data'][0]['price'],
                        'fiat' => $baseFiatCurrency
                    ];
                    $cryptoToFiatConversionRes = $this->apiCall->getCryptoToFiatRate($cryptoToFiatConversionParam);
                    
                    if ($cryptoToFiatConversionRes['success']) {
                        $minimumInvAmountInFiat = $cryptoToFiatConversionRes['data'][0]['amount'];
                        $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
                        $items['minimum_invoice_amount'] = $minimumInvAmountInFiat;
                        //update table entry
                        
                        $data = ["minimum_invoice_amount" => $minimumInvAmountInFiat,'base_fiat_symbol' => $baseFiatCurrency];
                        $where = ['id' => $items['id']];
                        $connection->update($tableName, $data, $where);
                    }
                }

                $items['minimum_invoice_amount'] = $items['minimum_invoice_amount'] . ' ' . $baseFiatCurrency;

                if (isset($walletData['success']) && $walletData['success']) {
                    $items['balance'] = $walletData['data']['balance'];
                } else {
                    $items['balance'] = '<style>.wallet_balance.message:before{left:0 }</style><span class="message message-warning wallet_balance" style="background: none;" title="Invalid API key or password. Please check credential again."></span>';
                }

                $filename = strtoupper($items['coin_symbol']) . '.png';
                $coin_image_path =  $this->getRootPath() . '/view/adminhtml/web/images/' . $filename;
                if (!$this->fileDriver->isExists($coin_image_path)) {
                    $items['logo_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/wallet_default.png");
                    $items['logo_orig_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/wallet_default.png");
                } else {
                    $items['logo_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/" . $filename);
                    $items['logo_orig_src'] = $this->_assetRepo->getUrl("Coinremitter_Checkout::images/" . $filename);
                }

                $items['logo_alt'] = $this->getAlt($items) ?: $filename;

                // print_r($items);
                // die;
            }
        }

        return $dataSource;
    }
    protected function getAlt($row)
    {
        $altField = $this->getData('config/altField') ?: self::ALT_FIELD;
        return isset($row[$altField]) ? $row[$altField] : null;
    }
    public function getRootPath()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Module\Dir\Reader');
        return $directory->getModuleDir('', 'Coinremitter_Checkout');
    }
}
