<?php
/**
 * Plugin Name: WooCommerce PDB Viewer
 * Plugin URI:  https://linux.army
 * Description: Adds a PDB file viewer to WooCommerce products.
 * Version:     1.4.0
 * Author:      Erik Skogh
 * Author URI:  https://linux.army
 * License:     GPL2
 * Text Domain: woocommerce-pdb-viewer
 */

if (!defined('ABSPATH')) exit;

// -------------------------------------------------
// Constants
// -------------------------------------------------
define('WCPDBV_VERSION', '1.4.0');
define('WCPDBV_SLUG',    'woocommerce-pdb-viewer');
define('WCPDBV_OPT_GRP', 'woocommerce_pdb_viewer_settings');

// -------------------------------------------------
// Globals
// -------------------------------------------------
if (!isset($GLOBALS['wcpdbv_rendered_once'])) {
    $GLOBALS['wcpdbv_rendered_once'] = false;
}


// Default pinned URLs (allow override via settings + filters)
if (!defined('WCPDBV_3DMOL_CORE_URL')) {
    // You can pin a specific version by changing to e.g. @2.4.0
    define('WCPDBV_3DMOL_CORE_URL', 'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol-min.js');
}
if (!defined('WCPDBV_3DMOL_UI_URL')) {
    define('WCPDBV_3DMOL_UI_URL',   'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol.ui-min.js');
}

// -------------------------------------------------
// i18n
// -------------------------------------------------
add_action('init', function () {
    load_plugin_textdomain('woocommerce-pdb-viewer', false, dirname(plugin_basename(__FILE__)).'/languages');
});

// -------------------------------------------------
// Robustly allow PDB uploads
// -------------------------------------------------
add_filter('upload_mimes', function ($mimes) {
    $mimes['pdb'] = 'chemical/x-pdb';
    return $mimes;
});

add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes, $real_mime) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'pdb') {
        $ok = true;
        if (is_readable($file)) {
            $head = file_get_contents($file, false, null, 0, 8192);
            if ($head !== false && !preg_match('/\\b(HEADER|TITLE|COMPND|ATOM|HETATM|REMARK|CONECT)\\b/', $head)) {
                $ok = false; // looks nothing like a PDB text file
            }
        }
        if ($ok) {
            $data['ext']  = 'pdb';
            $data['type'] = 'chemical/x-pdb';
        }
    }
    return $data;
}, 10, 5);

// Optional: reject oversized PDBs (configurable)
add_filter('wp_handle_upload_prefilter', function ($file) {
    $limit_mb = (int) get_option('pdb_viewer_max_size_mb', 20); // default 20MB
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'pdb' && $limit_mb > 0 && !empty($file['size'])) {
        $limit_bytes = $limit_mb * 1024 * 1024;
        if ((int) $file['size'] > $limit_bytes) {
            $file['error'] = sprintf(
                /* translators: 1: file size limit in MB */
                esc_html__('PDB files over %d MB are not allowed.', 'woocommerce-pdb-viewer'),
                $limit_mb
            );
        }
    }
    return $file;
});

