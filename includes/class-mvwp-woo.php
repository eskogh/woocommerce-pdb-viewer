<?php
/**
 * WooCommerce integration for Molecule Viewer.
 *
 * - Adds product-level panel for selecting molecule files
 * - Adds "Structure" product tab on frontend
 * - Supports per-variation attachments + live variation switching
 */

if (!defined('ABSPATH')) exit;

class MVWP_Woo {

    public static function boot() {
        if (!self::is_active()) return;

        // Admin: own product tab + panel
        add_filter('woocommerce_product_data_tabs',   [__CLASS__, 'admin_product_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'admin_product_panel']);

        // Save main product meta
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_meta']);

        // Variations
        add_action('woocommerce_product_after_variable_attributes', [__CLASS__, 'variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [__CLASS__, 'save_variation_meta'], 10, 1);
        add_action('admin_print_footer_scripts', [__CLASS__, 'variation_inline_js']);

        // Frontend product tab + live variation switching
        add_filter('woocommerce_product_tabs', [__CLASS__, 'add_product_tab']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_localize_variation_map']);
        add_action('wp_print_footer_scripts', [__CLASS__, 'variation_reload_inline_js']);

        // Assets for the product panel
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue']);
    }

    public static function is_active() { return class_exists('WooCommerce'); }

    /** Admin: product tab registration */
    public static function admin_product_tab($tabs){
        $tabs['mvwp'] = [
            'label'    => __('Molecule Viewer', 'molecule-viewer'),
            'target'   => 'mvwp_product_data',
            'class'    => ['show_if_simple','show_if_variable'],
            'priority' => 65,
        ];
        return $tabs;
    }

    /** Admin: product panel markup */
    public static function admin_product_panel(){
        global $post; if (!$post) return;

        $attachment_only = get_option('mvwp_attachment_only', 'no') === 'yes';
        $att_id   = (int) get_post_meta($post->ID, '_mvwp_attachment_id', true);
        $file_url = get_post_meta($post->ID, '_mvwp_file_url', true);
        $attached_url = $att_id ? wp_get_attachment_url($att_id) : '';
        ?>
        <div id="mvwp_product_data" class="panel woocommerce_options_panel">
            <?php wp_nonce_field('mvwp_save_meta', 'mvwp_nonce'); ?>

            <div class="mvwp-row">
                <p class="form-field">
                    <label for="_mvwp_attachment_id"><?php esc_html_e('Attachment ID', 'molecule-viewer'); ?></label>
                    <input type="number" id="_mvwp_attachment_id" name="_mvwp_attachment_id" value="<?php echo esc_attr($att_id ?: ''); ?>" readonly class="short" />
                    <span class="description"><?php esc_html_e('Chosen molecular file (PDB/SDF/MOL2/XYZ/CUBE) stored as a media attachment.', 'molecule-viewer'); ?></span>
                </p>

                <p class="form-field">
                    <label for="_mvwp_attachment_url_display"><?php esc_html_e('Selected File URL', 'molecule-viewer'); ?></label>
                    <input type="url" id="_mvwp_attachment_url_display" value="<?php echo esc_attr($attached_url ?: ''); ?>" readonly style="width: 60%;" />
                </p>

                <p class="form-field">
                    <label><?php esc_html_e('Upload / Select', 'molecule-viewer'); ?></label>
                    <button type="button" class="button button-primary mvwp-upload"><?php esc_html_e('Choose file', 'molecule-viewer'); ?></button>
                    <button type="button" class="button mvwp-clear"><?php esc_html_e('Clear', 'molecule-viewer'); ?></button>
                </p>

                <?php if (!$attachment_only): ?>
                <p class="form-field">
                    <label for="_mvwp_file_url"><?php esc_html_e('(Optional) Remote File URL', 'molecule-viewer'); ?></label>
                    <input type="url" id="_mvwp_file_url" name="_mvwp_file_url" value="<?php echo esc_attr($file_url ?: ''); ?>" style="width: 60%;" placeholder="https://…" />
                    <span class="description"><?php esc_html_e('Use only if you cannot upload. Remote hosts must allow CORS or use the Proxy setting.', 'molecule-viewer'); ?></span>
                </p>
                <?php endif; ?>
            </div>

            <hr class="mvwp-sep" />

            <div class="mvwp-tip">
                <strong><?php esc_html_e('Tip:', 'molecule-viewer'); ?></strong>
                <?php esc_html_e('On the front-end product page, a “Structure” tab is added automatically when a file is set.', 'molecule-viewer'); ?>
            </div>
        </div>
        <?php
    }

    /** Admin: enqueue small inline handlers */
    public static function admin_enqueue($hook) {
        if (!in_array($hook, ['post.php','post-new.php'], true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'product' !== $screen->id) return;
        wp_enqueue_media();

        add_action('admin_print_footer_scripts', function(){
            ?>
            <script>
            (function($){
                $(document).on('click', '.mvwp-upload', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: '<?php echo esc_js(__('Select a molecular file', 'molecule-viewer')); ?>',
                        button:{ text:'<?php echo esc_js(__('Use this file', 'molecule-viewer')); ?>' },
                        multiple:false,
                        library:{ type:'application' }
                    });
                    frame.on('select', function(){
                        var a = frame.state().get('selection').first().toJSON();
                        var ok = /\.(pdb|sdf|mol2|xyz|cube)(\?.*)?$/i.test(a.url);
                        if (!ok) { alert('<?php echo esc_js(__('Please choose a pdb/sdf/mol2/xyz/cube file.', 'molecule-viewer')); ?>'); return; }
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
            })(jQuery);
            </script>
            <?php
        });
    }

    /** Persist product meta */
    public static function save_product_meta($post_id) {
        if (!isset($_POST['mvwp_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['mvwp_nonce']), 'mvwp_save_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $attachment_only = get_option('mvwp_attachment_only', 'no') === 'yes';

        $att_id = isset($_POST['_mvwp_attachment_id']) ? (int) $_POST['_mvwp_attachment_id'] : 0;
        if ($att_id > 0) update_post_meta($post_id, '_mvwp_attachment_id', $att_id);
        else delete_post_meta($post_id, '_mvwp_attachment_id');

        if (!$attachment_only && isset($_POST['_mvwp_file_url']) && $_POST['_mvwp_file_url'] !== '') {
            update_post_meta($post_id, '_mvwp_file_url', esc_url_raw($_POST['_mvwp_file_url']));
        } else {
            delete_post_meta($post_id, '_mvwp_file_url');
        }
    }

    /* Variations */

    public static function variation_fields($loop, $variation_data, $variation) {
        $att_id = (int) get_post_meta($variation->ID, '_mvwp_attachment_id', true);
        $url    = $att_id ? wp_get_attachment_url($att_id) : '';
        ?>
        <div class="form-row form-row-full">
            <label><?php echo esc_html__('Molecule (Attachment)', 'molecule-viewer'); ?></label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="number" class="short" style="width:140px" name="_mvwp_attachment_id[<?php echo (int)$variation->ID; ?>]" value="<?php echo esc_attr($att_id ?: ''); ?>" readonly />
                <input type="url" class="short" style="flex:1" value="<?php echo esc_attr($url); ?>" readonly />
                <button class="button mvwp-upload-var" data-varid="<?php echo (int)$variation->ID; ?>"><?php esc_html_e('Choose', 'molecule-viewer'); ?></button>
                <button class="button mvwp-clear-var"  data-varid="<?php echo (int)$variation->ID; ?>"><?php esc_html_e('Clear', 'molecule-viewer'); ?></button>
            </div>
        </div>
        <?php
    }

    public static function save_variation_meta($variation_id) {
        if (!current_user_can('edit_post', $variation_id)) return;
        $var_ids = isset($_POST['_mvwp_attachment_id']) && is_array($_POST['_mvwp_attachment_id']) ? $_POST['_mvwp_attachment_id'] : [];
        if (isset($var_ids[$variation_id])) {
            $att_id = (int) $var_ids[$variation_id];
            if ($att_id > 0) update_post_meta($variation_id, '_mvwp_attachment_id', $att_id);
            else delete_post_meta($variation_id, '_mvwp_attachment_id');
        }
    }

    public static function variation_inline_js() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'product') return;
        ?>
        <script>
        (function($){
            $(document).on('click', '.mvwp-upload-var', function(e){
                e.preventDefault();
                var varid = $(this).data('varid');
                var frame = wp.media({
                    title: '<?php echo esc_js(__('Select a molecular file', 'molecule-viewer')); ?>',
                    button:{ text:'<?php echo esc_js(__('Use this file', 'molecule-viewer')); ?>' },
                    multiple:false,
                    library:{ type:'application' }
                });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    var ok = /\.(pdb|sdf|mol2|xyz|cube)(\?.*)?$/i.test(a.url);
                    if (!ok) { alert('<?php echo esc_js(__('Please choose a pdb/sdf/mol2/xyz/cube file.', 'molecule-viewer')); ?>'); return; }
                    $('input[name="_mvwp_attachment_id['+varid+']"]').val(a.id);
                    $('.mvwp-upload-var[data-varid="'+varid+'"]').prev('input[type=url]').val(a.url);
                });
                frame.open();
            });
            $(document).on('click', '.mvwp-clear-var', function(e){
                e.preventDefault();
                var varid = $(this).data('varid');
                $('input[name="_mvwp_attachment_id['+varid+']"]').val('');
                $('.mvwp-upload-var[data-varid="'+varid+'"]').prev('input[type=url]').val('');
            });
        })(jQuery);
        </script>
        <?php
    }

    /* Frontend: product tab + variation map */

    public static function add_product_tab($tabs) {
        if (!function_exists('is_product') || !is_product()) return $tabs;
        global $post; if (!$post) return $tabs;

        $src = self::get_product_file_url($post->ID);
        if ($src) {
            $tabs['mvwp_viewer'] = [
                'title'    => __('Structure', 'molecule-viewer'),
                'priority' => 25,
                'callback' => function () use ($src) {
                    echo do_shortcode('[mol_viewer file_url="'.esc_url($src).'"]');
                }
            ];
        }
        return $tabs;
    }

    public static function maybe_localize_variation_map() {
        if (!function_exists('is_product') || !is_product()) return;
        global $post; if (!$post) return;
        if (!function_exists('wc_get_product')) return;

        $product = wc_get_product($post->ID);
        if (!$product || !$product->is_type('variable')) return;

        $map = [];
        foreach ($product->get_children() as $vid) {
            $url = self::get_product_file_url($vid);
            if ($url) $map[(string)$vid] = $url;
        }
        if (!$map) return;

        wp_register_script('mvwp-varmap', false, ['jquery'], null, true);
        wp_enqueue_script('mvwp-varmap');
        wp_localize_script('mvwp-varmap', 'MVWP_VAR', [ 'variationMap' => $map ]);
    }

    public static function variation_reload_inline_js() {
        if (!function_exists('is_product') || !is_product()) return;
        ?>
        <script>
        (function($){
            $(document).on('found_variation', 'form.variations_form', function(e, variation){
                if (!variation || !variation.variation_id) return;
                var map = (window.MVWP_VAR && MVWP_VAR.variationMap) || {};
                var newUrl = map[String(variation.variation_id)];
                if (!newUrl) return;

                var el = document.querySelector('.mvwp-viewer');
                if (!el) return;

                el.setAttribute('data-url', newUrl);

                if (el._mvwp && window.$3Dmol) {
                    var viewer = el._mvwp;
                    fetch(newUrl).then(r=>r.text()).then(function(data){
                        var typ = el.getAttribute('data-type') || 'pdb';
                        viewer.removeAllModels();
                        viewer.addModel(data, typ);
                        var style = {};
                        try { style = JSON.parse(el.getAttribute('data-style')||'{}'); } catch(e){ style = {cartoon:{color:'spectrum'}}; }
                        viewer.setStyle({}, style||{cartoon:{color:'spectrum'}});
                        viewer.zoomTo(); viewer.render();
                        var spin = el.getAttribute('data-spin');
                        if (spin && viewer.spin) {
                            var axis='y', speed=1;
                            if (spin !== 'true') {
                                var p = spin.split(':'); axis=(p[0]||'y').toLowerCase(); speed=parseFloat(p[1]||'1'); if (isNaN(speed)) speed=1;
                            }
                            try { viewer.spin(axis, speed); } catch(e){}
                        }
                    });
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /** Resolve file URL for product/variation (with attachment-only + remote URL support) */
    public static function get_product_file_url($product_id) {
        // Variation context
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_type('variation')) {
                $att_id = (int) get_post_meta($product_id, '_mvwp_attachment_id', true);
                if ($att_id) return wp_get_attachment_url($att_id) ?: '';
            }
            if ($product && $product->is_type('variable')) {
                $att_id = (int) get_post_meta($product_id, '_mvwp_attachment_id', true);
                if ($att_id) return wp_get_attachment_url($att_id) ?: '';
            }
        }

        // Product level
        $att_id = (int) get_post_meta($product_id, '_mvwp_attachment_id', true);
        if ($att_id) {
            $u = wp_get_attachment_url($att_id);
            if ($u) return $u;
        }

        // Optional remote URL if allowed
        $attachment_only = get_option('mvwp_attachment_only', 'no') === 'yes';
        if (!$attachment_only) {
            $url = get_post_meta($product_id, '_mvwp_file_url', true);
            if ($url) return $url;
        }

        return '';
    }
}
