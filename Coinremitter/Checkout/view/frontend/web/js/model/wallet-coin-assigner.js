define([
    'jquery'
], function ($) {
    'use strict';

    /** Override default place order action and add agreement_ids to request */
    return function (paymentData) {
        var pickPaForm,
            pickPaData;

        pickPaForm = $('form[data-role=walletcoin-form] #transaction_result');
        pickPaData = pickPaForm.val();

        if (paymentData['extension_attributes'] === undefined) {
            paymentData['extension_attributes'] = {};
        }

        paymentData['extension_attributes']['transaction_result'] = pickPaData;
    };
});