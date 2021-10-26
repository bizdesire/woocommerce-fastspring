jQuery(function ($) {
    jQuery(document.body).on('click', '.change_plan_btn', function (e) {
        $(document.body).find('.response-message').html('');

        e.preventDefault();
        product_id = jQuery(this).data("product_id");
        nonce = jQuery(this).data("nonce");
        upgrade = jQuery(this).data("upgrade")

        jQuery.ajax({
            type: "post",
            //dataType: "json",
            url: myAjax.ajaxurl,
            data: {action: "change_fastspring_plan", product_id: product_id, nonce: nonce,upgrade:upgrade},
            success: function (response) {
                   $(document.body).find('.response-message').html(response);
                   setTimeout(function(){
                         location.reload();
                   },5000)
            }
        })

    })
    jQuery(document.body).on('click', '.activate-starter-request', function (e) {
        $(document.body).find('.response-message').html('');

        e.preventDefault();
           jQuery.ajax({
            type: "post",
            //dataType: "json",
            url: myAjax.ajaxurl,
            data: {action: "activate_starter_request"},
            success: function (response) {
                   $(document.body).find('.response-message').html(response);
                   setTimeout(function(){
                         location.reload();
                   },5000)
            }
        })

    })

})