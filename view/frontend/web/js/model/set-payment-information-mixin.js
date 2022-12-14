define([
    'jquery',
    'mage/utils/wrapper',
    'Coinremitter_Checkout/js/model/wallet-coin-assigner'
    ], function ($, wrapper, walletCoinAssigner) {
        'use strict';

        return function (placeOrderAction) {

            /** Override place-order-mixin for set-payment-information action as they differs only by method signature */
            return wrapper.wrap(placeOrderAction, function (originalAction, messageContainer, paymentData) {
                walletCoinAssigner(paymentData);

                return originalAction(messageContainer, paymentData);
            });
        };
    });