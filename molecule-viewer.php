<?php
/**
 * Plugin Name: Molecule Viewer for WordPress
 * Plugin URI:  https://skogh.org
 * Description: Render interactive molecular structures (PDB/SDF/MOL2/XYZ/CUBE) anywhere in WordPress. Shortcode, Block, Elementor. Optional WooCommerce integration.
 * Version:     1.5.9-dev
 * Author:      Erik Skogh
 * Author URI:  https://skogh.org
 * Text Domain: molecule-viewer
 * Domain Path: /languages
 * License:     GPL2
 */

if (!defined('ABSPATH')) exit;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * Constants
 * Keep public URLs overrideable via defines for custom CDN/self-hosting.
 * ─────────────────────────────────────────────────────────────────────────────
 */
define('MVWP_VERSION', '1.5.9-dev');
define('MVWP_SLUG',    'molecule-viewer');
define('MVWP_OPT_GRP', 'mvwp_settings');

if (!defined('MVWP_3DMOL_CORE_URL')) define('MVWP_3DMOL_CORE_URL', 'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol-min.js');
if (!defined('MVWP_3DMOL_UI_URL'))   define('MVWP_3DMOL_UI_URL',   'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol.ui-min.js');

/**
 * Woo detection (guards all Woo paths)
 */
function mvwp_is_woo_active() { return class_exists('WooCommerce'); }

/**
 * i18n
 */
add_action('init', function () {
    load_plugin_textdomain('molecule-viewer', false, dirname(plugin_basename(__FILE__)).'/languages');
});

/**
 * Upload mimes: allow all supported text-based formats.
 */
add_filter('upload_mimes', function ($m) {
    $m['pdb']  = 'chemical/x-pdb';
    $m['sdf']  = 'chemical/x-mdl-sdfile';
    $m['mol2'] = 'chemical/x-mol2';
    $m['xyz']  = 'chemical/x-xyz';
    $m['cube'] = 'chemical/x-gaussian-cube';
    return $m;
});

/**
 * Allow text-like molecular files when PHP finfo returns text/plain.
 * A quick sniff prevents false negatives on valid uploads.
 */
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes, $real_mime) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdb','sdf','mol2','xyz','cube'], true)) return $data;

    $ok = true;
    if (is_readable($file)) {
        $head = file_get_contents($file, false, null, 0, 8192);
        if ($head !== false) {
            $patterns = [
                'pdb'  => '/\b(HEADER|TITLE|COMPND|ATOM|HETATM|REMARK|CONECT)\b/',
                'sdf'  => '/M\s+END/s',
                'mol2' => '/@<TRIPOS>/s',
                'xyz'  => '/^\s*\d+\s*$/m',
                'cube' => '/\bCUBE\b/i',
            ];
            $pat = $patterns[$ext] ?? null;
            if ($pat && !preg_match($pat, $head)) $ok = false;
        }
    }
    if ($ok) {
        $map = [
            'pdb'  => 'chemical/x-pdb',
            'sdf'  => 'chemical/x-mdl-sdfile',
            'mol2' => 'chemical/x-mol2',
            'xyz'  => 'chemical/x-xyz',
            'cube' => 'chemical/x-gaussian-cube',
        ];
        $data['ext']  = $ext;
        $data['type'] = $map[$ext] ?? 'text/plain';
    }
    return $data;
}, 10, 5);

/**
 * Optional upload size limit for supported formats (MB).
 * 0 = unlimited.
 */
