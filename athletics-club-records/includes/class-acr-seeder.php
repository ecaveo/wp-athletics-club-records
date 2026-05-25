<?php
/**
 * Seeder — imports the club's existing Ninja Tables records into the plugin
 * so the public page is never empty during the first scrape cycle.
 *
 * Maps Ninja columns -> our schema. The current BBAC tables (id 9354 / 9359)
 * use the following column meanings:
 *   column_1 = event (e.g. "60m")
 *   column_2 = school year (e.g. "yr8", "u20")
 *   column_3 = age group (e.g. "u14")
 *   column_4 = age value (numeric)
 *   column_5 = performance (e.g. "8.59i")
 *   column_6 = athlete name
 *   column_7 = venue
 *   column_8 = date (DD/MM/YYYY)
 *   column_9 = notes (mostly empty)
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Seeder {

	const ENDPOINT = 'https://www.beagles.org.uk/wp-admin/admin-ajax.php';

	/**
	 * Pull a Ninja Tables table by ID via the public endpoint and return rows.
	 *
	 * @param int $table_id Ninja Tables ID.
	 * @return array|WP_Error
	 */
	public static function fetch_ninja_table( $table_id ) {
		// Try a nonce-less request first; many Ninja Tables installations allow it.
		$url = add_query_arg( array(
			'action'                   => 'wp_ajax_ninja_tables_public_action',
			'table_id'                 => $table_id,
			'target_action'            => 'get-all-data',
			'default_sorting'          => 'old_first',
			'skip_rows'                => 0,
			'limit_rows'               => 0,
			'ninja_table_public_nonce' => 'e4fd4d6e68', // observed live nonce; can be overridden.
		), self::ENDPOINT );

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'headers' => array(
				'Referer'    => 'https://www.beagles.org.uk/',
				'User-Agent' => 'ACR-Seeder/1.0',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'acr_seed_invalid', 'Ninja Tables response was not valid JSON.' );
		}
		return $decoded;
	}

	/**
	 * Map a single Ninja row to our records schema.
	 */
	protected static function map_row( array $row, $sex ) {
		$v = isset( $row['value'] ) ? $row['value'] : array();

		$event_raw = isset( $v['ninja_column_1'] ) ? trim( $v['ninja_column_1'] ) : '';
		$event = self::normalise_event( $event_raw );

		$age_raw = isset( $v['ninja_column_3'] ) ? trim( strtolower( $v['ninja_column_3'] ) ) : '';
		$age_group = self::map_age_group( $age_raw, isset( $v['ninja_column_4'] ) ? (int) $v['ninja_column_4'] : null );

		$perf = isset( $v['ninja_column_5'] ) ? trim( $v['ninja_column_5'] ) : '';
		$athlete = isset( $v['ninja_column_6'] ) ? trim( $v['ninja_column_6'] ) : '';
		$venue = isset( $v['ninja_column_7'] ) ? trim( $v['ninja_column_7'] ) : '';
		$date  = isset( $v['ninja_column_8'] ) ? trim( $v['ninja_column_8'] ) : '';

		if ( ! $event || ! $age_group || ! $perf ) {
			return null;
		}

		$parsed = ACR_PerfValue::parse( $perf, $event );

		return array(
			'sex'                => strtoupper( substr( $sex, 0, 1 ) ),
			'age_group'          => $age_group,
			'event'              => $event,
			'performance_raw'    => $perf,
			'performance_value'  => $parsed['value'],
			'athlete_name'       => $athlete,
			'venue'              => $venue ?: null,
			'perf_date'          => self::parse_uk_date( $date ),
			'source'             => 'seed',
			'is_verified'        => 0,
		);
	}

	/**
	 * Run the seed: returns counts per gender.
	 */
	public static function run() {
		$settings = acr_get_settings();
		$results  = array( 'women' => 0, 'men' => 0, 'skipped' => 0 );

		foreach ( array( 'women' => $settings['ninja_women_id'], 'men' => $settings['ninja_men_id'] ) as $gender => $table_id ) {
			$sex = $gender === 'women' ? 'F' : 'M';
			$rows = self::fetch_ninja_table( $table_id );
			if ( is_wp_error( $rows ) ) {
				continue;
			}
			foreach ( $rows as $row ) {
				$mapped = self::map_row( $row, $sex );
				if ( ! $mapped ) {
					$results['skipped']++;
					continue;
				}
				ACR_Records::upsert( $mapped );
				if ( ! empty( $mapped['athlete_name'] ) ) {
					ACR_Athletes::upsert( array(
						'name' => $mapped['athlete_name'],
						'sex'  => $mapped['sex'],
					) );
				}
				$results[ $gender ]++;
			}
		}
		update_option( 'acr_last_seed', current_time( 'mysql' ) );
		return $results;
	}

	protected static function normalise_event( $raw ) {
		$raw = preg_replace( '/\s+/', ' ', $raw );
		// Strip a trailing "m" on numeric distances (the BBAC table uses "60m", "100m").
		if ( preg_match( '/^(\d+)m$/i', $raw, $m ) ) {
			return $m[1];
		}
		// Common aliases.
		$map = array(
			'60h'  => '60H',
			'80h'  => '80H',
			'100h' => '100H',
			'110h' => '110H',
			'300h' => '300H',
			'400h' => '400H',
			'hj'   => 'High Jump',
			'lj'   => 'Long Jump',
			'tj'   => 'Triple Jump',
			'pv'   => 'Pole Vault',
			'sp'   => 'Shot',
			'dt'   => 'Discus',
			'jt'   => 'Javelin',
			'ht'   => 'Hammer',
		);
		$key = strtolower( trim( $raw ) );
		return $map[ $key ] ?? $raw;
	}

	/**
	 * BBAC's table uses both "u8".."u20" age tags AND school-year tags ("yr8").
	 * We squash everything to the new age-group structure (U14/U16/U18/U20/SEN/V35..).
	 *
	 * @param string $raw    e.g. "u14", "u20", "u23", "v40", "sen"
	 * @param int|null $age  the numeric age the row was tagged with
	 */
	protected static function map_age_group( $raw, $age = null ) {
		$raw = strtolower( $raw );

		// Direct mapping for new age-group cells.
		$direct = array(
			'u14' => 'U14',
			'u16' => 'U16',
			'u18' => 'U18',
			'u20' => 'U20',
			'u23' => 'SEN', // U23 folded into seniors for club records.
			'sen' => 'SEN',
			'v35' => 'V35', 'v40' => 'V40', 'v45' => 'V45', 'v50' => 'V50',
			'v55' => 'V55', 'v60' => 'V60', 'v65' => 'V65', 'v70' => 'V70',
			'v75' => 'V75', 'v80' => 'V80', 'v85' => 'V80', 'v90' => 'V80',
		);
		if ( isset( $direct[ $raw ] ) ) {
			return $direct[ $raw ];
		}

		// Legacy single-year tags (u7..u13) — derive from numeric age.
		if ( preg_match( '/^u(\d+)$/', $raw, $m ) ) {
			$n = (int) $m[1];
			$age = $age ?: $n;
		}

		if ( $age !== null ) {
			$buckets = acr_age_groups();
			foreach ( $buckets as $code => $b ) {
				if ( $age >= $b['min'] && $age <= $b['max'] ) {
					return $code;
				}
			}
		}
		return '';
	}

	protected static function parse_uk_date( $str ) {
		if ( ! $str ) {
			return null;
		}
		$d = DateTime::createFromFormat( 'd/m/Y', $str );
		return $d ? $d->format( 'Y-m-d' ) : null;
	}
}
