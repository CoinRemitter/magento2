require(
    [
    'jquery'],
    function ($) {

        const ORDER_STATUS = {
            'pending': 0,
            'paid': 1,
            'under_paid': 2,
            'over_paid': 3,
            'expired': 4,
            'cancelled': 5,
        }
        var interval = null;
        var checkPaymentInterval = null;
        var imageBase = $("#imageBase").val();
        $(document).ready(function () {
            ajaxCall();
            interval = setInterval(dateTimer, 1000);
            checkPaymentInterval = setInterval(ajaxCall, 10000);
        });
        function dateDiff(date)
        {
            var d2 = getUTCTime();
            // var d2 = new Date();
            var d1 = new Date(Date.parse(date)).getTime();
            var date_diff = d2 - d1;

            var years = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30 / 12);
            if (years > 0) {
                return years + " year(s) ago";
            }
            var months = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30);
            if (months > 0) {
                return months + " month(s) ago";
            }
            var days = Math.floor((date_diff) / 1000 / 60 / 60 / 24);
            if (days > 0) {
                return days + " day(s) ago";
            }
            var hours = Math.floor((date_diff) / 1000 / 60 / 60);
            if (hours > 0) {
                return hours + " hour(s) ago";
            }
            var minutes = Math.floor((date_diff) / 1000 / 60);
            if (minutes > 0) {
                return minutes + " minute(s) ago";
            }
            var seconds = Math.floor((date_diff) / 1000);
            if (seconds > 0) {
                return seconds + " second(s) ago";
            }
        }
        function dateTimer()
        {
            if ($("#expire_on").val() != '') {
                var current = getUTCTime();
                var expire = new Date($("#expire_on").val()).getTime();
                var date_diff = expire - current;
                var hours = Math.floor(date_diff / (1000 * 60 * 60));
                var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
                if (hours < 0 && minutes < 0 && seconds < 0) {
                    var order_id = $("#order_id").val();
                    clearInterval(interval);
                    funExpire(order_id);
                    return;
                } else {
                    $("#ehours").html(('0' + hours).slice(-3));
                    $("#eminutes").html(('0' + minutes).slice(-2));
                    $("#eseconds").html(('0' + seconds).slice(-2));
                }
            }
        }

        function ajaxCall()
        {
            var address = $("#address").val();
            $.ajax({
                url: BASE_URL + "coinremitter/invoice/paymenthistory",
                type: "POST",
                data: { address },
            }).done(function (response) {

                if (response.flag == 0) {
                      clearInterval(interval);
                      clearInterval(checkPaymentInterval);
                }

                let data = response.data
                let transactions = data.transactions
                $("#paid-amt").text(data.paid_amount + " " + data.coin_symbol);
                $("#pending-amt").text(data.pending_amount + " " + data.coin_symbol);


                if (data.status_code == ORDER_STATUS.expired) {
                     funExpire(data.order_id);
                } else if (data.status_code == ORDER_STATUS.paid || data.status_code == ORDER_STATUS.over_paid) {
                    window.location = BASE_URL + "coinremitter/invoice/success?order_id=" + data.order_id;
                } else {
                    if (Object.keys(transactions).length && data.status_code == ORDER_STATUS.pending) {
                         clearInterval(interval);
                         $("#paymentStatus").empty();
                    }
                    var paymenthistory = "";
                    if (Object.keys(transactions).length) {
                        $.each(Object.values(transactions), function (index, payment) {
                            var confirmations = '';
                            if (payment.status_code) {
                                confirmations = '<div class="cr-plugin-history-ico" title="Payment Confirmed"><img src="' + imageBase + '/check.png" alt=""></div>';
                            } else {
                                confirmations = '<div class="cr-plugin-history-ico" title="' + payment.confirmations + ' confirmation(s)"><img src="' + imageBase + '/error.png" alt=""></div>';
                            }

                            paymenthistory += '<div class="cr-plugin-history-box"><div class="cr-plugin-history">' + confirmations + '<div class="cr-plugin-history-des"><span><a href="' + payment.explorer_url + '" target="_blank">' + payment.txid.slice(0, 16) + '...</a></span><p>' + payment.amount + ' ' + data.coin_symbol + '</p></div><div class="cr-plugin-history-date"><span title="' + payment.date + ' (UTC)">' + dateDiff(payment.date) + '</span></div></div></div>';
                        });
                           $("#timerStatus").html("<span>Payment Status : " + data.status + "</span>");
                    } else {
                        $("#paymentStatus").html("<span style='margin-top: 5px;'>Awaiting Payment</span><div></div>");
                        $("#expire_on").val(data.expire_on);
                        if ($("#timerStatus").html() == '') {
                            $("#timerStatus").html('<span>Your order expired in</span><ul><li><span id="ehours">00</span></li><li><span id="eminutes">00</span></li><li><span id="eseconds">00</span></li></ul>');
                        }
                        paymenthistory = '<div class="cr-plugin-history-box"><div class="cr-plugin-history"><div class="cr-plugin-history-des cr-plugin-no-history" style="text-align: center;"><p>No Transaction Found</p></div></div></div>';
                    }
                    $("#cr-plugin-history-list").html(paymenthistory);
                }
            })
        }

        function funExpire(order_id)
        {
            window.location = BASE_URL + "coinremitter/invoice/cancel?order_id=" + order_id;
        }

        $(".copyToClipboard").click(function () {
            $(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
            var value = $(this).attr("data-copy-detail");
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(value).select();
            document.execCommand("copy");
            $temp.remove();
        });
        function getUTCTime()
        {
            var tmLoc = new Date();
            return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
        }
    }
);