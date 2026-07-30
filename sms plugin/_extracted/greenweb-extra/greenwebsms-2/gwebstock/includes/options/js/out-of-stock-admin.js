jQuery(document).ready(function($) {

    // Instantiates color-picker
    $('.my-color-field').wpColorPicker();

    // Resets all options
    $('#submit-reset').on('click', function(e) {
      e.preventDefault();
      if ( confirm("Reset All settings?") ) {
        $('#gweb_stock_alert_options_default_settings').val("reset");
        $("#gweb-stock-notify-form").submit();
        $("#mainform").submit();
        // gweb_woo_menu_post_option();
      }
    });

    // Sends the test mobile and handles the response
    $('#mobile-notification-test').on('submit', function(e) {
      e.preventDefault();
      $(this).find('.loader-mobile').show();
      send_test_mobile_ajax();
    });
    function send_test_mobile_ajax() {
          mobileValue = $('#recipient_mobile_field').val();
          payload = { 'test_mobile' : mobileValue };
          $('.loader-mobile').show(300);
      $.ajax( {
          url: alertDataAdmin.api_base_url + '/mobile-test',
          method: 'POST',
          beforeSend: function ( xhr ) {
              xhr.setRequestHeader( 'X-WP-Nonce', alertDataAdmin.nonce );
          },
          data: payload,
          success: function(data) {
            $('.loader-mobile').hide();
            if ( data == false ) {
              $err_message = 'Please check your mobile configuration!<br> You must install and configure an mobile provider to use this plugin.'
              $('.isa-error-message').html( $err_message ).slideDown(300).delay(8000).hide(500);
            } else {
              $('.isa-success-message').html( 'Success!!! Now check your mobile address.' ).slideDown(300).delay(8000).hide(500);
            }

          },
          error: function(status, error) {
            $('.loader-mobile').hide();
            $err_message = 'Please first verify that your mobile address is correct!<br> You must install and configure an mobile provider to use this plugin.'
            $('.isa-error-message').stop().html( $err_message ).slideDown(300).delay(8000).hide(500);
          }

        } ).done( function ( response ) {
          console.log( response );
        });
    }

    // Switch to mobile grouping/no grouping
    $('.slider-groupby').on('click', function(e) {
      e.stopPropagation();
      setTimeout( function() {
        if ( $('.switch').find('input').attr('checked') == 'checked' ) {
           window.location.href = go_to_groupby_mobile;
        } else {
           window.location.href = go_to_no_groupby;
        }
      }, 410);
    });

    // Send Single Pending mobile
    function reload_empty_list() {
      if ( $('#the-list tr').length ) return;
      location.reload();
      return false;

    }
    $('.isa-send-alert-btn').on('click', function(e) {
        let user_mobile = $(this).attr('data-user_mobile');
        let block_id = $(this).attr('data-block-id');
        let $pending_obj = $(this).parent();
        $pending_obj.find('.loader').removeClass('hidden-loader');

        payload = { 'mobile' : user_mobile };
        $.ajax( {
            url: alertDataAdmin.api_base_url + '/alert/send',
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', alertDataAdmin.nonce );
            },
            data: payload,
            success: function(data) {
              $pending_obj.find('.loader').addClass('hidden-loader');
              if ( data.payload == true ) {
                $('#block-'+block_id).hide(300, function(e) {
                  $(this).remove();
                  reload_empty_list();
                });
              } else {
 $('.isa-error-message').html( jqxhr.responseText ).slideDown(300).delay(8000).hide(500);

			  }
            }

          } ).done( function ( response ) {
            console.log( response );
          });
      });

    // Delete (Pending) request/requests
    $('.isa-delete-btn').on('click', function(e) {
      if (  !confirm("Are you sure you want to delete this request?") ) return;

      $(this).parent().find('.loader').removeClass('hidden-loader');
      let ids = $(this).attr('data-proudct-ids');
      let block_id = $(this).attr('data-block-id');
      payload = { 'request_ids' : ids };

      $.ajax( {
          url: alertDataAdmin.api_base_url + '/requests/remove',
          method: 'POST',
          beforeSend: function ( xhr ) {
              xhr.setRequestHeader( 'X-WP-Nonce', alertDataAdmin.nonce );
          },
          data: payload,
          success: function(data) {
            $('.loader').addClass('hidden-loader');
            $('#block-'+block_id).hide(300, function(e) {
              $(this).remove();
              reload_empty_list();
            });
          }

        } ).done( function ( response ) {
          console.log( response );
        });
    });

    // Delete Sent mobile
    $('.mobile-action-btn').on('click', function(e) {
        $(this).parent().find('.loader').removeClass('hidden-loader');
        let mobile_id = $(this).attr('data-mobile-id');
        let block_id = $(this).attr('data-block-id');
        $.ajax( {
            url: alertDataAdmin.api_base_url + '/mobile/remove/' + mobile_id,
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', alertDataAdmin.nonce );
            },
            success: function(data) {
              $('.loader').addClass('hidden-loader');
              $('#block-'+block_id).hide(300, function(e) {
                $(this).remove();
                reload_empty_list();
              });
            }
          } ).done( function ( response ) {
            console.log( response );
          });
    });

    // ( WooCommerce Custom Menu Tab only )
    if ( typeof is_woo_menu_gweb_tab !== 'undefined' ) {

          $('#mainform').attr('action', 'options.php');

    }

});
