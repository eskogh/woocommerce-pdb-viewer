# Molecule Viewer for WordPress

Render interactive molecular structures (PDB / SDF / MOL2 / XYZ / Gaussian **CUBE**) anywhere in WordPress.  
Supports a shortcode, Gutenberg block, an Elementor widget, and optional WooCommerce integration.  
Powered by [3Dmol.js](https://3dmol.csb.pitt.edu/).

---

## Features

- 🧬 File formats: **PDB / SDF / MOL2 / XYZ / CUBE**
- 🎛️ Two style modes
  - **Simple:** choose representation + colors in settings
  - **JSON:** pass raw 3Dmol style object
- 🧩 Gutenberg block + **Elementor widget**
- 🛒 WooCommerce product tab with **per‑variation** files
- 🌐 Optional REST proxy to work around CORS on approved hosts
- ⚙️ Script URLs overrideable via Settings or constants

> Default representation is **Ball and stick** (server + UI).

---

## Requirements

- WordPress 6.0+ (PHP 7.4+)
- Optional: WooCommerce 8+ and/or Elementor 3.18+

---

## Installation

1. Copy the folder **`molecule-viewer/`** to `wp-content/plugins/` (or upload a ZIP).
2. Activate **Molecule Viewer for WordPress**.
3. Optional: go to **Settings → Molecule Viewer** and tweak defaults:
   - Representation, color scheme, stick radius, sphere scale, surface opacity
   - Show toolbar UI, disable mouse, default spin, show download link
   - Max upload size, script URLs, and proxy with allowed hosts

---

## Quickstart

The fastest way is the shortcode:

```html
[mol_viewer file_url="https://files.rcsb.org/download/1CRN.pdb" height="420px" width="100%" type="auto" spin="y:0.6"]
```

- **`file_url`** can be a full URL, an attachment **ID**, or a root‑relative path (`/wp-content/uploads/...`).
- **`type`** can be `auto|pdb|sdf|mol2|xyz|cube`. Leave `auto` unless you know the format.
- **`spin`** can be `true` or like `y:0.6` (`axis:speed`).

---

## Shortcodes — full reference

The plugin exposes two aliases (identical behavior):

- `[mol_viewer ...]` **(preferred)**
- `[pdb_viewer ...]` (back‑compat alias)

### Common attributes

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `file_url` | string \| int | — | URL, ID, or root‑relative path |
| `type` | string | `auto` | One of `auto,pdb,sdf,mol2,xyz,cube` |
| `height` | CSS length | `400px` | e.g. `480px`, `60vh` |
| `width` | CSS length | `100%` | |
| `ui` | `true|false` | inherited from Settings | Shows 3Dmol toolbar |
| `nomouse` | `true|false` | inherited | Disables interaction entirely |
| `bgalpha` | number | `0` | Canvas background alpha 0..1 |
| `spin` | string | empty | `true` or `axis:speed` (e.g. `x:1`, `z:0.5`) |
| `download` | `yes|no` | inherited | Renders a Download button |

### Style control (two modes)

#### 1) Simple mode (friendly controls)

Uses the site‑wide defaults from **Settings → Molecule Viewer** and optional per‑shortcode overrides:

| Attribute | Example | Meaning |
| --- | --- | --- |
| `style_mode` | `simple` | Use simple mode (default) |
| `representation` | `ballandstick` | One of `cartoon,stick,ballandstick,surface,line,sphere` |
| `color` | `element` | One of `spectrum,chain,element,residue,bfactor,white,grey,rainbow` |
| `stick_radius` | `0.2` | Stick thickness (0..1) |
| `sphere_scale` | `0.3` | Ball size multiplier (0..2) |
| `surface_opacity` | `0.6` | Surface alpha (0..1) |

Example — **Ball‑and‑stick**, element colors:

```html
[mol_viewer file_url="12345" style_mode="simple" representation="ballandstick" color="element" stick_radius="0.22" sphere_scale="0.28"]
```

#### 2) JSON mode (raw 3Dmol style object)

Pass any valid 3Dmol style JSON via the `style` attribute and set `style_mode="json"`.

Example — **Cartoon, rainbow**:

```html
[mol_viewer file_url="/wp-content/uploads/2025/01/1abc.pdb"
            style_mode="json"
            style='{"cartoon":{"color":"spectrum"}}']
```

Example — **Ball‑and‑stick** with custom sizes:

```html
[mol_viewer file_url="https://example.com/ligand.sdf"
            type="sdf"
            style_mode="json"
            style='{"stick":{"radius":0.22},"sphere":{"scale":0.28,"colorscheme":"elem"}}']
```

> If `style_mode="json"` is set but your JSON is invalid/empty, the plugin falls back to **Simple** mode to avoid accidental all‑cartoon rendering.

---

## Elementor

### In the Elementor editor

1. Open a page/template in Elementor.
2. Search for **“Molecule Viewer”** and drop the widget.
3. In the widget controls, paste a **File URL** or **Attachment ID** and optionally set `Type`, `Spin`, etc.  
   (The widget renders the same server shortcode behind the scenes.)

### Programmatic include in a PHP template (theme)

If you’re inside a PHP template and just need the viewer, use the shortcode:

```php
<?php
echo do_shortcode('[mol_viewer file_url="https://files.rcsb.org/download/1CRN.pdb" height="420px" spin="y:0.6"]');
```

### Rendering an Elementor Template that contains the widget

Create an Elementor **Section** or **Page** template that includes the *Molecule Viewer* widget, note its template ID, then render it in PHP:

```php
<?php
if ( class_exists( '\Elementor\Plugin' ) ) {
    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( 1234 ); // replace 1234 with your template ID
}
```

This is useful if you want designers to manage layout in Elementor while developers include the result in theme files.

---

## Gutenberg (Block Editor)

A minimal dynamic block is provided. Search for **Molecule Viewer** in the block inserter.  
For advanced control, use the shortcode in a Paragraph/Shortcode block.

---

## WooCommerce

- Adds a **Structure** tab on product pages when a file is configured.
- **Variable products:** choose a different molecule file **per variation**; the viewer auto‑switches on variation change.
- When you insert the shortcode on a product page **without** `file_url`, it auto‑resolves to the current product (or variation) file.

Example — automatic source on product page:

```html
[mol_viewer height="420px" spin="true"]
```

---

## Advanced

### Proxy (CORS helper)

Enable **Settings → Molecule Viewer → Proxy** and list allowed hosts (one per line).  
The shortcode will automatically proxy matching `file_url`s through `/wp-json/mvwp/v1/proxy?url=…` and cache for 12h.

### Script URLs / CDNs

You can override 3Dmol URLs in **Settings → Molecule Viewer** or via constants in `wp-config.php`:

```php
define('MVWP_3DMOL_CORE_URL', 'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol-min.js');
define('MVWP_3DMOL_UI_URL',   'https://cdn.jsdelivr.net/npm/3dmol/build/3Dmol.ui-min.js');
```

---

## Troubleshooting

- **Blank viewer / “No atoms found”**  
  Ensure the file is reachable (no CORS block), the format matches, or set `type="pdb|sdf|mol2|xyz|cube"` explicitly.
- **Toolbar doesn’t show**  
  Set `ui="true"` in the shortcode or enable it in Settings.
- **Mouse still active when disabled**  
  `nomouse="true"` disables interactions fully; it also hard‑disables pointer events on the container.
- **Hidden in tabs**  
  The viewer resizes and re‑renders when Woo tabs open; if your theme uses custom tab markup, call `viewer.resize()` after showing.

---

## Developer notes

- Main entry: `molecule-viewer.php`  
- Front‑end loader: `assets/mvwp-frontend.js`  
- Admin UI/JS/CSS: `assets/`  
- WooCommerce & Elementor glue: `includes/`

PRs welcome. Please run PHP 7.4+ and WP 6.0+ locally.

## License

GPL-2.0-or-later

© 2025 Erik Skogh. See the bundled `LICENSE` file or <https://www.gnu.org/licenses/gpl-2.0.html>.