// -------------------------------------------------
// Admin product meta (attachment-first, optional URL when allowed)
// -------------------------------------------------
add_action('woocommerce_product_options_general_product_data', function () {
    global $post; if (!$post) return;

    $attachment_only  = get_option('pdb_viewer_attachment_only', 'no') === 'yes';
    $pdb_attachment_id = (int) get_post_meta($post->ID, '_pdb_attachment_id', true);
    $pdb_file_url      = get_post_meta($post->ID, '_pdb_file_url', true); // legacy/fallback

    echo '<div class="options_group">';
    wp_nonce_field('wcpdbv_save_meta', 'wcpdbv_nonce');

    woocommerce_wp_text_input([
        'id'                => '_pdb_attachment_id',
        'label'             => __('PDB Attachment ID', 'woocommerce-pdb-viewer'),
        'type'              => 'number',
        'value'             => $pdb_attachment_id ?: '',
        'desc_tip'          => true,
        'description'       => __('Stores the selected PDB file as an attachment ID. Prefer this over raw URLs.', 'woocommerce-pdb-viewer'),
        'custom_attributes' => ['readonly' => 'readonly'],
    ]);

    $attached_url = $pdb_attachment_id ? wp_get_attachment_url($pdb_attachment_id) : '';
    woocommerce_wp_text_input([
        'id'                => '_pdb_attachment_url_display',
        'label'             => __('Selected PDB URL', 'woocommerce-pdb-viewer'),
        'type'              => 'url',
        'value'             => $attached_url ?: '',
        'custom_attributes' => ['readonly' => 'readonly'],
    ]);

    if (!$attachment_only) {
        woocommerce_wp_text_input([
            'id'          => '_pdb_file_url',
            'label'       => __('(Optional) PDB File URL', 'woocommerce-pdb-viewer'),
            'type'        => 'url',
            'value'       => $pdb_file_url ?: '',
            'desc_tip'    => true,
            'description' => __('If you prefer a remote URL. Note: remote hosts must allow CORS or enable the REST proxy below.', 'woocommerce-pdb-viewer'),
        ]);
    }
    ?>
    <p class="form-field">
        <label><?php esc_html_e('Upload/Select PDB File', 'woocommerce-pdb-viewer'); ?></label>
        <button type="button" class="button wcpdbv-upload"><?php esc_html_e('Choose PDB', 'woocommerce-pdb-viewer'); ?></button>
        <button type="button" class="button wcpdbv-clear"><?php esc_html_e('Clear', 'woocommerce-pdb-viewer'); ?></button>
    </p>
    <?php
    echo '</div>';
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    $screen = get_current_screen();
    if (!$screen || 'product' !== $screen->id) return;

    wp_enqueue_media();

    wp_enqueue_script(
        'wcpdbv-admin',
        plugin_dir_url(__FILE__) . 'assets/wcpdbv-admin.js',
        ['jquery'],
        WCPDBV_VERSION,
        true
    );

    wp_localize_script('wcpdbv-admin', 'WCPDBV', [
        'title'       => __('Select a PDB file', 'woocommerce-pdb-viewer'),
        'button'      => __('Use this file', 'woocommerce-pdb-viewer'),
        'invalidType' => __('Please select a .pdb file.', 'woocommerce-pdb-viewer'),
    ]);
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    if (!isset($_POST['wcpdbv_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['wcpdbv_nonce']), 'wcpdbv_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $attachment_only = get_option('pdb_viewer_attachment_only', 'no') === 'yes';

    $att_id = isset($_POST['_pdb_attachment_id']) ? (int) $_POST['_pdb_attachment_id'] : 0;
    if ($att_id > 0) {
        update_post_meta($post_id, '_pdb_attachment_id', $att_id);
    } else {
        delete_post_meta($post_id, '_pdb_attachment_id');
    }

    if (!$attachment_only && isset($_POST['_pdb_file_url']) && $_POST['_pdb_file_url'] !== '') {
        update_post_meta($post_id, '_pdb_file_url', esc_url_raw($_POST['_pdb_file_url']));
    } else {
        delete_post_meta($post_id, '_pdb_file_url');
    }
});

// -------------------------------------------------
// Variation support (per-variation PDB)
// -------------------------------------------------
add_action('woocommerce_product_after_variable_attributes', function ($loop, $variation_data, $variation) {
    $att_id = (int) get_post_meta($variation->ID, '_pdb_attachment_id', true);
    $url    = $att_id ? wp_get_attachment_url($att_id) : '';
    ?>
    <div class="form-row form-row-full">
        <label><?php echo esc_html__('PDB (Attachment)', 'woocommerce-pdb-viewer'); ?></label>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="number" class="short" style="width:140px" name="_pdb_attachment_id[<?php echo (int)$variation->ID; ?>]" value="<?php echo esc_attr($att_id ?: ''); ?>" readonly />
            <input type="url" class="short" style="flex:1" value="<?php echo esc_attr($url); ?>" readonly />
            <button class="button wcpdbv-upload-var" data-varid="<?php echo (int)$variation->ID; ?>"><?php esc_html_e('Choose PDB', 'woocommerce-pdb-viewer'); ?></button>
            <button class="button wcpdbv-clear-var"  data-varid="<?php echo (int)$variation->ID; ?>"><?php esc_html_e('Clear', 'woocommerce-pdb-viewer'); ?></button>
        </div>
    </div>
    <?php
}, 10, 3);

add_action('woocommerce_save_product_variation', function ($variation_id, $i) {
    if (!current_user_can('edit_post', $variation_id)) return;
    $var_ids = isset($_POST['_pdb_attachment_id']) && is_array($_POST['_pdb_attachment_id']) ? $_POST['_pdb_attachment_id'] : [];
    if (isset($var_ids[$variation_id])) {
        $att_id = (int) $var_ids[$variation_id];
        if ($att_id > 0) update_post_meta($variation_id, '_pdb_attachment_id', $att_id);
        else delete_post_meta($variation_id, '_pdb_attachment_id');
    }
}, 10, 2);

// -------------------------------------------------
// Front-end viewer (shortcode + product tab + dynamic loading)
// -------------------------------------------------
add_shortcode('pdb_viewer', function ($atts) {
    global $post;

    $a = shortcode_atts([
        'height'     => '400px',
        'width'      => '100%',
        'style'      => get_option('pdb_viewer_default_style', '{"stick":{"radius":0.2},"sphere":{"scale":0.3}}'),
        'ui'         => get_option('pdb_viewer_ui', 'yes') === 'yes' ? 'true' : 'false',
        'nomouse'    => get_option('pdb_viewer_nomouse', 'no') === 'yes' ? 'true' : 'false',
        'bgalpha'    => '0.0',
        'type'       => 'pdb',
        'spin'       => '',          // per-instance override
        'download'   => '',          // yes|no override; if empty, fall back to global
        'pdb_id'     => '',          // NEW
        'product_id' => '',          // NEW
        'file_url'   => '',          // NEW explicit URL (highest priority)
    ], $atts, 'pdb_viewer');

    $default_spin = get_option('pdb_viewer_default_spin', '');
    if ($a['spin'] === '' && $default_spin !== '') {
        $a['spin'] = $default_spin;
    }
    if ($a['download'] === '') {
        $a['download'] = get_option('pdb_viewer_show_download', 'yes');
    }

    // Resolve source URL priority: file_url > product_id > current product > pdb_id resolver
    $source = '';
    if ($a['file_url'] !== '') {
        $source = wcpdbv_maybe_proxy_url( esc_url_raw($a['file_url']) );
    } elseif ($a['product_id'] !== '') {
        $source = wcpdbv_get_current_product_pdb_url( absint($a['product_id']) );
    } elseif (is_singular('product') && !empty($post)) {
        $source = wcpdbv_get_current_product_pdb_url( $post->ID );
    } elseif ($a['pdb_id'] !== '') {
        // Simple resolver example (customize for your storage/cdn):
        // $source = wcpdbv_maybe_proxy_url( home_url('/wp-content/pdb/'.rawurlencode($a['pdb_id']).'.pdb') );
        // For now: bail with message
        return '<p>'.esc_html__('No resolver for pdb_id; provide file_url or product_id.', 'woocommerce-pdb-viewer').'</p>';
    }

    if (!$source) {
        return '<p>'.esc_html__('No PDB file available.', 'woocommerce-pdb-viewer').'</p>';
    }

    // Enqueue frontend scripts
    wcpdbv_enqueue_frontend_scripts();

    $id         = 'pdbv_' . wp_generate_uuid4();
    $aria_label = sprintf(esc_attr__('3D molecular structure for %s', 'woocommerce-pdb-viewer'), get_bloginfo('name'));

    ob_start(); ?>
    <div id="<?php echo esc_attr($id); ?>"
         class="wcpdbv-viewer"
         role="img"
         aria-label="<?php echo esc_attr($aria_label); ?>"
         style="height: <?php echo esc_attr($a['height']); ?>; width: <?php echo esc_attr($a['width']); ?>; position: relative; margin: 20px 0;"
         data-url="<?php echo esc_url($source); ?>"
         data-type="<?php echo esc_attr($a['type']); ?>"
         data-bgalpha="<?php echo esc_attr($a['bgalpha']); ?>"
         data-style='<?php echo esc_attr($a['style']); ?>'
         data-ui="<?php echo esc_attr($a['ui']); ?>"
         data-nomouse="<?php echo esc_attr($a['nomouse']); ?>"
         data-spin="<?php echo esc_attr($a['spin']); ?>"
    ></div>
    <?php if ($a['download'] === 'yes') : ?>
        <p><a class="button" href="<?php echo esc_url($source); ?>" download><?php echo esc_html__('Download PDB', 'woocommerce-pdb-viewer'); ?></a></p>
    <?php endif; ?>
    <?php
    $GLOBALS['wcpdbv_rendered_once'] = true;
    return ob_get_clean();
});

// Add product tab
add_filter('woocommerce_product_tabs', function ($tabs) {
    if (!is_product()) return $tabs;
    global $post; if (!$post) return $tabs;
    if (wcpdbv_get_current_product_pdb_url($post->ID)) {
        $tabs['pdb_viewer'] = [
            'title'    => __('Structure', 'woocommerce-pdb-viewer'),
            'priority' => 25,
            'callback' => function () {
                if (!empty($GLOBALS['wcpdbv_rendered_once'])) return;
                $GLOBALS['wcpdbv_rendered_once'] = true;
                echo do_shortcode('[pdb_viewer]');
            }
        ];
    }
    return $tabs;
});

// Helper: choose best source (variation, then product-level; apply proxy if enabled)
function wcpdbv_get_current_product_pdb_url($product_id) {
    // Variation context
    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $att_id = (int) get_post_meta($product_id, '_pdb_attachment_id', true);
            if ($att_id) return wcpdbv_prepare_pdb_url_from_attachment($att_id);
        }
        // For variable product: no active variation server-side; fall back to parent
        if ($product && $product->is_type('variable')) {
            $att_id = (int) get_post_meta($product_id, '_pdb_attachment_id', true);
            if ($att_id) return wcpdbv_prepare_pdb_url_from_attachment($att_id);
        }
    }

    // Product-level
    $att_id = (int) get_post_meta($product_id, '_pdb_attachment_id', true);
    if ($att_id) return wcpdbv_prepare_pdb_url_from_attachment($att_id);

    $attachment_only = get_option('pdb_viewer_attachment_only', 'no') === 'yes';
    if ($attachment_only) return '';

    $url = get_post_meta($product_id, '_pdb_file_url', true);
    if (!$url) return '';

    return wcpdbv_maybe_proxy_url($url);
}

function wcpdbv_prepare_pdb_url_from_attachment($att_id) {
    $url = wp_get_attachment_url($att_id);
    return $url ? esc_url_raw($url) : '';
}

// Variation map for front-end switching
add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;
    global $post; if (!$post) return;

    $product = function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
    if (!$product || !$product->is_type('variable')) return;

    $map = [];
    foreach ($product->get_children() as $vid) {
        $att_id = (int) get_post_meta($vid, '_pdb_attachment_id', true);
        if ($att_id) {
            $map[(string) $vid] = wcpdbv_prepare_pdb_url_from_attachment($att_id);
        }
    }

    if ($map) {
        wp_register_script('wcpdbv-varmap', plugin_dir_url(__FILE__) . 'assets/wcpdbv-frontend.js', ['jquery'], WCPDBV_VERSION, true);
        wp_localize_script('wcpdbv-varmap', 'WCPDBV_VAR', [ 'variationMap' => $map ]);
        wp_enqueue_script('wcpdbv-varmap');
    }
});