add_filter('wp_handle_upload_prefilter', function ($file) {
    $limit_mb = (int) get_option('mvwp_max_size_mb', 20);
    if ($limit_mb > 0 && !empty($file['size'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdb','sdf','mol2','xyz','cube'], true)) {
            $limit_bytes = $limit_mb * 1024 * 1024;
            if ((int)$file['size'] > $limit_bytes) {
                $file['error'] = sprintf(esc_html__('Molecular files over %d MB are not allowed.', 'molecule-viewer'), $limit_mb);
            }
        }
    }
    return $file;
});

/**
 * Helpers
 */
function mvwp_guess_type_from_url($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $ext  = strtolower(pathinfo($path ?: '', PATHINFO_EXTENSION));
    $map  = ['pdb'=>'pdb','sdf'=>'sdf','mol2'=>'mol2','xyz'=>'xyz','cube'=>'cube'];
    return $map[$ext] ?? 'pdb';
}

function mvwp_maybe_proxy_url($url) {
    if (get_option('mvwp_enable_proxy', 'no') !== 'yes') return $url;
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return $url;
    $allowed = array_filter(array_map('trim', explode("\n", (string) get_option('mvwp_allowed_hosts', ''))));
    if (!in_array($host, $allowed, true)) return $url; // block unknown hosts
    return rest_url('mvwp/v1/proxy?url=' . rawurlencode($url));
}

/**
 * Frontend scripts (3Dmol + loader)
 * Enqueued in the head to cope with themes missing wp_footer().
 */
function mvwp_enqueue_frontend_scripts() {
    $core = get_option('mvwp_script_core', MVWP_3DMOL_CORE_URL);
    $ui   = get_option('mvwp_script_ui',   MVWP_3DMOL_UI_URL);
    $urls = apply_filters('mvwp_3dmol_urls', ['core' => $core, 'ui' => $ui]);

    wp_enqueue_script('3dmol-js', $urls['core'], [], null, false);
    wp_enqueue_script('3dmol-ui', $urls['ui'],   ['3dmol-js'], null, false);

    wp_enqueue_script(
        'mvwp-frontend',
        plugin_dir_url(__FILE__) . 'assets/mvwp-frontend.js',
        ['3dmol-js'],
        MVWP_VERSION,
        false
    );

    wp_localize_script('mvwp-frontend', 'MVWP_CFG', [
        'core'  => $urls['core'],
        'ui'    => $urls['ui'],
        'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? 1 : 0,
    ]);
}

/**
 * Build a 3Dmol style object from simple settings.
 * Default is Ball-and-stick for small molecules; protein-friendly cartoon is still one click away.
 */
function mvwp_build_style_from_simple($args){
    $rep     = $args['representation'] ?? 'ballandstick';
    $color   = $args['color'] ?? 'spectrum';
    $stick_r = isset($args['stick_radius']) ? (float)$args['stick_radius'] : 0.2;
    $sphere_s= isset($args['sphere_scale']) ? (float)$args['sphere_scale'] : 0.3;
    $surf_o  = isset($args['surface_opacity']) ? (float)$args['surface_opacity'] : 0.6;

    $color_map = [
        'spectrum' => 'spectrum',
        'chain'    => 'chain',
        'element'  => 'elem',
        'residue'  => 'residue',
        'bfactor'  => 'b',
        'rainbow'  => 'spectrum',
        'white'    => 'whiteCarbon',
        'grey'     => 'greyCarbon',
    ];
    $cs = $color_map[$color] ?? 'spectrum';

    switch ($rep) {
        case 'stick':
            return ['stick' => ['radius' => $stick_r, 'colorscheme' => $cs]];
        case 'ballandstick':
            return ['stick' => ['radius' => $stick_r], 'sphere' => ['scale' => $sphere_s, 'colorscheme' => $cs]];
        case 'line':
            return ['line' => ['colorscheme' => $cs]];
        case 'sphere':
            return ['sphere' => ['scale' => $sphere_s, 'colorscheme' => $cs]];
        case 'surface':
            return ['cartoon' => ['color' => $cs], 'surface' => ['opacity' => $surf_o]];
        case 'cartoon':
        default:
            return ['cartoon' => ['color' => $cs]];
    }
}

/**
 * Shortcodes
 * - [mol_viewer]  primary
 * - [pdb_viewer]  back-compat alias
 */
add_shortcode('mol_viewer', 'mvwp_render_shortcode');
add_shortcode('pdb_viewer', 'mvwp_render_shortcode'); // back-compat

/**
 * Render viewer container + enqueue scripts.
 * Style priority:
 *   1) style_mode=json + valid JSON => use as-is
 *   2) otherwise derive from simple fields (prevents "always cartoon" mistakes)
 */
function mvwp_render_shortcode($atts){
    $a = shortcode_atts([
        'height'    => '400px',
        'width'     => '100%',
        'ui'        => get_option('mvwp_ui','yes') === 'yes' ? 'true' : 'false',
        'nomouse'   => get_option('mvwp_nomouse','no') === 'yes' ? 'true' : 'false',
        'bgalpha'   => '0.0',
        'spin'      => get_option('mvwp_default_spin',''),
        'download'  => get_option('mvwp_show_download','yes'),

        // Source
        'file_url'  => '',
        'type'      => '',

        // Style
        'style_mode'        => get_option('mvwp_style_mode','simple'),
        'representation'    => get_option('mvwp_repr','ballandstick'),
        'color'             => get_option('mvwp_color','spectrum'),
        'stick_radius'      => get_option('mvwp_stick_radius','0.2'),
        'sphere_scale'      => get_option('mvwp_sphere_scale','0.3'),
        'surface_opacity'   => get_option('mvwp_surface_opacity','0.6'),
        'style'             => get_option('mvwp_default_style',''),

        // Woo hook (auto-resolve)
        'product_id'=> '',
        'pdb_id'    => '',
    ], $atts, 'mol_viewer');

    // Resolve source URL (attachment id, absolute or root-relative)
    $raw = trim((string)$a['file_url']);
    if ($raw === '') { $raw = apply_filters('mvwp/shortcode/resolve_file_url', ''); }
    if ($raw !== '' && ctype_digit($raw)) {
        $maybe = wp_get_attachment_url((int)$raw);
        if ($maybe) $raw = $maybe;
    }
    if ($raw !== '' && $raw[0] === '/') { $raw = home_url($raw); }

    $source = ($raw !== '') ? mvwp_maybe_proxy_url($raw) : '';
    if ($source === '') {
        return '<p>'.esc_html__('No molecular file URL provided.', 'molecule-viewer').'</p>';
    }

    // Normalize type + mode
    $a['type'] = ($a['type'] === '' || strtolower($a['type']) === 'auto') ? 'auto' : strtolower($a['type']);
    $mode = in_array($a['style_mode'], ['simple','json'], true) ? $a['style_mode'] : 'simple';

    // Validate raw JSON if JSON mode selected
    $style_json = trim((string)$a['style']);
    $decoded    = json_decode($style_json, true);
    $has_valid  = is_array($decoded) && !empty($decoded);

    if ($mode !== 'json' || !$has_valid) {
        $style_arr  = mvwp_build_style_from_simple([
            'representation'   => $a['representation'],
            'color'            => $a['color'],
            'stick_radius'     => (float)$a['stick_radius'],
            'sphere_scale'     => (float)$a['sphere_scale'],
            'surface_opacity'  => (float)$a['surface_opacity'],
        ]);
        $style_json = wp_json_encode($style_arr);
    }

    mvwp_enqueue_frontend_scripts();
    $id = 'mvwp_' . wp_generate_uuid4();

    ob_start(); ?>
    <div id="<?php echo esc_attr($id); ?>"
         class="mvwp-viewer"
         role="img"
         aria-label="<?php echo esc_attr__('Molecular structure', 'molecule-viewer'); ?>"
         style="height: <?php echo esc_attr($a['height']); ?>; width: <?php echo esc_attr($a['width']); ?>; position: relative; margin:20px 0;"
         data-url="<?php echo esc_url($source); ?>"
         data-type="<?php echo esc_attr($a['type']); ?>"
         data-bgalpha="<?php echo esc_attr($a['bgalpha']); ?>"
         data-style='<?php echo esc_attr($style_json); ?>'
         data-ui="<?php echo esc_attr($a['ui']); ?>"
         data-nomouse="<?php echo esc_attr($a['nomouse']); ?>"
         data-spin="<?php echo esc_attr($a['spin']); ?>"
    ></div>
    <?php if ($a['download'] === 'yes') : ?>
      <p><a class="button" href="<?php echo esc_url($source); ?>" download><?php echo esc_html__('Download file', 'molecule-viewer'); ?></a></p>
    <?php endif;

    // Inline safety net when theme missed wp_head/wp_footer.
    $need_inline = ! did_action('wp_head') || ! did_action('wp_footer');
    if ($need_inline) {
        $core = get_option('mvwp_script_core', MVWP_3DMOL_CORE_URL);
        $ui   = get_option('mvwp_script_ui',   MVWP_3DMOL_UI_URL);
        $frontend = plugin_dir_url(__FILE__) . 'assets/mvwp-frontend.js';

        echo '<script>window.MVWP_CFG = {core:"'.esc_js($core).'", ui:"'.esc_js($ui).'", debug:'.(defined('WP_DEBUG')&&WP_DEBUG?1:0).'};</script>';
        echo '<script src="'.esc_url($core).'" defer data-mvwp-inline="1"></script>';
        echo '<script src="'.esc_url($ui).'" defer data-mvwp-inline="1"></script>';
        echo '<script src="'.esc_url($frontend).'" defer data-mvwp-inline="1"></script>';
    }

    return ob_get_clean();
}

/**
 * Gutenberg block (server-rendered)
 */
add_action('init', function () {
    wp_register_script(
        'mvwp-block',
        plugin_dir_url(__FILE__) . 'assets/block.js',
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-i18n'],
        MVWP_VERSION,
        true
    );
    register_block_type('mvwp/viewer', [
        'editor_script'   => 'mvwp-block',
        'render_callback' => function ($attributes) {
            $atts = [];
            foreach (['height','width','style','spin','file_url','type'] as $k) {
                if (!empty($attributes[$k])) $atts[$k] = $attributes[$k];
            }
            if (isset($attributes['ui']))      $atts['ui']      = $attributes['ui'] ? 'true' : 'false';
            if (isset($attributes['nomouse'])) $atts['nomouse'] = $attributes['nomouse'] ? 'true' : 'false';
            return do_shortcode('[mol_viewer ' . http_build_query($atts, '', ' ') . ']');
        },
        'attributes' => [
            'height'  => ['type'=>'string','default'=>'400px'],
            'width'   => ['type'=>'string','default'=>'100%'],
            'ui'      => ['type'=>'boolean','default'=>true],
            'nomouse' => ['type'=>'boolean','default'=>false],
            'style'   => ['type'=>'string','default'=>''],
            'spin'    => ['type'=>'string','default'=>''],
            'file_url'=> ['type'=>'string','default'=>''],
            'type'    => ['type'=>'string','default'=>''],
        ],
    ]);
});

/**
 * REST proxy (optional) — CORS helper for approved hosts only, cached 12h.
 */
add_action('rest_api_init', function(){
    register_rest_route('mvwp/v1', '/proxy', [
        'methods'  => 'GET',
        'callback' => function($req){
            if (get_option('mvwp_enable_proxy', 'no') !== 'yes') {
                return new WP_Error('forbidden', __('Proxy disabled', 'molecule-viewer'), ['status'=>403]);
            }
            $url = esc_url_raw($req->get_param('url'));
            if (!$url || !preg_match('#^https?://#i', $url)) {
                return new WP_Error('bad_request', __('Invalid URL', 'molecule-viewer'), ['status'=>400]);
            }
            $host = wp_parse_url($url, PHP_URL_HOST);
            $allowed = array_filter(array_map('trim', explode("\n", (string) get_option('mvwp_allowed_hosts', ''))));
            if (!$host || !in_array($host, $allowed, true)) {
                return new WP_Error('forbidden', __('Host not allowed', 'molecule-viewer'), ['status'=>403]);
            }
            $cache_key = 'mvwp_proxy_' . md5($url);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new WP_REST_Response($cached, 200, ['Content-Type'=>'text/plain; charset=UTF-8']);
            }
            $resp = wp_remote_get($url, ['timeout'=>15]);
            if (is_wp_error($resp)) return new WP_Error('upstream_error', $resp->get_error_message(), ['status'=>502]);
            $code = wp_remote_retrieve_response_code($resp);
            if ($code !== 200) return new WP_Error('upstream_status', 'Upstream HTTP '.(int)$code, ['status'=>502]);
            $body = wp_remote_retrieve_body($resp);
            if ($body === '') return new WP_Error('empty', __('Empty upstream response', 'molecule-viewer'), ['status'=>502]);
            set_transient($cache_key, $body, 12 * HOUR_IN_SECONDS);
            return new WP_REST_Response($body, 200, ['Content-Type'=>'text/plain; charset=UTF-8']);
        },
        'permission_callback' => '__return_true',
        'args' => [ 'url' => ['required'=>true, 'type'=>'string'] ],
    ]);
});

