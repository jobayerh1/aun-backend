jQuery(document).ready(function($) {

    // Instantiates color-picker
    $('.my-color-field').wpColorPicker();

    /**
    * Automatically opens the Current Saved tab to show the options menu as it reloads
    */
    function go_to_option() {
      if ( window.location.hash ) {
        let open_option = window.location.hash.substr(1);
        $("#"+open_option).delay(200).slideDown(300);
        $("#"+open_option).parent().find('.gweb-option-title').animate({width:"100%"},200);
        history.pushState("", document.title, window.location.pathname + window.location.search);
      }
    }
    go_to_option();


  /**
   * Animates Option windows slide up and down
   */
    $('.gweb-option-title').on('click', function(e) {
      e.preventDefault();
      if ( e.target.className == 'gweb-option-title' ||  e.target.className == 'mobile-removable' ) {
        $(this).parent().find('.gweb-option-container').slideToggle(300,"linear", function() {
        });
      }
    });

    // Resets options
    $('#submit-reset-button').on('click', function(e) {
      e.preventDefault();
      if ( confirm("Reset Button settings?") ) {
        $('#gweb_stock_alert_options_default_settings_button').val("reset");
        $("#gweb-stock-notify-form-button").submit();
        $("#mainform").submit();
      }
    });
    $('#submit-reset-mobile').on('click', function(e) {
      e.preventDefault();
      if ( confirm("Reset mobile settings?") ) {
        $('#gweb_stock_alert_options_default_settings_mobile').val("reset");
        $("#gweb-stock-notify-form-mobile").submit();
        $("#mainform").submit();
      }
    });

    /**
    * Preview Header Image
    */
      var header_img_input = $('#gweb_stock_alert_mobile_options_gweb_mobile_header_img_url');
      var header_container = $('<div>', { id: 'header_img_input_container' });
      $(header_container).insertAfter(header_img_input);
      $(header_img_input).appendTo(header_container);
      function update_header_img( source ) {
          $('.header_img').remove();
          let $img_display = $('<img>', { class: 'header_img' });
          $img_display.hide();
          $img_display.on('load', function() {
                                                $('.header_img').remove();
                                                $(this).appendTo(header_container);
                                                $img_display.show(400);
                                              })
                      .on('error', function() {
                                                $(this).attr('src', alertDataAdmin.no_image_link );
                                                $(this).addClass('no-image-found');
                                                $img_display.show();
                                              })
                      .attr("src", source);
      }
      $('#gweb_stock_alert_mobile_options_gweb_mobile_header_img_url').on('paste keyup', function() {
        let new_source = $(this).val();
        if ( new_source ) {
           update_header_img( new_source );
        } else {
           update_header_img( alertDataAdmin.default_header_img_url );
        }
      }).trigger('paste');

    // Sends the test mobile and handles the response
    $('#mobile-notification-test').on('submit', function(e) {
      e.preventDefault();
      $(this).find('.loader-mobile').show();
      send_test_mobile_ajax();
    });
    function send_test_mobile_ajax() {
          let mobileValue = $('#recipient_mobile_field').val();
          let payload = { 'test_mobile' : mobileValue };
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
        if ( $('.switch').find('input').prop('checked') ) {
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

        let payload = { 'mobile' : user_mobile };
var getData;
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
              }
			  getData = data;
			  
            },
            error: function(status, error) {
              console.log(error);
            }

          } ).done( function (response) {
            console.log(JSON.stringify(response));
			 $('#gwebsmsresult').html( response.payload ).slideDown(300).delay(8000).hide(500);
          });
      });

    // Delete (Pending) request/requests
    $('.isa-delete-btn').on('click', function(e) {
      if (  !confirm("Are you sure you want to delete this request?") ) return;

      $(this).parent().find('.loader').removeClass('hidden-loader');
      let ids = $(this).attr('data-proudct-ids');
      let block_id = $(this).attr('data-block-id');
      let payload = { 'request_ids' : ids };

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

    // Reassignes current color on the submenu
    $('#toplevel_page_gweb-stock-notify li').removeClass('current');
    let $links = $('#toplevel_page_gweb-stock-notify').find('a');

    $.each( $links, function( index, value ) {

      let page = $(value).text();
      let current_tab = alertDataAdmin.current_tab;

      if ( !current_tab.toLowerCase() && page.toLowerCase() === 'pending') {
        $(value).parent().addClass('current');
      }
      if ( page.toLowerCase() === current_tab.toLowerCase() ) {
        $(value).parent().addClass('current');
      }

    });


});