// -------------------------------------------------
// Frontend scripts: enqueue 3Dmol + our loader
// -------------------------------------------------
function wcpdbv_enqueue_frontend_scripts() {
    $core = get_option('pdb_viewer_script_core', WCPDBV_3DMOL_CORE_URL);
    $ui   = get_option('pdb_viewer_script_ui',   WCPDBV_3DMOL_UI_URL);

    // Allow devs to filter URLs in code
    $urls = apply_filters('wcpdbv_3dmol_urls', ['core' => $core, 'ui' => $ui]);

    wp_register_script('3dmol-js', $urls['core'], [], null, true);
    wp_register_script('3dmol-ui', $urls['ui'],   ['3dmol-js'], null, true);

    wp_register_script(
        'wcpdbv-frontend',
        plugin_dir_url(__FILE__) . 'assets/wcpdbv-frontend.js',
        ['3dmol-ui'],
        WCPDBV_VERSION,
        true
    );

    // Settings to JS
    wp_localize_script('wcpdbv-frontend', 'WCPDBV_CFG', [
        'deferInit' => true,
    ]);

    wp_enqueue_script('wcpdbv-frontend');
}


// -------------------------------------------------
// Replace product image
// -------------------------------------------------
add_action('show_user_profile', 'wcpdbv_user_pdb_field');
add_action('edit_user_profile', 'wcpdbv_user_pdb_field');
function wcpdbv_user_pdb_field($user){
    $key = get_option('pdb_viewer_avatar_meta_key','wcpdbv_avatar_url');
    $val = get_user_meta($user->ID, $key, true);
    ?>
    <h2><?php esc_html_e('PDB Avatar', 'woocommerce-pdb-viewer'); ?></h2>
    <table class="form-table">
      <tr>
        <th><label for="wcpdbv_avatar_url"><?php esc_html_e('PDB file URL', 'woocommerce-pdb-viewer'); ?></label></th>
        <td>
            <input type="url" name="wcpdbv_avatar_url" id="wcpdbv_avatar_url" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="https://…/avatar.pdb" />
            <p class="description"><?php esc_html_e('If set and feature enabled, this PDB will render as the user’s avatar.', 'woocommerce-pdb-viewer'); ?></p>
        </td>
      </tr>
    </table>
    <?php
}
add_action('personal_options_update', 'wcpdbv_save_user_pdb_field');
add_action('edit_user_profile_update', 'wcpdbv_save_user_pdb_field');
function wcpdbv_save_user_pdb_field($user_id){
    if (!current_user_can('edit_user', $user_id)) return;
    $key = get_option('pdb_viewer_avatar_meta_key','wcpdbv_avatar_url');
    if (isset($_POST['wcpdbv_avatar_url'])) {
        update_user_meta($user_id, $key, esc_url_raw($_POST['wcpdbv_avatar_url']));
    }
}

