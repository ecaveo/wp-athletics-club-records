<?php
/**
 * Scrape jobs queue.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Jobs {

	const TYPE_ATHLETE_PROFILE = 'athlete_profile';
	const TYPE_CLUB_RANKING    = 'club_ranking';
	const TYPE_CLUB_ATHLETES   = 'club_athletes';

	const STATUS_PENDING   = 'pending';
	const STATUS_CLAIMED   = 'claimed';
	const STATUS_DONE      = 'done';
	const STATUS_FAILED    = 'failed';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acr_scrape_jobs';
	}

	public static function enqueue( $type, $url, $payload = null ) {
		global $wpdb;
		// Dedupe — don't add the same pending URL twice.
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . self::table() . " WHERE target_url = %s AND status IN ('pending','claimed') LIMIT 1",
			$url
		) );
		if ( $existing ) {
			return $existing;
		}
		$wpdb->insert( self::table(), array(
			'job_type'    => $type,
			'target_url'  => $url,
			'payload'     => $payload ? wp_json_encode( $payload ) : null,
			'status'      => self::STATUS_PENDING,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function next_batch( $limit = 10 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY id ASC LIMIT %d',
			self::STATUS_PENDING, $limit
		) );
		// Mark claimed.
		if ( $rows ) {
			$ids = wp_list_pluck( $rows, 'id' );
			$in  = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = %s, claimed_at = %s, attempts = attempts + 1 WHERE id IN ($in)",
				self::STATUS_CLAIMED, current_time( 'mysql' )
			) );
		}
		return $rows;
	}

	public static function complete( $id, $result_json ) {
		global $wpdb;
		$wpdb->update( self::table(), array(
			'status'       => self::STATUS_DONE,
			'completed_at' => current_time( 'mysql' ),
			'result_json'  => is_string( $result_json ) ? $result_json : wp_json_encode( $result_json ),
		), array( 'id' => $id ) );
	}

	public static function fail( $id, $error ) {
		global $wpdb;
		$wpdb->update( self::table(), array(
			'status'      => self::STATUS_FAILED,
			'completed_at' => current_time( 'mysql' ),
			'last_error'  => $error,
		), array( 'id' => $id ) );
	}

	public static function stats() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) as n FROM ' . self::table() . ' GROUP BY status' );
		$out = array( 'pending' => 0, 'claimed' => 0, 'done' => 0, 'failed' => 0 );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	public static function clear_completed() {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . self::table() . " WHERE status IN ('done','failed')" );
	}

	public static function recent( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit
		) );
	}
}
