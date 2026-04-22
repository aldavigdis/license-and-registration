<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

use AldaVigdis\ConnectorForDK\Config;
use AldaVigdis\ConnectorForDK\Admin;
use WP_Error;
use WC_Customer;
use WC_Order;
use WP_Post;

/**
 * The Kennitala Field hook class
 *
 * This adds a kennitala field to the checkout form and assigns it to the order.
 *
 * We have to add the kennitala field to three different places and handle them
 * differently each time. First of all, it's the "classic" shortcode based
 * checkout form and then there's the more recent block-based checkout form.
 *
 * The third one is the user profile editor, that requires its own thing in
 * addition to "protecting" the kennitala meta value so that it does not appear
 * in the Custom Fields metabox and overrides things when trying to edit the
 * value.
 *
 * It's not nice (or cheap) having to do things three times over during a
 * transition period like that, but it's not like Gutenberg hasn't been out for
 * 6 years already!
 *
 * Support for adding custom text fields to the new block based checkout page is
 * currently considered experimental by Automattic/WooCommerce and the relevant
 * function does not support assigning a value to fields that are added using
 * their method.
 *
 * I have chimed in to their open conversation on Github but it feels futile as
 * it looks like their developers don't understand why one would want to assign
 * a default value to a text field. I wish them good luck in their endevours.
 *
 * @link https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/checkout-block/additional-checkout-fields.md
 * @link https://github.com/woocommerce/woocommerce/discussions/42995?sort=new#discussioncomment-8959477
 */
class KennitalaField {
	const KENNITALA_PATTERN                 = '^([0-9]{6}(-|\s)?[0-9]{4})$';
	const SANITIZED_KENNITALA_PATTERN       = '[^0-9]';
	const SANITIZED_KENNITALA_PATTERN_ALPHA = '[^0-9A-Z]';

	/**
	 * The class constructor, obviously
	 */
	public function __construct() {
		add_action(
			'woocommerce_after_checkout_billing_form',
			array( __CLASS__, 'render_classic_checkout_field' ),
			10,
			0
		);

		add_action(
			'woocommerce_after_checkout_validation',
			array( __CLASS__, 'check_classic_checkout_field' ),
			10,
			2
		);

		add_action(
			'woocommerce_checkout_update_order_meta',
			array( __CLASS__, 'save_classic_checkout_field' ),
			10,
			1
		);

		add_action(
			'woocommerce_blocks_loaded',
			array( __CLASS__, 'register_block_checkout_field' ),
			10,
			0
		);

		add_filter(
			'woocommerce_order_get_formatted_billing_address',
			array( __CLASS__, 'add_kennitala_to_formatted_billing_address' ),
			10,
			3
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			array( __CLASS__, 'set_billing_kennitala_meta' ),
			10,
			1
		);

		add_filter(
			'woocommerce_customer_meta_fields',
			array( __CLASS__, 'add_field_to_user_profile' ),
		);

		add_filter(
			'woocommerce_admin_billing_fields',
			array( __CLASS__, 'add_billing_fields_to_order_editor' ),
			10,
			1
		);

		add_filter(
			'woocommerce_admin_shipping_fields',
			array( __CLASS__, 'remove_shipping_fields_from_order_editor' ),
			200,
			1
		);

		add_action(
			'woocommerce_process_shop_order_meta',
			array( __CLASS__, 'update_order_meta' ),
			10,
			2
		);

		add_filter(
			'is_protected_meta',
			array( __CLASS__, 'protect_meta' ),
			10,
			2
		);

		add_action(
			'order_edit_form_top',
			array( __CLASS__, 'add_nonce_to_order_editor' ),
			10,
			2
		);

		add_action(
			'connector_for_dk_end_of_invoices_section',
			array( __CLASS__, 'add_partial_to_admin' ),
			10,
			0
		);
	}

	/**
	 * Render the kennitala admin partial
	 */
	public static function add_partial_to_admin(): void {
		$view_path = '/views/admin_sections/kennitala.php';
		require dirname( __DIR__ ) . $view_path;
	}

