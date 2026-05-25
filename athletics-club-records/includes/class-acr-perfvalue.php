<?php
/**
 * Performance value normaliser.
 *
 * Converts raw Po10 performance strings to a comparable numeric value:
 *  - Times: stored as total seconds (DECIMAL(12,4)). Lower is better.
 *  - Field events: stored as metres / points. Higher is better.
 *
 * Handles formats: "10.62i", "1:23.45", "2:01:34", "12.45m", "5234",
 * "10.62w" (wind), "DNF", "NM", "0.00".
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_PerfValue {

	/**
	 * Events where bigger numbers are better.
	 */
	const FIELD_EVENTS = array(
		'High Jump', 'Pole Vault', 'Long Jump', 'Triple Jump',
		'Shot', 'Discus', 'Hammer', 'Javelin', 'Weight',
		'Heptathlon', 'Decathlon', 'Pentathlon', 'Indoor Pen', 'Indoor Hep',
	);

	/**
	 * Returns true if the event is a field event (higher = better).
	 */
	public static function is_field_event( $event ) {
		return in_array( $event, self::FIELD_EVENTS, true );
	}

	/**
	 * Parse a raw performance string into a numeric value.
	 *
	 * @param string $raw   e.g. "10.62i", "1:23.45", "12.45m"
	 * @param string $event Event name — used to decide field-vs-track.
	 * @return array { value: float|null, indoor: bool, wind: bool, valid: bool }
	 */
	public static function parse( $raw, $event = '' ) {
		$out = array(
			'value'  => null,
			'indoor' => false,
			'wind'   => false,
			'valid'  => false,
		);

		if ( $raw === '' || $raw === null ) {
			return $out;
		}

		$raw = trim( (string) $raw );

		// Wind-assisted markers.
		if ( substr( $raw, -1 ) === 'w' ) {
			$out['wind'] = true;
			$raw = substr( $raw, 0, -1 );
		}

		// Indoor markers (Po10 uses trailing 'i').
		if ( substr( $raw, -1 ) === 'i' ) {
			$out['indoor'] = true;
			$raw = substr( $raw, 0, -1 );
		}

		// Strip trailing 'm' on field events.
		if ( substr( $raw, -1 ) === 'm' ) {
			$raw = substr( $raw, 0, -1 );
		}

		// Reject non-results.
		$upper = strtoupper( $raw );
		if ( in_array( $upper, array( 'DNF', 'DNS', 'DQ', 'NM', 'NH' ), true ) ) {
			return $out;
		}

		// Time formats with colons.
		if ( strpos( $raw, ':' ) !== false ) {
			$parts = array_reverse( explode( ':', $raw ) );
			$seconds = 0.0;
			$mult = 1;
			foreach ( $parts as $p ) {
				$seconds += (float) $p * $mult;
				$mult *= 60;
			}
			$out['value'] = $seconds;
			$out['valid'] = $seconds > 0;
			return $out;
		}

		// Plain numeric — seconds (track) or metres (field).
		if ( is_numeric( $raw ) ) {
			$out['value'] = (float) $raw;
			$out['valid'] = $out['value'] > 0;
		}

		return $out;
	}

	/**
	 * Compare two performances for a given event. Returns -1 if $a is better, +1
	 * if $b is better, 0 if equal or incomparable.
	 */
	public static function compare( $event, $a_value, $b_value ) {
		if ( $a_value === null || $b_value === null ) {
			return 0;
		}
		$bigger_better = self::is_field_event( $event );
		if ( $a_value == $b_value ) {
			return 0;
		}
		if ( $bigger_better ) {
			return $a_value > $b_value ? -1 : 1;
		}
		return $a_value < $b_value ? -1 : 1;
	}
}
