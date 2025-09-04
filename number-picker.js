/* VC Number Picker ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â stable click + live label
   - Unpick guard prevents poll from re-picking just-unpicked tiles
   - Button shows "Add X Entries" and disables at 0
   - Single, namespaced click handler
*/
(function () {
  if (!window.jQuery) { return; }
  jQuery(function ($) {
    var $wrap = $('.vc-np').first();
    if (!$wrap.length) { return; }

    var pid = parseInt($wrap.data('product'), 10) || 0;
    if (!pid) { return; }

    var ajax = (window.VCNP && VCNP.ajax) || window.ajaxurl || '';
    var nonce = (window.VCNP && VCNP.nonce) || (function () {
      var m = document.querySelector('meta[name="vcnp-nonce"]');
      return m ? m.getAttribute('content') : '';
    })();
    if (!ajax || !nonce) { return; }

    var $form = $('form.cart').first();
    var $btn = $form.find('.single_add_to_cart_button');
    var $qty = $form.find('input.qty');
    var $hidden = $wrap.find('.vc-np-numbers');

    var REFRESH_MS = 3000;
    var UNPICK_GUARD_MS = 800;
    var CLICK_DEBOUNCE_MS = 80;
    var AFTER_CLICK_REFRESH_MS = 150;

    var lastClickTs = 0;
    var unpickGuard = {}; // n -> ts

    function getPicked() {
      var out = [];
      $wrap.find('.vc-cell.is-picked').each(function () {
        out.push(parseInt($(this).data('n'), 10));
      });
      return out;
    }

    function setPicked(n, on) {
      var $c = $wrap.find('.vc-cell[data-n="' + n + '"]');
      $c.toggleClass('is-picked', !!on);
      if (on) { $c.removeClass('is-res'); }

      var nums = getPicked();
      $hidden.val(nums.join(','));
      if ($qty.length) { $qty.val(nums.length).trigger('change'); }

      var base = $btn.data('vcnpBase');
      if (!base) { base = $.trim($btn.text()) || 'Add to cart'; $btn.data('vcnpBase', base); }
      var label = nums.length ? ('Add ' + nums.length + ' Entries') : base;
      $btn.text(label).prop('disabled', nums.length === 0);
    }

    function post(data) {
      data = data || {};
      data.pid = pid;
      data.nonce = nonce;
      return $.post(ajax, data);
    }

    function applyState(data) {
      if (!data) { return; }
      var sold = {}, res = {}, mine = {};
      var i, arr;

      arr = data.sold || [];
      for (i = 0; i < arr.length; i++) { sold[arr[i]] = 1; }
      arr = data.reserved || [];
      for (i = 0; i < arr.length; i++) { res[arr[i]] = 1; }
      arr = data.mine || [];
      for (i = 0; i < arr.length; i++) { mine[arr[i]] = 1; }

      var now = Date.now();

      $wrap.find('.vc-cell').each(function () {
        var $c = $(this);
        var n = parseInt($c.data('n'), 10);

        var guardTs = unpickGuard[n] || 0;
        var guardActive = (now - guardTs) < UNPICK_GUARD_MS;

        var isSold = !!sold[n];
        // Local intent wins briefly after an unpick
        var isMine = guardActive ? false : (!!mine[n] || $c.hasClass('is-picked'));
        var isResOther = !!res[n] && !isMine && !isSold;

        $c.toggleClass('is-sold', isSold)
          .toggleClass('is-res', isResOther)
          .toggleClass('is-picked', isMine);
      });

      // sync hidden/qty/button
      var nums = getPicked();
      $hidden.val(nums.join(','));
      if ($qty.length) { $qty.val(nums.length); }
      var base = $btn.data('vcnpBase');
      if (!base) { base = $.trim($btn.text()) || 'Add to cart'; $btn.data('vcnpBase', base); }
      var label = nums.length ? ('Add ' + nums.length + ' Entries') : base;
      $btn.text(label).prop('disabled', nums.length === 0);
    }

    function refresh() {
      post({ action: 'vc_np_state' }).done(function (r) {
        if (r && r.success && r.data) { applyState(r.data); }
      });
    }

    // One clean, namespaced handler
    $wrap.off('click.vcnp').on('click.vcnp', '.vc-cell', function (e) {
      e.preventDefault();
      var now = Date.now();
      if (now - lastClickTs < CLICK_DEBOUNCE_MS) { return; }
      lastClickTs = now;

      var $c = $(this);
      var n = parseInt($c.data('n'), 10);
      if (!n || $c.hasClass('is-sold')) { return; }

      // Unpick
      if ($c.hasClass('is-picked')) {
        setPicked(n, false);
        unpickGuard[n] = now;
        post({ action: 'vc_np_release', num: n }).always(function () {
          setTimeout(refresh, AFTER_CLICK_REFRESH_MS);
        });
        return;
      }
      // DonÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢t fight someone elseÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢s hold
      if ($c.hasClass('is-res')) { return; }

      // Pick
      setPicked(n, true);
      post({ action: 'vc_np_reserve', num: n }).done(function (r) {
        if (!(r && r.success)) { setPicked(n, false); }
      }).always(function () {
        setTimeout(refresh, AFTER_CLICK_REFRESH_MS);
      });
    });

    // Initial sync + poll
    refresh();
    setInterval(refresh, REFRESH_MS);
  });
})();









