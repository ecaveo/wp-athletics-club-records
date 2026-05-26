<?php
/**
 * Delta planner (v0.3.0) — club-rankings sweep.
 *
 * Po10's club page Rankings widget lets you pick year × sex × age × event.
 * Selecting age=OVERALL returns every age group inline-tagged. So one query
 * per (year × sex × event) cell yields *all* club performances for that
 * combination — across every age group — already filtered to first-claim
 * members.
 *
 * Strategies:
 *   - `rankings_sweep`   : full historical seed. Years from
 *                           settings.performances_since up to current year,
 *                           both sexes, every track-and-field event.
 *   - `current_year`     : just the current year (incremental refresh).
 *   - `single_year`      : one year passed as payload.year.
 *
 * Each job's URL is the club page; the payload carries the filters the
 * agent must apply in the UI.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Planner {

	/**
	 * Plan a batch of club_ranking jobs.
	 *
	 * @param string $strategy
	 * @param int    $max maximum jobs to enqueue this call.
	 * @param array  $args optional overrides (year, sex, etc).
	 * @return int jobs enqueued.
	 */
	public function plan( $strategy = 'rankings_sweep', $max = 300, $args = array() ) {
		$settings   = acr_get_settings();
		$club_url   = 'https://www.powerof10.uk/Home/Club/' . $settings['po10_club_uuid'];
		$start_year = (int) substr( $settings['performances_since'], 0, 4 );
		$current    = (int) gmdate( 'Y' );

		switch ( $strategy ) {
			case 'current_year':
				$years = array( $current );
				break;
			case 'single_year':
				$years = array( (int) ( $args['year'] ?? $current ) );
				break;
			case 'rankings_sweep':
			default:
				$years = range( $start_year, $current );
				break;
		}

		$sexes  = ! empty( $args['sexes'] ) ? $args['sexes'] : array( 'W', 'M' );
		$events = ! empty( $args['events'] ) ? $args['events'] : self::default_events();

		$added = 0;
		foreach ( $years as $year ) {
			foreach ( $sexes as $sex ) {
				foreach ( $events as $event ) {
					if ( $added >= $max ) {
						return $added;
					}
					$payload = array(
						'year'  => $year,
						'sex'   => $sex,
						'age'   => 'OVERALL',
						'event' => $event,
					);
					if ( ACR_Jobs::enqueue( ACR_Jobs::TYPE_CLUB_RANKING, $club_url, $payload ) ) {
						$added++;
					}
				}
			}
		}
		return $added;
	}

	/**
	 * Standard track & field events shown in the Po10 club rankings widget.
	 */
	public static function default_events() {
		return array(
			'60', '100', '200', '400', '800', '1500', 'Mile',
			'3000', '5000', '10000', '2000SC', '3000SC',
			'60 Hurdles', '100 Hurdles', '110 Hurdles', '400 Hurdles',
			'High Jump', 'Pole Vault', 'Long Jump', 'Triple Jump',
			'Shot', 'Discus', 'Hammer', 'Javelin',
			'Heptathlon', 'Decathlon', 'Indoor Pen', '4x100', '4x400',
		);
	}
}
