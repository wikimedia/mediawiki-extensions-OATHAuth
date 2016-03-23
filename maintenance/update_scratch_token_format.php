<?php
/**
 * Update scratch_token column format
 *
 * Usage: php update_scratch_token_format.php
 *
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
 *
 * @file
 * @author Darian Anthony Patrick
 * @ingroup Maintenance
 */


if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class UpdateScratchTokenFormat extends Maintenance {

	private $mPurgeDays = null;

	function __construct() {
		parent::__construct();
		$this->mDescription = 'Script to update scratch_token column format';
	}

	public function execute() {
		$dbr = wfGetDB( DB_SLAVE );
		if ( !OATHAuthHooks::schemaUpdateOldUsers( $dbr ) ) {
			$this->error( "Failed to update scratch_token rows.\n", 1);
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = "UpdateScratchTokenFormat";
require_once( RUN_MAINTENANCE_IF_MAIN );