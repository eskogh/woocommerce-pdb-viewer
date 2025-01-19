# WooCommerce PDB Viewer
A simple WordPress addon to add interactive protein or molecule visualization using 3Dmol.js.

## Installation

### Option 1: Download the .zip File
1. Download the plugin as a `.zip` file.
2. In your WordPress admin panel, navigate to **Plugins -> Add New**, and click **Upload Plugin**.
3. Select the `.zip` file and click **Install Now**.
4. Activate the plugin after installation.

### Option 2: Clone via Git
1. Open your terminal and navigate to the `./wp-content/plugins/` directory of your WordPress installation.
2. Run the following command:

```git clone https://github.com/eskogh/woocommerce-pdb-viewer/```

3. Go to your WordPress admin panel and activate the plugin under **Plugins**.

## Usage

### Plugin Settings
- Configure settings by navigating to **Settings -> PDB Viewer** in your WordPress admin panel.

### Adding PDB Files to Products
1. On the **Product Edit Page**, locate the **Product Data** box.
2. Under the **General** tab, you will find a button to upload your `.pdb` file. Use open-babel to convert almost any file to .pdb

### Displaying the Viewer
- Use the shortcode `[pdb_viewer]` anywhere in the product description to display the 3D protein or molecule visualization on the front end.
