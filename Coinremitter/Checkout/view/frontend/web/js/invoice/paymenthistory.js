require([
   'jquery'],
   function($){
      var interval = null;
      var imageBase = $("#imageBase").val();
      $(document).ready(function(){
         ajaxCall();
         interval = setInterval(dateTimer,1000);
         setInterval(ajaxCall,5000);
      });
      function dateDiff(date) {
         // var d2 = getUTCTime();
         var d2 = new Date();
         var d1 = new Date(Date.parse(date)).getTime();
         var date_diff = d2 - d1;

         var years = Math.floor( (date_diff) / 1000 / 60 / 60 / 24 / 30 / 12);
         if(years > 0)
            return years+" year(s) ago";
         var months = Math.floor( (date_diff) / 1000 / 60 / 60 / 24 / 30);
         if(months > 0)
            return months+" month(s) ago";
         var days = Math.floor( (date_diff) / 1000 / 60 / 60 / 24);
         if(days > 0)
            return days+" day(s) ago";
         var hours = Math.floor( (date_diff) / 1000 / 60 / 60 );
         if(hours > 0)
            return hours+" hour(s) ago";
         var minutes = Math.floor( (date_diff) / 1000 / 60 );
         if(minutes > 0)
            return minutes+" minute(s) ago";
         var seconds = Math.floor( (date_diff) / 1000 );
         if(seconds > 0)
            return seconds+" second(s) ago";
      }
      function dateTimer() {
         if($("#expire_on").val() != ''){
            var current = getUTCTime();
            var expire = new Date($("#expire_on").val()).getTime();
            var date_diff = expire - current;
            var hours = Math.floor(date_diff / (1000 * 60 * 60));
            var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
            if(hours < 0 && minutes < 0 && seconds < 0){
               var order_id = $("#order_id").val();
               funExpire(order_id);
               return;
            }else {  
               $("#ehours").html(('0' + hours).slice(-3));
               $("#eminutes").html(('0' + minutes).slice(-2));
               $("#eseconds").html(('0' + seconds).slice(-2));
            }
         }
      }

      function ajaxCall() {
         var address = $("#address").val();
         var coin = $("#coin").val();
         $.ajax({
            url: BASE_URL+"coinremitter/invoice/paymenthistory",
            type: "POST",
            data: {address,coin},
         }).done(function (data) {
            var result = data.output;
            if(result.status == "expire"){
               funExpire(result.order_id);
            } else if(result.status == "success"){
               window.open(BASE_URL+"coinremitter/invoice/success?order_id="+result.order_id,"_SELF");
            } else {
               if(result.nopayment == 1){
                  if ($('#paymentStatus').is(':empty')){
                     $("#timerStatus").empty();
                     clearInterval(interval);
                     $("#paymentStatus").append("<span style='margin-top: 3px;'>Awaiting Payment</span>");
                     $("#paymentStatus").append("<div></div>");
                  }
               }else{
                  if ($('#timerStatus').is(':empty')){
                     $("#timerStatus").append("<span>Your order Expired In</span>");
                     $("#timerStatus").append('<ul><li><span id="ehours">00</span></li><li><span id="eminutes">00</span></li><li><span id="eseconds">00</span></li></ul>');
                  }
               }
               $("#expire_on").val(result.expire_on);
               $("#paid-amt").text(result.totalPaid + " " + result.coin);
               $("#pending-amt").text(result.totalPending + " " + result.coin);
               var paymenthistory = "";
               if(result.flag == 1){
                  $.each(result.data, function( index, payment ) {
                     var confirmations = '';
                     if(payment.confirmations >= 3)
                        confirmations = '<div class="cr-plugin-history-ico" title="Payment Confirmed"><img src="'+imageBase+'/check.png" alt=""></div>';
                     else
                        confirmations = '<div class="cr-plugin-history-ico" title="'+payment.confirmations+' confirmation(s)"><img src="'+imageBase+'/error.png" alt=""></div>';

                     paymenthistory+='<div class="cr-plugin-history-box"><div class="cr-plugin-history">'+confirmations+'<div class="cr-plugin-history-des"><span><a href="'+payment.explorer_url+'" target="_blank">'+payment.txid+'</a></span><p>'+payment.amount+' '+payment.coin+'</p></div><div class="cr-plugin-history-date"><span title="'+payment.paid_date+' (UTC)">'+dateDiff(payment.paid_date)+'</span></div></div></div>';
                  });
               } else {
                  paymenthistory='<div class="cr-plugin-history-box"><div class="cr-plugin-history"><div class="cr-plugin-history-des cr-plugin-no-history" style="text-align: center;"><p>'+result.msg+'</p></div></div></div>';
               }
               $("#cr-plugin-history-list").html(paymenthistory);
            }
            return true;
         });
      }

      function funExpire(order_id) {
         window.open(BASE_URL+"coinremitter/invoice/cancel?order_id="+order_id,"_SELF");
      }

      $(".copyToClipboard").click(function() {
         $(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
         var value = $(this).attr("data-copy-detail");
         var $temp = $("<input>");
         $("body").append($temp);
         $temp.val(value).select();
         document.execCommand("copy");
         $temp.remove();
      });
      function getUTCTime(){
         var tmLoc = new Date();
         return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
      }
   }
);