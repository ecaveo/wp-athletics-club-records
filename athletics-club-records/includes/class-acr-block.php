<?php
/**
 * Gutenberg block stub. Renders via the same shortcode handler so editors
 * can drop "Athletics Club Records" into a page without remembering syntax.
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Block {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type( 'acr/records', array(
			'attributes' => array(
				'gender' => array( 'type' => 'string', 'default' => 'women' ),
				'filter' => array( 'type' => 'string', 'default' => '1' ),
			),
			'render_callback' => function( $attrs ) {
				return ACR_Shortcode::render( $attrs );
			},
		) );
	}
}
