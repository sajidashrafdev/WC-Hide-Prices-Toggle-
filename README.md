# WC Hide Prices (Toggle)

Hide or replace WooCommerce product prices and short descriptions, with targeting by products and/or categories. Optionally disable Add to cart for matched products. Includes bulk tools to Draft/Publish matched products and bulk update Regular Price.

## Features

- Hide/replace **price** on the frontend
- Hide/replace **short description** on the frontend
- Optionally **disable Add to cart** (makes matched products not purchasable)
- Target by:
  - All products
  - Specific products (IDs / SKUs)
  - Categories
  - Products OR Categories
- Bulk tools (for matched products):
  - Publish ↔ Draft
  - Bulk set Regular Price (optional: clear sale price)
  - Variable products supported (updates variations and re-syncs)

## Requirements

- WordPress 6.0+
- WooCommerce installed and active

## Installation

### Option A: Install from ZIP
1. Download this repository as a ZIP.
2. In WordPress Admin: **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate.

### Option B: Manual install
1. Copy the `wc-hide-prices-toggle` folder into:
   `wp-content/plugins/`
2. Activate the plugin from **Plugins**.

## Usage

1. Go to **WooCommerce → Hide Prices**
2. Configure:
   - **Hide prices on the frontend**
   - **Hide/replace short description**
   - **Hide/disable Add to cart**
3. Choose **Targeting**:
   - **All products**
   - **Specific products** (enter Product IDs or SKUs, one per line)
   - **Selected categories**
   - **Products OR Categories**
4. (Optional) Set replacement text:
   - **Price replacement text** (example: `Login to see price`)
   - **Short description replacement** (basic HTML allowed)

### Bulk Actions
On the same page, use **Bulk Actions** to run one-time operations on all matched products:
- Move matched products to **Draft**
- Publish matched products
- Bulk set **Regular Price** (optionally clear Sale Price)

> Note: Bulk price updates modify actual product prices. Variable products update each variation and re-sync.

## How targeting works

- Product list accepts Product IDs or SKUs (one per line).
- Variations are supported: a variation can match if its **parent** product ID/SKU is listed.
- Category targeting checks product categories (variations check the parent product’s categories).

## Data stored

Settings are stored in the WordPress options table under:
- `wchpt_settings`

## Support / Contributions

Issues and pull requests are welcome.
