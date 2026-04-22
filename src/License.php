<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration;

use AldaVigdis\LicenseAndRegistration\Ramsey\Uuid\Uuid;
use AldaVigdis\LicenseAndRegistration\Tuupola\Base85;
use OpenSSLAsymmetricKey;

class License {
	const VERSION         = '0.1';
	const CURRENT_KEYPAIR = '1761757900';

	private OpenSSLAsymmetricKey $private_key;
	public OpenSSLAsymmetricKey $public_key;
	public array $products;
	public array $attributes;
	public string $uuid;
	public int $timestamp;
	public int|null $expires;

	public function __construct(
		array $products,
		array $attributes,
	) {
		$this->private_key = openssl_pkey_get_private(
			file_get_contents(
				dirname( __DIR__ ) . '/keys/' . self::CURRENT_KEYPAIR
			)
		);

		$this->public_key = openssl_pkey_get_public(
			file_get_contents(
				dirname( __DIR__ ) . '/keys/' . self::CURRENT_KEYPAIR . '.pub'
			)
		);

		$this->products   = $products;
		$this->attributes = $attributes;

		$this->uuid      = self::generate_serial();
		$this->timestamp = time();
	}

	public function to_json(): string {
		return wp_json_encode(
			array(
				'version'    => self::VERSION,
				'uuid'       => $this->uuid,
				'products'   => $this->products,
				'attributes' => $this->attributes,
				'timestamp'  => $this->timestamp,
			)
		);
	}

	public function encrypt(): string {
		$base85           = new Base85();
		$encrypted_string = '';

		openssl_private_encrypt(
			$base85->encode( $this->to_json() ),
			$encrypted_string,
			$this->private_key
		);

		return $base85->encode( $encrypted_string );
	}

	private static function generate_serial(): string {
		return Uuid::uuid4()->toString();
	}
}
