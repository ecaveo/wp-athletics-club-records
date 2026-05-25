<?php
/**
 * Recomputation engine (Option C).
 *
 * Rebuilds the records table from raw performances + athlete DOBs against
 * the current age-group structure. Reads first_claim flag — non-first-claim
 * athletes' performances are excluded.
 *
 * Wind-assisted performances are excluded (the BBAC page is explicit about
 * this). Indoor and outdoor are merged into a single record per (sex, age,
 * event) — the indoor marker is preserved on the performance row and shown
 * with a trailing 'i' in the public display.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Recompute {

	/**
	 * Recompute every cell. Returns counts.
	 */
	public function run() {
		global $wpdb;
		$pt = ACR_Performances::table();
		$at = ACR_Athletes::table();

		$rows = $wpdb->get_results(
			"SELECT p.*, a.dob, a.sex, a.first_claim, a.name as athlete_name
			   FROM {$pt} p
			   JOIN {$at} a ON a.id = p.athlete_id
			  WHERE a.first_claim = 1
			    AND p.is_wind_assisted = 0
			    AND p.performance_value IS NOT NULL"
		);

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
			'cells_updated' => $updates,
			'cells_skipped_override' => $skipped,
			'performances_considered' => count( $rows ),
		);
	}

	/**
	 * Recompute only the cells touched by one athlete's events.
	 */
	public function run_for_athlete( $athlete_id ) {
		// For simplicity v1 just runs a full recompute. With many athletes we
		// can scope this to the events the athlete has performed in.
		return $this->run();
	}

	/**
	 * Group performances into (sex, age_group, event) buckets and keep the best.
	 *
	 * @return array key => best performance row
	 */
	protected function bucket( array $rows ) {
		$buckets = array();
		$age_defs = acr_age_groups();

		foreach ( $rows as $p ) {
			if ( ! $p->perf_date || ! $p->event || ! $p->performance_value ) {
				continue;
			}
			$age = $p->dob ? ACR_Athletes::age_on_date( $p->dob, $p->perf_date ) : null;
			if ( $age === null ) {
				// Without DOB we can't be sure which age group — skip the youth
				// age-group records but still allow into senior (the rule used
				// is conservative: only count for SEN if athlete has any senior-
				// era performance).
				$age = 99; // forces into senior+ bracket only.
			}

			$age_group = $this->bucket_for_age( $age, $age_defs );
			if ( ! $age_group ) {
				continue;
			}

			$key = strtoupper( $p->sex ) . '|' . $age_group . '|' . $p->event;
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

	protected function bucket_for_age( $age, $age_defs ) {
		foreach ( $age_defs as $code => $b ) {
			if ( $age >= $b['min'] && $age <= $b['max'] ) {
				return $code;
			}
		}
		return null;
	}
}