/**
 * Admin: enqueue assets on product/settings screens
 */
add_action('admin_enqueue_scripts', function($hook){
    if (in_array($hook, ['post.php','post-new.php'], true)) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'product') {
            wp_enqueue_media();
            wp_enqueue_script('mvwp-admin', plugin_dir_url(__FILE__).'assets/mvwp-admin.js', ['jquery','wp-i18n'], MVWP_VERSION, true);
            wp_enqueue_style('mvwp-admin', plugin_dir_url(__FILE__).'assets/mvwp-admin.css', [], MVWP_VERSION);
            wp_set_script_translations('mvwp-admin', 'molecule-viewer');
        }
    }
    if ($hook === 'settings_page_'.MVWP_SLUG) {
        wp_enqueue_script('mvwp-admin', plugin_dir_url(__FILE__).'assets/mvwp-admin.js', ['jquery','wp-i18n'], MVWP_VERSION, true);
        wp_enqueue_style('mvwp-admin', plugin_dir_url(__FILE__).'assets/mvwp-admin.css', [], MVWP_VERSION);
        wp_set_script_translations('mvwp-admin', 'molecule-viewer');
    }
});

/**
 * Settings page (single-page layout)
 */
add_action('admin_menu', function () {
    add_options_page(
        __('Molecule Viewer', 'molecule-viewer'),
        __('Molecule Viewer', 'molecule-viewer'),
        'manage_options',
        MVWP_SLUG,
        'mvwp_settings_page'
    );
});

