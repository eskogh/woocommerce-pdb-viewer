# WooCommerce PDB Viewer

A WordPress plugin that integrates a **3D PDB (Protein Data Bank) viewer** directly into WooCommerce product pages.  
Built with [3Dmol.js](https://3dmol.csb.pitt.edu/) for interactive molecular visualization.

## ✨ Features

- Adds a **PDB viewer tab** to WooCommerce product pages.
- Supports **shortcodes** (`[pdb_viewer]`) for embedding structures in posts, pages, or Elementor.
- **Elementor widget** included – drag and drop into theme builder.
- Gutenberg block support.
- Settings page with:
  - Default styles (stick, sphere, etc.)
  - Toolbar UI toggle
  - Mouse interaction toggle
  - Max PDB size enforcement
  - Remote URL proxy for CORS handling
- **Variation support**: different PDB files per product variation.
- Optional **download link** for customers.
- Optional **auto-spin** of molecules.
- Replace **WordPress avatars with PDBs** (experimental feature).

## 🔧 Installation

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-pdb-viewer` directory,  
   or install directly via the WordPress plugins page.
2. Activate through **Plugins > Installed Plugins**.
3. Configure under **Settings > PDB Viewer**.

## 🖥️ Usage

### Shortcode
```php
[pdb_viewer product_id="123" height="400px" width="100%" spin="true"]
```

**Attributes:**
- `product_id` – Load PDB attached to a product.
- `file_url` – Explicit URL to a `.pdb` file.
- `pdb_id` – Placeholder for custom resolvers.
- `height` / `width` – Viewer size.
- `style` – JSON style, e.g. `{"stick":{"radius":0.2},"sphere":{"scale":0.3}}`.
- `ui="true|false"` – Show toolbar.
- `nomouse="true|false"` – Disable mouse interaction.
- `spin="true|y:0.5"` – Auto-spin molecules.
- `download="yes|no"` – Show download link.

### Elementor
Drag **PDB Viewer** widget into your page and configure options.

### WooCommerce
Automatically adds a **Structure** tab if a PDB file is attached.

## 🛠️ Development

- Written in PHP + JavaScript.
- Uses **3Dmol.js** via CDN.
- Includes fallbacks if assets fail to load.

## 📹 Demo

A demo video is available:  
👉 [Download / Watch](./demo.mp4)

*(Yes, including the demo video in GitHub is a good idea! It helps users quickly see what the plugin does.)*

## 📝 License

GPL-2.0-or-later  
Copyright (c) Erik Skogh
