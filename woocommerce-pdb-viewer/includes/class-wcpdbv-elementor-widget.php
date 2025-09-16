<?php
if ( ! defined('ABSPATH') ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class WCPDBV_Elementor_Widget extends Widget_Base {
    public function get_name(){ return 'wcpdbv_viewer'; }
    public function get_title(){ return __('PDB Viewer', 'woocommerce-pdb-viewer'); }
    public function get_icon(){ return 'eicon-photo-library'; }
    public function get_categories(){ return ['general']; } // or 'woocommerce-elements' if you prefer

    protected function _register_controls(){
        $this->start_controls_section('content', ['label'=>__('Content','woocommerce-pdb-viewer')]);

        $this->add_control('file_url', [
            'label' => __('File URL', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXT,
            'placeholder' => 'https://…/molecule.pdb',
        ]);
        $this->add_control('product_id', [
            'label' => __('Product ID (optional)', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::NUMBER,
        ]);
        $this->add_control('pdb_id', [
            'label' => __('PDB ID (optional)', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $this->add_control('height', [
            'label' => __('Height', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXT,
            'default' => '400px',
        ]);
        $this->add_control('width', [
            'label' => __('Width', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXT,
            'default' => '100%',
        ]);
        $this->add_control('ui', [
            'label' => __('Show Toolbar UI', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('nomouse', [
            'label' => __('Disable mouse', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => '',
        ]);
        $this->add_control('style', [
            'label' => __('Style (JSON)', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXTAREA,
            'rows'  => 3,
            'placeholder' => '{"stick":{"radius":0.2},"sphere":{"scale":0.3}}',
        ]);
        $this->add_control('spin', [
            'label' => __('Spin (e.g. true or y:0.5)', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::TEXT,
            'default' => '',
        ]);
        $this->add_control('download', [
            'label' => __('Show Download Link', 'woocommerce-pdb-viewer'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    protected function render(){
        $s = $this->get_settings_for_display();

        $atts = [];
        foreach (['file_url','product_id','pdb_id','height','width','style','spin'] as $k) {
            if (!empty($s[$k])) $atts[$k] = $s[$k];
        }
        $atts['ui']      = !empty($s['ui']) ? 'true' : 'false';
        $atts['nomouse'] = !empty($s['nomouse']) ? 'true' : 'false';
        if (isset($s['download']) && $s['download'] !== '') {
            $atts['download'] = !empty($s['download']) ? 'yes' : 'no';
        }

        echo do_shortcode('[pdb_viewer '.esc_attr( http_build_query($atts, '', ' ') ).']');
    }
}
