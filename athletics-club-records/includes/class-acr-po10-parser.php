<?php
/**
 * Po10 parser — accepts the JSON the Claude-in-Chrome agent posts back
 * and persists it into athletes/performances/jobs.
 *
 * Expected payload shapes (the SOP doc tells the agent to produce these):
 *
 *  athlete_profile: {
 *    "po10_id": "12345",
 *    "name": "Jane Doe",
 *    "sex": "F",
 *    "dob": "2009-03-14",          // YYYY-MM-DD, optional
 *    "profile_url": "https://...",
 *    "performances": [
 *      { "event": "100", "performance_raw": "12.34", "perf_date": "2025-06-12",
 *        "venue": "Chelmsford", "position": "1", "is_indoor": false,
 *        "is_wind_assisted": false, "source_url": "..." }
 *    ]
 *  }
 *
 *  club_ranking: {
 *    "rows": [
 *      { "po10_id": "12345", "athlete_name": "Jane Doe", "sex": "F",
 *        "event": "100", "age_group_po10": "U17",
 *        "performance_raw": "12.34", "perf_date": "2025-06-12",
 *        "venue": "Chelmsford", "source_url": "..." }
 *    ]
 *  }
 *
 *  club_athletes: {
 *    "athletes": [
 *      { "po10_id": "12345", "name": "Jane Doe", "sex": "F",
 *        "profile_url": "https://..." }
 *    ]
 *  }
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Po10_Parser {

	public static function ingest( $job_type, array $body, $source_url ) {
		switch ( $job_type ) {
			case ACR_Jobs::TYPE_ATHLETE_PROFILE:
				return self::ingest_profile( $body, $source_url );
			case ACR_Jobs::TYPE_CLUB_RANKING:
				return self::ingest_ranking( $body, $source_url );
			case ACR_Jobs::TYPE_CLUB_ATHLETES:
				return self::ingest_club_athletes( $body, $source_url );
		}
		return null;
	}

	protected static function ingest_profile( array $body, $source_url ) {
		$athlete_id = ACR_Athletes::upsert( array(
			'po10_id'             => isset( $body['po10_id'] ) ? (string) $body['po10_id'] : '',
			'name'                => isset( $body['name'] ) ? $body['name'] : '',
			'sex'                 => isset( $body['sex'] ) ? $body['sex'] : '',
			'dob'                 => isset( $body['dob'] ) ? $body['dob'] : null,
			'profile_url'         => isset( $body['profile_url'] ) ? $body['profile_url'] : $source_url,
			'last_profile_scrape' => current_time( 'mysql' ),
		) );

		$count = 0;
		foreach ( ( $body['performances'] ?? array() ) as $p ) {
			ACR_Performances::insert_unique( array(
				'athlete_id'        => $athlete_id,
				'event'             => isset( $p['event'] ) ? $p['event'] : '',
				'performance_raw'   => isset( $p['performance_raw'] ) ? $p['performance_raw'] : '',
				'perf_date'         => isset( $p['perf_date'] ) ? $p['perf_date'] : null,
				'venue'             => isset( $p['venue'] ) ? $p['venue'] : null,
				'position'          => isset( $p['position'] ) ? $p['position'] : null,
				'is_indoor'         => ! empty( $p['is_indoor'] ),
				'is_wind_assisted'  => ! empty( $p['is_wind_assisted'] ),
				'source_url'        => isset( $p['source_url'] ) ? $p['source_url'] : $source_url,
			) );
			$count++;
		}

		// Trigger a recompute targeted at this athlete's events.
		( new ACR_Recompute() )->run_for_athlete( $athlete_id );

		return array( 'athlete_id' => $athlete_id, 'performances' => $count );
	}

	protected static function ingest_ranking( array $body, $source_url ) {
		$count = 0;
		foreach ( ( $body['rows'] ?? array() ) as $r ) {
			$athlete_id = ACR_Athletes::upsert( array(
				'po10_id' => isset( $r['po10_id'] ) ? (string) $r['po10_id'] : '',
				'name'    => isset( $r['athlete_name'] ) ? $r['athlete_name'] : '',
				'sex'     => isset( $r['sex'] ) ? $r['sex'] : '',
			) );
			ACR_Performances::insert_unique( array(
				'athlete_id'      => $athlete_id,
				'event'           => isset( $r['event'] ) ? $r['event'] : '',
				'performance_raw' => isset( $r['performance_raw'] ) ? $r['performance_raw'] : '',
				'perf_date'       => isset( $r['perf_date'] ) ? $r['perf_date'] : null,
				'venue'           => isset( $r['venue'] ) ? $r['venue'] : null,
				'source_url'      => isset( $r['source_url'] ) ? $r['source_url'] : $source_url,
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