function mvwp_settings_page() {
    echo '<div id="mvwp-wrap" class="wrap">';
    echo '<h1>'.esc_html__('Molecule Viewer', 'molecule-viewer').'</h1>';

    echo '<form method="post" action="options.php">';
    settings_fields(MVWP_OPT_GRP);

    // Render ALL sections on one page (no tabs)
    do_settings_sections('mvwp_tab_general');
    do_settings_sections('mvwp_tab_scripts');
    do_settings_sections('mvwp_tab_proxy');
    do_settings_sections('mvwp_tab_integrations');

    echo '<p class="submit"><button type="submit" class="button button-primary">'
        .esc_html__('Save Changes','molecule-viewer').'</button></p>';
    echo '</form></div>';
}

/**
 * Register options + fields
 * Sanitizers are tolerant and map common slips to valid values.
 */
add_action('admin_init', function () {
    $yesno = ['type'=>'string','sanitize_callback'=>fn($v)=> $v==='yes'?'yes':'no'];

    // JSON style default: match ball-and-stick so JSON mode also looks consistent
    register_setting(MVWP_OPT_GRP, 'mvwp_default_style', [
        'type'=>'string',
        'sanitize_callback'=>function($v){
            $v = (string)$v;
            json_decode($v);
            if (json_last_error()) {
                return '{"stick":{"radius":0.2},"sphere":{"scale":0.3,"colorscheme":"spectrum"}}';
            }
            return $v;
        }
    ]);
    register_setting(MVWP_OPT_GRP, 'mvwp_ui',              $yesno);
    register_setting(MVWP_OPT_GRP, 'mvwp_nomouse',         $yesno);
    register_setting(MVWP_OPT_GRP, 'mvwp_show_download',   $yesno);
    register_setting(MVWP_OPT_GRP, 'mvwp_default_spin',    ['type'=>'string','sanitize_callback'=>'sanitize_text_field']);
    register_setting(MVWP_OPT_GRP, 'mvwp_attachment_only', $yesno);
    register_setting(MVWP_OPT_GRP, 'mvwp_max_size_mb',     ['type'=>'integer','sanitize_callback'=>'absint']);
    register_setting(MVWP_OPT_GRP, 'mvwp_script_core',     ['type'=>'string','sanitize_callback'=>'esc_url_raw']);
    register_setting(MVWP_OPT_GRP, 'mvwp_script_ui',       ['type'=>'string','sanitize_callback'=>'esc_url_raw']);
    register_setting(MVWP_OPT_GRP, 'mvwp_enable_proxy',    $yesno);
    register_setting(MVWP_OPT_GRP, 'mvwp_allowed_hosts',   [
        'type'=>'string',
        'sanitize_callback'=>function($v){
            $lines = array_filter(array_map('trim', explode("\n", (string)$v)));
            $hosts = [];
            foreach ($lines as $h) {
                $h=preg_replace('#^https?://#i','',$h);
                $h=preg_replace('#/.*$#','',$h);
                if($h!=='') $hosts[]=$h;
            }
            return implode("\n", array_unique($hosts));
        }
    ]);
    register_setting(MVWP_OPT_GRP, 'mvwp_style_mode', [
        'type'=>'string',
        'sanitize_callback'=>fn($v)=> in_array($v,['simple','json'],true)?$v:'simple'
    ]);
    register_setting(MVWP_OPT_GRP, 'mvwp_repr', [
        'type' => 'string',
        'sanitize_callback' => function($v){
            $v = is_string($v) ? strtolower(trim($v)) : 'cartoon';
            // common slips
            $syn = ['ballsandstick'=>'ballandstick','ballnstick'=>'ballandstick','ballsandsticks'=>'ballandstick'];
            if (isset($syn[$v])) $v = $syn[$v];
            $allowed = ['cartoon','stick','ballandstick','surface','line','sphere'];
            return in_array($v, $allowed, true) ? $v : 'cartoon';
        }
    ]);
    register_setting(MVWP_OPT_GRP, 'mvwp_color',            ['type'=>'string','sanitize_callback'=>fn($v)=> in_array($v,['spectrum','chain','element','residue','bfactor','white','grey','rainbow'],true)?$v:'spectrum']);
    register_setting(MVWP_OPT_GRP, 'mvwp_stick_radius',     ['type'=>'string','sanitize_callback'=>fn($v)=> is_numeric($v)? $v : '0.2']);
    register_setting(MVWP_OPT_GRP, 'mvwp_sphere_scale',     ['type'=>'string','sanitize_callback'=>fn($v)=> is_numeric($v)? $v : '0.3']);
    register_setting(MVWP_OPT_GRP, 'mvwp_surface_opacity',  ['type'=>'string','sanitize_callback'=>fn($v)=> is_numeric($v)? $v : '0.6']);

    // Sections
    add_settings_section('mvwp_general', __('Viewer Settings','molecule-viewer'), null, 'mvwp_tab_general');
    add_settings_section('mvwp_scripts', __('Script URLs','molecule-viewer'), null, 'mvwp_tab_scripts');
    add_settings_section('mvwp_proxy',   __('Proxy','molecule-viewer'),       null, 'mvwp_tab_proxy');
    add_settings_section('mvwp_integrations', __('Integrations','molecule-viewer'), null, 'mvwp_tab_integrations');

    // Fields: General
    add_settings_field('mvwp_default_style', __('Default Style (JSON)','molecule-viewer'), function(){
        $v = get_option('mvwp_default_style','{"stick":{"radius":0.2},"sphere":{"scale":0.3,"colorscheme":"spectrum"}}');
        echo '<textarea id="mvwp_default_style" name="mvwp_default_style" rows="5" cols="70">'.esc_textarea($v).'</textarea>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_ui', __('Show Toolbar UI','molecule-viewer'), function(){
        $v=get_option('mvwp_ui','yes');
        echo '<select id="mvwp_ui" name="mvwp_ui"><option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','molecule-viewer').'</option><option value="no"'.selected($v,'no',false).'>'.esc_html__('No','molecule-viewer').'</option></select>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_nomouse', __('Disable Mouse','molecule-viewer'), function(){
        $v=get_option('mvwp_nomouse','no');
        echo '<select id="mvwp_nomouse" name="mvwp_nomouse"><option value="no"'.selected($v,'no',false).'>'.esc_html__('No','molecule-viewer').'</option><option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','molecule-viewer').'</option></select>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_show_download', __('Show Download Link by default','molecule-viewer'), function(){
        $v=get_option('mvwp_show_download','yes');
        echo '<select id="mvwp_show_download" name="mvwp_show_download"><option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','molecule-viewer').'</option><option value="no"'.selected($v,'no',false).'>'.esc_html__('No','molecule-viewer').'</option></select>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_default_spin', __('Default Spin','molecule-viewer'), function(){
        $v=get_option('mvwp_default_spin','');
        echo '<input type="text" id="mvwp_default_spin" name="mvwp_default_spin" value="'.esc_attr($v).'" placeholder="true or y:0.6" />';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_style_mode', __('Style mode','molecule-viewer'), function(){
        $v = get_option('mvwp_style_mode','simple');
        echo '<select id="mvwp_style_mode" name="mvwp_style_mode">
                <option value="simple"'.selected($v,'simple',false).'>'.esc_html__('Simple controls','molecule-viewer').'</option>
                <option value="json"'.selected($v,'json',false).'>'.esc_html__('Raw JSON','molecule-viewer').'</option>
            </select>';
        echo '<p class="description">'.esc_html__('“Simple” shows dropdowns/inputs; “JSON” uses the raw style object below or per-shortcode.', 'molecule-viewer').'</p>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_repr', __('Default representation','molecule-viewer'), function(){
        $v = get_option('mvwp_repr','ballandstick');
        $opts = ['cartoon','stick','ballandstick','surface','line','sphere'];
        echo '<select id="mvwp_repr" name="mvwp_repr">';
        foreach($opts as $o){ echo '<option value="'.$o.'"'.selected($v,$o,false).'>'.esc_html(ucfirst($o)).'</option>'; }
        echo '</select>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_color', __('Default color scheme','molecule-viewer'), function(){
        $v = get_option('mvwp_color','spectrum');
        $opts = ['spectrum','chain','element','residue','bfactor','white','grey','rainbow'];
        echo '<select id="mvwp_color" name="mvwp_color">';
        foreach($opts as $o){ echo '<option value="'.$o.'"'.selected($v,$o,false).'>'.esc_html(ucfirst($o)).'</option>'; }
        echo '</select>';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_stick_radius', __('Stick radius','molecule-viewer'), function(){
        $v = get_option('mvwp_stick_radius','0.2');
        echo '<input type="number" step="0.01" min="0" id="mvwp_stick_radius" name="mvwp_stick_radius" value="'.esc_attr($v).'" />';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_sphere_scale', __('Sphere scale','molecule-viewer'), function(){
        $v = get_option('mvwp_sphere_scale','0.3');
        echo '<input type="number" step="0.01" min="0" id="mvwp_sphere_scale" name="mvwp_sphere_scale" value="'.esc_attr($v).'" />';
    }, 'mvwp_tab_general', 'mvwp_general');

    add_settings_field('mvwp_surface_opacity', __('Surface opacity','molecule-viewer'), function(){
        $v = get_option('mvwp_surface_opacity','0.6');
        echo '<input type="number" step="0.05" min="0" max="1" id="mvwp_surface_opacity" name="mvwp_surface_opacity" value="'.esc_attr($v).'" />';
    }, 'mvwp_tab_general', 'mvwp_general');

    // Scripts
    add_settings_field('mvwp_script_core', __('Core JS URL','molecule-viewer'), function(){
        $v=get_option('mvwp_script_core', MVWP_3DMOL_CORE_URL);
        echo '<input type="url" id="mvwp_script_core" name="mvwp_script_core" style="width:100%" value="'.esc_attr($v).'" />';
    }, 'mvwp_tab_scripts', 'mvwp_scripts');

    add_settings_field('mvwp_script_ui', __('UI JS URL','molecule-viewer'), function(){
        $v=get_option('mvwp_script_ui', MVWP_3DMOL_UI_URL);
        echo '<input type="url" id="mvwp_script_ui" name="mvwp_script_ui" style="width:100%" value="'.esc_attr($v).'" />';
    }, 'mvwp_tab_scripts', 'mvwp_scripts');

    // Proxy
    add_settings_field('mvwp_enable_proxy', __('Enable REST proxy','molecule-viewer'), function(){
        $v=get_option('mvwp_enable_proxy','no');
        echo '<select id="mvwp_enable_proxy" name="mvwp_enable_proxy"><option value="no"'.selected($v,'no',false).'>'.esc_html__('No','molecule-viewer').'</option><option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','molecule-viewer').'</option></select>';
    }, 'mvwp_tab_proxy', 'mvwp_proxy');

    add_settings_field('mvwp_allowed_hosts', __('Allowed proxy hosts (one per line)','molecule-viewer'), function(){
        $v=get_option('mvwp_allowed_hosts','');
        echo '<textarea id="mvwp_allowed_hosts" name="mvwp_allowed_hosts" rows="4" cols="70" placeholder="rcsb.org&#10;files.rcsb.org&#10;example.com">'.esc_textarea($v).'</textarea>';
    }, 'mvwp_tab_proxy', 'mvwp_proxy');
});

/**
 * Elementor widget (load if Elementor present)
 */
add_action('elementor/widgets/register', function($widgets_manager){
    if ( ! class_exists('\Elementor\Widget_Base') ) return;
    $file = __DIR__.'/includes/class-mvwp-elementor-widget.php';
    if ( file_exists($file) ) {
        require_once $file;
        if ( class_exists('\MVWP_Elementor_Widget') ) {
            $widgets_manager->register( new \MVWP_Elementor_Widget() );
        }
    }
});

/**
 * WooCommerce integration class + helper plugin
 */
require_once __DIR__ . '/includes/class-mvwp-woo.php';
require_once __DIR__ . '/remove-duplicate-featured-image.php';

/**
 * Boot Woo integration after Woo is loaded
 */
add_action('plugins_loaded', function () {
    if (class_exists('MVWP_Woo')) {
        MVWP_Woo::boot();
    }
});

/**
 * Shortcode auto-resolve on product pages when file_url is omitted
 */
add_filter('mvwp/shortcode/resolve_file_url', function($file_url){
    if ($file_url || !function_exists('is_product') || !class_exists('MVWP_Woo') || !MVWP_Woo::is_active() || !is_product()) return $file_url;
    global $post; if (empty($post)) return $file_url;
    $auto = MVWP_Woo::get_product_file_url($post->ID);
    return $auto ?: $file_url;
});