add_filter('get_avatar', function($avatar, $id_or_email, $size, $default, $alt, $args){
    if (get_option('pdb_viewer_replace_avatar','no') !== 'yes') return $avatar;

    $user = false;
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int)$id_or_email);
    } elseif (is_object($id_or_email) && ! empty($id_or_email->user_id)) {
        $user = get_user_by('id', (int)$id_or_email->user_id);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    }
    if (!$user) return $avatar;

    $key = get_option('pdb_viewer_avatar_meta_key','wcpdbv_avatar_url');
    $url = get_user_meta($user->ID, $key, true);
    if (!$url) return $avatar;

    // Render a tiny viewer square
    $px = is_array($args) && !empty($args['height']) ? (int)$args['height'] : (int)$size;
    $spin = get_option('pdb_viewer_default_spin','');
    $short = sprintf(
        '[pdb_viewer file_url="%s" height="%dpx" width="%dpx" ui="false" nomouse="true" download="no" spin="%s" ]',
        esc_url($url), $px, $px, esc_attr($spin)
    );
    return do_shortcode($short);
}, 10, 6);

// -------------------------------------------------
// Settings page
// -------------------------------------------------
add_action('admin_menu', function () {
    add_options_page(
        __('WooCommerce PDB Viewer Settings', 'woocommerce-pdb-viewer'),
        __('PDB Viewer', 'woocommerce-pdb-viewer'),
        'manage_options',
        WCPDBV_SLUG,
        'wcpdbv_settings_page'
    );
});

