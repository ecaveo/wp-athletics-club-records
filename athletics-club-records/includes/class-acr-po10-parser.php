<?php
/**
 * Po10 parser (v0.2.0).
 *
 * Expected JSON payloads the Claude-in-Chrome agent POSTs back:
 *
 *  athlete_search: {
 *    "found": true,
 *    "po10_id": "ea487471-47a6-4062-bb80-c61355388ad1",
 *    "name": "Olivia Forrest",
 *    "sex": "F",
 *    "current_age_group": "U18",
 *    "profile_url": "https://www.powerof10.uk/Home/Athlete/ea487471-47a6-4062-bb80-c61355388ad1"
 *  }
 *  (If not found: {"found": false})
 *
 *  athlete_profile: {
 *    "po10_id": "<uuid>",
 *    "name": "Olivia Forrest",
 *    "sex": "F",
 *    "current_age_group": "U18",
 *    "profile_url": "...",
 *    "performances": [
 *      { "event": "1500", "performance_raw": "4:25.76", "is_indoor": true,
 *        "age_group_at_time": "U17", "perf_year": 2024, "is_pb": true,
 *        "perf_date": null, "venue": null, "meeting": null,
 *        "source_url": "..." }
 *    ]
 *  }
 *  The performances list typically comes from the "Best Known Performances"
 *  table — one row per (event, year) cell, plus the PB row. perf_date may
 *  be null if only year is known.
 *
 *  club_ranking: unchanged from v0.1.0.
 *  club_athletes: deprecated — kept for backward compatibility.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Po10_Parser {

	public static function ingest( $job_type, array $body, $source_url ) {
		switch ( $job_type ) {
			case ACR_Jobs::TYPE_ATHLETE_SEARCH:
				return self::ingest_search( $body, $source_url );
			case ACR_Jobs::TYPE_ATHLETE_PROFILE:
				return self::ingest_profile( $body, $source_url );
			case ACR_Jobs::TYPE_CLUB_RANKING:
				return self::ingest_ranking( $body, $source_url );
			case ACR_Jobs::TYPE_CLUB_ATHLETES:
				return self::ingest_club_athletes( $body, $source_url );
		}
		return null;
	}

	protected static function ingest_search( array $body, $source_url ) {
		if ( empty( $body['found'] ) ) {
			return array( 'matched' => false );
		}
		// Find the athlete by name & sex to attach the UUID.
		global $wpdb;
		$at = ACR_Athletes::table();
		$candidate = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$at} WHERE name = %s AND sex = %s AND (po10_id = '' OR po10_id IS NULL) LIMIT 1",
			$body['name'], strtoupper( substr( $body['sex'], 0, 1 ) )
		) );
		if ( ! $candidate ) {
			// Just upsert as a new athlete.
			$id = ACR_Athletes::upsert( array(
				'po10_id'     => $body['po10_id'],
				'name'        => $body['name'],
				'sex'         => $body['sex'],
				'profile_url' => $body['profile_url'] ?? '',
			) );
			return array( 'matched' => false, 'created_id' => $id );
		}
		$wpdb->update( $at, array(
			'po10_id'     => $body['po10_id'],
			'profile_url' => isset( $body['profile_url'] ) ? $body['profile_url'] : '',
		), array( 'id' => $candidate->id ) );

		// Auto-enqueue an athlete_profile job for the newly-identified athlete.
		$profile_url = isset( $body['profile_url'] ) ? $body['profile_url'] : ( 'https://www.powerof10.uk/Home/Athlete/' . $body['po10_id'] );
		ACR_Jobs::enqueue( ACR_Jobs::TYPE_ATHLETE_PROFILE, $profile_url, array( 'athlete_id' => $candidate->id ) );

		return array( 'matched' => true, 'athlete_id' => (int) $candidate->id, 'po10_id' => $body['po10_id'] );
	}

	protected static function ingest_profile( array $body, $source_url ) {
		$athlete_id = ACR_Athletes::upsert( array(
			'po10_id'             => isset( $body['po10_id'] ) ? (string) $body['po10_id'] : '',
			'name'                => isset( $body['name'] ) ? $body['name'] : '',
			'sex'                 => isset( $body['sex'] ) ? $body['sex'] : '',
			'profile_url'         => isset( $body['profile_url'] ) ? $body['profile_url'] : $source_url,
			'last_profile_scrape' => current_time( 'mysql' ),
		) );

		$settings = acr_get_settings();
		$cutoff_year = (int) substr( $settings['performances_since'], 0, 4 );

		$count = 0;
		$skipped = 0;
		foreach ( ( $body['performances'] ?? array() ) as $p ) {
			$year = isset( $p['perf_year'] ) ? (int) $p['perf_year'] : ( ! empty( $p['perf_date'] ) ? (int) substr( $p['perf_date'], 0, 4 ) : 0 );
			if ( $year && $year < $cutoff_year ) {
				$skipped++;
				continue;
			}
			ACR_Performances::insert_unique( array(
				'athlete_id'        => $athlete_id,
				'event'             => isset( $p['event'] ) ? $p['event'] : '',
				'performance_raw'   => isset( $p['performance_raw'] ) ? $p['performance_raw'] : '',
				'age_group_at_time' => isset( $p['age_group_at_time'] ) ? $p['age_group_at_time'] : null,
				'perf_year'         => $year ?: null,
				'perf_date'         => isset( $p['perf_date'] ) ? $p['perf_date'] : null,
				'venue'             => isset( $p['venue'] ) ? $p['venue'] : null,
				'meeting'           => isset( $p['meeting'] ) ? $p['meeting'] : null,
				'position'          => isset( $p['position'] ) ? $p['position'] : null,
				'is_indoor'         => ! empty( $p['is_indoor'] ),
				'is_wind_assisted'  => ! empty( $p['is_wind_assisted'] ),
				'is_pb'             => ! empty( $p['is_pb'] ),
				'source_url'        => isset( $p['source_url'] ) ? $p['source_url'] : $source_url,
			) );
			$count++;
		}

		// Targeted recompute.
		( new ACR_Recompute() )->run();

		return array( 'athlete_id' => $athlete_id, 'performances_added' => $count, 'skipped_pre_cutoff' => $skipped );
	}

	protected static function ingest_ranking( array $body, $source_url ) {
		$count = 0;
		$sex_ctx = isset( $body['sex'] ) ? strtoupper( substr( $body['sex'], 0, 1 ) ) : '';
		$year_ctx = isset( $body['year'] ) ? (int) $body['year'] : null;
		$event_ctx = isset( $body['event'] ) ? $body['event'] : '';

		foreach ( ( $body['rows'] ?? array() ) as $r ) {
			$athlete_sex = isset( $r['sex'] ) ? strtoupper( substr( $r['sex'], 0, 1 ) ) : $sex_ctx;
			$athlete_id  = ACR_Athletes::upsert( array(
				'po10_id'     => isset( $r['po10_id'] ) ? (string) $r['po10_id'] : '',
				'name'        => isset( $r['athlete_name'] ) ? $r['athlete_name'] : '',
				'sex'         => $athlete_sex,
				'profile_url' => isset( $r['profile_url'] ) ? $r['profile_url'] : '',
			) );

			$wind_raw = isset( $r['wind'] ) ? trim( (string) $r['wind'] ) : '';
			$is_wind  = ! empty( $r['is_wind_assisted'] ) || ( $wind_raw !== '' && stripos( $wind_raw, 'w' ) === 0 );

			ACR_Performances::insert_unique( array(
				'athlete_id'        => $athlete_id,
				'event'             => isset( $r['event'] ) ? $r['event'] : $event_ctx,
				'performance_raw'   => isset( $r['performance_raw'] ) ? $r['performance_raw'] : '',
				'age_group_at_time' => isset( $r['age_group_at_time'] ) ? $r['age_group_at_time'] : ( isset( $r['age_group_po10'] ) ? $r['age_group_po10'] : null ),
				'perf_year'         => isset( $r['perf_year'] ) ? (int) $r['perf_year'] : ( ! empty( $r['perf_date'] ) ? (int) substr( $r['perf_date'], 0, 4 ) : $year_ctx ),
				'perf_date'         => isset( $r['perf_date'] ) ? $r['perf_date'] : null,
				'venue'             => isset( $r['venue'] ) ? $r['venue'] : null,
				'meeting'           => isset( $r['meeting'] ) ? $r['meeting'] : null,
				'position'          => isset( $r['position'] ) ? (string) $r['position'] : null,
				'is_wind_assisted'  => $is_wind ? 1 : 0,
				'is_pb'             => ! empty( $r['is_pb'] ),
				'source_url'        => isset( $r['source_url'] ) ? $r['source_url'] : $source_url,
			) );
			$count++;
		}
		( new ACR_Recompute() )->run();
		return array( 'rows' => $count );
	}

	protected static function ingest_club_athletes( array $body, $source_url ) {
		$count = 0;
		foreach ( ( $body['athletes'] ?? array() ) as $a ) {
			ACR_Athletes::upsert( array(
				'po10_id'     => isset( $a['po10_id'] ) ? (string) $a['po10_id'] : '',
				'name'        => isset( $a['name'] ) ? $a['name'] : '',
				'sex'         => isset( $a['sex'] ) ? $a['sex'] : '',
				'profile_url' => isset( $a['profile_url'] ) ? $a['profile_url'] : '',
			) );
			$count++;
		}
		return array( 'athletes' => $count );
	}
}
