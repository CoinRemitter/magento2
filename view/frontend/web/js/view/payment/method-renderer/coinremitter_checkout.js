/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Coinremitter_Checkout/payment/form',
                transactionResult: ''
            },

            /** Returns send check to info */
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function () {
                return 'coinremitter_checkout';
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult()
                    }
                };
            },

            getTransactionResults: function () {
                return _.map(window.checkoutConfig.payment.sample_gateway.transactionResults, function (value, key) {
                    console.log(value);
                    return {
                        'value': value.coin_symbol,
                        'transaction_result': value.coin_symbol + ' - ' + value.coin_name
                    }
                });
            },

            getDescription: function () {
                return window.checkoutConfig.payment.sample_gateway.payment_description;
            },

            isWallets: function () {
                return window.checkoutConfig.payment.sample_gateway.isWallets;
            }
        });
    }
);
