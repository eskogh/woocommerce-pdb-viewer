<?php
/**
 * Plugin Name: WooCommerce PDB Viewer
 * Plugin URI: https://linux.army
 * Description: Adds a PDB file viewer to WooCommerce products.
 * Version: 1.0
 * Author: Erik Skogh
 * Author URI: https://linux.army
 * License: GPL2
 * Text Domain: woocommerce-pdb-viewer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add PDB upload button to the product editor
add_action('woocommerce_product_options_general_product_data', function () {
    global $post;
    $pdb_file_url = get_post_meta($post->ID, '_pdb_file_url', true);

    echo '<div class="options_group">';
    woocommerce_wp_text_input([
        'id' => '_pdb_file_url',
        'label' => __('PDB File URL', 'woocommerce-pdb-viewer'),
        'desc_tip' => true,
        'description' => __('Upload or enter the URL of the PDB file for the product.'),
        'type' => 'url',
        'value' => $pdb_file_url,
    ]);
    ?>
    <p class="form-field">
        <label><?php esc_html_e('Upload PDB File', 'woocommerce-pdb-viewer'); ?></label>
        <button type="button" class="button upload_pdb_button"><?php esc_html_e('Upload PDB', 'woocommerce-pdb-viewer'); ?></button>
    </p>
    <script>
        jQuery(document).ready(function($) {
            $('.upload_pdb_button').on('click', function(e) {
                e.preventDefault();
                const button = $(this);
                const input = button.closest('.form-field').prev().find('input');
                wp.media({
                    title: '<?php esc_html_e('Select a PDB file', 'woocommerce-pdb-viewer'); ?>',
                    button: { text: '<?php esc_html_e('Use this file', 'woocommerce-pdb-viewer'); ?>' },
                    library: { type: '' } // Allow all types; restrict if necessary
                }).open().on('select', function() {
                    const attachment = wp.media.frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                });
            });
        });
    </script>
    <?php
    echo '</div>';
});

// Save the uploaded PDB file URL
add_action('woocommerce_process_product_meta', function ($post_id) {
    if (isset($_POST['_pdb_file_url'])) {
        update_post_meta($post_id, '_pdb_file_url', esc_url_raw($_POST['_pdb_file_url']));
    }
});

add_action('woocommerce_after_single_product_summary', function () {
    global $post;

    $pdb_file_url = get_post_meta($post->ID, '_pdb_file_url', true);
    if ($pdb_file_url) {
        echo do_shortcode('[pdb_viewer]');
    }
}, 25);

add_shortcode('pdb_viewer', function ($atts) {
    global $post;

    $pdb_file_url = get_post_meta($post->ID, '_pdb_file_url', true);
    if (!$pdb_file_url) {
        return '<p>No PDB file available for this product.</p>';
    }

    $default_style = get_option('pdb_viewer_default_style', '{"stick": {"radius": 0.2}, "sphere": {"scale": 0.3}}');
    $disable_zoom = get_option('pdb_viewer_disable_zoom', 'yes') === 'yes' ? '{"zoom": true}' : '{}';

    ob_start(); ?>
    <script src="https://3Dmol.org/build/3Dmol-min.js"></script>
    <div 
        style="height: 400px; width: 500px; margin: 20px 0px 20px 0px" 
        class="viewer_3Dmoljs" 
        data-href="<?php echo esc_url($pdb_file_url); ?>" 
        data-backgroundalpha="0.0" 
        data-style='<?php echo esc_attr($default_style); ?>' 
        data-config='<?php echo esc_attr($disable_zoom); ?>' 
        data-ui="true">
    </div>
    <?php
    return ob_get_clean();
});

// Allow PDB file uploads
add_filter('upload_mimes', function ($mimes) {
    $mimes['pdb'] = 'chemical/x-pdb';
    return $mimes;
});

// Enqueue 3Dmol.js for the shortcode
add_action('wp_enqueue_scripts', function () {
    if (is_singular('product')) {
        wp_enqueue_script('3dmol-js', 'https://3Dmol.org/build/3Dmol-min.js', [], null, true);
    }
});

// Add a settings page for the plugin
add_action('admin_menu', function () {
    add_options_page(
        __('WooCommerce PDB Viewer Settings', 'woocommerce-pdb-viewer'),
        __('PDB Viewer', 'woocommerce-pdb-viewer'),
        'manage_options',
        'woocommerce-pdb-viewer',
        'woocommerce_pdb_viewer_settings_page'
    );
});

function woocommerce_pdb_viewer_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('WooCommerce PDB Viewer Settings', 'woocommerce-pdb-viewer'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('woocommerce_pdb_viewer_settings');
            do_settings_sections('woocommerce_pdb_viewer');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('woocommerce_pdb_viewer_settings', 'pdb_viewer_default_style');
    register_setting('woocommerce_pdb_viewer_settings', 'pdb_viewer_disable_zoom');

    add_settings_section(
        'woocommerce_pdb_viewer_main',
        __('Viewer Settings', 'woocommerce-pdb-viewer'),
        null,
        'woocommerce_pdb_viewer'
    );

    add_settings_field(
        'pdb_viewer_default_style',
        __('Default Viewer Style', 'woocommerce-pdb-viewer'),
        function () {
            $value = get_option('pdb_viewer_default_style', '{"stick": {"radius": 0.2}, "sphere": {"scale": 0.3}}');
            echo '<textarea name="pdb_viewer_default_style" rows="5" cols="50">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">' . __('Set the default 3Dmol.js style JSON.', 'woocommerce-pdb-viewer') . '</p>';
        },
        'woocommerce_pdb_viewer',
        'woocommerce_pdb_viewer_main'
    );

    add_settings_field(
        'pdb_viewer_disable_zoom',
        __('Disable Zoom', 'woocommerce-pdb-viewer'),
        function () {
            $value = get_option('pdb_viewer_disable_zoom', 'yes');
            echo '<select name="pdb_viewer_disable_zoom">';
            echo '<option value="yes"' . selected($value, 'yes', false) . '>' . __('Yes', 'woocommerce-pdb-viewer') . '</option>';
            echo '<option value="no"' . selected($value, 'no', false) . '>' . __('No', 'woocommerce-pdb-viewer') . '</option>';
            echo '</select>';
            echo '<p class="description">' . __('Disable zooming in the 3D viewer.', 'woocommerce-pdb-viewer') . '</p>';
        },
        'woocommerce_pdb_viewer',
        'woocommerce_pdb_viewer_main'
    );
});
