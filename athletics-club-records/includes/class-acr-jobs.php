<?php
/**
 * Scrape jobs queue.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Jobs {

	const TYPE_ATHLETE_PROFILE = 'athlete_profile';
	const TYPE_ATHLETE_SEARCH  = 'athlete_search';
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
		$payload_json = $payload ? wp_json_encode( $payload ) : null;

		// Dedupe on (type, url, payload). The club_ranking sweep produces 270+
		// jobs that all share the same URL — payload makes them distinct.
		if ( $payload_json !== null ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . self::table() . " WHERE job_type = %s AND target_url = %s AND payload = %s AND status IN ('pending','claimed') LIMIT 1",
				$type, $url, $payload_json
			) );
		} else {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . self::table() . " WHERE job_type = %s AND target_url = %s AND (payload IS NULL OR payload = '') AND status IN ('pending','claimed') LIMIT 1",
				$type, $url
			) );
		}

		if ( $existing ) {
			return $existing;
		}
		$wpdb->insert( self::table(), array(
			'job_type'    => $type,
			'target_url'  => $url,
			'payload'     => $payload_json,
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

	/**
	 * Reset jobs that have been "claimed" for longer than $min_age_minutes
	 * back to "pending" so a fresh agent run can pick them up.
	 *
	 * Stuck-claimed jobs happen when an agent GETs /jobs (which atomically
	 * marks them claimed) but never POSTs a result or fail.
	 *
	 * @param int $min_age_minutes default 10. Use 0 to release everything.
	 * @return int number of jobs released.
	 */
	public static function release_claimed( $min_age_minutes = 10 ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 0, (int) $min_age_minutes ) * 60 ) );
		$affected = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . " SET status = %s, claimed_at = NULL WHERE status = %s AND (claimed_at IS NULL OR claimed_at < %s)",
			self::STATUS_PENDING, self::STATUS_CLAIMED, $cutoff
		) );
		return (int) $affected;
	}

	public static function recent( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit
		) );
	}
}
