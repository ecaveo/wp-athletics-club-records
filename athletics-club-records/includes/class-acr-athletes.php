<?php
/**
 * Athletes table CRUD.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Athletes {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acr_athletes';
	}

	/**
	 * Upsert by Po10 ID (or by name+sex if no Po10 ID known yet).
	 *
	 * @param array $data athlete fields.
	 * @return int athlete row id.
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		$table = self::table();
		$po10  = isset( $data['po10_id'] ) ? $data['po10_id'] : '';
		$name  = isset( $data['name'] ) ? $data['name'] : '';
		$sex   = isset( $data['sex'] ) ? strtoupper( substr( $data['sex'], 0, 1 ) ) : '';

		$existing_id = 0;
		if ( $po10 ) {
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE po10_id = %s LIMIT 1",
				$po10
			) );
		}
		if ( ! $existing_id && $name && $sex ) {
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE name = %s AND sex = %s AND (po10_id = '' OR po10_id IS NULL) LIMIT 1",
				$name, $sex
			) );
		}

		$row = array(
			'po10_id'              => $po10,
			'name'                 => $name,
			'sex'                  => $sex,
			'dob'                  => isset( $data['dob'] ) ? $data['dob'] : null,
			'first_claim'          => isset( $data['first_claim'] ) ? (int) (bool) $data['first_claim'] : 1,
			'first_claim_since'    => isset( $data['first_claim_since'] ) ? $data['first_claim_since'] : null,
			'first_claim_until'    => isset( $data['first_claim_until'] ) ? $data['first_claim_until'] : null,
			'profile_url'          => isset( $data['profile_url'] ) ? $data['profile_url'] : '',
			'notes'                => isset( $data['notes'] ) ? $data['notes'] : null,
			'last_profile_scrape'  => isset( $data['last_profile_scrape'] ) ? $data['last_profile_scrape'] : null,
		);

		// Don't blank fields we have, with nulls we don't.
		$row = array_filter( $row, function( $v ) { return $v !== null; } );

		if ( $existing_id ) {
			$wpdb->update( $table, $row, array( 'id' => $existing_id ) );
			return $existing_id;
		}
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function all( $args = array() ) {
		global $wpdb;
		$where = '1=1';
		if ( ! empty( $args['sex'] ) ) {
			$where .= $wpdb->prepare( ' AND sex = %s', strtoupper( $args['sex'] ) );
		}
		if ( isset( $args['first_claim'] ) ) {
			$where .= $wpdb->prepare( ' AND first_claim = %d', (int) (bool) $args['first_claim'] );
		}
		return $wpdb->get_results( "SELECT * FROM " . self::table() . " WHERE {$where} ORDER BY name ASC" );
	}

	public static function set_first_claim( $id, $first_claim ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'first_claim' => (int) (bool) $first_claim ), array( 'id' => $id ) );
	}

	public static function age_on_date( $dob, $perf_date ) {
		if ( ! $dob || ! $perf_date ) {
			return null;
		}
		try {
			$d1 = new DateTime( $dob );
			$d2 = new DateTime( $perf_date );
			return $d2->diff( $d1 )->y;
		} catch ( Exception $e ) {
			return null;
		}
	}
}
