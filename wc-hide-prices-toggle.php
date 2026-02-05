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

		add_settings_field( 'enabled', 'Enable hiding prices', [ $this, 'field_enabled' ], 'wchpt', 'wchpt_section_main' );
		add_settings_field( 'hide_short_desc', 'Enable hiding short description', [ $this, 'field_hide_short_desc' ], 'wchpt', 'wchpt_section_main' );
		add_settings_field( 'hide_add_to_cart', 'Enable hiding Add to cart', [ $this, 'field_hide_add_to_cart' ], 'wchpt', 'wchpt_section_main' );

		add_settings_field( 'targeting_mode', 'Targeting', [ $this, 'field_targeting_mode' ], 'wchpt', 'wchpt_section_main' );
		add_settings_field( 'product_list', 'Product IDs / SKUs (one per line)', [ $this, 'field_product_list' ], 'wchpt', 'wchpt_section_main' );
		add_settings_field( 'category_ids', 'Categories', [ $this, 'field_categories' ], 'wchpt', 'wchpt_section_main' );

		add_settings_field( 'replacement_text', 'Price replacement text (optional)', [ $this, 'field_replacement_text' ], 'wchpt', 'wchpt_section_main' );
		add_settings_field( 'short_desc_replacement', 'Short description replacement (optional)', [ $this, 'field_short_desc_replacement' ], 'wchpt', 'wchpt_section_main' );

		/* ========== BULK SECTION (printed OUTSIDE settings form) ========== */
		add_settings_section(
			'wchpt_section_bulk',
			'Bulk Actions',
			function () {
				echo '<p>Run one-time actions on all matched products (based on Targeting settings you saved above).</p>';
			},
			'wchpt_bulk'
		);

		add_settings_field(
			'bulk_actions',
			'Status + Price bulk tools',
			[ $this, 'field_bulk_actions' ],
			'wchpt_bulk',
			'wchpt_section_bulk'
		);
	}

	public function sanitize_settings( $input ) {
		$category_ids = [];
		if ( isset( $input['category_ids'] ) ) {
			$raw = $input['category_ids'];
			if ( is_array( $raw ) ) {
				$category_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
			} else {
				$category_ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) ) ) );
			}
		}

		$allowed_modes = [ 'all', 'specific_products', 'categories', 'products_or_categories' ];
		$mode = isset( $input['targeting_mode'] ) ? (string) $input['targeting_mode'] : 'all';
		if ( ! in_array( $mode, $allowed_modes, true ) ) $mode = 'all';

		return [
			'enabled'                => ! empty( $input['enabled'] ) ? 1 : 0,
			'hide_short_desc'        => ! empty( $input['hide_short_desc'] ) ? 1 : 0,
			'hide_add_to_cart'       => ! empty( $input['hide_add_to_cart'] ) ? 1 : 0,

			'targeting_mode'         => $mode,
			'product_list'           => isset( $input['product_list'] ) ? trim( wp_unslash( $input['product_list'] ) ) : '',
			'category_ids'           => $category_ids,

			'replacement_text'       => isset( $input['replacement_text'] ) ? sanitize_text_field( wp_unslash( $input['replacement_text'] ) ) : '',
			'short_desc_replacement' => isset( $input['short_desc_replacement'] ) ? wp_kses_post( wp_unslash( $input['short_desc_replacement'] ) ) : '',
		];
	}

	private function get_settings() {
		$defaults = [
			'enabled'                => 0,
			'hide_short_desc'        => 0,
			'hide_add_to_cart'       => 0,

			'targeting_mode'         => 'all',
			'product_list'           => '',
			'category_ids'           => [],

			'replacement_text'       => '',
			'short_desc_replacement' => '',
		];

		$s = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( is_array( $s ) ? $s : [], $defaults );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		echo '<div class="wrap"><h1>WooCommerce Hide Prices & Tools</h1>';

		// SETTINGS FORM (no nested forms inside)
		echo '<form method="post" action="options.php">';
		settings_fields( 'wchpt_group' );
		do_settings_sections( 'wchpt' );
		submit_button( 'Save Settings' );
		echo '</form>';

		// BULK ACTIONS (rendered outside of the settings form)
		echo '<hr style="margin:18px 0;">';
		do_settings_sections( 'wchpt_bulk' );

		echo '</div>';
	}

	/* ===================== Settings fields ===================== */

	public function field_enabled() {
		$s = $this->get_settings();
		printf(
			'<label><input type="checkbox" name="%s[enabled]" value="1" %s> Hide prices on the frontend</label>',
			esc_attr( self::OPTION_KEY ),
			checked( 1, (int) $s['enabled'], false )
		);
	}

	public function field_hide_short_desc() {
		$s = $this->get_settings();
		printf(
			'<label><input type="checkbox" name="%s[hide_short_desc]" value="1" %s> Hide/replace product short description on the frontend</label>',
			esc_attr( self::OPTION_KEY ),
			checked( 1, (int) $s['hide_short_desc'], false )
		);
	}

	public function field_hide_add_to_cart() {
		$s = $this->get_settings();
		printf(
			'<label><input type="checkbox" name="%s[hide_add_to_cart]" value="1" %s> Hide/disable Add to cart for matched products</label><p class="description">This makes matched products not purchasable (hides add-to-cart buttons and disables purchase).</p>',
			esc_attr( self::OPTION_KEY ),
			checked( 1, (int) $s['hide_add_to_cart'], false )
		);
	}

	public function field_targeting_mode() {
		$s = $this->get_settings();
		$name = esc_attr( self::OPTION_KEY ) . '[targeting_mode]';
		?>
		<label style="display:block;margin-bottom:6px;">
			<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="all" <?php checked( $s['targeting_mode'], 'all' ); ?>>
			Apply to <strong>all products</strong>
		</label>
		<label style="display:block;margin-bottom:6px;">
			<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="specific_products" <?php checked( $s['targeting_mode'], 'specific_products' ); ?>>
			Apply to <strong>specific products</strong> (IDs/SKUs)
		</label>
		<label style="display:block;margin-bottom:6px;">
			<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="categories" <?php checked( $s['targeting_mode'], 'categories' ); ?>>
			Apply to <strong>selected categories</strong>
		</label>
		<label style="display:block;">
			<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="products_or_categories" <?php checked( $s['targeting_mode'], 'products_or_categories' ); ?>>
			Apply when <strong>product matches IDs/SKUs OR selected categories</strong>
		</label>
		<?php
	}

	public function field_product_list() {
		$s = $this->get_settings();
		printf(
			'<textarea name="%s[product_list]" rows="8" cols="50" class="large-text code" placeholder="Examples:\n123\nSKU-ABC\n456">%s</textarea><p class="description">Used when targeting includes products. One per line.</p>',
			esc_attr( self::OPTION_KEY ),
			esc_textarea( $s['product_list'] )
		);
	}

	public function field_categories() {
		$s = $this->get_settings();
		$selected = is_array( $s['category_ids'] ) ? $s['category_ids'] : [];

		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p class="description">No product categories found.</p>';
			return;
		}

		echo '<div style="max-height:240px;overflow:auto;border:1px solid #ccd0d4;padding:10px;background:#fff;">';
		foreach ( $terms as $t ) {
			printf(
				'<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="%s[category_ids][]" value="%d" %s>
					%s <span style="color:#666;">(#%d)</span>
				</label>',
				esc_attr( self::OPTION_KEY ),
				(int) $t->term_id,
				checked( in_array( (int) $t->term_id, $selected, true ), true, false ),
				esc_html( $t->name ),
				(int) $t->term_id
			);
		}
		echo '</div>';
		echo '<p class="description">Used when targeting includes categories.</p>';
	}

	public function field_replacement_text() {
		$s = $this->get_settings();
		printf(
			'<input type="text" name="%s[replacement_text]" value="%s" class="regular-text" placeholder="e.g. Login to see price">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['replacement_text'] )
		);
		echo '<p class="description">If empty, price HTML is removed.</p>';
	}

	public function field_short_desc_replacement() {
		$s = $this->get_settings();
		printf(
			'<textarea name="%s[short_desc_replacement]" rows="5" class="large-text" placeholder="e.g. Contact us for details">%s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_textarea( $s['short_desc_replacement'] )
		);
		echo '<p class="description">If empty, short description is removed. Basic HTML allowed.</p>';
	}

	public function field_bulk_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			echo '<p class="description">You do not have permission.</p>';
			return;
		}

		$url = admin_url( 'admin-post.php' );
		$nonce_field = wp_nonce_field( self::NONCE_KEY, '_wchpt_nonce', true, false );
		?>
		<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">
			<form method="post" action="<?php echo esc_url( $url ); ?>">
				<input type="hidden" name="action" value="wchpt_set_draft">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button type="submit" class="button button-secondary"
					onclick="return confirm('Move matched products from Published to Draft?');">
					Move Matched Products to Draft
				</button>
			</form>

			<form method="post" action="<?php echo esc_url( $url ); ?>">
				<input type="hidden" name="action" value="wchpt_set_publish">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button type="submit" class="button button-primary"
					onclick="return confirm('Publish matched products (Draft → Published)?');">
					Publish Matched Products
				</button>
			</form>
		</div>

		<hr style="margin:14px 0;">

		<form method="post" action="<?php echo esc_url( $url ); ?>" style="max-width:520px;">
			<input type="hidden" name="action" value="wchpt_set_price">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p style="margin:0 0 8px;"><strong>Bulk set Regular Price</strong> (for matched products)</p>

			<label style="display:block;margin:0 0 6px;">
				New Regular Price:
				<input type="number" step="0.01" min="0" name="wchpt_new_regular_price" class="regular-text" required>
			</label>

			<label style="display:block;margin:0 0 10px;">
				<input type="checkbox" name="wchpt_clear_sale" value="1">
				Clear Sale Price
			</label>

			<button type="submit" class="button"
				onclick="return confirm('This will update the ACTUAL product prices for all matched products. Continue?');">
				Bulk Update Prices
			</button>

			<p class="description" style="margin-top:8px;">
				Simple products: updates regular price and price. Variable products: updates each variation and re-syncs.
			</p>
		</form>
		<?php
	}

	/* ===================== Targeting ===================== */

	private function should_apply_to_product( $product ) {
		$s = $this->get_settings();
		$mode = (string) $s['targeting_mode'];

		if ( $mode === 'all' ) return true;

		$match_products = false;
		$match_categories = false;

		$list = $this->parse_list( $s['product_list'] );
		if ( ! empty( $list ) ) $match_products = $this->product_matches_list( $product, $list );

		$cats = is_array( $s['category_ids'] ) ? $s['category_ids'] : [];
		if ( ! empty( $cats ) ) $match_categories = $this->product_in_selected_categories( $product, $cats );

		if ( $mode === 'specific_products' ) return $match_products;
		if ( $mode === 'categories' ) return $match_categories;

		return ( $match_products || $match_categories );
	}

	/* ===================== Frontend: price + short desc ===================== */

	public function maybe_hide_price_html( $price_html, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) return $price_html;

		$s = $this->get_settings();
		if ( empty( $s['enabled'] ) ) return $price_html;

		if ( $this->should_apply_to_product( $product ) ) {
			$text = trim( (string) $s['replacement_text'] );
			return ( $text !== '' )
				? '<span class="price wchpt-hidden-price">' . esc_html( $text ) . '</span>'
				: '';
		}

		return $price_html;
	}

	public function maybe_hide_short_description( $short_desc ) {
		if ( is_admin() && ! wp_doing_ajax() ) return $short_desc;

		$s = $this->get_settings();
		if ( empty( $s['hide_short_desc'] ) ) return $short_desc;

		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) return $short_desc;

		if ( $this->should_apply_to_product( $product ) ) {
			$replacement = trim( (string) $s['short_desc_replacement'] );
			return ( $replacement !== '' )
				? '<div class="woocommerce-product-details__short-description wchpt-hidden-short-desc">' . $replacement . '</div>'
				: '';
		}

		return $short_desc;
	}

	/* ===================== Frontend: hide add-to-cart ===================== */

	public function maybe_make_not_purchasable( $purchasable, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) return $purchasable;

		$s = $this->get_settings();
		if ( empty( $s['hide_add_to_cart'] ) ) return $purchasable;

		if ( $product && $this->should_apply_to_product( $product ) ) return false;
		return $purchasable;
	}

	public function maybe_make_variation_not_purchasable( $purchasable, $variation ) {
		return $this->maybe_make_not_purchasable( $purchasable, $variation );
	}

	/* ===================== Bulk actions ===================== */

	public function handle_set_draft()   { $this->handle_bulk_status_change( 'draft', 'publish' ); }
	public function handle_set_publish() { $this->handle_bulk_status_change( 'publish', 'draft' ); }

	private function handle_bulk_status_change( $to_status, $from_status ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Permission denied.' );

		if ( ! isset( $_POST['_wchpt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wchpt_nonce'] ) ), self::NONCE_KEY ) ) {
			wp_die( 'Invalid nonce.' );
		}

		$ids = $this->get_matched_product_ids_for_bulk();
		$updated = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== 'product' ) continue;
			if ( $post->post_status !== $from_status ) continue;

			$res = wp_update_post(
				[
					'ID'          => (int) $id,
					'post_status' => $to_status,
				],
				true
			);

			if ( ! is_wp_error( $res ) ) $updated++;
		}

		$this->redirect_with_notice( 'status', $updated, $to_status );
	}

	public function handle_set_price() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Permission denied.' );

		if ( ! isset( $_POST['_wchpt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wchpt_nonce'] ) ), self::NONCE_KEY ) ) {
			wp_die( 'Invalid nonce.' );
		}

		$new_price_raw = isset( $_POST['wchpt_new_regular_price'] ) ? wc_clean( wp_unslash( $_POST['wchpt_new_regular_price'] ) ) : '';
		$clear_sale    = ! empty( $_POST['wchpt_clear_sale'] );

		if ( $new_price_raw === '' || ! is_numeric( $new_price_raw ) ) {
			$this->redirect_with_notice( 'price', 0, 'invalid_price' );
		}

		$new_price = (float) $new_price_raw;
		if ( $new_price < 0 ) $new_price = 0.0;

		$ids = $this->get_matched_product_ids_for_bulk();
		$updated = 0;

		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( ! $product ) continue;

			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				foreach ( $children as $vid ) {
					$variation = wc_get_product( (int) $vid );
					if ( ! $variation ) continue;

					$variation->set_regular_price( (string) $new_price );
					if ( $clear_sale ) $variation->set_sale_price( '' );

					$variation->set_price( $variation->get_sale_price() !== '' ? $variation->get_sale_price() : $variation->get_regular_price() );
					$variation->save();
					$updated++;
				}

				WC_Product_Variable::sync( $product->get_id() );
				continue;
			}

			$product->set_regular_price( (string) $new_price );
			if ( $clear_sale ) $product->set_sale_price( '' );

			$product->set_price( $product->get_sale_price() !== '' ? $product->get_sale_price() : $product->get_regular_price() );
			$product->save();
			$updated++;
		}

		$this->redirect_with_notice( 'price', $updated, 'updated' );
	}

	private function redirect_with_notice( $type, $count, $extra ) {
		$redirect = add_query_arg(
			[
				'page'         => 'wchpt-hide-prices',
				'wchpt_notice' => sanitize_key( $type ),
				'wchpt_count'  => (int) $count,
				'wchpt_extra'  => sanitize_key( (string) $extra ),
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function maybe_show_notice() {
		if ( ! is_admin() ) return;
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wchpt-hide-prices' ) return;

		// Default WP notice after saving settings
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		if ( empty( $_GET['wchpt_notice'] ) ) return;

		$type  = sanitize_key( wp_unslash( $_GET['wchpt_notice'] ) );
		$count = isset( $_GET['wchpt_count'] ) ? absint( $_GET['wchpt_count'] ) : 0;
		$extra = isset( $_GET['wchpt_extra'] ) ? sanitize_key( wp_unslash( $_GET['wchpt_extra'] ) ) : '';

		$msg = '';
		if ( $type === 'status' ) {
			$label = ( $extra === 'publish' ) ? 'Published' : ( ( $extra === 'draft' ) ? 'Draft' : $extra );
			$msg = sprintf( 'Bulk status: %d product(s) were set to %s.', $count, $label );
		} elseif ( $type === 'price' ) {
			$msg = ( $extra === 'invalid_price' )
				? 'Bulk price: Invalid price provided. No products were updated.'
				: sprintf( 'Bulk price: updated for %d item(s) (products/variations).', $count );
		}

		if ( $msg !== '' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/* ===================== Bulk matching ===================== */

	private function get_matched_product_ids_for_bulk() {
		$s = $this->get_settings();
		$mode = (string) $s['targeting_mode'];

		$base = [
			'post_type'      => 'product',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'post_status'    => [ 'publish', 'draft' ],
		];

		$list    = $this->parse_list( $s['product_list'] );
		$cat_ids = is_array( $s['category_ids'] ) ? $s['category_ids'] : [];

		if ( $mode === 'all' ) return get_posts( $base );

		if ( $mode === 'specific_products' ) {
			$args = $this->apply_product_list_to_query_args( $base, $list );
			return get_posts( $args );
		}

		if ( $mode === 'categories' ) {
			$args = $this->apply_categories_to_query_args( $base, $cat_ids );
			return get_posts( $args );
		}

		// products_or_categories
		$ids_a = [];
		$ids_b = [];

		if ( ! empty( $list ) ) {
			$args_a = $this->apply_product_list_to_query_args( $base, $list );
			$ids_a  = get_posts( $args_a );
		}
		if ( ! empty( $cat_ids ) ) {
			$args_b = $this->apply_categories_to_query_args( $base, $cat_ids );
			$ids_b  = get_posts( $args_b );
		}

		return array_values( array_unique( array_merge( $ids_a, $ids_b ) ) );
	}

	private function apply_categories_to_query_args( $args, $cat_ids ) {
		$cat_ids = array_values( array_filter( array_map( 'absint', (array) $cat_ids ) ) );
		if ( empty( $cat_ids ) ) {
			$args['post__in'] = [ 0 ];
			return $args;
		}

		$args['tax_query'] = [
			[
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $cat_ids,
			],
		];

		return $args;
	}

	private function apply_product_list_to_query_args( $args, $list ) {
		$ids  = [];
		$skus = [];

		foreach ( (array) $list as $item ) {
			$item = trim( (string) $item );
			if ( $item === '' ) continue;

			if ( ctype_digit( $item ) ) $ids[] = (int) $item;
			else $skus[] = $item;
		}

		$ids  = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$skus = array_values( array_unique( array_filter( $skus ) ) );

		if ( ! empty( $ids ) && empty( $skus ) ) {
			$args['post__in'] = $ids;
			return $args;
		}

		if ( empty( $ids ) && ! empty( $skus ) ) {
			$args['meta_query'] = [
				[
					'key'     => '_sku',
					'value'   => $skus,
					'compare' => 'IN',
				],
			];
			return $args;
		}

		// Both: merge SKU matches + explicit IDs
		$args_sku = $args;
		$args_sku['meta_query'] = [
			[
				'key'     => '_sku',
				'value'   => $skus,
				'compare' => 'IN',
			],
		];
		$ids_from_skus = get_posts( $args_sku );

		$merged = array_values( array_unique( array_merge( $ids_from_skus, $ids ) ) );
		$args['post__in'] = $merged;

		return $args;
	}

	/* ===================== Helpers ===================== */

	private function parse_list( $raw ) {
		$raw = (string) $raw;
		$lines = preg_split( "/\r\n|\n|\r/", $raw );
		$out = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) continue;
			$out[] = $line;
		}
		return array_values( array_unique( $out ) );
	}

	private function product_matches_list( $product, $list ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return false;

		$id  = (string) $product->get_id();
		$sku = (string) $product->get_sku();

		if ( in_array( $id, $list, true ) ) return true;
		if ( $sku !== '' && in_array( $sku, $list, true ) ) return true;

		// Variation: match parent too
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (string) $product->get_parent_id();
			if ( $parent_id && in_array( $parent_id, $list, true ) ) return true;

			$parent = wc_get_product( (int) $parent_id );
			if ( $parent && $parent->get_sku() && in_array( (string) $parent->get_sku(), $list, true ) ) return true;
		}

		return false;
	}

	private function product_in_selected_categories( $product, $selected_cat_ids ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return false;

		$product_id = $product->get_id();
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id > 0 ) $product_id = $parent_id;
		}

		$term_ids = wc_get_product_term_ids( (int) $product_id, 'product_cat' );
		if ( empty( $term_ids ) ) return false;

		foreach ( $term_ids as $tid ) {
			if ( in_array( (int) $tid, (array) $selected_cat_ids, true ) ) return true;
		}
		return false;
	}
}
