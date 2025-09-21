=== Molecule Viewer for WordPress ===
Contributors: erikskogh
Donate link: https://skogh.org
Tags: 3dmol, pdb, molecule, chemistry, 3d, viewer, woo, elementor, gutenberg
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.5.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Render interactive molecular structures (PDB/SDF/MOL2/XYZ/CUBE) anywhere in WordPress. Shortcode + Block + Elementor, with optional WooCommerce product tab & variation support. Powered by 3Dmol.js.

== Description ==

**Molecule Viewer for WordPress** lets you display interactive 3D structures directly in posts, pages, and products.
It supports common text-based formats — **PDB, SDF, MOL2, XYZ, Gaussian CUBE** — and ships with:

- 🧬 Shortcode, Gutenberg Block, and Elementor widget
- 🛒 WooCommerce product **Structure** tab + per-variation files
- 🎛️ “Simple” style presets *or* raw 3Dmol.js style JSON
- 🌐 Optional REST **proxy** to work around CORS-limited hosts
- ⚙️ Script URLs overrideable in **Settings** or via constants

This plugin uses the excellent open‑source viewer **[3Dmol.js](https://3dmol.csb.pitt.edu/)** under its license.

= Shortcode =

Basic example:

`[mol_viewer file_url="https://files.rcsb.org/download/1CRN.pdb" height="420px" type="auto" spin="y:0.6"]`

**Attributes** (selected):
- `file_url`: Absolute URL or attachment ID
- `type`: `auto|pdb|sdf|mol2|xyz|cube` (defaults to `auto`)
- `height`, `width`: CSS sizes (e.g. `420px`, `100%`)
- `ui`: `true|false` (toolbar)
- `nomouse`: `true|false` (disable mouse/touch)
- `bgalpha`: Viewer background alpha (0–1)
- `spin`: `true` or `axis:speed` (e.g. `y:0.6`)

**Simple mode** fields (converted to a 3Dmol style object):
- `representation`: `cartoon|stick|ballandstick|surface|line|sphere`
- `color`: `spectrum|chain|element|residue|bfactor|white|grey|rainbow`
- `stick_radius`, `sphere_scale`, `surface_opacity`

**JSON mode** (advanced):
- `style`: Raw 3Dmol.js style JSON
- `style_mode=json`

= WooCommerce =

- Adds a **Structure** tab on product pages when a molecular file is configured.
- Variable products: pick different files **per variation**.
- The shortcode will **auto‑resolve** the current product’s file when `file_url` is omitted.

= Settings =

**Settings → Molecule Viewer** includes:
- Default representation (defaults to **Ball and stick**)
- Default color scheme
- Spin / UI / mouse / download link defaults
- Max upload size for molecular files
- 3Dmol.js script URLs
- Proxy toggle + allowed host list

== Installation ==

1. Upload the plugin folder `molecule-viewer` to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New**.
2. Activate **Molecule Viewer for WordPress**.
3. (Optional) Visit **Settings → Molecule Viewer** to set defaults and proxy/limits.
4. Use the `[mol_viewer]` shortcode, the **Molecule Viewer** Gutenberg block, or the **Elementor** widget.

== Frequently Asked Questions ==

= How do I make Ball‑and‑stick the default? =
Go to **Settings → Molecule Viewer** and set **Default representation** to **Ball and stick**.  
You can also force it per‑embed: `[mol_viewer representation="ballandstick"]`.

= I can’t load a remote file because of CORS. =
Enable the **Proxy** in settings and list allowed hosts (one per line). Then pass your URL as usual.

= Can I disable interaction? =
Yes. In settings set **Disable mouse** to **Yes**, or per‑embed: `[mol_viewer nomouse="true"]`.

= What formats are supported? =
Text‑based **PDB, SDF, MOL2, XYZ, CUBE**. Files are uploaded as media; the plugin also tolerates files detected as `text/plain` by doing a quick content sniff.

= The toolbar UI is missing. =
Make sure `ui="true"` (and at least one viewer requests it). The UI file can also be overridden in Settings.

== Screenshots ==

1. Front‑end viewer (Ball‑and‑stick)
2. Settings panel (Simple & JSON modes)
3. WooCommerce product tab

== Changelog ==

= 1.5.9 =
* Default representation set to **Ball and stick**.
* More robust settings UI sync + sanitizers.
* Safer 3Dmol loader and fallback download path.
* Elementor + WooCommerce integrations updated.

== Upgrade Notice ==

= 1.5.9 =
Default representation changed to **Ball and stick**. If you prefer Cartoon, update the setting or pass `representation="cartoon"` per embed.
