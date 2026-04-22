<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

wp_nonce_field(
	'license_and_registration_order',
	'license_and_registration_order_nonce'
);

woocommerce_form_field(
	'license_and_registration_send_swag_checkbox',
	array(
		'type'  => 'checkbox',
		'label' => __( 'Send me trinkets — I would be happy to receive surprise gifts in the mail such as stickers, postcards and more', 'license-and-registration' ),
	)
);

woocommerce_form_field(
	'license_and_registration_marketing_mailing_list_checkbox',
	array(
		'type'  => 'checkbox',
		'label' => __( 'I would like to subscribe to your mailing list, for special offers, surveys and news about new features and updates', 'license-and-registration' ),
	)
);

woocommerce_form_field(
	'license_and_registration_alert_mailing_list_checkbox',
	array(
		'type'    => 'checkbox',
		'label'   => __( 'I would like to receive super important updates about security updates and license expiry via email', 'license-and-registration' ),
		'default' => '1',
	)
);
