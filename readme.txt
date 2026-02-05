=== WC Hide Prices (Toggle) ===
Contributors: softechgenics
Tags: woocommerce, hide price, catalog mode, pricing, add to cart, bulk edit
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.4
Stable tag: 1.5.1
Requires Plugins: woocommerce
License: MIT
License URI: https://opensource.org/licenses/MIT

Hide/replace WooCommerce prices and short descriptions with targeting by products/categories. Optionally disable Add to cart. Includes bulk Draft/Publish and bulk Regular Price tools.

== Description ==

WC Hide Prices (Toggle) lets you:
- Hide or replace product prices on the frontend
- Hide or replace product short descriptions
- Optionally disable Add to cart (matched products become not purchasable)
- Target by all products, specific products (IDs/SKUs), categories, or products OR categories
- Run bulk tools on matched products:
  - Publish ↔ Draft
  - Bulk set Regular Price (optional clear Sale Price)
  - Variable products supported (updates variations + re-sync)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` OR upload the ZIP via Plugins → Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce → Hide Prices to configure.

== Frequently Asked Questions ==

= How do I target only some products? =
Choose “Specific products” and enter Product IDs or SKUs (one per line). Or choose “Categories” and select categories.

= Can I show a message instead of the price? =
Yes. Set “Price replacement text” (example: Login to see price).

= What does “Hide/disable Add to cart” do? =
It makes matched products not purchasable and hides/disables add-to-cart actions on the frontend.

= Do bulk price updates change real prices? =
Yes. Bulk price updates modify actual product prices. Variable products update each variation and re-sync.

== Changelog ==

= 1.5.1 =
- Current release.

== License ==

MIT License.
