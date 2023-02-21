jQuery(document).ready(function($){
    $('.siusk24_terminal').select2();
    function loader(on){
        if (on){
            $('#siusk24_shipping_meta_box').addClass('loading');
        } else {
            $('#siusk24_shipping_meta_box').removeClass('loading');
        }
    }
    
    function parse_error(error){
        $('#siusk24_shipping_meta_box .inside .errors').html(`
            <div class="siusk24-notice-error">
                <p>${error}</p>
              </div>
        `);
        loader(false);
    }
    
    function load_order(){
        loader(true);
        wp.ajax.post( "load_siusk24_order", {
            order_id: $('#post_ID').val()
        } )
        .done(function(response) {
            if (response.content !== 'undefined') {
                $('#siusk24_shipping_meta_box .inside').html(response.content );
            }
            loader(false);
        });
    }
    
    $('#siusk24_shipping_meta_box').on('click', '#siusk24_create', function(){
        loader(true);
        var services = [];
        var terminal = 0;
        var eori = "";
        var hs = "";
        $('.siusk24_services').each(function(index, el){
            if ($(el).is(":checked")){
                services.push($(el).val());
            }
        });
        if ($('.siusk24_terminal').length > 0){
            terminal = $('.siusk24_terminal').val();
        }
        if ($('.siusk24_eori').length > 0){
            eori = $('.siusk24_eori').val();
        }
        if ($('.siusk24_hs').length > 0){
            hs = $('.siusk24_hs').val();
        }
        wp.ajax.post( "create_siusk24_order", {
            order_id: $('#post_ID').val(),
            services: services,
            terminal: terminal,
            eori: eori,
            hs: hs
        } )
        .done(function(response) {
            if (response.status === 'error'){
                parse_error(response.msg);
            } else {
                load_order();
            }
        });
    });
    
    $('#siusk24_shipping_meta_box').on('click', '#siusk24_delete', function(){
        loader(true);
        wp.ajax.post( "delete_siusk24_order", {
            order_id: $('#post_ID').val()
        } )
        .done(function(response) {
            if (response.status === 'error'){
                parse_error(response.msg);
            } else {
                load_order();
            }
        });
    });
});

