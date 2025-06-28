require(
    [
    'jquery',
    'mage/translate',
    'jquery/validate'],
    function ($) {
        $.validator.addMethod(
            'validate-invoice-expiry-max-value',
            function (v) {
                return (v <= 10080);
            },
            $.mage.__('Invoice expiry minutes should be max 10080')
        );
        $.validator.addMethod(
            'validate-exchange-rate-range',
            function (v) {
                return (v >= 1 && v <= 100);
            },
            $.mage.__('Exchange rate multiplier should be between 1 to 100')
        );
    }
);