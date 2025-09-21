# Molecule Viewer for WordPress

Render interactive molecular structures (PDB/SDF/MOL2/XYZ/CUBE) anywhere in WordPress. Shortcode + Block + Elementor, with optional WooCommerce integration. Powered by [3Dmol.js](https://3dmol.csb.pitt.edu/).

## Features

- 🧬 Supports **PDB / SDF / MOL2 / XYZ / Gaussian CUBE**
- 🎛️ Simple presets *or* raw 3Dmol style JSON
- 🧩 Gutenberg block + Elementor widget
- 🛒 WooCommerce product tab + per‑variation files
- 🌐 Optional REST proxy for CORS‑limited hosts
- ⚙️ Script URLs overrideable via settings or constants

## Quickstart

```html
[mol_viewer file_url="https://files.rcsb.org/download/1CRN.pdb" height="420px" width="100%" type="auto" spin="y:0.6"]
```

### Shortcode (selected)

- `file_url`: URL or attachment ID  
- `type`: `auto|pdb|sdf|mol2|xyz|cube`  
- `height` / `width`  
- `ui`, `nomouse`, `bgalpha`, `spin`

**Simple mode**: `representation`, `color`, `stick_radius`, `sphere_scale`, `surface_opacity`  
**JSON mode**: `style` (raw 3Dmol style JSON), `style_mode=json`

### WooCommerce

- Adds a **Structure** tab on product pages when a file is configured.  
- Variable products can map a different file **per variation**.  
- The shortcode auto‑resolves the current product’s file when `file_url` is omitted.

## Settings

**Settings → Molecule Viewer** lets you configure defaults (representation, colors, spin, UI/mouse/download), max upload size, script URLs, and an optional proxy with allowed hosts.  
The plugin now defaults to **Ball and stick** representation.

## Installation

1. Copy the folder `molecule-viewer/` to `wp-content/plugins/` (or upload the ZIP in **Plugins → Add New**).  
2. Activate **Molecule Viewer for WordPress**.  
3. (Optional) Configure defaults in **Settings → Molecule Viewer**.  
4. Use the shortcode, block, or Elementor widget.

## Development

- PHP 7.4+ / WP 6.0+ recommended.
- Main entry: `molecule-viewer.php`  
- Admin UI/JS/CSS: `assets/`  
- WooCommerce + Elementor glue: `includes/`  
- Front‑end loader: `assets/mvwp-frontend.js`

### Local tips

- To force Ball‑and‑stick by default in code, ensure `mvwp_build_style_from_simple()` uses `ballandstick`, or set the option **mvwp_repr** accordingly in Settings.
- If your theme omits `wp_footer`, the shortcode emits inline fallbacks to load scripts.

## License

GPL‑2.0‑or‑later. © Erik Skogh.
