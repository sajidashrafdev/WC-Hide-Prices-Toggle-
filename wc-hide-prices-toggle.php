<?php
/**
 * Plugin Name: WC Hide Prices (Toggle)
 * Description: Hide/replace WooCommerce prices + short descriptions, target by products/categories, hide add-to-cart, and bulk Draft/Publish + bulk set Regular Price.
 * Version: 1.5.1
 * Author: SoftechGenics
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Hide_Prices_Toggle {
	const OPTION_KEY = 'wchpt_settings';
	const NONCE_KEY  = 'wchpt_bulk_action_nonce';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Frontend filters
		add_filter( 'woocommerce_get_price_html', [ $this, 'maybe_hide_price_html' ], 999, 2 );
		add_filter( 'woocommerce_short_description', [ $this, 'maybe_hide_short_description' ], 999 );

		// Hide add to cart / purchasing
		add_filter( 'woocommerce_is_purchasable', [ $this, 'maybe_make_not_purchasable' ], 999, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', [ $this, 'maybe_make_variation_not_purchasable' ], 999, 2 );

		// Bulk action handlers
		add_action( 'admin_post_wchpt_set_draft',   [ $this, 'handle_set_draft' ] );
		add_action( 'admin_post_wchpt_set_publish', [ $this, 'handle_set_publish' ] );
		add_action( 'admin_post_wchpt_set_price',   [ $this, 'handle_set_price' ] );

		// Admin notice
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
	}

		public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			'Hide Prices & Tools',
			'Hide Prices',
			'manage_woocommerce',
			'wchpt-hide-prices',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'wchpt_group', self::OPTION_KEY, [ $this, 'sanitize_settings' ] );

		/* ========== SETTINGS SECTION (printed inside settings form) ========== */
		add_settings_section(
			'wchpt_section_main',
			'Visibility Settings',
			function () {
				echo '<p>Hide/replace Price and Short Description. Optionally hide Add to cart too. Use Targeting to decide what products are affected.</p>';
			},
			'wchpt'
		);
