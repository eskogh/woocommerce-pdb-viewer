=== WooCommerce PDB Viewer ===
Contributors: eskogh
Donate link: https://linux.army
Tags: woocommerce, pdb, protein, molecules, 3dmol, viewer
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a PDB (Protein Data Bank) 3D viewer to WooCommerce products using 3Dmol.js. Includes Elementor widget, shortcode, and Gutenberg block.

== Description ==

WooCommerce PDB Viewer lets you attach `.pdb` files to products and view them in 3D directly on the product page.  
Powered by [3Dmol.js](https://3dmol.csb.pitt.edu/).

* Adds a new **Structure tab** on product pages.
* Embed viewers anywhere with a **shortcode** or **Elementor widget**.
* Per-variation PDB support.
* Option to show **download button**.
* Auto-rotation/spin support.
* Optional **user avatar replacement** with a PDB viewer.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/woocommerce-pdb-viewer/`.
2. Activate the plugin through `Plugins > Installed Plugins`.
3. Go to `Settings > PDB Viewer` to configure.

== Usage ==

Shortcode example:

`[pdb_viewer product_id="123" height="400px" width="100%" spin="true"]`

Available attributes:
* `product_id` – Load a WooCommerce product’s PDB.
* `file_url` – Direct link to a `.pdb` file.
* `height`, `width` – Viewer size.
* `ui`, `nomouse`, `spin`, `style`, `download` – Configure interaction and rendering.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =
Yes – you can use the shortcode with `file_url` even on non-product pages.

= Can I use Elementor? =
Yes, the plugin adds a drag-and-drop Elementor widget.

= Can I show multiple viewers? =
Yes, just use multiple shortcodes.

== Screenshots ==

1. Product page with PDB tab
2. Elementor widget
3. Shortcode demo

== Changelog ==

= 1.4.0 =
* Elementor widget
* Avatar replacement feature
* Download link toggle
* Default spin option
* Shortcode: `file_url`, `product_id`, `pdb_id`

== Upgrade Notice ==

= 1.4.0 =
New shortcode attributes and Elementor widget.  
Update recommended.

== License ==

This plugin is licensed under the GPL v2 or later.
