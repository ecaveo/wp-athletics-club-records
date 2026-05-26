<?php
/**
 * Plugin Name:       Athletics Club Records
 * Plugin URI:        https://github.com/brentwoodbeagles/wp-athletics-club-records
 * Description:       Maintains an athletics club's age-group records by pulling first-claim member performances from Power of 10 via an admin-driven Claude-in-Chrome agent loop. Records are recomputed from raw performances against the current age-group structure (U14/U16/U18/U20/senior/masters).
 * Version:           0.3.7
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Brentwood Beagles Athletics Club
 * Author URI:        https://www.beagles.org.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       athletics-club-records
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

define( 'ACR_VERSION', '0.3.7' );
define( 'ACR_PLUGIN_FILE', __FILE__ );
define( 'ACR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACR_DB_VERSION', '3' );

// Autoload the includes directory.
foreach ( glob( ACR_PLUGIN_DIR . 'includes/class-*.php' ) as $file ) {
	require_once $file;
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'ACR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACR_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin once WordPress is ready.
 * Also runs an in-place DB upgrade if the schema version is behind.
 */
function acr_bootstrap() {
	$installed = get_option( 'acr_db_version' );
	if ( $installed !== ACR_DB_VERSION ) {
		ACR_Activator::activate();
	}
	ACR_Admin::init();
	ACR_REST::init();
	ACR_Shortcode::init();
	ACR_Block::init();
}
add_action( 'plugins_loaded', 'acr_bootstrap' );

/**
 * Convenience accessor for the plugin's option array.
 *
 * @return array
 */
