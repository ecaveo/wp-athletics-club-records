<?php
/**
 * Records table.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Records {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acr_records';
	}

	/**
	 * Upsert a single record cell by (sex, age_group, event).
	 * Will not overwrite a cell flagged is_manual_override = 1.
	 *
	 * @return int|false row id or false if skipped (override protected).
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		$t = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, is_manual_override FROM {$t} WHERE sex = %s AND age_group = %s AND event = %s LIMIT 1",
			$data['sex'], $data['age_group'], $data['event']
		) );

		if ( $existing && $existing->is_manual_override ) {
			return false;
		}

		$row = array(
			'sex'                => $data['sex'],
			'age_group'          => $data['age_group'],
			'event'              => $data['event'],
			'performance_raw'    => isset( $data['performance_raw'] ) ? $data['performance_raw'] : '',
			'performance_value'  => isset( $data['performance_value'] ) ? $data['performance_value'] : null,
			'athlete_id'         => isset( $data['athlete_id'] ) ? $data['athlete_id'] : null,
			'athlete_name'       => isset( $data['athlete_name'] ) ? $data['athlete_name'] : '',
			'venue'              => isset( $data['venue'] ) ? $data['venue'] : null,
			'perf_date'          => isset( $data['perf_date'] ) ? $data['perf_date'] : null,
			'performance_id'     => isset( $data['performance_id'] ) ? $data['performance_id'] : null,
			'is_verified'        => isset( $data['is_verified'] ) ? (int) $data['is_verified'] : 0,
			'source'             => isset( $data['source'] ) ? $data['source'] : 'computed',
			'notes'              => isset( $data['notes'] ) ? $data['notes'] : null,
			'computed_at'        => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( $t, $row, array( 'id' => $existing->id ) );
			return (int) $existing->id;
		}
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Direct manual upsert that respects an admin override.
	 */
	public static function manual_set( array $data ) {
		$data['is_manual_override'] = 1;
		$data['source'] = 'manual';
		global $wpdb;
		$t = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$t} WHERE sex = %s AND age_group = %s AND event = %s LIMIT 1",
			$data['sex'], $data['age_group'], $data['event']
		) );
		$row = array_intersect_key( $data, array_flip( array(
			'sex','age_group','event','performance_raw','performance_value','athlete_id',
			'athlete_name','venue','perf_date','is_manual_override','source','notes',
		) ) );
		$row['computed_at'] = current_time( 'mysql' );
		if ( $existing ) {
			$wpdb->update( $t, $row, array( 'id' => $existing->id ) );
			return (int) $existing->id;
		}
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function for_sex( $sex ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE sex = %s ORDER BY event ASC, age_group ASC',
			strtoupper( $sex )
		) );
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	public static function count_verified() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_verified = 1' );
	}
}
