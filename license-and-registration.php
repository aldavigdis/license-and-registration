<?php

/**
 * Plugin Name: License and Registration
 * Plugin URI: https://github.com/aldavigdis/license-and-registration/
 * Description: Sell cryptographically secure licenses for your monetised apps in your WooCommerce store
 * Version: 0.0.1
 * Requires at least: 6.1.5
 * Requires PHP: 8.2
 * Author: Alda Vigdis
 * Author URI: https://aldavigdis.is
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: license-and-registration
 * Requires Plugins: woocommerce
 */

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

use AldaVigdis\LicenseAndRegistration\KennitalaField;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

new VariationFields();
new KennitalaField();
