<?php
/**
 * Performances table.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Performances {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acr_performances';
	}

	/**
	 * Insert a performance unless an identical (athlete, event, date, raw) already exists.
	 *
	 * v0.3.5: when an identical row is found, mutable fields (age_group_at_time,
	 * is_indoor, is_wind_assisted, is_pb, position, venue, meeting, source_url,
	 * plus the parser-derived performance_value and is_field) are refreshed from
	 * the new payload. This lets a re-POST correct earlier data — most importantly
	 * a wrong age_group_at_time — without requiring a manual wp-admin edit. Only
	 * non-null caller-supplied values overwrite existing values, so a sparse
	 * re-POST never clobbers a populated field with NULL.
	 *
	 * The dedupe key fields (athlete_id, event, perf_date / perf_year, performance_raw)
	 * are never updated — by definition they match.
	 *
	 * @return int performance row id.
	 */
	public static function insert_unique( array $data ) {
		global $wpdb;
		$t = self::table();

		// Dedupe on (athlete, event, date if present else year, raw)
		if ( ! empty( $data['perf_date'] ) ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t} WHERE athlete_id = %d AND event = %s AND perf_date = %s AND performance_raw = %s LIMIT 1",
				$data['athlete_id'], $data['event'], $data['perf_date'], $data['performance_raw']
			) );
		} else {
			$year = isset( $data['perf_year'] ) ? (int) $data['perf_year'] : 0;
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t} WHERE athlete_id = %d AND event = %s AND perf_year = %d AND performance_raw = %s AND perf_date IS NULL LIMIT 1",
				$data['athlete_id'], $data['event'], $year, $data['performance_raw']
			) );
		}

		$parsed = ACR_PerfValue::parse( $data['performance_raw'], $data['event'] );

		if ( $existing ) {
			// Refresh mutable fields on the existing row.
			$update = array(
				'performance_value' => $parsed['value'],
				'is_field'          => ACR_PerfValue::is_field_event( $data['event'] ) ? 1 : 0,
			);
			$bool_fields   = array( 'is_indoor', 'is_wind_assisted', 'is_pb' );
			$string_fields = array( 'age_group_at_time', 'position', 'venue', 'meeting', 'source_url' );
			foreach ( $bool_fields as $f ) {
				if ( array_key_exists( $f, $data ) && $data[ $f ] !== null ) {
					$update[ $f ] = (int) (bool) $data[ $f ];
				}
			}
			foreach ( $string_fields as $f ) {
				if ( array_key_exists( $f, $data ) && $data[ $f ] !== null && $data[ $f ] !== '' ) {
					$update[ $f ] = $data[ $f ];
				}
			}
			$wpdb->update( $t, $update, array( 'id' => $existing ) );
			return $existing;
		}

		$row = array(
			'athlete_id'        => (int) $data['athlete_id'],
			'event'             => $data['event'],
			'performance_raw'   => $data['performance_raw'],
			'performance_value' => $parsed['value'],
			'age_group_at_time' => isset( $data['age_group_at_time'] ) ? $data['age_group_at_time'] : null,
			'perf_year'         => isset( $data['perf_year'] ) ? (int) $data['perf_year'] : ( ! empty( $data['perf_date'] ) ? (int) substr( $data['perf_date'], 0, 4 ) : null ),
			'is_indoor'         => isset( $data['is_indoor'] ) ? (int) $data['is_indoor'] : ( $parsed['indoor'] ? 1 : 0 ),
			'is_wind_assisted'  => isset( $data['is_wind_assisted'] ) ? (int) $data['is_wind_assisted'] : ( $parsed['wind'] ? 1 : 0 ),
			'is_field'          => ACR_PerfValue::is_field_event( $data['event'] ) ? 1 : 0,
			'is_pb'             => isset( $data['is_pb'] ) ? (int) (bool) $data['is_pb'] : 0,
			'position'          => isset( $data['position'] ) ? $data['position'] : null,
			'venue'             => isset( $data['venue'] ) ? $data['venue'] : null,
			'meeting'           => isset( $data['meeting'] ) ? $data['meeting'] : null,
			'perf_date'         => isset( $data['perf_date'] ) ? $data['perf_date'] : null,
			'source_url'        => isset( $data['source_url'] ) ? $data['source_url'] : null,
		);
		$wpdb->insert( $t, $row );
		return (int) $wpdb->insert_id;
	}

	public static function for_athlete( $athlete_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE athlete_id = %d ORDER BY perf_date ASC',
			$athlete_id
		) );
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}
}
