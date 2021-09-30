<?php

namespace Coinremitter\Checkout\Block;

class Invoice extends \Magento\Framework\View\Element\Template
{
    protected $request;
    protected $apiCall;
    protected $_scopeConfig;

    /**      * @param \Magento\Framework\App\Action\Context $context      */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Request\Http $request,
        \Coinremitter\Checkout\Model\Wallets\Api $apiCall
    ) {
        $this->request = $request;
        $this->apiCall = $apiCall;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function getDetail()
    {

        $order_id = $this->getData('order_id');
        $resultData = array();

        if ($order_id) {
            $order = $this->getOrder($order_id);
            $selectcoin = $order->getPayment()->getTransactionResult();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $sql = "SELECT co.address, co.address_qrcode, co.crp_amount, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id= '" . $order_id . "'";
            $result = $connection->fetchAll($sql);

            if (!empty($result)) {

                $orderData = $result[0];

                // Get Order Information
                $resultData['order_id'] = $order_id;
                $resultData['entityID'] = $order->getEntityId();
                $resultData['incrementID'] = $order->getIncrementId();
                $resultData['state'] = $order->getState();
                $resultData['status'] = $order->getStatus();
                $resultData['storeID'] = $order->getStoreId();
                $resultData['grandTotal'] = $order->getGrandTotal();
                $resultData['subTotal'] = $order->getSubtotal();
                $resultData['shippingAmount'] = $order->getShippingAmount();
                $resultData['taxAmount'] = $order->getTaxAmount();
                $resultData['totalQtyOrdered'] = $order->getTotalQtyOrdered();
                $resultData['orderCurrencyCode'] = $order->getOrderCurrencyCode();
                $resultData['orderCurrencySymbol'] = $this->getCurrencySymbol($order->getOrderCurrencyCode());

                $resultData['expire_on'] = $orderData['expire_on'];
                $resultData['order_address'] = $orderData['address'];
                $resultData['qr_code'] = $orderData['address_qrcode'];
                $resultData['totalAmount'] = $orderData['crp_amount'];
                $resultData['coin'] = $selectcoin;

                // get customer details
                $resultData['customerFirstName'] = $order->getCustomerFirstname();
                $resultData['customerLastName'] = $order->getCustomerLastname();

                // get Billing details
                $billingAddress = $order->getBillingAddress();
                $resultData['billingFirstName'] = $billingAddress->getFirstName();
                $resultData['billingLastName'] = $billingAddress->getLastName();
                $resultData['billingCity'] = $billingAddress->getCity();
                $resultData['billingStreet'] = $billingAddress->getStreet();
                $resultData['billingPostcode'] = $billingAddress->getPostcode();
                $resultData['billingTelephone'] = $billingAddress->getTelephone();
                $resultData['billingState_code'] = $billingAddress->getRegionCode();
                $resultData['billingState_name'] = $this->getRegionName($billingAddress->getRegionCode(), $billingAddress->getCountryId());
                $resultData['billingCountry_name'] = $this->getCountryName($billingAddress->getCountryId());

                // get shipping details
                $shippingAddress = $order->getShippingAddress();
                $resultData['show_shipping'] = true;
                if ($shippingAddress !== null) {
                    $resultData['shippingFirstName'] = $shippingAddress->getFirstName();
                    $resultData['shippingLastName'] = $shippingAddress->getLastName();
                    $resultData['shippingCity'] = $shippingAddress->getCity();
                    $resultData['shippingStreet'] = $shippingAddress->getStreet();
                    $resultData['shippingPostcode'] = $shippingAddress->getPostcode();
                    $resultData['shippingTelephone'] = $shippingAddress->getTelephone();
                    $resultData['shippingState_code'] = $shippingAddress->getRegionCode();
                    $resultData['shippingState_name'] = $this->getRegionName($shippingAddress->getRegionCode(), $shippingAddress->getCountryId());
                    $resultData['shippingCountry_name'] = $this->getCountryName($shippingAddress->getCountryId());
                } else {
                    $resultData['show_shipping'] = false;
                    $resultData['shippingFirstName'] = "";
                    $resultData['shippingLastName'] = "";
                    $resultData['shippingCity'] = "";
                    $resultData['shippingStreet'] = [];
                    $resultData['shippingPostcode'] = "";
                    $resultData['shippingTelephone'] = "";
                    $resultData['shippingState_code'] = "";
                    $resultData['shippingState_name'] = "";
                    $resultData['shippingCountry_name'] = "";
                }

                // fetch specific payment information
                $resultData['amount'] = $order->getPayment()->getAmountPaid();
                $resultData['paymentMethod'] = $order->getPayment()->getMethod();
                $resultData['info'] = $order->getPayment()->getAdditionalInformation('method_title');

                // Get Order Items
                $orderItems = $order->getAllItems();
                $resultData['products'] = array();

                foreach ($orderItems as $item) {
                    $data = array();
                    $data['itemId'] = $item->getItemId();
                    $data['orderId'] = $item->getOrderId();
                    $data['storeId'] = $item->getStoreId();
                    $data['productId'] = $item->getProductId();
                    $data['productImage'] = $this->getProductImage($item->getProductId());
                    $data['sku'] = $item->getSku();
                    $data['productName'] = $item->getName();
                    $data['productQty'] = (int) $item->getQtyOrdered();
                    $data['productPrice'] = $item->getPrice();
                    $options = "";
                    if (isset($item->getProductOptions()['options'])) {
                        $productOptions = $item->getProductOptions()['options'];
                        for ($i = 0; $i < count($productOptions); $i++) {
                            if ($i == 0) {
                                $options .= "(";
                            }

                            $options .= $productOptions[$i]['label'] . "-" . $productOptions[$i]['value'];
                            if ($i + 1 == count($productOptions)) {
                                $options .= ")";
                            } else {
                                $options .= ", ";
                            }

                        }
                    }
                    $data['productOptions'] = $options;
                    array_push($resultData['products'], $data);
                }
                $resultData['flag'] = 1;
                $resultData['msg'] = "Success";

            } else {
                $resultData['flag'] = 0;
                $resultData['msg'] = "Error to Fetch Order Data";
            }

        } else {
            $resultData['flag'] = 0;
            $resultData['msg'] = "No order Found";
        }

        return $resultData;
    }

    public function getOrder($_order_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($_order_id);
        return $order;

    }

    public function getBaseUrl()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        return $storeManager->getStore()->getBaseUrl();

    }

    public function getRegionName($_region_code, $_country_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $regionName = $objectManager->create('Magento\Directory\Model\Region')->loadByCode($_region_code, $_country_id)->getName();
        return $regionName;

    }

    public function getCountryName($_country_id)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $countryName = $objectManager->get('Magento\Directory\Model\CountryFactory')->create()->loadByCode($_country_id)->getName();
        return $countryName;
    }

    public function getCurrencySymbol($_currency_code)
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $currencySymbol = $objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load($_currency_code)->getCurrencySymbol();
        return $currencySymbol;
    }

    public function getProductImage($_product_id)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $product = $objectManager->get('Magento\Catalog\Api\ProductRepositoryInterface')->getById($_product_id);
        $store = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
        $imageHelper = $objectManager->get(\Magento\Catalog\Helper\Image::class);

        $getSmallImage = $product->getSmallImage();

        if ($getSmallImage != '' && $getSmallImage != null) {
            $productImageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $getSmallImage;
        } else {
            $productImageUrl = $imageHelper->getDefaultPlaceholderUrl('small_image');
        }

        return $productImageUrl;
    }

}
