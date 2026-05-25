<?php
/**
 * REST API for the Claude-in-Chrome agent.
 *
 * Endpoints (all under /wp-json/acr/v1):
 *   GET  /jobs                — list pending+claimed jobs (auth required)
 *   POST /jobs/plan           — enqueue a fresh batch of work (auth required)
 *   POST /jobs/{id}/result    — submit scraped data (auth required)
 *   POST /jobs/{id}/fail      — mark a job failed (auth required)
 *   GET  /records             — public read-only view used by the frontend
 *   POST /recompute           — force a recompute pass (auth required)
 *
 * Authentication: a bearer token set in plugin settings. The token rotates if
 * the admin clicks "regenerate". Designed to be pasted into the Claude in
 * Chrome SOP so the agent can read jobs and post results back without
 * impersonating a logged-in WP user.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_rest_route( 'acr/v1', '/jobs', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_jobs' ),
			'permission_callback' => array( __CLASS__, 'check_token' ),
		) );
		register_rest_route( 'acr/v1', '/jobs/plan', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'plan_jobs' ),
			'permission_callback' => array( __CLASS__, 'check_token' ),
		) );
		register_rest_route( 'acr/v1', '/jobs/(?P<id>\d+)/result', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit_result' ),
			'permission_callback' => array( __CLASS__, 'check_token' ),
		) );
		register_rest_route( 'acr/v1', '/jobs/(?P<id>\d+)/fail', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit_fail' ),
			'permission_callback' => array( __CLASS__, 'check_token' ),
		) );
		register_rest_route( 'acr/v1', '/records', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'public_records' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'acr/v1', '/recompute', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'recompute' ),
			'permission_callback' => array( __CLASS__, 'check_token' ),
		) );
	}

	public static function check_token( $request ) {
		$settings = acr_get_settings();
		$expected = $settings['agent_token'];
		if ( ! $expected ) {
			return new WP_Error( 'acr_no_token', 'Agent token not set.', array( 'status' => 401 ) );
		}
		$auth = $request->get_header( 'Authorization' );
		if ( $auth && stripos( $auth, 'bearer ' ) === 0 ) {
			$got = trim( substr( $auth, 7 ) );
		} else {
			$got = $request->get_param( 'token' );
		}
		if ( ! $got || ! hash_equals( $expected, $got ) ) {
			return new WP_Error( 'acr_bad_token', 'Bad agent token.', array( 'status' => 403 ) );
		}
		// Allow admin users without a token (they can hit endpoints from wp-admin AJAX too).
		return true;
	}

	public static function list_jobs( $request ) {
		$limit = (int) $request->get_param( 'limit' ) ?: 25;
		$jobs  = ACR_Jobs::next_batch( $limit );
		return rest_ensure_response( array(
			'jobs' => array_map( function( $j ) {
				return array(
					'id'         => (int) $j->id,
					'type'       => $j->job_type,
					'url'        => $j->target_url,
					'payload'    => $j->payload ? json_decode( $j->payload, true ) : null,
					'attempts'   => (int) $j->attempts,
				);
			}, $jobs ),
			'count' => count( $jobs ),
		) );
	}

	public static function plan_jobs( $request ) {
		$strategy = $request->get_param( 'strategy' ) ?: 'stale_first';
		$max      = (int) ( $request->get_param( 'max' ) ?: 25 );

		$planner = new ACR_Planner();
		$added   = $planner->plan( $strategy, $max );

		return rest_ensure_response( array(
			'planned' => $added,
			'stats'   => ACR_Jobs::stats(),
		) );
	}

	public static function submit_result( $request ) {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'acr_bad_body', 'Expected JSON body.', array( 'status' => 400 ) );
		}
		ACR_Jobs::complete( $id, $body );

		// Dispatch to the appropriate parser based on job type.
		global $wpdb;
		$job = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . ACR_Jobs::table() . ' WHERE id = %d', $id
		) );
		$parsed = null;
		if ( $job ) {
			$parsed = ACR_Po10_Parser::ingest( $job->job_type, $body, $job->target_url );
		}
		return rest_ensure_response( array(
			'ok'     => true,
			'parsed' => $parsed,
		) );
	}

	public static function submit_fail( $request ) {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		$err  = is_array( $body ) && isset( $body['error'] ) ? $body['error'] : 'unknown';
		ACR_Jobs::fail( $id, $err );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function public_records( $request ) {
		$sex = strtoupper( substr( $request->get_param( 'sex' ) ?: 'F', 0, 1 ) );
		$rows = ACR_Records::for_sex( $sex );
		return rest_ensure_response( $rows );
	}

	public static function recompute( $request ) {
		$engine = new ACR_Recompute();
		$result = $engine->run();
		return rest_ensure_response( $result );
	}
}
