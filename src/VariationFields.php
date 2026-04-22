<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

use AldaVigdis\LicenseAndRegistration\License;
use AldaVigdis\LicenseAndRegistration\Le\PDF417\PDF417 as PDF417PDF417;
use AldaVigdis\LicenseAndRegistration\Le\PDF417\Renderer\SvgRenderer;
use WC_Order_Item;
use WC_Order_Item_Product;
use WP_Post;
use WC_Order;

class VariationFields {
	const ACTUAL_MONTH_IN_SECONDS = 2628000;

	public function __construct() {
		add_action(
			'woocommerce_variation_options_download',
			array( __CLASS__, 'add_fields_to_variation_editor' ),
			10,
			3
		);

		add_action(
			'woocommerce_save_product_variation',
			array( __CLASS__, 'save_variation_meta' ),
			10,
			2
		);

		add_action(
			'woocommerce_after_variations_table',
			array( __CLASS__, 'add_domain_field_to_product_page' )
		);

		add_filter(
			'woocommerce_add_to_cart_validation',
			array( __CLASS__, 'add_to_cart_validation' ),
			10,
			3
		);

		add_action(
			'woocommerce_add_to_cart',
			array( __CLASS__, 'add_to_cart_set_meta' ),
			10,
			6
		);

		add_filter(
			'woocommerce_get_item_data',
			array( __CLASS__, 'add_domain_to_item_data' ),
			10,
			2
		);

		add_action(
			'woocommerce_checkout_create_order_line_item',
			array( __CLASS__, 'add_meta_to_order_item' ),
			10,
			4
		);

		add_filter(
			'woocommerce_order_item_display_meta_key',
			array( __CLASS__, 'filter_domain_display_key' ),
			10,
			3
		);

		add_filter(
			'woocommerce_order_item_display_meta_value',
			array( __CLASS__, 'filter_domain_display_value' ),
			10,
			3
		);

		add_filter(
			'woocommerce_hidden_order_itemmeta',
			array( __CLASS__, 'hide_itemmeta' ),
			10,
			1
		);

		add_action(
			'woocommerce_after_order_itemmeta',
			array( __CLASS__, 'display_code_textarea_in_order_editor' ),
			10,
			3
		);

		add_action(
			'woocommerce_order_details_after_order_table',
			array( __CLASS__, 'display_code_textarea_in_order_confirmation' ),
			999,
			1
		);

		add_action(
			'woocommerce_email_after_order_table',
			function ( WC_Order $order ): void {
				self::display_code_textarea_in_order_confirmation( $order, false );
			},
			999,
			1
		);

		add_action(
			'init',
			function (): void {
				remove_action(
					'woocommerce_order_details_after_order_table',
					'woocommerce_order_again_button'
				);
				remove_action(
					'storefront_before_content',
					'woocommerce_breadcrumb'
				);
			},
			10,
			0
		);

		add_action(
			'woocommerce_after_order_notes',
			array( __CLASS__, 'add_checkboxes_to_order' ),
			10,
			0
		);

		add_action(
			'woocommerce_checkout_update_order_meta',
			array( __CLASS__, 'save_order_checkboxes' ),
			10,
			1
		);

		add_filter(
			'woocommerce_add_to_cart_redirect',
			'wc_get_checkout_url',
			10,
			0
		);

		add_action(
			'wp_head',
			array( __CLASS__, 'apply_inline_css' ),
			10,
			0
		);

		add_action(
			'admin_head',
			array( __CLASS__, 'apply_inline_css' ),
			10,
			0
		);

		add_filter(
			'woocommerce_order_email_verification_required',
			'__return_false',
			10,
			0
		);
	}

	public static function add_fields_to_variation_editor(
		$loop,
		$variation_data,
		WP_Post $variation
	) {
		wp_nonce_field(
			'set_license_and_registration_expiry',
			'set_license_and_registration_expiry_nonce'
		);

		woocommerce_wp_select(
			array(
				'id'      => 'license_and_registration_product_expiry_' . $loop,
				'name'    => 'license_and_registration_product_expiry[' . $loop . ']',
				'value'   => $variation_data['license_and_registration_expiry'],
				'label'   => __( 'License Expiry', 'license-and-registration' ),
				'options' => array(
					'1'  => __( '1 Month', 'license-and-registration' ),
					'6'  => __( '6 Months', 'license-and-registration' ),
					'12' => __( '12 Months', 'license-and-registration' ),
					'24' => __( '24 Months', 'license-and-registration' ),
				),
			),
		);

		woocommerce_wp_text_input(
			array(
				'id'    => 'license_and_registration_product_code_' . $loop,
				'name'  => 'license_and_registration_product_code[' . $loop . ']',
				'value' => $variation_data['license_and_registration_product_code'][0],
				'label' => __( 'Product Code', 'license-and-registration' ),
			)
		);
	}

