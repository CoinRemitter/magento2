var config = {
    map: {
        '*': {
            "select_coin": 'Coinremitter_Checkout/js/coinremitter_config'
        }
    },
    shim: {
        //dependency third-party lib
        "select_coin": {
             deps: [
                'jquery' //dependency jquery will load first
            ]
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/action/place-order': {
                'Coinremitter_Checkout/js/model/place-order-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information': {
                'Coinremitter_Checkout/js/model/set-payment-information-mixin': true
            }
        }
    }
};