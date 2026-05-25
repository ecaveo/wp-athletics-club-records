<?php
/**
 * [acr_records] shortcode + a Ninja-Tables-compatible HTML renderer so the
 * existing site CSS continues to apply unchanged.
 *
 * Usage:
 *   [acr_records gender="women"]
 *   [acr_records gender="men" filter="search"]   (default: search shown)
 *   [acr_records gender="women" show_age_value="0"]
 *
 * @package AthleticsClubRecords
 */

defined( 'ABSPATH' ) || exit;

class ACR_Shortcode {

	public static function init() {
		add_shortcode( 'acr_records', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		wp_register_style( 'acr-public', ACR_PLUGIN_URL . 'public/assets/public.css', array(), ACR_VERSION );
	}

	public static function render( $atts ) {
		wp_enqueue_style( 'acr-public' );

		$atts = shortcode_atts( array(
			'gender'         => 'women',
			'filter'         => '1',
			'show_age_value' => '0',
		), $atts, 'acr_records' );

		$sex = strtolower( $atts['gender'] ) === 'men' ? 'M' : 'F';
		$rows = ACR_Records::for_sex( $sex );

		// Index by event and age_group so we can render in a stable order.
		$by_event = array();
		foreach ( $rows as $r ) {
			$by_event[ $r->event ][ $r->age_group ] = $r;
		}

		$age_groups = acr_age_groups();
		$settings   = acr_get_settings();
		$last       = get_option( 'acr_last_recompute' );

		ob_start();
		?>
		<div class="acr-records-wrapper" data-gender="<?php echo esc_attr( $atts['gender'] ); ?>">
			<?php if ( $atts['filter'] === '1' ) : ?>
				<div class="acr-filter">
					<input type="search" class="acr-filter-input" placeholder="Filter by event, athlete, venue&hellip;" />
				</div>
			<?php endif; ?>
			<table class="ninja_table_pro acr-records" cellspacing="0" cellpadding="0">
				<thead>
					<tr>
						<th class="ninja_column_1">Event</th>
						<th class="ninja_column_2">Age Group</th>
						<th class="ninja_column_5">Performance</th>
						<th class="ninja_column_6">Athlete</th>
						<th class="ninja_column_7">Venue</th>
						<th class="ninja_column_8">Date</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$events_flat = array_merge( ...array_values( acr_events() ) );
					foreach ( $events_flat as $event ) :
						foreach ( $age_groups as $ag => $ag_def ) :
							$r = $by_event[ $event ][ $ag ] ?? null;
							$perf  = $r ? esc_html( $r->performance_raw ) : '';
							$ath   = $r ? esc_html( $r->athlete_name ) : '';
							$venue = $r ? esc_html( $r->venue ) : '';
							$date  = $r && $r->perf_date ? esc_html( date_i18n( 'd/m/Y', strtotime( $r->perf_date ) ) ) : '';
							$cls   = $r && $r->is_verified ? 'acr-row acr-verified' : 'acr-row';
							?>
							<tr class="<?php echo esc_attr( $cls ); ?>">
								<td class="ninja_column_1"><?php echo esc_html( $event ); ?></td>
								<td class="ninja_column_2"><?php echo esc_html( $ag_def['label'] ); ?></td>
								<td class="ninja_column_5" style="color: <?php echo esc_attr( $settings['record_colour'] ); ?>; font-weight:bold;"><?php echo $perf; // already escaped ?></td>
								<td class="ninja_column_6"><?php echo $ath; ?></td>
								<td class="ninja_column_7"><?php echo $venue; ?></td>
								<td class="ninja_column_8"><?php echo $date; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $last ) : ?>
				<p class="acr-meta">Last verified from Power of 10: <?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $last ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<script>
		(function(){
			var w = document.currentScript.previousElementSibling;
			var input = w.querySelector('.acr-filter-input');
			if (!input) return;
			input.addEventListener('input', function(e){
				var q = e.target.value.toLowerCase();
				w.querySelectorAll('tbody tr').forEach(function(tr){
					tr.style.display = tr.innerText.toLowerCase().indexOf(q) > -1 ? '' : 'none';
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
