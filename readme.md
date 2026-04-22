# License and Registration (Please)

This WooCommerce dependent WordPress plugin faciliates the sale of software license codes, using cryptographic keys.

The private/public key pairs are located in ./ and use the format `nnnnnnnnnn` for the private key and `nnnnnnnnnn.pub` for the public key, with n being a 10-digit numeric sequence representing the Unix timestamp for when they were generated.

License information is encrypted using the private key and serialised using Base85, to be delivered to the customer on the order confirmation page.

An example implementation for decoding the license key [can be found in the connector-for-dk codebase](https://github.com/aldavigdis/connector-for-dk/blob/main/src/License.php). This implementation is to be spun off into a separate Composer package or as an example file with this codebase.

## Important notes

- Software distributed using this plugin may be cracked.
- Please keep your private key safe and out of prying eyes.

## Todo

- [ ] Make sure we can pick different key pairs instead of hard coding `License::CURRENT_KEYPAIR`
- [ ] Fix the mess in the product variation partial
- [ ] Finish the readme
- [ ] Document the code
- [ ] Add type annotations to the code
- [ ] Write more tests
- [ ] Get some CI going
- [ ] Split KennitalaField into a separate plugin or Composer package
- [ ] Identify and clean up more mess

## License

(C) 2026 Alda Vigdís Skarphéðinsdóttir

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

