<?php

declare(strict_types = 1);

namespace AldaVigdis\LicenseAndRegistration\Tests;

use AldaVigdis\LicenseAndRegistration\License;
use AldaVigdis\LicenseAndRegistration\Opis\JsonSchema\Validator;
use AldaVigdis\LicenseAndRegistration\Tuupola\Base85;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertIsObject;
use function PHPUnit\Framework\assertIsString;
use function PHPUnit\Framework\assertNotEmpty;

#[TestDox( 'The License class' )]
final class LicenseTest extends TestCase {
	public array $products;
	public array $attributes;

	public Validator $validator;

	public string $json_schema;

	public License $license;

	public function setUp(): void {
		$this->products = array( 'foo', 'foo_pro' );

		$this->attributes = array(
			'domain' => 'example.com',
			'expiry' => YEAR_IN_SECONDS
		);


		$this->validator = new Validator();

		$this->json_schema = file_get_contents(
			dirname( __DIR__ ) . '/json_schemas/license.json'
		);

		$this->license = new License( $this->products, $this->attributes );
	}

	#[TestDox( 'generates a valid JSON object' )]
	public function testGeneratesValidJSONObject(): void {
		$license_json = json_decode( $this->license->to_json() );

		$validation = $this->validator->validate(
			$license_json,
			$this->json_schema
		);

		assertFalse( $validation->hasError() );
	}

	#[TestDox( 'generates license codes that can be decrypted using the public key' )]
	public function testGeneratesCodesThatCanBeDecryptedWithPublicKey(): void {
		$encrypted    = $this->license->encrypt();
		$license_json = $this->license->to_json();

		$base85           = new Base85();
		$decrypted_string = '';

		openssl_public_decrypt(
			$base85->decode( ( $encrypted ) ),
			$decrypted_string,
			$this->license->public_key
		);

		assertIsString( $license_json );
		assertNotEmpty( $license_json );
		assertIsString( $decrypted_string );
		assertNotEmpty( $decrypted_string );
		assertIsObject( json_decode( $license_json ) );
		assertIsObject( json_decode( $base85->decode( $decrypted_string ) ) );
		assertEquals( $license_json, $base85->decode( $decrypted_string ) );
	}
}
