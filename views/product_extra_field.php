<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

wp_nonce_field(
	'license_and_registration_domain',
	'license_and_registration_domain_nonce'
);
?>

<div class="license_and_registration_extra_product_fields">
	<label
		for="license_and_registration_add_to_cart_domain_name_field"
		style="font-weight: bold;"
	>
		<?php esc_html_e( 'Enter Your Domain Name', 'license-and-registration' ); ?>
	</label>
	<input
		id="license_and_registration_add_to_cart_domain_name_field"
		name="license_and_registration_add_to_cart_domain_name"
		type="text"
		placeholder="<?php esc_attr_e( 'example.is', 'license-and-registration' ); ?>"
		required
		style="padding: 1em; font-size: 1em;"
	/>
</div>

<p>
	<?php
	echo (
		sprintf(
			esc_html__(
				'The plugin is licensed on a %1$sper-domain basis%2$s and you will receive an activation code after checkout. The license key is specific to your site and you can use in any development or staging environment.',
				'license-and-registration'
			),
			'<strong>',
			'</strong>'
		)
	);
	?>
</p>
