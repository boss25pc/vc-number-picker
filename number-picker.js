jQuery(function($){
  // --- Early exits / handles ---
  const $form = $('form.cart').first(); if(!$form.length) return;
  const $btn  = $form.find('.single_add_to_cart_button');
  const $wrap = $('.vc-np').first(); if(!$wrap.length) return;
  const pid   = parseInt($wrap.data('product'),10) || 0;
  const $hidden  = $wrap.find('.vc-np-numbers');
  const $qty  = $form.find('input.qty');

  const REFRESH_MS = 6000; // quicker board sync
const HOLD_MS = 1200;     // skip polling ~1.2s after user click
const INTENT_MS = 1600;   // respect local pick/unpick intent for this long
let refreshHoldUntil = 0;
const intent = new Map(); // n -> {picked:boolean, ts:number}

  // --- Helpers ---
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

  // --- Apply server state (patched to avoid "my pick becomes RES") ---
  function applyState(state){
    const sold = new Set(state.sold || []);
    const res  = new Set(state.reserved || []);
    const mineFromServer = new Set(state.mine || []);
    const now = Date.now();

    $wrap.find('.vc-cell').each(function(){
      const $cell = $(this);
      const n = parseInt($cell.data('n'),10);
      const seen = intent.get(n);
      const hasIntent = seen && (now - seen.ts) < INTENT_MS;
      const desiredPicked = hasIntent ? !!seen.picked : null;

      const isSold = sold.has(n);
      let isMine = mineFromServer.has(n) || $cell.hasClass('is-picked');
      if (hasIntent) {
        // Respect recent local intent over server during grace window
        isMine = desiredPicked;
      }
      const isResOther = res.has(n) && !isMine && !isSold;

      $cell
        .toggleClass('is-sold', isSold)
        .toggleClass('is-res',  isResOther)
        .toggleClass('is-picked', isMine);

      // Disable only if SOLD or reserved by someone else
      const disable = isSold || isResOther;
      $cell.prop('disabled', disable).attr('aria-disabled', disable ? 'true' : null);
    });

    $hidden.val(getPicked().join(','));
    syncQty();
  }

  function refreshState(){
    if (Date.now() < refreshHoldUntil) return;
    if(!pid) return;
    $.post(VCNP.ajax, { action:'vc_np_state', pid, nonce:VCNP.nonce })
      .done(function(r){ if(r && r.success) applyState(r.data); });
  }

  // Initial sync + polling
  refreshState();
  setInterval(refreshState, REFRESH_MS);

  // --- Optimistic click: instant UI, then confirm with server ---
  $wrap.on('click','.vc-cell',function(){
    const $c = $(this);
    const n = parseInt($c.data('n'),10);
    if(!pid || !n) return;
    if ($c.is('.is-sold, .is-res')) return; // blocked if sold or reserved by others

    refreshHoldUntil = Date.now() + HOLD_MS;

    if ($c.hasClass('is-picked')){
      // Optimistic unpick
      setPicked(n,false);
      intent.set(n,{picked:false, ts: Date.now()});
      $.post(VCNP.ajax, { action:'vc_np_release', pid, num:n, nonce:VCNP.nonce })
        .always(refreshState);
      return;
    }

    // Optimistic pick
    setPicked(n,true);
    intent.set(n,{picked:true, ts: Date.now()});
    $.post(VCNP.ajax, { action:'vc_np_reserve', pid, num:n, nonce:VCNP.nonce })
      .done(function(r){
        if(!(r && r.success)){
          // Server refused (taken/race) -> revert
          setPicked(n,false);
          alert(VCNP.i18n?.taken || 'That number just got taken. Please pick another.');
          refreshState();
        }
      })
      .fail(function(){
        setPicked(n,false);
        alert('Could not reserve that number.');
        refreshState();
      });
  });

  // --- Add to cart: one line per picked number ---
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
          refreshState();
        }
      })
      .fail(function(){ alert('Could not add to cart.'); });
    return false;
  });

  // --- Release my holds on navigation (prevents sticky RES after refresh) ---
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