jQuery(function($){
  // --- Early exits / handles ---
  const $form = $('form.cart').first(); if(!$form.length) return;
  const $btn  = $form.find('.single_add_to_cart_button');
  const $wrap = $('.vc-np').first(); if(!$wrap.length) return;
  const pid   = parseInt($wrap.data('product'),10) || 0;
  const $hidden  = $wrap.find('.vc-np-numbers');
  const $qty  = $form.find('input.qty');

  // Timings
  const REFRESH_MS = 8000;  // background poll interval
  const HOLD_MS    = 1500;  // pause polling after a user click
  const INTENT_MS  = 2000;  // prefer local intent for this long

  // Local intent + poll hold
  let refreshHoldUntil = 0;   // timestamp until which poll is skipped
  const intent = new Map();   // n -> { wantPicked: bool, ts: number }
  function noteIntent(n, wantPicked){ intent.set(n, { wantPicked: !!wantPicked, ts: Date.now() }); }
  function isIntentFresh(n){ const i = intent.get(n); return !!i && (Date.now() - i.ts) < INTENT_MS; }

  // Helpers
  function getPicked(){
    return $wrap.find('.vc-cell.is-picked').map(function(){
      return parseInt($(this).data('n'),10);
    }).get();
  }
  function syncQty(){
    const c = getPicked().length || 1;
    if ($qty.length) $qty.val(c).trigger('change');
    $btn.prop('disabled', getPicked().length===0);
  }
  function setPicked(n, on){
    const $c = $wrap.find('.vc-cell[data-n="'+n+'"]');
    $c.toggleClass('is-picked', !!on);
    $hidden.val(getPicked().join(','));
    syncQty();
  }

  // Apply server state (respect recent local intent to prevent "pop-back")
  function applyState(state){
    const sold = new Set(state.sold || []);
    const res  = new Set(state.reserved || []);
    const mine = new Set(state.mine || []);

    $wrap.find('.vc-cell').each(function(){
      const $cell = $(this);
      const n = parseInt($cell.data('n'),10);

      const isSold = sold.has(n);
      const mineNow = mine.has(n);
      const isResOther = res.has(n) && !mineNow && !isSold;

      let nextPicked;
      if (isIntentFresh(n)) {
        nextPicked = intent.get(n).wantPicked;     // honor local intent briefly
      } else {
        nextPicked = mineNow;                      // otherwise follow server
      }

      $cell
        .toggleClass('is-sold', isSold)
        .toggleClass('is-res',  isResOther)
        .toggleClass('is-picked', nextPicked);

      const disable = isSold || isResOther;        // disable only if not pickable
      $cell.prop('disabled', disable).attr('aria-disabled', disable ? 'true' : null);
    });

    $hidden.val(getPicked().join(','));
    syncQty();
  }

  // Poll (with optional force bypass)
  function refreshState(force){
    if (!pid) return;
    if (!force && Date.now() < refreshHoldUntil) return; // don't stomp fresh clicks
    $.post(VCNP.ajax, { action:'vc_np_state', pid, nonce:VCNP.nonce })
      .done(function(r){ if(r && r.success) applyState(r.data); });
  }

  // Initial sync + polling
  refreshState(true);
  setInterval(refreshState, REFRESH_MS);

  // Click handling: optimistic UI + server call, then forced refresh
  $wrap.on('click','.vc-cell',function(){
    const $c = $(this);
    const n = parseInt($c.data('n'),10);
    if(!pid || !n) return;
    if ($c.is('.is-sold, .is-res')) return; // blocked if sold or reserved by others

    // hold background poll briefly
    refreshHoldUntil = Date.now() + HOLD_MS;

    if ($c.hasClass('is-picked')){
      // Optimistic unpick
      noteIntent(n,false);
      setPicked(n,false);
      $.post(VCNP.ajax, { action:'vc_np_release', pid, num:n, nonce:VCNP.nonce })
        .always(function(){ refreshState(true); }); // confirm server truth asap
      return;
    }

    // Optimistic pick
    noteIntent(n,true);
    setPicked(n,true);
    $.post(VCNP.ajax, { action:'vc_np_reserve', pid, num:n, nonce:VCNP.nonce })
      .done(function(r){
        if(!(r && r.success)){
          // Server refused (taken/race) -> revert
          setPicked(n,false);
          alert(VCNP.i18n?.taken || 'That number just got taken. Please pick another.');
        }
      })
      .fail(function(){
        setPicked(n,false);
        alert('Could not reserve that number.');
      })
      .always(function(){ refreshState(true); });   // confirm server truth asap
  });

  // Add to cart: one line per picked number (unchanged)
  $form.on('submit', function(e){
    const nums = getPicked();
    if(!nums.length){ alert(VCNP.i18n?.pick || 'Please select at least one number.'); return false; }

    const $skill = $wrap.find('#vc_np_skill_answer');
    const ans = ($skill.val()||'').trim();
    if($skill.length && !ans){ alert(VCNP.i18n?.skill || 'Please answer the skill question.'); return false; }

    e.preventDefault();
    $.post(VCNP.ajax, { action:'vc_np_add_to_cart', pid, nums: nums, skill: ans, nonce:VCNP.nonce })
      .done(function(r){
        if (r && r.success) {
          const dest = (r.data && r.data.cart) ? r.data.cart : '/cart';
          window.location.href = dest;
        } else {
          alert((r && r.data && r.data.msg) ? r.data.msg : 'Could not add to cart.');
          refreshState(true);
        }
      })
      .fail(function(){ alert('Could not add to cart.'); });
    return false;
  });

  // Release my holds on navigation
  window.addEventListener('beforeunload', function(){
    const nums = getPicked(); if(!nums.length) return;
    for (const n of nums){
      try{
        const fd = new FormData();
        fd.append('action','vc_np_release');
        fd.append('pid', String(pid));
        fd.append('num', String(n));
        fd.append('nonce', VCNP.nonce);
        if (navigator.sendBeacon) navigator.sendBeacon(VCNP.ajax, fd);
      }catch(e){ /* ignore */ }
    }
  });
});
