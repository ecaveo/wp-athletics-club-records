<?php
/**
 * Activator — creates DB tables, sets defaults.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Activator {

	public static function activate() {
		self::create_tables();
		self::migrate();
		self::set_default_options();
		update_option( 'acr_db_version', ACR_DB_VERSION );
	}

	/**
	 * Run any in-place migrations needed beyond what dbDelta can express.
	 * dbDelta cannot drop indexes or change column types in some cases.
	 */
	private static function migrate() {
		global $wpdb;
		$athletes = $wpdb->prefix . 'acr_athletes';
		$installed = get_option( 'acr_db_version' );

		if ( version_compare( $installed ?: '0', '3', '<' ) ) {
			// v3: po10_id widened to VARCHAR(40) and UNIQUE→INDEX (multiple
			// empty po10_ids must coexist while we discover UUIDs).
			$idx = $wpdb->get_results( "SHOW INDEX FROM {$athletes} WHERE Key_name = 'po10_id'" );
			if ( $idx ) {
				$is_unique = isset( $idx[0]->Non_unique ) && (int) $idx[0]->Non_unique === 0;
				if ( $is_unique ) {
					$wpdb->query( "ALTER TABLE {$athletes} DROP INDEX po10_id" );
					$wpdb->query( "ALTER TABLE {$athletes} ADD INDEX po10_id (po10_id)" );
				}
			}
			$wpdb->query( "ALTER TABLE {$athletes} MODIFY po10_id VARCHAR(40) NOT NULL DEFAULT ''" );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$athletes = "CREATE TABLE {$wpdb->prefix}acr_athletes (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			po10_id VARCHAR(40) NOT NULL DEFAULT '',
			name VARCHAR(191) NOT NULL,
			sex CHAR(1) NOT NULL,
			dob DATE NULL,
			first_claim TINYINT(1) NOT NULL DEFAULT 1,
			first_claim_since DATE NULL,
			first_claim_until DATE NULL,
			profile_url VARCHAR(255) NOT NULL DEFAULT '',
			notes TEXT NULL,
			last_profile_scrape DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY po10_id (po10_id),
			KEY name (name(50)),
			KEY sex (sex)
		) $charset_collate;";

		$performances = "CREATE TABLE {$wpdb->prefix}acr_performances (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			athlete_id BIGINT(20) UNSIGNED NOT NULL,
			event VARCHAR(32) NOT NULL,
			performance_raw VARCHAR(32) NOT NULL,
			performance_value DECIMAL(12,4) NULL,
			age_group_at_time VARCHAR(8) NULL,
			perf_year SMALLINT NULL,
			is_indoor TINYINT(1) NOT NULL DEFAULT 0,
			is_wind_assisted TINYINT(1) NOT NULL DEFAULT 0,
			is_field TINYINT(1) NOT NULL DEFAULT 0,
			is_pb TINYINT(1) NOT NULL DEFAULT 0,
			position VARCHAR(8) NULL,
			venue VARCHAR(191) NULL,
			meeting VARCHAR(191) NULL,
			perf_date DATE NULL,
			source_url VARCHAR(255) NULL,
			fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY athlete_event_date (athlete_id, event, perf_date),
			KEY event (event),
			KEY perf_date (perf_date),
			KEY age_group (age_group_at_time)
		) $charset_collate;";

		$records = "CREATE TABLE {$wpdb->prefix}acr_records (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sex CHAR(1) NOT NULL,
			age_group VARCHAR(8) NOT NULL,
			event VARCHAR(32) NOT NULL,
			performance_raw VARCHAR(32) NOT NULL,
			performance_value DECIMAL(12,4) NULL,
			athlete_id BIGINT(20) UNSIGNED NULL,
			athlete_name VARCHAR(191) NOT NULL,
			venue VARCHAR(191) NULL,
			perf_date DATE NULL,
			performance_id BIGINT(20) UNSIGNED NULL,
			is_manual_override TINYINT(1) NOT NULL DEFAULT 0,
			is_verified TINYINT(1) NOT NULL DEFAULT 0,
			source VARCHAR(32) NOT NULL DEFAULT 'seed',
			notes TEXT NULL,
			computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY cell (sex, age_group, event),
			KEY athlete (athlete_id)
		) $charset_collate;";

		$jobs = "CREATE TABLE {$wpdb->prefix}acr_scrape_jobs (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_type VARCHAR(32) NOT NULL,
			target_url VARCHAR(500) NOT NULL,
			payload TEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			result_json LONGTEXT NULL,
			claimed_at DATETIME NULL,
			completed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY job_type (job_type)
		) $charset_collate;";

		dbDelta( $athletes );
		dbDelta( $performances );
		dbDelta( $records );
		dbDelta( $jobs );
	}

	private static function set_default_options() {
		$existing = get_option( 'acr_settings' );
		if ( ! is_array( $existing ) ) {
			update_option( 'acr_settings', array(
				'club_name'      => 'Brentwood Beagles Athletics Club',
				'club_short'     => 'BBAC',
				'po10_club_uuid' => '0448550d-8759-4234-a7e1-415cdeb12ae1',
				'ninja_women_id' => 9354,
				'ninja_men_id'   => 9359,
				'record_colour'  => '#c0392b',
				'agent_token'    => wp_generate_password( 32, false ),
			) );
			return;
		}
		if ( empty( $existing['agent_token'] ) ) {
			$existing['agent_token'] = wp_generate_password( 32, false );
			update_option( 'acr_settings', $existing );
		}
	}
}