/* vcnp cta hook v5.1 (decorates updateCta; honors VCNP.settings) */
(function(){
  if (!window.jQuery) return;
  jQuery(function($){
    var $wrap = $('.vc-np').first(); if(!$wrap.length) return;
    var $form = $('form.cart').first();
    var $btn  = $form.find('.single_add_to_cart_button'); if(!$btn.length) return;

    var cfg  = (window.VCNP && VCNP.settings) || {};
    var BASE = (typeof CTA_BASE !== 'undefined' && CTA_BASE) ? CTA_BASE : ($btn.text() || 'Participate now');
    var showTotal = (cfg.show_total !== false);
    var unit = (typeof cfg.unit_price === 'number' && cfg.unit_price>0) ? cfg.unit_price : NaN;
    var sym  = cfg.currency_symbol || '£';
    var pending=false;

    function parseMoney(txt){
      var m = (txt||'').replace(/\s+/g,'').match(/([£$€])?([\d.,]+)/); if(!m) return NaN;
      if(!sym) sym = m[1] || '£';
      var s = m[2], dot=s.lastIndexOf('.'), comma=s.lastIndexOf(',');
      var dec = dot>comma ? '.' : ',', th = dec==='.' ? /,/g : /\./g;
      s = s.replace(th,'').replace(dec,'.'); var v = parseFloat(s);
      return isFinite(v) ? v : NaN;
    }
    function detectUnit(){
      if (isFinite(unit)) return;
      var wrap = document.querySelector('.entry-summary .price, .summary .price, .product .summary .price, .product .price');
      if (wrap){
        var cand = wrap.querySelector('ins .woocommerce-Price-amount, .woocommerce-Price-amount, .amount');
        if(cand){ var v=parseMoney(cand.textContent); if(isFinite(v)){ unit=v; return; } }
      }
      var list = document.querySelectorAll('.entry-summary .price .woocommerce-Price-amount, .summary .price .woocommerce-Price-amount, .woocommerce-Price-amount, .amount');
      for (var i=0;i<list.length;i++){ var v2=parseMoney(list[i].textContent); if(isFinite(v2)){ unit=v2; return; } }
      var h1 = document.querySelector('h1.product_title, .product_title.entry-title, .entry-title');
      if (h1){ var v3=parseMoney(h1.textContent); if(isFinite(v3)){ unit=v3; return; } }
    }
    function fmt(n){
      try{
        var code=(window.wc_price_params&&wc_price_params.currency_code)||(cfg.currency||'');
        if(window.Intl && code) return new Intl.NumberFormat(document.documentElement.lang||'en-GB',{style:'currency',currency:code}).format(n);
      }catch(e){}
      return (sym||'£') + n.toFixed(2);
    }
    function countPicked(){ return $wrap.find('.vc-cell.is-picked').length; }
    function refreshCta(){
      var n = countPicked();
      var label = BASE;
      if (n>0){
        var extra = '';
        if (showTotal){
          detectUnit();
          if(isFinite(unit)) extra = ' ('+fmt(n*unit)+')';
        }
        label = 'Add ' + n + ' ' + (n===1?'Entry':'Entries') + extra;
      }
      $btn.text(label);
    }
    function schedule(){ if(pending) return; pending=true; setTimeout(function(){ pending=false; refreshCta(); }, 0); }

    if (typeof window.updateCta === 'function'){
      var __old = window.updateCta;
      window.updateCta = function(c){ try{ __old(c); } finally { schedule(); } };
    }

    var mo = new MutationObserver(schedule);
    mo.observe($wrap[0], {subtree:true, attributes:true, attributeFilter:['class']});
    $form.on('change input','input.qty', schedule);
    jQuery(document).on('ajaxComplete', function(){ schedule(); });
    refreshCta();
  });
})();
