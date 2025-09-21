jQuery(function($){
  // Detect plugin settings page
  const onSettings = new URLSearchParams(window.location.search).get('page') === 'molecule-viewer';
  if (!onSettings) {
    // WooCommerce product editor: media picker wiring
    $(document).on('click', '.mvwp-upload', function(e){
      e.preventDefault();
      const frame = wp.media({
        title: 'Select a molecular file',
        button:{ text:'Use this file' }, multiple:false, library:{ type:'application' }
      });
      frame.on('select', function(){
        const a = frame.state().get('selection').first().toJSON();
        const ok = /\.(pdb|sdf|mol2|xyz|cube)(\?.*)?$/i.test(a.url);
        if (!ok) { alert('Please choose a pdb/sdf/mol2/xyz/cube file.'); return; }
        $('#_mvwp_attachment_id').val(a.id);
        $('#_mvwp_attachment_url_display').val(a.url);
      });
      frame.open();
    });
    $(document).on('click', '.mvwp-clear', function(e){
      e.preventDefault();
      $('#_mvwp_attachment_id').val('');
      $('#_mvwp_attachment_url_display').val('');
      $('#_mvwp_file_url').val('');
    });
    return;
  }

  // i18n helper (no hard dependency during early boot)
  const __ = (window.wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : (s)=>s;

  const $form = $('#mvwp-wrap form').first();
  if (!$form.length) return;

  // Ensure Settings API inputs exist even if a section fails to render.
  const ensureHidden = (name) => {
    if (!$form.find(`[name="${name}"]`).length) {
      $('<input>', { type:'hidden', name }).appendTo($form);
    }
  };
  [
    'mvwp_style_mode','mvwp_repr','mvwp_color','mvwp_stick_radius','mvwp_sphere_scale',
    'mvwp_surface_opacity','mvwp_ui','mvwp_nomouse','mvwp_show_download','mvwp_default_spin',
    'mvwp_attachment_only','mvwp_max_size_mb','mvwp_script_core','mvwp_script_ui',
    'mvwp_enable_proxy','mvwp_allowed_hosts','mvwp_default_style'
  ].forEach(ensureHidden);

  const val = (name, fallback='') => {
    const $f = $form.find(`[name="${name}"]`);
    return $f.length ? ($f.val() ?? fallback) : fallback;
  };

  // Model the current state (read from Settings API inputs)
  const state = {
    mode: val('mvwp_style_mode','simple'),
    repr: val('mvwp_repr','cartoon'),
    color: val('mvwp_color','spectrum'),
    stick: parseFloat(val('mvwp_stick_radius','0.2')) || 0.2,
    sphere: parseFloat(val('mvwp_sphere_scale','0.3')) || 0.3,
    surf: parseFloat(val('mvwp_surface_opacity','0.6')) || 0.6,
    ui: (val('mvwp_ui','yes') === 'yes'),
    nomouse: (val('mvwp_nomouse','no') === 'yes'),
    dload: (val('mvwp_show_download','yes') === 'yes'),
    proxy: (val('mvwp_enable_proxy','no') === 'yes'),
    attachOnly: (val('mvwp_attachment_only','no') === 'yes'),
    spin: val('mvwp_default_spin',''),
    core: val('mvwp_script_core',''),
    uiurl: val('mvwp_script_ui',''),
    maxmb: parseInt(val('mvwp_max_size_mb','20'),10) || 20,
    hosts: val('mvwp_allowed_hosts',''),
    json: $form.find('textarea[name="mvwp_default_style"]').val() || '{"cartoon":{"color":"spectrum"}}',
  };

  // UI frame
  const $frame = $(`
    <div class="mvwp-card">
      <div style="display:flex;justify-content:space-between;align-items:center;" class="mvwp-header">
        <div>
          <h2 style="margin:0">Molecule Viewer</h2>
          <div class="mvwp-subtle">Friendly controls + JSON for power users</div>
        </div>
        <div class="mvwp-segment" id="mvwp-mode">
          <button data-mode="simple">${__('Simple','molecule-viewer')}</button>
          <button data-mode="json">${__('JSON','molecule-viewer')}</button>
        </div>
      </div>
      <div id="mvwp-simple-card" class="mvwp-card" style="margin-top:0">
        <div class="mvwp-controls-grid">
          <label>${__('Representation','molecule-viewer')}</label>
          <select id="mvwp-repr">
            ${['cartoon','stick','ballandstick','surface','line','sphere'].map(o=>`<option value="${o}">${o[0].toUpperCase()+o.slice(1)}</option>`).join('')}
          </select>

          <label>${__('Color scheme','molecule-viewer')}</label>
          <select id="mvwp-color">
            ${['spectrum','chain','element','residue','bfactor','white','grey','rainbow'].map(o=>`<option value="${o}">${o[0].toUpperCase()+o.slice(1)}</option>`).join('')}
          </select>

          <label>${__('Stick radius','molecule-viewer')}</label>
          <div class="mvwp-range-wrap">
            <input id="mvwp-stick" class="mvwp-slider" type="range" min="0" max="1" step="0.01">
            <input id="mvwp-stick-num" type="number" min="0" step="0.01">
          </div>

          <label>${__('Sphere scale','molecule-viewer')}</label>
          <div class="mvwp-range-wrap">
            <input id="mvwp-sphere" class="mvwp-slider" type="range" min="0" max="2" step="0.01">
            <input id="mvwp-sphere-num" type="number" min="0" step="0.01">
          </div>

          <label>${__('Surface opacity','molecule-viewer')}</label>
          <div class="mvwp-range-wrap">
            <input id="mvwp-surf" class="mvwp-slider" type="range" min="0" max="1" step="0.05">
            <input id="mvwp-surf-num" type="number" min="0" max="1" step="0.05">
          </div>

          <div class="desc">${__('The style JSON below reflects these choices while in “Simple” mode.','molecule-viewer')}</div>
        </div>
      </div>

      <div id="mvwp-json-card" class="mvwp-card" style="display:none;margin-top:0">
        <div class="mvwp-json-wrap">
          <textarea id="mvwp-json"></textarea>
          <p class="mvwp-subtle" style="margin:.5rem 0 0">${__('Edit the raw 3Dmol.js style object. Switch back to Simple to see parsed values when compatible.','molecule-viewer')}</p>
        </div>
      </div>
    </div>

    <div class="mvwp-card">
      <h3 style="margin-top:0">${__('Viewer Behavior','molecule-viewer')}</h3>
      <div class="mvwp-controls-grid">
        <label>${__('Show toolbar UI','molecule-viewer')}</label>
        <label class="mvwp-switch"><input id="mvwp-ui" type="checkbox"><span class="mvwp-slider-boolean"></span></label>

        <label>${__('Disable mouse','molecule-viewer')}</label>
        <label class="mvwp-switch"><input id="mvwp-nomouse" type="checkbox"><span class="mvwp-slider-boolean"></span></label>

        <label>${__('Show download link','molecule-viewer')}</label>
        <label class="mvwp-switch"><input id="mvwp-dload" type="checkbox"><span class="mvwp-slider-boolean"></span></label>

        <label>${__('Default spin','molecule-viewer')}</label>
        <input id="mvwp-spin" type="text" placeholder="true or y:0.6" style="max-width:220px">
      </div>
    </div>

    <div class="mvwp-card">
      <h3 style="margin-top:0">${__('Advanced','molecule-viewer')}</h3>
      <div class="mvwp-controls-grid">
        <label>${__('Attachment-only mode','molecule-viewer')}</label>
        <label class="mvwp-switch"><input id="mvwp-attach" type="checkbox"><span class="mvwp-slider-boolean"></span></label>

        <label>${__('Max file size (MB)','molecule-viewer')}</label>
        <input id="mvwp-maxmb" type="number" min="0" step="1" style="max-width:120px">

        <label>${__('Core JS URL','molecule-viewer')}</label>
        <input id="mvwp-core" type="url" style="max-width:620px">

        <label>${__('UI JS URL','molecule-viewer')}</label>
        <input id="mvwp-uiurl" type="url" style="max-width:620px">
      </div>
    </div>

    <div class="mvwp-card">
      <h3 style="margin-top:0">${__('Proxy (CORS helper)','molecule-viewer')}</h3>
      <div class="mvwp-controls-grid">
        <label>${__('Enable proxy','molecule-viewer')}</label>
        <label class="mvwp-switch"><input id="mvwp-proxy" type="checkbox"><span class="mvwp-slider-boolean"></span></label>

        <label>${__('Allowed hosts','molecule-viewer')}</label>
        <textarea id="mvwp-hosts" rows="4" style="max-width:620px"></textarea>
      </div>
    </div>
  `);

  // Insert UI inside the settings form
  const $firstTable = $form.find('.form-table').first();
  if ($firstTable.length) { $firstTable.before($frame); } else { $form.prepend($frame); }

  // Prefill custom controls from state
  $('#mvwp-repr').val(state.repr);
  $('#mvwp-color').val(state.color);
  $('#mvwp-stick,#mvwp-stick-num').val(state.stick);
  $('#mvwp-sphere,#mvwp-sphere-num').val(state.sphere);
  $('#mvwp-surf,#mvwp-surf-num').val(state.surf);
  $('#mvwp-ui').prop('checked', state.ui);
  $('#mvwp-nomouse').prop('checked', state.nomouse);
  $('#mvwp-dload').prop('checked', state.dload);
  $('#mvwp-spin').val(state.spin);
  $('#mvwp-attach').prop('checked', state.attachOnly);
  $('#mvwp-maxmb').val(state.maxmb);
  $('#mvwp-core').val(state.core);
  $('#mvwp-uiurl').val(state.uiurl);
  $('#mvwp-proxy').prop('checked', state.proxy);
  $('#mvwp-hosts').val(state.hosts);
  $('#mvwp-json').val(state.json);

  const setField = (name, value) => { const $f = $form.find(`[name="${name}"]`); if ($f.length) $f.val(value); };
  const setMode = (m) => {
    state.mode = m;
    $('#mvwp-mode button').removeClass('active')
      .filter(`[data-mode="${m}"]`).addClass('active');
    $('#mvwp-simple-card').toggle(m==='simple');
    $('#mvwp-json-card').toggle(m==='json');
    if (m==='simple') buildJSON();
    setField('mvwp_style_mode', m);
  };
  const ensureSimpleMode = () => { if (state.mode !== 'simple') setMode('simple'); };

  $('#mvwp-mode').on('click','button',function(e){
    e.preventDefault();
    setMode($(this).data('mode'));
  });
  setMode(state.mode);

  // Keep JSON + hidden settings in sync
  const linkSlider = (sliderSel, numSel, key, fieldName) => {
    const $s = $(sliderSel), $n = $(numSel);
    const update = (v) => {
      const num = parseFloat(v);
      state[key] = isNaN(num) ? 0 : num;
      $s.val(state[key]); $n.val(state[key]);
      ensureSimpleMode();
      if (state.mode==='simple') buildJSON();
      setField(fieldName, state[key]);
    };
    $s.on('input change', ()=>update($s.val()));
    $n.on('input change', ()=>update($n.val()));
  };
  linkSlider('#mvwp-stick',  '#mvwp-stick-num',  'stick',  'mvwp_stick_radius');
  linkSlider('#mvwp-sphere', '#mvwp-sphere-num', 'sphere', 'mvwp_sphere_scale');
  linkSlider('#mvwp-surf',   '#mvwp-surf-num',   'surf',   'mvwp_surface_opacity');

  $('#mvwp-repr').on('change', function(){
    state.repr = this.value;
    ensureSimpleMode();
    buildJSON();
    setField('mvwp_repr', this.value);
  });
  $('#mvwp-color').on('change', function(){
    state.color = this.value;
    ensureSimpleMode();
    buildJSON();
    setField('mvwp_color', this.value);
  });

  $('#mvwp-ui').on('change', function(){ state.ui = this.checked; setField('mvwp_ui', this.checked ? 'yes' : 'no'); });
  $('#mvwp-nomouse').on('change', function(){ state.nomouse = this.checked; setField('mvwp_nomouse', this.checked ? 'yes' : 'no'); });
  $('#mvwp-dload').on('change', function(){ state.dload = this.checked; setField('mvwp_show_download', this.checked ? 'yes' : 'no'); });
  $('#mvwp-attach').on('change', function(){ state.attachOnly = this.checked; setField('mvwp_attachment_only', this.checked ? 'yes' : 'no'); });
  $('#mvwp-proxy').on('change', function(){ state.proxy = this.checked; setField('mvwp_enable_proxy', this.checked ? 'yes' : 'no'); });

  $('#mvwp-spin').on('input', function(){ state.spin = this.value; setField('mvwp_default_spin', this.value); });
  $('#mvwp-maxmb').on('input', function(){ state.maxmb = parseInt(this.value||'0',10)||0; setField('mvwp_max_size_mb', state.maxmb); });
  $('#mvwp-core').on('input', function(){ state.core = this.value; setField('mvwp_script_core', this.value); });
  $('#mvwp-uiurl').on('input', function(){ state.uiurl = this.value; setField('mvwp_script_ui', this.value); });
  $('#mvwp-hosts').on('input', function(){ state.hosts = this.value; setField('mvwp_allowed_hosts', this.value); });

  function buildJSON(){
    const colorMap = { spectrum:'spectrum', chain:'chain', element:'elem', residue:'residue', bfactor:'b', white:'whiteCarbon', grey:'greyCarbon', rainbow:'spectrum' };
    const cs = colorMap[state.color] || 'spectrum';
    let style = {};
    switch (state.repr) {
      case 'stick':
        style = { stick: { radius: state.stick, colorscheme: cs } }; break;
      case 'ballandstick':
        style = { stick: { radius: state.stick }, sphere: { scale: state.sphere, colorscheme: cs } }; break;
      case 'line':
        style = { line: { colorscheme: cs } }; break;
      case 'sphere':
        style = { sphere: { scale: state.sphere, colorscheme: cs } }; break;
      case 'surface':
        style = { cartoon: { color: cs }, surface: { opacity: state.surf } }; break;
      case 'cartoon':
      default:
        style = { cartoon: { color: cs } };
    }
    const out = JSON.stringify(style);
    state.json = out;
    $('#mvwp-json').val(out);
    $form.find('textarea[name="mvwp_default_style"]').val(out);
  }
  if (state.mode==='simple') buildJSON();

  // Mirror to Settings API fields on submit
  $form.on('submit', function(){
    if (state.mode==='simple') buildJSON();
    setField('mvwp_style_mode', state.mode);
    setField('mvwp_repr', state.repr);
    setField('mvwp_color', state.color);
    setField('mvwp_stick_radius', state.stick);
    setField('mvwp_sphere_scale', state.sphere);
    setField('mvwp_surface_opacity', state.surf);
    setField('mvwp_ui', state.ui ? 'yes' : 'no');
    setField('mvwp_nomouse', state.nomouse ? 'yes' : 'no');
    setField('mvwp_show_download', state.dload ? 'yes' : 'no');
    setField('mvwp_default_spin', state.spin);
    setField('mvwp_attachment_only', state.attachOnly ? 'yes' : 'no');
    setField('mvwp_max_size_mb', state.maxmb);
    setField('mvwp_script_core', state.core);
    setField('mvwp_script_ui', state.uiurl);
    setField('mvwp_enable_proxy', state.proxy ? 'yes' : 'no');
    setField('mvwp_allowed_hosts', state.hosts);
    $form.find('textarea[name="mvwp_default_style"]').val(state.json);
  });
});