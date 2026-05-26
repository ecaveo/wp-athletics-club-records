<?php
/**
 * wp-admin screens.
 *
 * Top-level menu "Athletics Records" with subpages:
 *   - Dashboard  (overview, counts, last refresh)
 *   - Records    (read-only view of current records, manual override per cell)
 *   - Athletes   (toggle first_claim, view DOB / Po10 ID)
 *   - Agent Queue (Plan refresh, view pending jobs, copy-paste prompt)
 *   - Settings   (club name, Po10 UUID, Ninja Tables IDs, regenerate agent token)
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_acr_seed', array( __CLASS__, 'handle_seed' ) );
		add_action( 'admin_post_acr_plan', array( __CLASS__, 'handle_plan' ) );
		add_action( 'admin_post_acr_clear_completed', array( __CLASS__, 'handle_clear_completed' ) );
		add_action( 'admin_post_acr_recompute', array( __CLASS__, 'handle_recompute' ) );
		add_action( 'admin_post_acr_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_acr_rotate_token', array( __CLASS__, 'handle_rotate_token' ) );
		add_action( 'admin_post_acr_toggle_claim', array( __CLASS__, 'handle_toggle_claim' ) );
	}

	public static function assets( $hook ) {
		if ( strpos( $hook, 'acr-' ) === false && strpos( $hook, 'athletics-records' ) === false ) {
			return;
		}
		wp_enqueue_style( 'acr-admin', ACR_PLUGIN_URL . 'admin/assets/admin.css', array(), ACR_VERSION );
	}

	public static function menu() {
		add_menu_page(
			'Athletics Records',
			'Athletics Records',
			self::CAP,
			'athletics-records',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-awards',
			30
		);
		add_submenu_page( 'athletics-records', 'Dashboard', 'Dashboard', self::CAP, 'athletics-records', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'athletics-records', 'Records',   'Records',   self::CAP, 'acr-records',   array( __CLASS__, 'render_records' ) );
		add_submenu_page( 'athletics-records', 'Athletes',  'Athletes',  self::CAP, 'acr-athletes', array( __CLASS__, 'render_athletes' ) );
		add_submenu_page( 'athletics-records', 'Agent Queue', 'Agent Queue', self::CAP, 'acr-agent',  array( __CLASS__, 'render_agent' ) );
		add_submenu_page( 'athletics-records', 'Settings',  'Settings',  self::CAP, 'acr-settings', array( __CLASS__, 'render_settings' ) );
	}

	private static function check_cap() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Insufficient permissions.' );
		}
	}

	/* ---------------- Renderers ---------------- */

	public static function render_dashboard() {
		self::check_cap();
		$stats = array(
			'records'      => ACR_Records::count(),
			'verified'     => ACR_Records::count_verified(),
			'performances' => ACR_Performances::count(),
			'athletes'     => count( ACR_Athletes::all() ),
			'jobs'         => ACR_Jobs::stats(),
			'last_seed'    => get_option( 'acr_last_seed' ),
			'last_recompute' => get_option( 'acr_last_recompute' ),
		);
		include ACR_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function render_records() {
		self::check_cap();
		$sex_filter = isset( $_GET['sex'] ) ? strtoupper( substr( sanitize_text_field( wp_unslash( $_GET['sex'] ) ), 0, 1 ) ) : 'F';
		$rows = ACR_Records::for_sex( $sex_filter );
		include ACR_PLUGIN_DIR . 'admin/views/records.php';
	}

	public static function render_athletes() {
		self::check_cap();
		$athletes = ACR_Athletes::all();
		include ACR_PLUGIN_DIR . 'admin/views/athletes.php';
	}

	public static function render_agent() {
		self::check_cap();
		$settings = acr_get_settings();
		$jobs     = ACR_Jobs::recent( 100 );
		$stats    = ACR_Jobs::stats();
		include ACR_PLUGIN_DIR . 'admin/views/agent-queue.php';
	}

	public static function render_settings() {
		self::check_cap();
		$settings = acr_get_settings();
		include ACR_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/* ---------------- Action handlers ---------------- */

	public static function handle_seed() {
		self::check_cap();
		check_admin_referer( 'acr_seed' );
		$result = ACR_Seeder::run();
		set_transient( 'acr_notice', 'Seed complete: women ' . $result['women'] . ', men ' . $result['men'] . ', skipped ' . $result['skipped'] . '.', 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=athletics-records' ) );
		exit;
	}

	public static function handle_plan() {
		self::check_cap();
		check_admin_referer( 'acr_plan' );
		$strategy = isset( $_POST['strategy'] ) ? sanitize_text_field( wp_unslash( $_POST['strategy'] ) ) : 'rankings_sweep';
		$max      = isset( $_POST['max'] ) ? (int) $_POST['max'] : 300;
		$added    = ( new ACR_Planner() )->plan( $strategy, $max );
		set_transient( 'acr_notice', "Queued {$added} jobs ({$strategy}).", 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=acr-agent' ) );
		exit;
	}

	public static function handle_clear_completed() {
		self::check_cap();
		check_admin_referer( 'acr_clear_completed' );
		ACR_Jobs::clear_completed();
		wp_safe_redirect( admin_url( 'admin.php?page=acr-agent' ) );
		exit;
	}

	public static function handle_recompute() {
		self::check_cap();
		check_admin_referer( 'acr_recompute' );
		$res = ( new ACR_Recompute() )->run();
		set_transient( 'acr_notice', 'Recompute: ' . $res['cells_updated'] . ' updated, ' . $res['cells_skipped_override'] . ' protected by override.', 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=athletics-records' ) );
		exit;
	}

	public static function handle_save_settings() {
		self::check_cap();
		check_admin_referer( 'acr_save_settings' );
		$patch = array(
			'club_name'          => sanitize_text_field( wp_unslash( $_POST['club_name'] ?? '' ) ),
			'club_short'         => sanitize_text_field( wp_unslash( $_POST['club_short'] ?? '' ) ),
			'po10_club_uuid'     => sanitize_text_field( wp_unslash( $_POST['po10_club_uuid'] ?? '' ) ),
			'po10_club_name'     => sanitize_text_field( wp_unslash( $_POST['po10_club_name'] ?? '' ) ),
			'ninja_women_id'     => (int) ( $_POST['ninja_women_id'] ?? 0 ),
			'ninja_men_id'       => (int) ( $_POST['ninja_men_id'] ?? 0 ),
			'record_colour'      => sanitize_hex_color( wp_unslash( $_POST['record_colour'] ?? '#c0392b' ) ),
			'performances_since' => sanitize_text_field( wp_unslash( $_POST['performances_since'] ?? '2022-01-01' ) ),
		);
		acr_update_settings( $patch );
		set_transient( 'acr_notice', 'Settings saved.', 15 );
		wp_safe_redirect( admin_url( 'admin.php?page=acr-settings' ) );
		exit;
	}

	public static function handle_rotate_token() {
		self::check_cap();
		check_admin_referer( 'acr_rotate_token' );
		acr_update_settings( array( 'agent_token' => wp_generate_password( 32, false ) ) );
		set_transient( 'acr_notice', 'Agent token rotated. Update Claude in Chrome SOP.', 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=acr-settings' ) );
		exit;
	}

	public static function handle_toggle_claim() {
		self::check_cap();
		check_admin_referer( 'acr_toggle_claim' );
		$id = (int) ( $_POST['id'] ?? 0 );
		$to = (int) ( $_POST['to'] ?? 0 );
		if ( $id ) {
			ACR_Athletes::set_first_claim( $id, $to );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=acr-athletes' ) );
		exit;
	}

	/* helpers */
	public static function notice() {
		$n = get_transient( 'acr_notice' );
		if ( $n ) {
			delete_transient( 'acr_notice' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $n ) . '</p></div>';
		}
	}
}
