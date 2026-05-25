<?php
/**
 * Uninstall handler — fires when the user deletes the plugin via wp-admin.
 *
 * Drops all custom tables and deletes plugin options. Existing Ninja Tables
 * data on the site is NOT touched (we never owned it).
 *
 * @package AthleticsClubRecords
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'acr_records',
	$wpdb->prefix . 'acr_performances',
	$wpdb->prefix . 'acr_athletes',
	$wpdb->prefix . 'acr_scrape_jobs',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'acr_settings' );
delete_option( 'acr_db_version' );
delete_option( 'acr_last_recompute' );