	public static function save_variation_meta(
		$variation_id,
		$i
	) {
		if (
			empty( $_POST['license_and_registration_product_code'][ $i ] ) ||
			empty( $_POST['license_and_registration_product_expiry'][ $i ] ) ||
			empty( $_POST['set_license_and_registration_expiry_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['set_license_and_registration_expiry_nonce']
					)
				),
				'set_license_and_registration_expiry'
			)
		) {
			return;
		}

		$expiry = intval(
			sanitize_text_field(
				wp_unslash(
					$_POST['license_and_registration_product_expiry'][ $i ]
				)
			)
		);

		$product_code = sanitize_text_field(
			wp_unslash(
				$_POST['license_and_registration_product_code'][ $i ]
			)
		);

		$variation = wc_get_product( $variation_id );

		$variation->update_meta_data(
			'license_and_registration_expiry',
			$expiry
		);

		$variation->update_meta_data(
			'license_and_registration_product_code',
			$product_code
		);

		$variation->save_meta_data();
	}

	public static function add_domain_field_to_product_page() {
		require dirname( __DIR__ ) . '/views/product_extra_field.php';
	}

	public static function add_to_cart_validation(
		$valid,
		$item,
		$quantity
	): bool {
		$domain_field_name = 'license_and_registration_add_to_cart_domain_name';

		if (
			! isset( $_REQUEST[ $domain_field_name ] ) &&
			! isset( $_REQUEST['license_and_registration_domain_nonce'] )
		) {
			return $valid;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_REQUEST['license_and_registration_domain_nonce']
					)
				),
				'license_and_registration_domain'
			)
		) {
			return false;
		}

		$domain = sanitize_text_field(
			wp_unslash(
				$_REQUEST[ $domain_field_name ]
			)
		);

		$filter_domain = filter_var( $domain, FILTER_VALIDATE_DOMAIN );

		if ( ! $filter_domain ) {
			return false;
		}

		return true;
	}

	public static function add_to_cart_set_meta(
		$cart_item_key,
		$product_id,
		$quantity,
		$variation_id,
		$variation,
		$cart_item_data,
	) {
		$domain_field_name = 'license_and_registration_add_to_cart_domain_name';
		$meta_key          = 'license_and_registration_domain_name';

		if (
			! isset( $_REQUEST[ $domain_field_name ] ) &&
			! isset( $_REQUEST['license_and_registration_domain_nonce'] )
		) {
			return;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_REQUEST['license_and_registration_domain_nonce']
					)
				),
				'license_and_registration_domain'
			)
		) {
			return;
		}

		$meta_value = sanitize_text_field(
			wp_unslash(
				$_REQUEST[ $domain_field_name ]
			)
		);

		WC()->cart->cart_contents[ $cart_item_key ][ $meta_key ] = $meta_value;
		WC()->cart->set_session();
	}

	public static function add_domain_to_item_data( $item_data, $cart_item ) {
		if (
			! array_key_exists(
				'license_and_registration_domain_name',
				$cart_item
			)
		) {
			return $item_data;
		}

		if ( ! is_array( $item_data ) ) {
			return $item_data;
		}

		$domain_name = $cart_item['license_and_registration_domain_name'];

		$additional_values = array(
			array(
				'key'     => 'Domain',
				'display' => $domain_name,
			),
		);

		return array_merge( $additional_values, $item_data );
	}

	public static function add_meta_to_order_item(
		WC_Order_Item_Product $item,
		$cart_item_key,
		$values,
		$order
	) {
		$cart        = WC()->cart->cart_contents;
		$cart_item   = $cart[ $cart_item_key ];
		$domain_name = $cart_item['license_and_registration_domain_name'];
		$product     = $item->get_product();
		$expiry      = intval(
			$product->get_meta( 'license_and_registration_expiry' )
		);

		$expires = time() + ( $expiry * self::ACTUAL_MONTH_IN_SECONDS );

		$license = new License(
			array( $product->get_sku() ),
			array(
				'domain'  => $domain_name,
				'expires' => $expires,
			),
		);

		$item->add_meta_data(
			'license_and_registration_expires',
			$expires
		);

		$item->add_meta_data(
			'_license_and_registration_license_code',
			$license->encrypt()
		);

		$item->add_meta_data(
			'license_and_registration_domain_name',
			$domain_name
		);

		$item->save_meta_data();
	}

	public static function filter_domain_display_key(
		$display_key,
		$meta,
		$item
	) {
		if ( $display_key === 'license_and_registration_domain_name' ) {
			return __( 'Domain', 'license-and-registration' );
		}

		if ( $display_key === 'license_and_registration_license_code' ) {
			return __( 'License Code', 'license-and-registration' );
		}

		if ( $display_key === 'license_and_registration_expires' ) {
			return __( 'License Expires', 'license-and-registration' );
		}

		return $display_key;
	}

	public static function filter_domain_display_value(
		$meta_value,
		$meta,
		$item
	) {
		if ( $meta->key === 'license_and_registration_expires' ) {
			return date( get_option( 'date_format' ), intval( $meta_value ) );
		}

		if ( $meta->key === '_license_and_registration_license_code' ) {
			return $meta_value;
		}

		return $meta_value;
	}

	public static function hide_itemmeta( $meta ) {
		$meta[] = '_license_and_registration_license_code';
		return $meta;
	}

	public static function display_code_textarea_in_order_editor(
		$item_id,
		WC_Order_Item $item,
		$product
	) {
		echo '<label for="license_code_';
		echo esc_html( $item_id );
		echo '">License Code</label>';

		self::display_code_textarea(
			$item_id,
			$item->get_meta( '_license_and_registration_license_code' )
		);
	}

	public static function order_has_license_code( WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			$code = $item->get_meta( '_license_and_registration_license_code' );
			if ( ! empty( $code ) ) {
				return true;
			}
		}

		return false;
	}

	public static function display_code_textarea_in_order_confirmation(
		WC_Order $order,
		bool $display_barcode = true
	) {
		if ( ! self::order_has_license_code( $order ) ) {
			return;
		}

		if ( $order->get_status( 'edit' ) !== 'completed' ) {
			return;
		}

		if ( $order->get_item_count() > 1 ) {
			echo '<h2>';
			esc_html_e( 'License Codes', 'license-and-registration' );
			echo '</h2>';
		}

		foreach ( $order->get_items() as $item ) {
			if ( $order->get_item_count() > 1 ) {
				echo '<h3>';
				echo $item->get_meta( 'license_and_registration_domain_name' );
				echo '</h3>';
			} else {
				echo '<h2>';
				echo esc_html(
					sprintf(
						__(
							'License Code for %s',
							'license-and-registration'
						),
						$item->get_meta(
							'license_and_registration_domain_name'
						)
					)
				);
				echo '</h2>';
			}

			if ( $display_barcode ) {
				echo '<img src="';
				echo esc_attr(
					self::code_pdf417_as_data_url(
						$item->get_meta( '_license_and_registration_license_code' )
					)
				);
				echo '" />';
			}

			echo '<p style="font-family: monospace; font-size: 0.8em; font-weight: bold;">';
			echo esc_html(
				$item->get_meta( '_license_and_registration_license_code' )
			);
			echo '</p>';
		}

		echo '<p><strong>Please keep this code safe!</strong> You will need to copy and paste it after installing Connector for DK in order to use the features it has to offer and to enable automatic updates during the validity of the license. We recommend printing this page or keeping the code in your password manager.</p>';
	}

	public static function display_code_textarea( $id, $code ) {
		echo '<textarea id="license_code_' . $id . '" rows="3" cols="40" style="font-family: monospace;">' . $code . '</textarea>';
	}

	public static function code_as_pdf417( $code ) {
		$pdf417 = new PDF417PDF417();

		$data = $pdf417->encode( $code );

		$svg_renderer = new SvgRenderer(
			array( 'ratio' => 1 )
		);

		return base64_encode( $svg_renderer->render( $data ) );
	}

	public static function code_pdf417_as_data_url( $data ) {
		$svg_code = self::code_as_pdf417( $data );

		return 'data:image/svg+xml;base64,' . $svg_code;
	}

	public static function add_checkboxes_to_order() {
		require dirname( __DIR__ ) . '/views/order_extra_fields.php';
	}

	public static function save_order_checkboxes( int $order_id ): void {
		if ( ! isset( $_POST['license_and_registration_order_nonce'] ) ) {
			return;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['license_and_registration_order_nonce']
					)
				),
				'license_and_registration_order'
			)
		) {
			wp_die( 'Kennitala nonce not valid!' );
			return;
		}

		$order = new WC_Order( $order_id );

		if ( isset( $_POST['license_and_registration_send_swag_checkbox'] ) ) {
			$order->update_meta_data( 'swag_ok', '1' );
		}

		if ( isset( $_POST['license_and_registration_marketing_mailing_list_checkbox'] ) ) {
			$order->update_meta_data( 'email_marketing_ok', '1' );
		}

		if ( isset( $_POST['license_and_registration_alert_mailing_list_checkbox'] ) ) {
			$order->update_meta_data( 'email_alerts_ok', '1' );
		}

		$order->save_meta_data();
	}

	public static function apply_inline_css() {
		echo '<style>';
		echo '*::selection { background-color: rgba(255, 208, 0, 0.29); color: #222; }';
		echo '#home-link { transform: rotateZ(-2deg); border: 1px solid #fff; border-radius: 3px; }';
		echo '.woocommerce-page main { max-width: none; }';
		echo '.license_and_registration_extra_product_fields { display: flex; flex-direction: column; }';
		echo '.postid-23 .license_and_registration_extra_product_fields label { font-size: 1.2em; }';
		echo '.wp-block-add-to-cart-with-options-quantity-selector { display: none; }';
		echo '.wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute-name { font-weight: bold; font-size: 1.2em; }';
		echo '.wc-block-components-product-price { text-align: right; }';
		echo '.wc-block-components-product-price del { display: block; font-size: 0.6em; color: #666; }';
		echo '</style>';
	}
}
