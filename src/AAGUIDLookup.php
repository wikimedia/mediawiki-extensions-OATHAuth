<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\WebAuthn;

/**
 * Lookup table for mapping AAGUIDs to human-readable device names.
 *
 * AAGUID (Authenticator Attestation Globally Unique Identifier) is a 128-bit
 * identifier that uniquely identifies a type of authenticator (e.g., a specific
 * password manager or hardware security key model).
 *
 * @see https://fidoalliance.org/specs/fido-v2.0-rd-20180702/fido-metadata-statement-v2.0-rd-20180702.html
 */
class AAGUIDLookup {

	/**
	 * Known AAGUIDs mapped to device/provider names.
	 */
	private const KNOWN_AAGUIDS = [
		// Google Password Manager
		'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google Password Manager',

		// iCloud Keychain
		'dd4ec289-e01d-41c9-bb89-70fa845d4bf2' => 'iCloud Keychain',
		'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'iCloud Keychain',

		// 1Password
		'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',

		// Bitwarden
		'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',

		// Dashlane
		'531126d6-e717-415c-9320-3d9aa6981239' => 'Dashlane',

		// YubiKey
		'cb69481e-8ff7-4039-93ec-0a2729a154a8' => 'YubiKey 5',
		'73bb0cd4-e502-49b8-9c6f-b59445bf720b' => 'YubiKey 5 FIPS',
		'c5ef55ff-ad9a-4b9f-b580-adebafe026d0' => 'YubiKey 5Ci',
		'c1f9a0bc-1dd2-404a-b27f-8e29047a43fd' => 'YubiKey 5Ci FIPS',
		'd8522d9f-575b-4866-88a9-ba99fa02f35b' => 'YubiKey Bio',
		'85203421-48f9-4355-9bc8-8a53846e5083' => 'YubiKey Bio FIDO Edition',

		// Windows Hello
		'08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
		'6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
		'9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',

		// Chrome on Mac
		'adce0002-35bc-c60a-648b-0b25f1f05503' => 'Chrome on Mac',

		// Android
		'b93fd961-f2e6-462f-b122-82002247de78' => 'Android',
		'3f59672f-20aa-4afe-b6f4-7e5e916b6e64' => 'Android',

		// Samsung Pass
		'53414d53-554e-4700-0000-000000000000' => 'Samsung Pass',

		// Keeper
		'0ea242b4-43c4-4a1b-8b17-dd6d0b6baec6' => 'Keeper',

		// NordPass
		'b84e4048-15dc-4dd0-8640-f4f60813c8af' => 'NordPass'
	];

	/**
	 * Look up a device name by AAGUID.
	 *
	 * @param string $aaguid The AAGUID in UUID format (e.g., "ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4")
	 * @return string|null The device name, or null if not found
	 */
	public static function getDeviceName( string $aaguid ): ?string {
		return self::KNOWN_AAGUIDS[ strtolower( $aaguid ) ] ?? null;
	}

	/**
	 * Generate a friendly name for a passkey based on its AAGUID.
	 *
	 * @param string $aaguid The AAGUID in UUID format
	 * @return string The device name, or "Passkey" if AAGUID is unknown
	 */
	public static function generateFriendlyName( string $aaguid ): string {
		return self::getDeviceName( $aaguid ) ?? 'Passkey';
	}
}
