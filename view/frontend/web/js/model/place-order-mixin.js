define([
    'jquery',
    'mage/utils/wrapper',
    'Coinremitter_Checkout/js/model/wallet-coin-assigner'
    ], function ($, wrapper, walletCoinAssigner) {
        'use strict';

        return function (placeOrderAction) {

            /** Override default place order action and add agreement_ids to request */
            return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
                walletCoinAssigner(paymentData);

                return originalAction(paymentData, messageContainer);
            });
        };
    });