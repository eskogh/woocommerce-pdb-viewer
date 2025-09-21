<?php
/**
 * Elementor widget adapter for Molecule Viewer.
 *
 * Registers a simple widget that renders the shortcode output so users can drop
 * a viewer in Elementor without dealing with shortcodes manually.
 *
 * @package Molecule_Viewer_WP
 */

if (!defined('ABSPATH')) exit;

if (class_exists('Elementor\\Widget_Base')) {
    /**
     * Class MVWP_Elementor_Widget
     *
     * @extends \Elementor\Widget_Base
     */
    class MVWP_Elementor_Widget extends \Elementor\Widget_Base {
        /** @inheritDoc */
        public function get_name() { return 'mvwp_viewer'; }

        /** @inheritDoc */
        public function get_title() { return __('Molecule Viewer', 'molecule-viewer'); }

        /** @inheritDoc */
        public function get_icon() { return 'eicon-code'; }

        /** @inheritDoc */
        public function get_categories() { return ['general']; }

        /**
         * Render the widget by delegating to the shortcode.
         * Keep server-rendered output for consistency with block/shortcode paths.
         *
         * @return void
         */
        protected function render() { echo do_shortcode('[mol_viewer]'); }
    }
}
