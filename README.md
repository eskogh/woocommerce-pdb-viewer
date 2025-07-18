# WooCommerce PDB Viewer

**Contributors:** Erik Skogh  
**Tags:** WooCommerce, PDB, Protein Data Bank, 3D Viewer, Molecule, Science  
**Requires at least:** 5.0  
**Tested up to:** 6.5  
**Stable tag:** 1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

A WooCommerce plugin that allows store owners to upload and display interactive 3D Protein Data Bank (PDB) files on product pages using [3Dmol.js](https://3dmol.csb.pitt.edu/).

---

## ✨ Features

- Adds a PDB file upload field to the WooCommerce product editor.
- Allows customers to interact with 3D molecular models (zoom, rotate, pan).
- Uses [3Dmol.js](https://3dmol.org/) for rendering high-performance 3D molecules.
- Optional viewer settings for zoom control and rendering styles.
- Adds a settings page for customizing the viewer's behavior.

---

## 🧪 Use Case

Perfect for:

- Scientific supply stores
- Educational or biotech products
- Any product involving molecular data visualization

---

## 📦 Installation

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-pdb-viewer` directory, or install via the WordPress Plugin Directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce > Products**, edit a product, and enter or upload a `.pdb` file under **General** tab.
4. The 3D viewer will automatically be embedded on the product page.

---

## ⚙️ Settings

Navigate to **Settings > PDB Viewer** to configure:

- **Default Viewer Style**: Customize the default molecule appearance via 3Dmol.js style JSON.
- **Disable Zoom**: Toggle zooming functionality in the 3D viewer.

---

## 🧬 Usage

### Uploading a PDB File

1. Edit a WooCommerce product.
2. In the **General** section, paste a URL or use the **Upload PDB** button to attach a `.pdb` file.
3. Save the product.

### Displaying the Viewer

The viewer automatically appears below the product description.  
Alternatively, use the shortcode:

```php
[pdb_viewer]
```

## 📁 Supported File Types

- `.pdb` (MIME type: `chemical/x-pdb`)

---

## 🧰 Developer Notes

- Viewer is rendered via a `<div class="viewer_3Dmoljs">` using external JS from `https://3Dmol.org/build/3Dmol-min.js`.
- Customize the viewer with `data-style`, `data-config`, and other 3Dmol.js attributes.

---

## 🔐 License

This plugin is licensed under the [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) or later.

---

## 🧑‍💻 Author

**Erik Skogh**  
🌐 https://linux.army  
📧 info@linux.army