function wcpdbv_settings_page() { ?>
    <div class="wrap">
        <h1><?php esc_html_e('WooCommerce PDB Viewer Settings', 'woocommerce-pdb-viewer'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields(WCPDBV_OPT_GRP);
            do_settings_sections(WCPDBV_SLUG);
            submit_button();
            ?>
        </form>
    </div>
<?php }

add_action('admin_init', function () {
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_show_download',   ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_default_spin',    ['type' => 'string',  'sanitize_callback' => 'sanitize_text_field']); // e.g. 'true' or 'y:0.6'
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_replace_avatar',  ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_avatar_meta_key', ['type' => 'string',  'sanitize_callback' => 'sanitize_key']); // user_meta key for file URL
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_default_style',   ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_style_json']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_ui',              ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_nomouse',         ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_attachment_only', ['type' => 'string',  'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_max_size_mb',     ['type' => 'integer', 'sanitize_callback' => 'absint']);

    // Script URL overrides (let admins pin versions)
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_script_core', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_script_ui',   ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);

    // Proxy settings
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_enable_proxy',   ['type' => 'string', 'sanitize_callback' => 'wcpdbv_sanitize_yesno']);
    register_setting(WCPDBV_OPT_GRP, 'pdb_viewer_allowed_hosts',  ['type' => 'string', 'sanitize_callback' => 'wcpdbv_sanitize_hostlist']);

    add_settings_section('wcpdbv_main', __('Viewer Settings', 'woocommerce-pdb-viewer'), null, WCPDBV_SLUG);

    add_settings_field('pdb_viewer_default_style', __('Default Viewer Style (JSON)', 'woocommerce-pdb-viewer'), function () {
        $value = get_option('pdb_viewer_default_style', '{"stick":{"radius":0.2},"sphere":{"scale":0.3}}');
        echo '<textarea name="pdb_viewer_default_style" rows="5" cols="70">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Set the default 3Dmol.js style JSON. Used if the shortcode/block does not provide one.', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_field('pdb_viewer_ui', __('Show Toolbar UI', 'woocommerce-pdb-viewer'), function () {
        $value = get_option('pdb_viewer_ui', 'yes');
        echo '<select name="pdb_viewer_ui">';
        echo '<option value="yes"' . selected($value, 'yes', false) . '>' . esc_html__('Yes', 'woocommerce-pdb-viewer') . '</option>';
        echo '<option value="no"'  . selected($value, 'no',  false) . '>' . esc_html__('No', 'woocommerce-pdb-viewer') . '</option>';
        echo '</select>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_field('pdb_viewer_nomouse', __('Disable Mouse Interactions', 'woocommerce-pdb-viewer'), function () {
        $value = get_option('pdb_viewer_nomouse', 'no');
        echo '<select name="pdb_viewer_nomouse">';
        echo '<option value="no"'  . selected($value, 'no',  false) . '>' . esc_html__('No', 'woocommerce-pdb-viewer') . '</option>';
        echo '<option value="yes"' . selected($value, 'yes', false) . '>' . esc_html__('Yes', 'woocommerce-pdb-viewer') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('When enabled, disables zoom/rotate/pan.', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_field('pdb_viewer_attachment_only', __('Attachment-only mode', 'woocommerce-pdb-viewer'), function () {
        $value = get_option('pdb_viewer_attachment_only', 'no');
        echo '<select name="pdb_viewer_attachment_only">';
        echo '<option value="no"'  . selected($value, 'no',  false) . '>' . esc_html__('No', 'woocommerce-pdb-viewer') . '</option>';
        echo '<option value="yes"' . selected($value, 'yes', false) . '>' . esc_html__('Yes', 'woocommerce-pdb-viewer') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Disallow remote URLs; require uploads to Media Library.', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_field('pdb_viewer_max_size_mb', __('Max PDB size (MB)', 'woocommerce-pdb-viewer'), function () {
        $value = (int) get_option('pdb_viewer_max_size_mb', 20);
        echo '<input type="number" min="0" step="1" name="pdb_viewer_max_size_mb" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('0 = unlimited (not recommended).', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_section('wcpdbv_scripts', __('3Dmol Script URLs', 'woocommerce-pdb-viewer'), function(){
        echo '<p class="description">' . esc_html__('Pin to a specific version via jsDelivr if desired, e.g., https://cdn.jsdelivr.net/npm/3dmol@2.4.0/build/3Dmol-min.js', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG);

    add_settings_field('pdb_viewer_script_core', __('Core JS URL', 'woocommerce-pdb-viewer'), function(){
        $value = get_option('pdb_viewer_script_core', WCPDBV_3DMOL_CORE_URL);
        echo '<input type="url" name="pdb_viewer_script_core" style="width:100%" value="' . esc_attr($value) . '" />';
    }, WCPDBV_SLUG, 'wcpdbv_scripts');

    add_settings_field('pdb_viewer_script_ui', __('UI JS URL', 'woocommerce-pdb-viewer'), function(){
        $value = get_option('pdb_viewer_script_ui', WCPDBV_3DMOL_UI_URL);
        echo '<input type="url" name="pdb_viewer_script_ui" style="width:100%" value="' . esc_attr($value) . '" />';
    }, WCPDBV_SLUG, 'wcpdbv_scripts');

    add_settings_section('wcpdbv_proxy', __('Remote URL Proxy (Advanced)', 'woocommerce-pdb-viewer'), function(){
        echo '<p class="description">' . esc_html__('Enable only if you must load remote PDBs without CORS. Restrict allowed hosts below.', 'woocommerce-pdb-viewer') . '</p>';
    }, WCPDBV_SLUG);

    add_settings_field('pdb_viewer_enable_proxy', __('Enable REST proxy', 'woocommerce-pdb-viewer'), function(){
        $value = get_option('pdb_viewer_enable_proxy', 'no');
        echo '<select name="pdb_viewer_enable_proxy">';
        echo '<option value="no"'  . selected($value, 'no',  false) . '>' . esc_html__('No', 'woocommerce-pdb-viewer') . '</option>';
        echo '<option value="yes"' . selected($value, 'yes', false) . '>' . esc_html__('Yes', 'woocommerce-pdb-viewer') . '</option>';
        echo '</select>';
    }, WCPDBV_SLUG, 'wcpdbv_proxy');

    add_settings_field('pdb_viewer_allowed_hosts', __('Allowed proxy hosts (one per line)', 'woocommerce-pdb-viewer'), function(){
        $value = get_option('pdb_viewer_allowed_hosts', "");
        echo '<textarea name="pdb_viewer_allowed_hosts" rows="4" cols="70" placeholder="rcsb.org\nfiles.rcsb.org\nexample.com">' . esc_textarea($value) . '</textarea>';
    }, WCPDBV_SLUG, 'wcpdbv_proxy');

    // Download toggle
    add_settings_field('pdb_viewer_show_download', __('Show download link by default', 'woocommerce-pdb-viewer'), function(){
        $v = get_option('pdb_viewer_show_download', 'yes');
        echo '<select name="pdb_viewer_show_download">';
        echo '<option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','woocommerce-pdb-viewer').'</option>';
        echo '<option value="no" '.selected($v,'no', false).'>'.esc_html__('No','woocommerce-pdb-viewer').'</option>';
        echo '</select>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    // Default spin
    add_settings_field('pdb_viewer_default_spin', __('Default spin', 'woocommerce-pdb-viewer'), function(){
        $v = get_option('pdb_viewer_default_spin', '');
        echo '<input type="text" name="pdb_viewer_default_spin" value="'.esc_attr($v).'" placeholder="e.g. true or y:0.6" />';
        echo '<p class="description">'.esc_html__('Use "true" to spin on Y axis at speed 1, or "axis:speed" like "y:0.6". Shortcode can override.', 'woocommerce-pdb-viewer').'</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    // Replace avatar
    add_settings_field('pdb_viewer_replace_avatar', __('Replace user avatars with PDB', 'woocommerce-pdb-viewer'), function(){
        $v = get_option('pdb_viewer_replace_avatar', 'no');
        echo '<select name="pdb_viewer_replace_avatar">';
        echo '<option value="no" '.selected($v,'no',false).'>'.esc_html__('No','woocommerce-pdb-viewer').'</option>';
        echo '<option value="yes"'.selected($v,'yes',false).'>'.esc_html__('Yes','woocommerce-pdb-viewer').'</option>';
        echo '</select>';
        echo '<p class="description">'.esc_html__('When enabled, get_avatar() will render a mini PDB viewer for users who have a PDB set in their profile.', 'woocommerce-pdb-viewer').'</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

    add_settings_field('pdb_viewer_avatar_meta_key', __('User meta key for avatar PDB URL', 'woocommerce-pdb-viewer'), function(){
        $v = get_option('pdb_viewer_avatar_meta_key', 'wcpdbv_avatar_url');
        echo '<input type="text" name="pdb_viewer_avatar_meta_key" value="'.esc_attr($v).'" />';
        echo '<p class="description">'.esc_html__('Simple approach: store a direct PDB URL in this user_meta key. (Below we also add a media-uploader field to user profiles.)', 'woocommerce-pdb-viewer').'</p>';
    }, WCPDBV_SLUG, 'wcpdbv_main');

});

// Sanitizers
function wcpdbv_validate_json($json) { json_decode($json); return (json_last_error() === JSON_ERROR_NONE); }
function wcpdbv_sanitize_style_json($value) { $value = (string)$value; return wcpdbv_validate_json($value) ? $value : '{"stick":{"radius":0.2},"sphere":{"scale":0.3}}'; }
function wcpdbv_sanitize_yesno($v) { return $v === 'yes' ? 'yes' : 'no'; }
function wcpdbv_sanitize_hostlist($value) {
    $lines = array_filter(array_map('trim', explode("\n", (string)$value)));
    // Keep only bare hosts
    $hosts = [];
    foreach ($lines as $h) {
        $h = preg_replace('#^https?://#i', '', $h);
        $h = preg_replace('#/.*$#', '', $h);
        if ($h !== '') $hosts[] = $h;
    }
    return implode("\n", array_unique($hosts));
}

// -------------------------------------------------
// REST proxy (optional, restricted to allowed hosts)
// -------------------------------------------------
add_action('rest_api_init', function(){
    register_rest_route('wcpdbv/v1', '/proxy', [
        'methods'  => 'GET',
        'callback' => 'wcpdbv_rest_proxy',
        'permission_callback' => '__return_true', // public, but locked by host allowlist
        'args' => [ 'url' => ['required' => true, 'type' => 'string'] ],
    ]);
});

function wcpdbv_rest_proxy($req) {
    if (get_option('pdb_viewer_enable_proxy', 'no') !== 'yes') {
        return new WP_Error('forbidden', __('Proxy disabled', 'woocommerce-pdb-viewer'), ['status' => 403]);
    }
    $url = esc_url_raw($req->get_param('url'));
    if (!$url || !preg_match('#^https?://#i', $url)) {
        return new WP_Error('bad_request', __('Invalid URL', 'woocommerce-pdb-viewer'), ['status' => 400]);
    }
    $host = wp_parse_url($url, PHP_URL_HOST);
    $allowed = array_filter(array_map('trim', explode("\n", (string) get_option('pdb_viewer_allowed_hosts', ''))));
    if (!$host || !in_array($host, $allowed, true)) {
        return new WP_Error('forbidden', __('Host not allowed', 'woocommerce-pdb-viewer'), ['status' => 403]);
    }

    // Cache short to reduce load
    $key = 'wcpdbv_proxy_' . md5($url);
    $cached = get_transient($key);
    if ($cached !== false) {
        return new WP_REST_Response($cached, 200, ['Content-Type' => 'chemical/x-pdb; charset=UTF-8']);
    }

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) return new WP_Error('upstream_error', $resp->get_error_message(), ['status' => 502]);
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) return new WP_Error('upstream_status', 'Upstream HTTP ' . (int)$code, ['status' => 502]);

    $body = wp_remote_retrieve_body($resp);
    if ($body === '') return new WP_Error('empty', __('Empty upstream response', 'woocommerce-pdb-viewer'), ['status' => 502]);

    set_transient($key, $body, HOUR_IN_SECONDS * 12);
    return new WP_REST_Response($body, 200, ['Content-Type' => 'chemical/x-pdb; charset=UTF-8']);
}

// Convert remote URL to proxy URL when enabled
function wcpdbv_maybe_proxy_url($url) {
    if (get_option('pdb_viewer_enable_proxy', 'no') !== 'yes') return $url;
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return $url;
    $allowed = array_filter(array_map('trim', explode("\n", (string) get_option('pdb_viewer_allowed_hosts', ''))));
    if (!in_array($host, $allowed, true)) return $url;
    return rest_url('wcpdbv/v1/proxy?url=' . rawurlencode($url));
}

// -------------------------------------------------
// Block editor block (simple client block -> server render)
// -------------------------------------------------
add_action('init', function () {
    // Editor script
    wp_register_script(
        'wcpdbv-block',
        plugin_dir_url(__FILE__) . 'assets/block.js',
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-i18n'],
        WCPDBV_VERSION,
        true
    );

    register_block_type('wcpdbv/viewer', [
        'editor_script'   => 'wcpdbv-block',
        'render_callback' => function ($attributes, $content) {
            // Map attributes to shortcode params
            $atts = [];
            if (!empty($attributes['height']))  $atts['height']  = $attributes['height'];
            if (!empty($attributes['width']))   $atts['width']   = $attributes['width'];
            if (isset($attributes['ui']))       $atts['ui']      = $attributes['ui'] ? 'true' : 'false';
            if (isset($attributes['nomouse']))  $atts['nomouse'] = $attributes['nomouse'] ? 'true' : 'false';
            if (!empty($attributes['style']))   $atts['style']   = $attributes['style'];
            if (!empty($attributes['spin']))    $atts['spin']    = $attributes['spin'];
            return do_shortcode('[pdb_viewer ' . http_build_query($atts, '', ' ') . ']');
        },
        'attributes' => [
            'height'  => ['type' => 'string', 'default' => '400px'],
            'width'   => ['type' => 'string', 'default' => '100%'],
            'ui'      => ['type' => 'boolean', 'default' => true],
            'nomouse' => ['type' => 'boolean', 'default' => false],
            'style'   => ['type' => 'string',  'default' => ''],
            'spin'    => ['type' => 'string',  'default' => ''],
        ],
    ]);
});

// -------------------------------------------------
// Uninstall cleanup (named function only)
// -------------------------------------------------
function wcpdbv_uninstall() {
    delete_option('pdb_viewer_default_style');
    delete_option('pdb_viewer_ui');
    delete_option('pdb_viewer_nomouse');
    delete_option('pdb_viewer_attachment_only');
    delete_option('pdb_viewer_max_size_mb');
    delete_option('pdb_viewer_script_core');
    delete_option('pdb_viewer_script_ui');
    delete_option('pdb_viewer_enable_proxy');
    delete_option('pdb_viewer_allowed_hosts');
}
register_uninstall_hook(__FILE__, 'wcpdbv_uninstall');

// -------------------------------------------------
// Admin & Frontend helper JS inline fallbacks (if files missing)
// -------------------------------------------------
add_action('admin_print_footer_scripts', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || ($screen->id !== 'product' && $screen->id !== 'edit-product')) return;
    ?>
    <script>
    (function($){
        $(document).on('click', '.wcpdbv-upload, .wcpdbv-upload-var', function(e){
            e.preventDefault();
            var $btn = $(this);
            var varid = $btn.data('varid');
            var frame = wp.media({ title: WCPDBV.title, button: { text: WCPDBV.button }, multiple:false, library:{ type:'application' } });
            frame.on('select', function(){
                var a = frame.state().get('selection').first().toJSON();
                var isPdb = /\.pdb(\?.*)?$/i.test(a.url) || (a.subtype && String(a.subtype).toLowerCase().indexOf('pdb')>=0);
                if (!isPdb) { alert(WCPDBV.invalidType); return; }
                if (varid) {
                    $('input[name="_pdb_attachment_id['+varid+']"]').val(a.id);
                    $('button.wcpdbv-upload-var[data-varid="'+varid+'"]').prev('input[type=url]').val(a.url);
                } else {
                    $('#_pdb_attachment_id').val(a.id);
                    $('#_pdb_attachment_url_display').val(a.url);
                }
            });
            frame.open();
        });
        $(document).on('click', '.wcpdbv-clear, .wcpdbv-clear-var', function(e){
            e.preventDefault();
            var varid = $(this).data('varid');
            if (varid) {
                $('input[name="_pdb_attachment_id['+varid+']"]').val('');
                $('button.wcpdbv-upload-var[data-varid="'+varid+'"]').prev('input[type=url]').val('');
            } else {
                $('#_pdb_attachment_id').val('');
                $('#_pdb_attachment_url_display').val('');
            }
        });
    })(jQuery);
    </script>
    <?php
});

add_action('wp_print_footer_scripts', function(){
    // Minimal fallback: if assets/wcpdbv-frontend.js didn't load for some reason, bootstrap a basic viewer
    ?>
    <script>
    (function(){
        if (!window.$3Dmol) return;
        var els = document.querySelectorAll('.wcpdbv-viewer');
        for (var i=0;i<els.length;i++){
            var el = els[i];
            if (el.getAttribute('data-wcpdbv-init')) continue;
            el.setAttribute('data-wcpdbv-init','1');
            try {
                var url = el.getAttribute('data-url');
                var typ = el.getAttribute('data-type') || 'pdb';
                var ui  = el.getAttribute('data-ui') === 'true';
                var nm  = el.getAttribute('data-nomouse') === 'true';
                var bga = parseFloat(el.getAttribute('data-bgalpha')||'0');
                var sty = el.getAttribute('data-style');
                var spin= el.getAttribute('data-spin');
                var style = {};
                try { style = JSON.parse(sty||'{}'); } catch(e){}
                var opts = { backgroundAlpha:bga, disableMouse: nm };
                var viewer = $3Dmol.createViewer(el, opts);
                fetch(url).then(r=>r.text()).then(function(data){
                    viewer.addModel(data, typ);
                    viewer.setStyle({}, style||{stick:{}});
                    viewer.zoomTo(); viewer.render();
                    if (spin) {
                        var axis = 'y', speed = 1;
                        if (spin !== 'true') {
                            var parts = spin.split(':');
                            axis = (parts[0] || 'y').toLowerCase();
                            speed = parseFloat(parts[1] || '1');
                            if (isNaN(speed)) speed = 1;
                        }
                        if (viewer.spin) { try { viewer.spin(axis, speed); } catch(e){} }
                    }
                });
                if (ui && $3Dmol.UI){ new $3Dmol.UI(viewer); }
            } catch(e){}
        }
    })();
    </script>
    <?php
});

// Elementor integration (safe, only after Elementor is ready)
add_action('elementor/widgets/register', function( $widgets_manager ) {
    // If Elementor isn't active, do nothing
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    // Include only now, when the Elementor classes definitely exist
    $file = __DIR__ . '/includes/class-wcpdbv-elementor-widget.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        if ( class_exists( '\WCPDBV_Elementor_Widget' ) ) {
            $widgets_manager->register( new \WCPDBV_Elementor_Widget() );
        }
    }
});

// EOF
