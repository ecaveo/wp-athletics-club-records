<?php
/**
 * Plugin Name:       Athletics Club Records
 * Plugin URI:        https://github.com/brentwoodbeagles/wp-athletics-club-records
 * Description:       Maintains an athletics club's age-group records by pulling first-claim member performances from Power of 10 via an admin-driven Claude-in-Chrome agent loop. Records are recomputed from raw performances against the current age-group structure (U14/U16/U18/U20/senior/masters).
 * Version:           0.1.0
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

define( 'ACR_VERSION', '0.1.0' );
define( 'ACR_PLUGIN_FILE', __FILE__ );
define( 'ACR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACR_DB_VERSION', '1' );

// Autoload the includes directory.
foreach ( glob( ACR_PLUGIN_DIR . 'includes/class-*.php' ) as $file ) {
	require_once $file;
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'ACR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACR_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin once WordPress is ready.
 */
function acr_bootstrap() {
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
		'club_name'       => 'Brentwood Beagles Athletics Club',
		'club_short'      => 'BBAC',
		'po10_club_uuid'  => '0448550d-8759-4234-a7e1-415cdeb12ae1',
		'ninja_women_id'  => 9354,
		'ninja_men_id'    => 9359,
		'record_colour'   => '#c0392b',
		'agent_token'     => '',
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
