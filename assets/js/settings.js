jQuery(document).ready(function($){
    $('.has-depends').on('change', function(){
        var id = $(this).attr('id');
        if ($(this).is(":checked")){
            $('[data-depends="' + id + '"]' ).parents('tr').fadeIn();
        } else {
            $('[data-depends="' + id + '"]' ).parents('tr').fadeOut();
        }
    });
    $('.has-depends').trigger('change');

    $('.check-api-this').on('change', function() {
        $('button.check-api-btn').prop('disabled', true);
    });

    if ( $('button.check-api-btn').hasClass('disable_all') ) {
        $('button.check-api-btn').closest('tr').nextAll().addClass('disabled');
    }

    $('button.check-api-btn').on('click', function() {
        var btn = $(this);
        var btn_row = btn.closest('tr');
        var check_status = btn.siblings('.check-api-status');
        check_status.removeClass('success warning error');
        check_status.text('');
        btn.addClass('loading').prop('disabled', true);
        jQuery.post(
            siusk24data.ajax_url,
            {
                'action': 'siusk24_check_api'
            },
            function( response ) {
                if ( typeof response !== 'object' || response === null ) {
                    check_status.addClass('error').text('Failed to check');
                    return;
                }
                if ( 'status' in response ) {
                    check_status.addClass(response.status);
                } else {
                    check_status.addClass('warning');
                }
                if ( 'message' in response ) {
                    check_status.text(response.message);
                } else {
                    check_status.text('Received an unknown response');
                }
                if ( check_status.hasClass('error') ) {
                    btn_row.nextAll().addClass('disabled');
                } else {
                    location.reload();
                }
            }
        ).fail(function() {
            check_status.addClass('error').text('Failed to check');
            btn_row.nextAll().addClass('disabled');
        }).always(function() {
            btn.removeClass('loading').prop('disabled', false);
        });
    });

    $('button.terminals-sync-btn').on('click', function() {
        var btn = $(this);
        btn.addClass('loading').prop('disabled', true);
        jQuery.post(
            siusk24data.ajax_url, 
            {
                'action': 'siusk24_terminals_sync'
            }, 
            function(response) {
                
            }
        ).always(function() {
            btn.removeClass('loading').prop('disabled', false);
        });
    });
    
    $('button.services-sync-btn').on('click', function() {
        var btn = $(this);
        btn.addClass('loading').prop('disabled', true);
        jQuery.post(
            siusk24data.ajax_url, 
            {
                'action': 'siusk24_services_sync'
            }, 
            function(response) {
                location.reload();
            }
        ).always(function() {
            btn.removeClass('loading').prop('disabled', false);
        });
    });

    $(".siusk24-toggle_controller").each(function( index ) {
        set_toggle_data_show(this);
        toggle_group_fields(this);
    });

    $(document).on("change", ".siusk24-toggle_controller", function() {
        set_toggle_data_show(this);
        toggle_group_fields(this);
    });

    function set_toggle_data_show(controller) {
        var controller_value = $(controller).val();
        var field = $(controller).attr("data-field");

        if (field == "price_type") {
            if (controller_value == "fixed") {
                $(controller).attr("data-show", "fixed");
            } else {
                $(controller).attr("data-show", "additional");
            }
        }
    }

    function toggle_group_fields(controller) {
        var group = $(controller).attr("data-group");
        var field = $(controller).attr("data-field");
        var show = $(controller).attr("data-show");
        var toggle_elements = $(".siusk24-toggle-" + group + "-" + field);
        var show_class = "siusk24-toggle_show-" + show;

        for (var i = 0; i <= toggle_elements.length; i++) {
            if ($(toggle_elements[i]).hasClass(show_class)) {
                $(toggle_elements[i]).closest("tr").show();
            } else {
                $(toggle_elements[i]).closest("tr").hide();
            }
        }
    }

    
});

