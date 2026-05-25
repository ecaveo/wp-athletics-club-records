<?php
/**
 * Delta planner.
 *
 * Decides which Po10 URLs the agent should visit next based on what we
 * already know, when we last fetched it, and where records are likely to
 * change. The aim is to keep the per-refresh scraping footprint small.
 *
 * Strategies:
 *   - `bootstrap`   : enqueue club athlete list + every known athlete profile.
 *   - `stale_first` : refresh athletes whose profile was scraped >14 days ago,
 *                     plus all current-year club rankings.
 *   - `full`        : reconciliation sweep — every athlete profile, every cell.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Planner {

	public function plan( $strategy = 'stale_first', $max = 25 ) {
		$settings = acr_get_settings();
		$uuid     = $settings['po10_club_uuid'];
		$added    = 0;

		// Always start with a club athletes list — that's how we discover new
		// members and confirm DOBs.
		$club_url = 'https://www.powerof10.uk/Home/Club/' . $uuid;
		ACR_Jobs::enqueue( ACR_Jobs::TYPE_CLUB_ATHLETES, $club_url );
		$added++;

		if ( $strategy === 'bootstrap' ) {
			foreach ( ACR_Athletes::all() as $a ) {
				if ( ! $a->po10_id ) {
					continue;
				}
				$url = $a->profile_url ?: 'https://www.powerof10.uk/athletes/profile.aspx?athleteid=' . $a->po10_id;
				if ( ACR_Jobs::enqueue( ACR_Jobs::TYPE_ATHLETE_PROFILE, $url, array( 'athlete_id' => $a->id ) ) ) {
					$added++;
					if ( $added >= $max ) {
						return $added;
					}
				}
			}
			return $added;
		}

		if ( $strategy === 'stale_first' ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . ACR_Athletes::table() . ' WHERE first_claim = 1 AND po10_id <> "" AND (last_profile_scrape IS NULL OR last_profile_scrape < %s) ORDER BY last_profile_scrape ASC LIMIT %d',
				$cutoff, $max
			) );
			foreach ( $rows as $a ) {
				$url = $a->profile_url ?: 'https://www.powerof10.uk/athletes/profile.aspx?athleteid=' . $a->po10_id;
				if ( ACR_Jobs::enqueue( ACR_Jobs::TYPE_ATHLETE_PROFILE, $url, array( 'athlete_id' => $a->id ) ) ) {
					$added++;
				}
			}
			return $added;
		}

		if ( $strategy === 'full' ) {
			$events = array_merge( ...array_values( acr_events() ) );
			$ages   = array_keys( acr_age_groups() );
			$count  = 0;
			foreach ( array( 'W', 'M' ) as $sex ) {
				foreach ( $ages as $age ) {
					if ( in_array( $age, array( 'SEN' ), true ) ) {
						$po10_age = 'OVER';
					} else {
						$po10_age = $age;
					}
					foreach ( $events as $ev ) {
						$url = sprintf(
							'https://www.powerof10.uk/Home/SearchRankingsTrackClub?ev=%s&yr=allTime&sex=%s&age=%s&clb=%s&pg=1',
							rawurlencode( $ev ), $sex, $po10_age, $uuid
						);
						if ( ACR_Jobs::enqueue( ACR_Jobs::TYPE_CLUB_RANKING, $url, array(
							'sex' => $sex, 'age' => $age, 'event' => $ev,
						) ) ) {
							$count++;
							if ( $count >= $max ) {
								return $added + $count;
							}
						}
					}
				}
			}
			$added += $count;
		}

		return $added;
	}
}