	/**
	 * Add our own nonce field to the post editor
	 */
	public static function add_nonce_to_order_editor(): void {
		wp_nonce_field(
			'connector_for_dk_edit_order',
			'connector_for_dk_edit_order_nonce_field',
		);
	}

	/**
	 * Protect the 'billing_kennitala' meta value
	 *
	 * This prevents the kennitala value from appearing in the Custom Fields
	 * metabox, overriding the order editor.
	 *
	 * @param bool   $protected Wether the meta value is already protected.
	 * @param string $meta_key The meta key.
	 */
	public static function protect_meta(
		bool $protected,
		string $meta_key
	): bool {
		if ( $meta_key === '_billing_kennitala' ) {
			return true;
		}

		return $protected;
	}

	/**
	 * Update the kennitala meta field when an order has been edited
	 *
	 * Used for the `woocommerce_process_shop_order_meta` hook, as the order
	 * meta is being processed.
	 *
	 * @param int              $post_id The order ID (unused).
	 * @param WP_Post|WC_Order $wc_order The order object.
	 */
	public static function update_order_meta(
		int $post_id,
		WP_Post|WC_Order $wc_order
	): void {
		if ( ! isset( $_POST['connector_for_dk_edit_order_nonce_field'] ) ) {
			return;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['connector_for_dk_edit_order_nonce_field']
					)
				),
				'connector_for_dk_edit_order'
			)
		) {
			return;
		}

		if ( isset( $_POST['_billing_kennitala'] ) ) {
			$kennitala = sanitize_text_field(
				wp_unslash( $_POST['_billing_kennitala'] )
			);

			$sanitized_kennitala = self::sanitize_kennitala( $kennitala );

			$wc_order->delete_meta_data( 'billing_kennitala' );
			$wc_order->delete_meta_data( '_billing_kennitala' );
			$wc_order->delete_meta_data( '_wc_other/connector_for_dk/kennitala' );

			$wc_order->update_meta_data(
				'_billing_kennitala',
				$sanitized_kennitala
			);

			$wc_order->save_meta_data();
		}
	}

	/**
	 * Add billing fields to the order editor
	 *
	 * This adds the kennitala text input and the "Invoice with Kennitala
	 * Requested" checkbox to the editor.
	 *
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-admin-meta-boxes-class-wc-meta-box-order-data.html
	 *
	 * @param array $fields The billing fields array to filter.
	 */
	public static function add_billing_fields_to_order_editor(
		array $fields,
	): array {
		$additional_fields = array(
			'kennitala' => array(
				'label' => __( 'Kennitala', 'license-and-registration' ),
				'show'  => false,
			),
		);

		return array_merge( $additional_fields, $fields );
	}

	/**
	 * Remove our fields from the order editor's shipping UI
	 *
	 * WooCommerce automatically assigns meta field outside of their own and the
	 * global namespace to the shipping section. This removes our meta fields
	 * from that part of the UI.
	 *
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-admin-meta-boxes-class-wc-meta-box-order-data.html
	 *
	 * @param array $fields The shipping fields array to filter.
	 */
	public static function remove_shipping_fields_from_order_editor(
		array $fields,
	): array {
		unset( $fields['connector_for_dk/kennitala'] );
		unset( $fields['connector_for_dk/kennitala_invoice_requested'] );
		return $fields;
	}

	/**
	 * Add a kennitala field to the user profile page
	 *
	 * This is used for the `woocommerce_customer_meta_fields` filter and adds
	 * the kennitala field to the "billing" section of the WooCommerce fields
	 * in the user profile editor.
	 *
	 * @param array $fields The original fields as they enter the
	 *                      `woocommerce_customer_meta_fields` filter.
	 */
	public static function add_field_to_user_profile( array $fields ): array {
		$billing = array_merge(
			array_slice( $fields['billing']['fields'], 0, 2 ),
			array(
				'kennitala' => array(
					'label'       => __( 'Kennitala', 'license-and-registration' ),
					'description' => '',
				),
			),
			array_slice( $fields['billing']['fields'], 2 )
		);

		$new_fields = $fields;

		$new_fields['billing']['fields'] = $billing;

		return $new_fields;
	}

	/**
	 * Set the billing order meta
	 *
	 * Takes the meta values set by the block editor fields and assigns them to
	 * _billing_kennitala and _billing_kennitala_invoice_requested.
	 *
	 * @param WC_Order $wc_order The order.
	 */
	public static function set_billing_kennitala_meta(
		WC_Order $wc_order
	): void {
		$block_field_kennitala = $wc_order->get_meta(
			'_wc_other/connector_for_dk/kennitala',
			true,
			'edit'
		);

		if ( $wc_order->get_customer_id() !== 0 ) {
			$customer = new WC_Customer( $wc_order->get_customer_id() );

			$customer_kennitala = self::sanitize_kennitala(
				$customer->get_meta( 'kennitala', true, 'edit' )
			);
		}

		$block_field_kennitala_invoice_requested = $wc_order->get_meta(
			'_wc_other/connector_for_dk/kennitala_invoice_requested',
			true,
			'edit'
		);

		if ( ! empty( $block_field_kennitala ) ) {
			$wc_order->update_meta_data(
				'_billing_kennitala',
				self::sanitize_kennitala( $block_field_kennitala )
			);
		} elseif ( ! empty( $customer_kennitala ) ) {
			$wc_order->update_meta_data(
				'_billing_kennitala',
				self::sanitize_kennitala( $customer_kennitala )
			);
		}

		$wc_order->save_meta_data();
	}

	/**
	 * Add a formated kennitala to a formatted billing address
	 *
	 * This is for the `woocommerce_order_get_formatted_billing_address` hook
	 * and adds a kennitala line to the formatted address as the 2nd line of
	 * the billing address.
	 *
	 * This is only assumed to be used in the chekcout confirmation and other
	 * user-facing parts, so it is disabled in the admin interface as we use a
	 * different method in the order editor.
	 *
	 * @param string   $address_data The original string containing the
	 *                               formatted address.
	 * @param array    $raw_address The address elements as an array (unused).
	 * @param WC_Order $wc_order The order object we pick the kennitala meta from.
	 */
	public static function add_kennitala_to_formatted_billing_address(
		string $address_data,
		array $raw_address,
		WC_Order $wc_order
	): string {
		if (
			$wc_order->get_billing_country() !==
			wc_get_base_location()['country']
		) {
			return $address_data;
		}

		$kennitala = $wc_order->get_meta( '_billing_kennitala', true );

		if ( empty( $kennitala ) ) {
			return $address_data;
		}

		$formatted_kennitala = self::sanitize_kennitala( $kennitala, true );

		$formatted_array = explode( '<br/>', $address_data );

		return implode(
			'<br/>',
			array_merge(
				array_slice( $formatted_array, 0, 1 ),
				array( 'Kt. ' . $formatted_kennitala ),
				array_slice( $formatted_array, 1 )
			)
		);
	}

	/**
	 * Render a kennitala field in the shortcode based checkout page
	 *
	 * If the current user has a kennitala assigned, a paragraph is displayed,
	 * indicating that it will be assigned automatically instead of the text
	 * input.
	 */
	public static function render_classic_checkout_field(): void {
		$customer = new WC_Customer( get_current_user_id() );

		$customer_kennitala = esc_attr(
			$customer->get_meta( 'kennitala', true, 'edit' )
		);

		wp_nonce_field(
			'connector_for_dk_classic_checkout_set_kennitala',
			'connector_for_dk_classic_checkout_set_kennitala_nonce_field'
		);

		woocommerce_form_field(
			'billing_kennitala',
			array(
				'default'           => $customer_kennitala,
				'required'          => true,
				'id'                => 'connector_for_dk_checkout_kennitala',
				'type'              => 'text',
				'label'             => __(
					'Kennitala',
					'license-and-registration'
				),
				'custom_attributes' => array(
					'pattern'   => self::KENNITALA_PATTERN,
					'minlength' => '10',
					'maxlength' => '10',
				),
			)
		);
	}

	/**
	 * Validate the kennitala input from the shortcode-based checkout page
	 *
	 * @param array    $data The data array (unused).
	 * @param WP_Error $errors The errors object.
	 */
	public static function check_classic_checkout_field(
		array $data,
		WP_Error $errors
	): void {
		if ( ! isset( $_POST['connector_for_dk_classic_checkout_set_kennitala_nonce_field'] ) ) {
			return;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['connector_for_dk_classic_checkout_set_kennitala_nonce_field']
					)
				),
				'connector_for_dk_classic_checkout_set_kennitala'
			)
		) {
			$errors->add(
				'invalid_kennitala_nonce',
				__( 'Invalid kennitala nonce', 'license-and-registration' )
			);
		}

		if ( isset( $_POST['billing_kennitala'] ) ) {
			$kennitala = sanitize_text_field(
				wp_unslash( $_POST['billing_kennitala'] )
			);

			$sanitized_kennitala = self::sanitize_kennitala( $kennitala );

			if ( ! empty( $sanitized_kennitala ) ) {
				$validation = self::validate_kennitala( $sanitized_kennitala );

				if ( $validation instanceof WP_Error ) {
					$errors->add(
						$validation->get_error_code(),
						$validation->get_error_message()
					);
				}
			}

			$errors->add(
				'kennitala_not_set',
				__( 'Kennitala is a required field', 'license-and-registration' )
			);
		}
	}

	/**
	 * Save the kennitala from the "classic" checkout process
	 *
	 * This is used by the `woocommerce_checkout_update_order_meta` hook.
	 *
	 * A nonce verification is not required here as WooCommerce has already
	 * taken care of that for us at this point.
	 *
	 * @param int $order_id The order id.
	 */
	public static function save_classic_checkout_field( int $order_id ): void {
		if ( ! isset( $_POST['connector_for_dk_classic_checkout_set_kennitala_nonce_field'] ) ) {
			return;
		}

		if (
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['connector_for_dk_classic_checkout_set_kennitala_nonce_field']
					)
				),
				'connector_for_dk_classic_checkout_set_kennitala'
			)
		) {
			wp_die( 'Kennitala nonce not valid!' );
			return;
		}

		$wc_order = new WC_Order( $order_id );

		if ( isset( $_POST['billing_kennitala'] ) && ! empty( $_POST['billing_kennitala'] ) ) {
			$kennitala = sanitize_text_field(
				wp_unslash( $_POST['billing_kennitala'] )
			);

			$sanitized_kennitala = self::sanitize_kennitala( $kennitala );
		} else {
			$customer = new WC_Customer( get_current_user_id() );

			$sanitized_kennitala = self::sanitize_kennitala(
				$customer->get_meta( 'kennitala', true, 'edit' ),
				true
			);
		}

		$wc_order->update_meta_data(
			'_billing_kennitala',
			$sanitized_kennitala
		);

		$wc_order->save_meta_data();
	}

	/**
	 * Register a kennitala checkout field for the block-based checkout page
	 *
	 * WooCommerce 8.7 adds a new block-based checkout page. This renders
	 * previously used ways of adding extra fields such as one for the Icelandic
	 * Kennitala pretty much useless.
	 *
	 * Running this function using the `woocommerce_blocks_loaded` hook adds
	 * a kennitala field to the block-based checkout page.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/checkout-block/additional-checkout-fields.md
	 */
	public static function register_block_checkout_field(): void {
		$customer = new WC_Customer( get_current_user_id() );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'connector_for_dk/kennitala',
				'required'          => true,
				'label'             => __(
					'Kennitala',
					'license-and-registration'
				),
				'optionalLabel'     => __(
					'Kennitala (Optional)',
					'license-and-registration'
				),
				'location'          => 'order',
				'type'              => 'text',
				'sanitize_callback' => array( __CLASS__, 'sanitize_kennitala' ),
				'validate_callback' => array( __CLASS__, 'validate_kennitala' ),
				'attributes'        => array(
					'autocomplete' => 'kennitala',
					'pattern'      => self::KENNITALA_PATTERN,
				),
			)
		);
	}

	/**
	 * Sanitize the kennitala input
	 *
	 * Kennitala is a 10-digit numeric string. This strips everything but
	 * numbers from the provided string and returns only the numbers from it.
	 *
	 * @param string $kennitala The unsanitized kennitala.
	 * @param bool   $allow_alpha True to allow alphabetical characters.
	 *
	 * @return string The sanitized kennitala.
	 */
	public static function sanitize_kennitala(
		string $kennitala,
		bool $allow_alpha = false
	): string {
		if ( $allow_alpha ) {
			return preg_replace(
				'/' . self::SANITIZED_KENNITALA_PATTERN_ALPHA . '/',
				'',
				strtoupper( $kennitala )
			);
		}

		return preg_replace(
			'/' . self::SANITIZED_KENNITALA_PATTERN . '/',
			'',
			$kennitala
		);
	}

	/**
	 * Validate kennitala input
	 *
	 * @param string $kennitala The (hopefully sanitized) kennitala.
	 *
	 * @return ?WP_Error A WP_Error object is generated if the kennitala
	 *                   is invalid, containing a code and a further explainer.
	 */
	public static function validate_kennitala(
		string $kennitala
	): null|WP_Error {
		if (
			preg_match_all(
				'/' . self::KENNITALA_PATTERN . '/',
				$kennitala
			) === 0 ||
			strlen( $kennitala ) !== 10
		) {
			return new WP_Error(
				'invalid_kennitala',
				__(
					'Invalid kennitala. A kennitala is a string of 10 numeric characters.',
					'license-and-registration'
				),
			);
		}

		return null;
	}

	/**
	 * Format a kennitala string
	 *
	 * This simply adds a divider between the birthdate portion and the rest of
	 * the kennitala. The default is a dash.
	 *
	 * @param string $kennitala The original, sanitized, unformatted kennitala.
	 * @param string $divider The divider to use (defaults to `-`).
	 */
	public static function format_kennitala(
		string $kennitala,
		string $divider = '-'
	): string {
		if ( empty( $kennitala ) ) {
			return '';
		}

		$sanitized_kennitala = self::sanitize_kennitala( $kennitala );

		$first_six = substr( $sanitized_kennitala, 0, 6 );
		$last_four = substr( $sanitized_kennitala, 6, 4 );

		return $first_six . $divider . $last_four;
	}

	/**
	 * Get the billing kennitala from an order
	 *
	 * @param WC_Order $wc_order The order.
	 */
	public static function get_kennitala_from_order(
		WC_Order $wc_order,
	): string {
		$block_kennitala = $wc_order->get_meta(
			'_wc_other/connector_for_dk/kennitala',
			true
		);

		if ( ! empty( $block_kennitala ) ) {
			return (string) $block_kennitala;
		}

		$classic_kennitala = $wc_order->get_meta( 'billing_kennitala', true );

		if ( ! empty( $classic_kennitala ) ) {
			return (string) $classic_kennitala;
		}

		$customer_id = $wc_order->get_customer_id();
		if ( $customer_id !== 0 ) {
			$customer           = new WC_Customer( $customer_id );
			$customer_kennitala = $customer->get_meta(
				'kennitala',
				true,
				'edit'
			);

			if ( ! empty( $customer_kennitala ) ) {
				return $customer_kennitala;
			}
		}

		$billing_kennitala = $wc_order->get_meta(
			'billing_kennitala',
			true,
			'edit'
		);

		if ( ! empty( $billing_kennitala ) ) {
			return $billing_kennitala;
		}

		return '';
	}
}
