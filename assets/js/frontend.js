jQuery(document).ready(function($) {
    "use strict";
    
    $(document).on('click', '#dpc-calculate-button', function(){
        var t = $(this);
        var original_button = t.text();
        
        var data = { 
            action        : 'dpc_calculate',
            nonce         : Down_Payment_Calculator.nonce,
            home_price    : $('#dpc-home-price').val(),
            down_payment  : $('#dpc-down-payment').val(),
            closing_costs : $('#dpc-closing-costs').val(),
            interest_rate : $('#dpc-interest-rate').val(),
            loan_term     : $('#dpc-loan-term').val()
        };
        
        t.text(Down_Payment_Calculator.calculating);
        t.attr('disabled', 'disabled');
        
        $.post(Down_Payment_Calculator.ajaxurl, data, function(res){
            if (res.success) {
                $('#dpc-down-payment-result').html(res.data.down_payment);
                $('#dpc-closing-costs-result').html(res.data.closing_costs);
                $('#dpc-upfront-costs-result').html(res.data.upfront_costs);
                $('#dpc-loan-amount-result').html(res.data.loan_amount);
                $('#dpc-monthly-payment-result').html(res.data.monthly_payment);
            } else {
                console.log(res);
            }
            
            t.text(original_button);
            t.removeAttr('disabled');
        }).fail(function(xhr, textStatus, e) {
            console.log(xhr.responseText);
        });
        
        return false;
    });
    
});