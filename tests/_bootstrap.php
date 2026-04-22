<?php

declare(strict_types = 1);

define( 'TEST_ENV', true );

require __DIR__ . '/../vendor/aldavigdis/wp-tests-strapon/bootstrap.php';
require __DIR__ . '/../vendor/woocommerce/woocommerce/woocommerce.php';
require __DIR__ . '/../vendor/woocommerce/wc-smooth-generator/wc-smooth-generator.php';

update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_default_country', 'DE' );
