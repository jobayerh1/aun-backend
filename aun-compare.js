/* AUN Projector Compare — v2.7.0
 * v2.7.0: selection persists across navigation (no more clear-on-compare),
 *         clicking an "Added" button removes that product (toggle),
 *         limit modal offers a "View Comparison" action,
 *         aria-pressed kept in sync on compare buttons. */
(function($){
  $(function(){
    function getStored(){ try { return JSON.parse(localStorage.getItem('aunCompareIDs')||'[]'); } catch(e){ return []; } }
    function saveStored(ids){ localStorage.setItem('aunCompareIDs', JSON.stringify(ids)); }
    var ids = getStored();
    function showCompareModal(message, action) {
      // Remove existing modal if any
      $('.aun-compare-modal-backdrop').remove();

      // Optional primary action link (e.g. "View Comparison") next to OK
      var actionHtml = '';
      if (action && action.href && action.text) {
        actionHtml = '<a class="aun-btn aun-btn--primary" style="margin-right:8px;" href="' + action.href + '">' + action.text + '</a>';
      }

      // Create modal HTML
      var modalHtml =
        '<div class="aun-compare-modal-backdrop">' +
          '<div class="aun-compare-modal">' +
            '<p>' + message + '</p>' +
            actionHtml +
            '<button type="button" class="aun-btn aun-btn--soft aun-compare-modal-close">OK</button>' +
          '</div>' +
        '</div>';
      
      $('body').append(modalHtml);

      // Force a reflow to allow CSS transition
      var $backdrop = $('.aun-compare-modal-backdrop');
      $backdrop.width(); 
      $backdrop.addClass('aun-modal-visible');

      // .on() is correct here — the target check inside handles clicks on modal
      // content by returning early, so .on() never causes double-fires.
      // .one() was wrong: it consumed the handler on the first inner click,
      // making the backdrop permanently unclickable afterwards.
      $backdrop.on('click', function(e) {
        if ($(e.target).closest('.aun-compare-modal').length) {
          return;
        }
        closeModal();
      });

      $('.aun-compare-modal-close').on('click', function() {
        closeModal();
      });

      function closeModal() {
        $backdrop.removeClass('aun-modal-visible').fadeOut(200, function() { $(this).remove(); });
      }
    }

    /**
     * Updates a single button's appearance (text and class).
     */
    function updateButtonState($btn, isAdded) {
        $btn.attr('aria-pressed', isAdded ? 'true' : 'false');
        if (isAdded) {
            $btn.addClass('added');
            if ($btn.hasClass('aun-compare--shop')) {
                $btn.find('span').text('Added');
            } else {
                $btn.find('span').text('Added to Compare');
            }
        } else {
            $btn.removeClass('added');
            if ($btn.hasClass('aun-compare--shop')) {
                $btn.find('span').text('Compare');
            } else {
                $btn.find('span').text('Add to Compare');
            }
        }
    }

    /**
     * Syncs all buttons on page load with the localStorage state.
     */
    function syncAllButtonsOnLoad() {
        $('.aun-add-to-compare').each(function() {
            var $btn = $(this);
            var id = parseInt($btn.data('product-id'), 10);
            
            // Check if the button's ID is in the stored array
            if (id && ids.indexOf(id) !== -1) {
                updateButtonState($btn, true);
            } else if (id) {
                // Ensure it's in the default state if not in array
                updateButtonState($btn, false);
            }
        });
    }

    function ensureDock(){
      if($('.aun-compare-dock').length) return;
      $('body').append(
        '<div class="aun-compare-dock" aria-live="polite">'+
          '<div class="dock-inner">'+
            '<span class="aun-compare-count">0</span>'+
            '<a class="aun-btn aun-btn--primary aun-compare-go" href="'+AUN_COMPARE.compare_page+'" aria-disabled="true">Compare</a>'+
            '<button type="button" class="aun-btn aun-btn--ghost dock-clear">Clear</button>'+
          '</div>'+
        '</div>'
      );
    }
    function dockBottomOffset(){
      var off = 24;
      var sticky = $('.sticky-add-to-cart,.product-footer,#product-footer,.sticky-addtocart,.add-to-cart-sticky');
      if(sticky.length){ off = 84; }
      return off;
    }
    function renderDock(){
      ensureDock();
      var dock = $('.aun-compare-dock'), count = $('.aun-compare-count'), go = $('.aun-compare-go');
      dock.css({bottom: dockBottomOffset()+'px', top: 'auto'});
      count.text(ids.length);
      if(ids.length < 1){ dock.fadeOut(); go.attr('aria-disabled','true').addClass('disabled').attr('href', AUN_COMPARE.compare_page); return; }
      dock.fadeIn();
      if(ids.length < 2){
        go.attr('aria-disabled','true').addClass('disabled').attr('href', AUN_COMPARE.compare_page);
      }else{
        go.removeAttr('aria-disabled').removeClass('disabled').attr('href', AUN_COMPARE.compare_page + '?ids=' + ids.join(','));
      }
    }

    $(document).on('click', '.aun-add-to-compare', function(){
      var $btn = $(this);
      var id = parseInt($btn.data('product-id'), 10);
      if(!id) return;

      // Toggle: clicking an already-added button removes that product.
      // (Previously there was no way to remove a single product — only Clear all.)
      var idx = ids.indexOf(id);
      if(idx !== -1){
        ids.splice(idx, 1); saveStored(ids);
        updateButtonState($btn, false);
        renderDock();
        return;
      }

      if(ids.length >= AUN_COMPARE.limit){
        var action = ids.length >= 2
          ? { href: AUN_COMPARE.compare_page + '?ids=' + ids.join(','), text: 'View Comparison' }
          : null;
        showCompareModal('You can compare up to ' + AUN_COMPARE.limit + ' projectors. Tap an "Added" button to remove one, or view your comparison.', action);
        return;
      }

      ids.push(id); saveStored(ids);
      updateButtonState($btn, true);
      renderDock();
    });

    $(document).on('click', '.dock-clear', function(){
      ids = []; saveStored(ids);
      
      // Find all compare buttons on the page and reset their state
      $('.aun-add-to-compare').each(function() {
          updateButtonState($(this), false);
      });
      
      renderDock();
    });

    /**
     * The selection intentionally SURVIVES navigation to the compare page
     * (v2.7.0). Clearing it here meant: back button = list gone, swapping one
     * model = rebuild from scratch. Users remove items by re-tapping an
     * "Added" button or via the dock's Clear.
     */
    $(document).on('click', '.aun-compare-go', function(e){
      if ($(this).attr('aria-disabled') === 'true') {
        e.preventDefault();
      }
    });

    function applyDiffFilter(on){
      // .aun-section-row is added by PHP to <tr> elements that are section headers.
      // We never touch them here — section headers stay visible regardless.
      var $rows = $('table.aun-compare-table tbody tr').not('.aun-section-row');
      if(on){
        $rows.not('.aun-diff').hide();
        // Hide section header rows whose section has no visible diff rows,
        // show those that do have at least one diff row.
        $('table.aun-compare-table tbody tr.aun-section-row').each(function(){
          var $sec = $(this);
          var hasDiff = false;
          var $next = $sec.nextAll('tr');
          $next.each(function(){
            if($(this).hasClass('aun-section-row')) return false; // stop at next section
            if($(this).hasClass('aun-diff')){ hasDiff = true; return false; }
          });
          if(hasDiff){ $sec.show(); } else { $sec.hide(); }
        });
      } else {
        $rows.show();
        $('table.aun-compare-table tbody tr.aun-section-row').show();
      }
    }

    $(document).on('change', '#aun-diff-only', function(){
      applyDiffFilter(this.checked);
    });

    $(document).on('click', '.aun-diff-toggle', function(e){
      if (e.target.id === 'aun-diff-only') return;
      var $cb = $(this).find('#aun-diff-only');
      $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });

    syncAllButtonsOnLoad();
    renderDock();

  });
})(jQuery);

