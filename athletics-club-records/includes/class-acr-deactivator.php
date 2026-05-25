<?php
/**
 * Deactivator — non-destructive. Drops scheduled events if any are ever added.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Deactivator {
	public static function deactivate() {
		wp_clear_scheduled_hook( 'acr_periodic_recompute' );
	}
}