function acr_get_settings() {
	$defaults = array(
		'club_name'           => 'Brentwood Beagles Athletics Club',
		'club_short'          => 'BBAC',
		'po10_club_uuid'      => '0448550d-8759-4234-a7e1-415cdeb12ae1',
		'po10_club_name'      => 'Brentwood Beagles', // for athlete_search filter
		'ninja_women_id'      => 9354,
		'ninja_men_id'        => 9359,
		'record_colour'       => '#c0392b',
		'agent_token'         => '',
		'performances_since'  => '2022-01-01',
	);
	$saved = get_option( 'acr_settings', array() );
	return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

/**
 * Persist a settings update.
 *
 * @param array $patch Keys to update.
 */
function acr_update_settings( array $patch ) {
	$current = acr_get_settings();
	update_option( 'acr_settings', array_merge( $current, $patch ) );
}

/**
 * The set of age groups the club currently tracks.
 * Order matters — used for display.
 *
 * @return array
 */
function acr_age_groups() {
	return array(
		'U14'    => array( 'min' => 0,  'max' => 13, 'label' => 'U14' ),
		'U16'    => array( 'min' => 14, 'max' => 15, 'label' => 'U16' ),
		'U18'    => array( 'min' => 16, 'max' => 17, 'label' => 'U18' ),
		'U20'    => array( 'min' => 18, 'max' => 19, 'label' => 'U20' ),
		'SEN'    => array( 'min' => 20, 'max' => 34, 'label' => 'Senior' ),
		'V35'    => array( 'min' => 35, 'max' => 39, 'label' => 'V35' ),
		'V40'    => array( 'min' => 40, 'max' => 44, 'label' => 'V40' ),
		'V45'    => array( 'min' => 45, 'max' => 49, 'label' => 'V45' ),
		'V50'    => array( 'min' => 50, 'max' => 54, 'label' => 'V50' ),
		'V55'    => array( 'min' => 55, 'max' => 59, 'label' => 'V55' ),
		'V60'    => array( 'min' => 60, 'max' => 64, 'label' => 'V60' ),
		'V65'    => array( 'min' => 65, 'max' => 69, 'label' => 'V65' ),
		'V70'    => array( 'min' => 70, 'max' => 74, 'label' => 'V70' ),
		'V75'    => array( 'min' => 75, 'max' => 79, 'label' => 'V75' ),
		'V80'    => array( 'min' => 80, 'max' => 200, 'label' => 'V80+' ),
	);
}

/**
 * The standard track and field events the club records cover.
 *
 * @return array
 */
function acr_events() {
	return array(
		'track' => array( '60', '100', '200', '300', '400', '600', '800', '1500', 'Mile', '3000', '5000', '10000', '2000SC', '3000SC' ),
		'hurdles' => array( '60H', '70H', '75H', '80H', '100H', '110H', '300H', '400H' ),
		'jumps' => array( 'High Jump', 'Pole Vault', 'Long Jump', 'Triple Jump' ),
		'throws' => array( 'Shot', 'Discus', 'Hammer', 'Javelin' ),
		'multi' => array( 'Heptathlon', 'Decathlon', 'Pentathlon', 'Indoor Pen' ),
		'relays' => array( '4x100', '4x200', '4x400' ),
		'road' => array( '5K', '10K', 'Half Marathon', 'Marathon', 'parkrun' ),
	);
}

/**
 * UKA event eligibility per age group (TR3 S2 / TR3 S4).
 *
 * Returns true if a competitor in $age_group is permitted to compete in
 * $event under the current UKA rules. Used by the public renderer to hide
 * (event, age_group) cells that are not legal (e.g. U14 Marathon, U14
 * Triple Jump, U16 race over 3000m).
 *
 * Senior (SEN) and Masters (V35+) age groups are eligible for everything;
 * specific Masters implement weights are not modelled here as Po10 already
 * records the appropriate event for each athlete.
 *
 * @param string $event      Event name as used in acr_events().
 * @param string $age_group  Age group code (U14|U16|U18|U20|SEN|V35..V80).
 * @return bool
 */
function acr_event_allowed( $event, $age_group ) {
	// Senior + Masters: nothing restricted by these rules.
	if ( $age_group === 'SEN' || strpos( $age_group, 'V' ) === 0 ) {
		return true;
	}

	// Helper to detect track race distance from event name like "1500", "10000", "3000SC".
	$is_distance_event = preg_match( '/^(\d+)(SC|H)?$/', $event, $m );
	$distance_metres   = $is_distance_event ? (int) $m[1] : 0;
	$is_steeplechase   = $is_distance_event && isset( $m[2] ) && $m[2] === 'SC';
	$is_hurdles        = $is_distance_event && isset( $m[2] ) && $m[2] === 'H';

	switch ( $age_group ) {
		case 'U14':
			// TR3 S2(1): max track race one mile (~1609m); no 300m or 400m;
			// no Triple Jump; 1200m SC only (not in our event list).
			if ( $event === 'Triple Jump' )                           return false;
			if ( in_array( $event, array( '300', '400', '300H', '400H' ), true ) ) return false;
			if ( $event === '2000SC' || $event === '3000SC' )         return false;
			if ( $event === '110H' )                                  return false; // senior men's hurdle
			if ( $is_distance_event && ! $is_hurdles && ! $is_steeplechase && $distance_metres > 1609 ) return false;
			// Road / off-track max 6km (TR3 S4):
			if ( $event === '10K' )                                   return false;
			if ( $event === 'Half Marathon' )                         return false;
			if ( $event === 'Marathon' )                              return false;
			return true;

		case 'U16':
			// TR3 S2(2): no race in excess of 3000m; 2000SC permitted for 16-by-Dec-31 athletes.
			if ( $event === '110H' )                                  return false;
			if ( $event === '3000SC' )                                return false;
			if ( $is_distance_event && ! $is_hurdles && ! $is_steeplechase && $distance_metres > 3000 ) return false;
			// Road max 12km (TR3 S4):
			if ( $event === 'Half Marathon' )                         return false;
			if ( $event === 'Marathon' )                              return false;
			return true;

		case 'U18':
			// TR3 S2(3): no track event in excess of 5000m.
			if ( $is_distance_event && ! $is_hurdles && ! $is_steeplechase && $distance_metres > 5000 ) return false;
			// Road max 25km (TR3 S4):
			if ( $event === 'Marathon' )                              return false;
			return true;

		case 'U20':
			// TR3 S2(4): no track event in excess of 10000m. Road incl. Marathon allowed.
			if ( $is_distance_event && ! $is_hurdles && ! $is_steeplechase && $distance_metres > 10000 ) return false;
			return true;
	}

	return true;
}
