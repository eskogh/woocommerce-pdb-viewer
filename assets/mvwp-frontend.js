(function(){
  /*
   * Molecule Viewer WP – frontend loader (production-friendly)
   * - Lazy-load 3Dmol core (and UI only if requested)
   * - Load text first; fallback to $3Dmol.download if needed
   * - Robust sniffers + sanitizers
   * - Default logging is silent unless MVWP_CFG.debug is truthy
   */

  var MVWP_DEBUG = !!(window.MVWP_CFG && window.MVWP_CFG.debug);
  function log(){ if (MVWP_DEBUG && console && console.debug) console.debug.apply(console, ['[MVWP]'].concat([].slice.call(arguments))); }
  function info(){ if (MVWP_DEBUG && console && console.info) console.info.apply(console, ['[MVWP]'].concat([].slice.call(arguments))); }
  function warn(){ if (console && console.warn) console.warn.apply(console, ['[MVWP]'].concat([].slice.call(arguments))); }
  function err(){ if (console && console.error) console.error.apply(console, ['[MVWP]'].concat([].slice.call(arguments))); }

  function overlay(el, msg){
    // lightweight debug overlay
    var d = document.createElement('div');
    d.style.position='absolute'; d.style.inset='0'; d.style.display='grid';
    d.style.placeItems='center'; d.style.background='rgba(0,0,0,0.02)';
    d.style.font='14px/1.4 system-ui, sans-serif'; d.style.color='#555';
    d.textContent = msg; el.appendChild(d);
  }

  function clearOverlays(el){
    Array.from(el.children).forEach(function(n){
      if (n && n.style && n.style.position === 'absolute' && n.textContent && /No atoms|Load failed|3Dmol load failed/i.test(n.textContent)) {
        el.removeChild(n);
      }
    });
  }

  function isVisible(el){
    if (!el || !el.isConnected) return false;
    if (!(el.offsetWidth || el.offsetHeight || el.getClientRects().length)) return false;
    var s = getComputedStyle(el);
    return s.visibility !== 'hidden' && s.display !== 'none';
  }

  // ----- script loader (deduped) -----
  var _loading = {};
  function loadScriptOnce(src){
    return new Promise(function(resolve, reject){
      if (!src) return reject(new Error('No src for script'));
      if (_loading[src] === 'done') return resolve();
      if (_loading[src] && _loading[src].then) return _loading[src].then(resolve).catch(reject);

      var el = document.querySelector('script[src="'+src+'"]');
      var s = el || document.createElement('script');
      if (!el) {
        s.src = src; s.async = false; s.defer = false; s.crossOrigin = 'anonymous';
        document.head.appendChild(s);
      }
      _loading[src] = new Promise(function(res, rej){
        s.addEventListener('load', function(){ s.setAttribute('data-loaded','1'); _loading[src] = 'done'; res(); });
        s.addEventListener('error', function(){ _loading[src] = null; rej(new Error('Failed to load '+src)); });
      });
      _loading[src].then(resolve).catch(reject);
    });
  }

  function ensure3DmolLoaded(){
    if (window.$3Dmol) return Promise.resolve();
    var needUI = !!document.querySelector('.mvwp-viewer[data-ui="true"]');
    var core = (window.MVWP_CFG && MVWP_CFG.core) || 'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol-min.js';
    var ui   = (window.MVWP_CFG && MVWP_CFG.ui)   || 'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol.ui-min.js';
    info('loading 3Dmol', core, needUI ? '(+UI)' : '(no UI)');
    return loadScriptOnce(core).then(function(){
      if (!needUI) return;
      return loadScriptOnce(ui);
    }).then(function(){
      if (!window.$3Dmol) throw new Error('$3Dmol still undefined after load');
    });
  }

  // ----- text sanitizers + sniffers -----
  function sanitize(txt){
    try {
      return (txt || '').replace(/=\r?\n/g,'').replace(/=20/g,' ').replace(/[ \t]+$/gm,'');
    } catch(e){ warn('sanitize failed', e); return txt || ''; }
  }
  function sniff(txt){
    var head = (txt || '').slice(0, 16000), ih = head.toUpperCase();
    if (ih.includes('@<TRIPOS>')) return 'mol2';
    if (/^\s*\$\$\$\$/m.test(head) || /M\s+END/m.test(head)) return 'sdf';
    if (/CUBE/i.test(head) && /ORIGIN/i.test(head)) return 'cube';
    if (/\b(HEADER|TITLE|COMPND|ATOM|HETATM|REMARK|CONECT)\b/i.test(head)) return 'pdb';
    if (/^\s*\d+\s*$/m.test(head) && /^[A-Za-z][A-Za-z]?\s+[-\d.+]/m.test(head)) return 'xyz';
    return 'pdb';
  }
  function hasAtoms(viewer){
    try {
      var m = (typeof viewer.getModel === 'function') ? viewer.getModel() : null;
      if (m && Array.isArray(m.atoms) && m.atoms.length) return true;
      var ms = (typeof viewer.getModels === 'function') ? viewer.getModels() : [];
      return (Array.isArray(ms) && ms[0] && Array.isArray(ms[0].atoms) && ms[0].atoms.length>0);
    } catch(_) { return false; }
  }
  function normalizeMinimalPDB(txt){
    return txt.replace(/^(?:ATOM|HETATM).{66}([A-Z][a-z]?)[+\-]\d\s*$/gm, function(m, elem){
      return m.replace(/([A-Z][a-z]?)[+\-]\d\s*$/, (elem + ' ').slice(0, 2));
    });
  }

  function tryAddModel(viewer, txt, types){
    for (var i=0;i<types.length;i++){
      var t = types[i];
      try {
        if (viewer.removeAllModels) viewer.removeAllModels();
        viewer.addModel(txt, t);
        if (hasAtoms(viewer)) return t;
      } catch(e){ warn('addModel threw for', t, e); }
    }
    return null;
  }

  function applyStyleRender(el, viewer, style, spin){
    try {
      // reset styles/surfaces
      if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
      if (viewer.setBackgroundColor) viewer.setBackgroundColor(0xffffff);
      if (viewer.removeAllSurfaces) viewer.removeAllSurfaces();
      if (viewer.setStyle) viewer.setStyle({}, null);

      // fallback style if empty/invalid
      var effective = (style && typeof style === 'object' && Object.keys(style).length)
        ? JSON.parse(JSON.stringify(style))
        : { stick: { radius: 0.22 }, sphere: { scale: 0.28 } };

      viewer.setStyle({}, effective);
      viewer.resize(); viewer.zoomTo(); viewer.render();

      // second pass for hidden tabs/late layout
      setTimeout(function(){ try{ viewer.resize(); viewer.zoomTo(); viewer.render(); }catch(_){ } }, 60);

      if (spin) {
        try {
          var axis='y', speed=1;
          if (spin !== 'true') { var p=(''+spin).split(':'); axis=(p[0]||'y').toLowerCase(); speed=parseFloat(p[1]||'1')||1; }
          viewer.spin(axis, speed);
        } catch(e){ warn('spin failed', e); }
      }
    } catch(e){ warn('applyStyleRender failed', e); }
  }

  // ----- init flow -----
  function actuallyInit(el){
    if (el.getAttribute('data-mvwp-init')) return;
    el.setAttribute('data-mvwp-init','1');

    ensure3DmolLoaded().then(function(){
      var url   = el.getAttribute('data-url');
      var typ   = (el.getAttribute('data-type') || 'auto').toLowerCase();
      var nm    = el.getAttribute('data-nomouse') === 'true';
      var bga   = parseFloat(el.getAttribute('data-bgalpha') || '0');
      var sty   = el.getAttribute('data-style') || '{}';
      var spin  = el.getAttribute('data-spin');

      if (!url) { overlay(el, 'No file URL'); return; }

      var style = {};
      try { style = JSON.parse(sty); } catch(e){ style = {"cartoon":{"color":"spectrum"}}; }

      var viewer;
      try {
        viewer = $3Dmol.createViewer(el, { backgroundAlpha: bga, disableMouse: nm });
      } catch (e) {
        err('createViewer failed', e); overlay(el, 'Viewer init failed'); return;
      }
      if (nm) { try { el.style.pointerEvents = 'none'; } catch(_){} }

      // Prefer text path; fallback to $3Dmol.download
      fetch(url, { credentials: 'same-origin' })
        .then(function(r){ if (!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
        .then(function(txt){
          var clean = sanitize(txt);
          if (/\b(ATOM|HETATM)\b/i.test(clean) || /\b(HEADER|TITLE|COMPND|REMARK|CONECT)\b/i.test(clean)) clean = normalizeMinimalPDB(clean);
          var guess = (typ && typ !== 'auto') ? typ : sniff(clean);
          var order = (typ && typ !== 'auto') ? [typ] : ['pdb', guess, 'sdf', 'mol2', 'xyz', 'cube'].filter(function(v,i,a){ return v && a.indexOf(v)===i; });

          var used = tryAddModel(viewer, clean, order);
          if (used && hasAtoms(viewer)) {
            clearOverlays(el);
            applyStyleRender(el, viewer, style, spin);
            el._mvwp = viewer;
            return;
          }

          warn('No atoms after text-parse; trying $3Dmol.download fallback');
          var fmt = guess || (typ && typ!=='auto' ? typ : 'pdb');
          var nocacheUrl = url + (url.includes('?') ? '&' : '?') + 'mvwp_nocache=' + Date.now();

          function afterDownload(){
            if (hasAtoms(viewer)) {
              clearOverlays(el);
              applyStyleRender(el, viewer, style, spin);
              el._mvwp = viewer;
            } else {
              overlay(el, 'No atoms found — check file/type');
            }
          }
          try {
            $3Dmol.download(nocacheUrl, viewer, { type: fmt }, afterDownload);
          } catch(e1) {
            try { $3Dmol.download(nocacheUrl, viewer, {}, afterDownload, fmt); }
            catch(e2) { err('download failed', e2); overlay(el, 'No atoms found — check file/type'); }
          }
        })
        .catch(function(e){ err('load error', url, e); overlay(el, 'Load failed — see console'); });

    }).catch(function(e){ err('Failed ensuring 3Dmol', e); overlay(el, '3Dmol load failed'); });
  }

  function initOne(el){
    if (el.getAttribute('data-mvwp-init')) return;
    if (!isVisible(el)) {
      var tries = 0;
      var t = setInterval(function(){
        if (isVisible(el)) { clearInterval(t); actuallyInit(el); }
        else if (++tries > 40) { clearInterval(t); warn('giving up init (not visible)'); }
      }, 500);
      if (window.jQuery) {
        jQuery(document).on('click', '.woocommerce-Tabs a, .wc-tabs a, .woocommerce-tabs a', function(){
          setTimeout(function(){ if (isVisible(el)) actuallyInit(el); }, 60);
        });
      }
      return;
    }
    actuallyInit(el);
  }

  function scan(){ document.querySelectorAll('.mvwp-viewer').forEach(initOne); }

  if ('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if (en.isIntersecting) { initOne(en.target); io.unobserve(en.target); }
      });
    }, {root:null, rootMargin:'200px', threshold:0.01});
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.mvwp-viewer').forEach(function(el){ io.observe(el); });
    });
  } else {
    document.addEventListener('DOMContentLoaded', scan);
  }

  // Woo tabs: keep viewer sized/rendered when panels toggle visibility
  const tabObserver = new MutationObserver(() => {
    document.querySelectorAll('.mvwp-viewer').forEach((el) => {
      if (el._mvwp && el.offsetParent !== null) { try { el._mvwp.resize(); el._mvwp.render(); } catch(_){} }
    });
  });
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.woocommerce-Tabs, .wc-tabs, .woocommerce-tabs')
      .forEach((tabs) => tabObserver.observe(tabs, { attributes: true, subtree: true, attributeFilter: ['class','style'] }));
  });

  // Late-added panels/viewers
  const lateObserver = new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      if (!m.addedNodes) return;
      m.addedNodes.forEach((n) => {
        if (!(n instanceof Element)) return;
        if (n.matches && n.matches('.mvwp-viewer')) { initOne(n); }
        if (n.querySelectorAll) { n.querySelectorAll('.mvwp-viewer').forEach((el) => initOne(el)); }
      });
    });
  });
  document.addEventListener('DOMContentLoaded', () => { lateObserver.observe(document.body, { childList: true, subtree: true }); });
})();
