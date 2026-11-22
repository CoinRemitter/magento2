define([
    'jquery',
], function ($) {
    'use strict';
    return function () {

        $(document).ready(function () {

            setTimeout(function () {
                $("input[name='payment[method]']").first().prop("checked", true);
            }, 500);

        });

    };
});