<?php
/**
 * Recomputation engine.
 *
 * Uses Po10's per-performance age-group tag (age_group_at_time) rather than
 * trying to compute age from DOB. Maps Po10's age groups to the club's
 * current age-group structure.
 *
 * Bucket semantics (strict, v0.3.8):
 *  Each performance counts for EXACTLY ONE age-group bucket — the bucket
 *  its mapped age_group_at_time falls into. There is no upward propagation
 *  for juniors and no downward propagation for masters. If no qualifying
 *  athlete has a performance in a given bucket, that records cell stays
 *  blank. (v0.3.7 briefly enabled inclusive UKA-style propagation; this
 *  was reverted in v0.3.8 because Brentwood Beagles wants strict club
 *  semantics.)
 *
 * Filters:
 *  - first_claim = 1
 *  - is_wind_assisted = 0
 *  - performance_value IS NOT NULL
 *  - perf_year >= settings.performances_since
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Recompute {

	/**
	 * Po10 age-group label → our internal code.
	 * Maps the legacy 2-yr (U13/U15/U17) into our new 2-yr (U14/U16/U18) where
	 * the boundary aligns; otherwise keep as the closest equivalent.
	 */
	const PO10_TO_OURS = array(
		'U11'  => 'U14',
		'U12'  => 'U14',
		'U13'  => 'U14',
		'U14'  => 'U14',
		'U15'  => 'U16',
		'U16'  => 'U16',
		'U17'  => 'U18',
		'U18'  => 'U18',
		'U20'  => 'U20',
		'U23'  => 'SEN',
		'SEN'  => 'SEN',
		'OVER' => 'SEN',
		'V35'  => 'V35', 'V40' => 'V40', 'V45' => 'V45', 'V50' => 'V50',
		'V55'  => 'V55', 'V60' => 'V60', 'V65' => 'V65', 'V70' => 'V70',
		'V75'  => 'V75', 'V80' => 'V80', 'V85' => 'V80', 'V90' => 'V80',
	);

	public function run() {
		global $wpdb;
		$pt = ACR_Performances::table();
		$at = ACR_Athletes::table();
		$rt = ACR_Records::table();
		$settings = acr_get_settings();
		$cutoff_year = (int) substr( $settings['performances_since'], 0, 4 );

		// v0.3.7: clear non-override recompute cells so cells whose qualifying
		// performance moved buckets (or was deleted) don't leave stale entries.
		// Retained in v0.3.8.
		$wpdb->query( "DELETE FROM {$rt} WHERE source = 'recompute' AND is_manual_override = 0" );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, a.sex, a.first_claim, a.name as athlete_name
			   FROM {$pt} p
			   JOIN {$at} a ON a.id = p.athlete_id
			  WHERE a.first_claim = 1
			    AND p.is_wind_assisted = 0
			    AND p.performance_value IS NOT NULL
			    AND (p.perf_year IS NULL OR p.perf_year >= %d)",
			$cutoff_year
		) );

		$buckets = $this->bucket( $rows );
		$updates = 0;
		$skipped = 0;
		foreach ( $buckets as $key => $best ) {
			list( $sex, $age_group, $event ) = explode( '|', $key );
			$res = ACR_Records::upsert( array(
				'sex'               => $sex,
				'age_group'         => $age_group,
				'event'             => $event,
				'performance_raw'   => $best['performance_raw'],
				'performance_value' => $best['performance_value'],
				'athlete_id'        => $best['athlete_id'],
				'athlete_name'      => $best['athlete_name'],
				'venue'             => $best['venue'],
				'perf_date'         => $best['perf_date'],
				'performance_id'    => $best['performance_id'],
				'is_verified'       => 1,
				'source'            => 'recompute',
			) );
			if ( $res === false ) {
				$skipped++;
			} else {
				$updates++;
			}
		}
		update_option( 'acr_last_recompute', current_time( 'mysql' ) );
		return array(
			'cells_updated'           => $updates,
			'cells_skipped_override'  => $skipped,
			'performances_considered' => count( $rows ),
			'buckets'                 => count( $buckets ),
		);
	}

	public function run_for_athlete( $athlete_id ) {
		return $this->run();
	}

	/**
	 * Strict v0.3.8 bucketing: each performance counts for exactly one bucket,
	 * the one its mapped age_group_at_time falls into. No upward (junior) or
	 * downward (masters) propagation. Performances with no mappable age group
	 * are skipped.
	 */
	protected function bucket( array $rows ) {
		$buckets = array();
		foreach ( $rows as $p ) {
			if ( ! $p->event || ! $p->performance_value ) {
				continue;
			}
			$bucket = $this->map_age_group( $p->age_group_at_time );
			if ( ! $bucket ) {
				continue;
			}
			$sex = strtoupper( $p->sex );
			$key = $sex . '|' . $bucket . '|' . $p->event;
			$candidate = array(
				'performance_raw'   => $p->performance_raw,
				'performance_value' => (float) $p->performance_value,
				'athlete_id'        => (int) $p->athlete_id,
				'athlete_name'      => $p->athlete_name,
				'venue'             => $p->venue,
				'perf_date'         => $p->perf_date,
				'performance_id'    => (int) $p->id,
			);
			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = $candidate;
				continue;
			}
			$cmp = ACR_PerfValue::compare(
				$p->event,
				$candidate['performance_value'],
				$buckets[ $key ]['performance_value']
			);
			if ( $cmp < 0 ) {
				$buckets[ $key ] = $candidate;
			}
		}
		return $buckets;
	}

	protected function map_age_group( $po10_label ) {
		if ( ! $po10_label ) {
			return null;
		}
		$key = strtoupper( trim( $po10_label ) );
		return self::PO10_TO_OURS[ $key ] ?? null;
	}
}
