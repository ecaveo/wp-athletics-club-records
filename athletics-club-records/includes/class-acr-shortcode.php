<?php
/**
 * [acr_records] shortcode renderer.
 *
 * Usage:
 *   [acr_records]                       — defaults to gender="all" with radios
 *   [acr_records gender="all"]          — show both, radio filter (W/M/All)
 *   [acr_records gender="women"]        — women only, no sex radio
 *   [acr_records gender="men"]          — men only, no sex radio
 *   [acr_records hide_empty="0"]        — show every event×age cell even if blank
 *   [acr_records filter="0"]            — hide the text search box
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
			'gender'         => 'all',
			'filter'         => '1',
			'hide_empty'     => '1',
			'default_sex'    => 'all', // initial radio selection when gender=all
		), $atts, 'acr_records' );

		$mode = strtolower( $atts['gender'] );
		if ( ! in_array( $mode, array( 'all', 'women', 'men' ), true ) ) {
			$mode = 'all';
		}

		// Pull records.
		if ( $mode === 'women' ) {
			$rows = ACR_Records::for_sex( 'F' );
			$sexes = array( 'F' );
		} elseif ( $mode === 'men' ) {
			$rows = ACR_Records::for_sex( 'M' );
			$sexes = array( 'M' );
		} else {
			$rows = array_merge( ACR_Records::for_sex( 'F' ), ACR_Records::for_sex( 'M' ) );
			$sexes = array( 'F', 'M' );
		}

		// Index by (sex, event, age_group) for quick lookup.
		$by = array();
		foreach ( $rows as $r ) {
			$by[ $r->sex ][ $r->event ][ $r->age_group ] = $r;
		}

		$age_groups  = acr_age_groups();
		$settings    = acr_get_settings();
		$last        = get_option( 'acr_last_recompute' );
		$events_flat = array_merge( ...array_values( acr_events() ) );
		$show_sex_col = ( $mode === 'all' );
		$show_radios  = ( $mode === 'all' );
		$default_sex  = strtoupper( substr( $atts['default_sex'], 0, 1 ) );
		if ( ! in_array( $default_sex, array( 'F', 'M' ), true ) ) {
			$default_sex = 'A';
		}

		ob_start();
		?>
		<div class="acr-records-wrapper" data-gender="<?php echo esc_attr( $mode ); ?>">
			<div class="acr-controls">
				<?php if ( $show_radios ) : ?>
					<div class="acr-control-group acr-sex-filter">
						<strong>Show:</strong>
						<label><input type="radio" name="acr-sex-<?php echo esc_attr( uniqid() ); ?>" value="A"<?php checked( $default_sex, 'A' ); ?>> All</label>
						<label><input type="radio" name="acr-sex-<?php echo esc_attr( uniqid() ); ?>" value="F"<?php checked( $default_sex, 'F' ); ?>> Women</label>
						<label><input type="radio" name="acr-sex-<?php echo esc_attr( uniqid() ); ?>" value="M"<?php checked( $default_sex, 'M' ); ?>> Men</label>
					</div>
				<?php endif; ?>
				<div class="acr-control-group acr-hide-empty-group">
					<label><input type="checkbox" class="acr-hide-empty"<?php checked( (bool) $atts['hide_empty'] ); ?>> Hide empty rows</label>
				</div>
				<?php if ( $atts['filter'] === '1' ) : ?>
					<div class="acr-control-group acr-filter">
						<input type="search" class="acr-filter-input" placeholder="Filter by event, athlete, venue&hellip;" />
					</div>
				<?php endif; ?>
			</div>

			<table class="ninja_table_pro acr-records" cellspacing="0" cellpadding="0">
				<thead>
					<tr>
						<?php if ( $show_sex_col ) : ?>
							<th class="ninja_column_0">Sex</th>
						<?php endif; ?>
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
					foreach ( $sexes as $sex ) :
						foreach ( $events_flat as $event ) :
							foreach ( $age_groups as $ag => $ag_def ) :
								// Skip (event, age_group) cells the UKA rules disallow.
								if ( ! acr_event_allowed( $event, $ag ) ) {
									continue;
								}
								$r = $by[ $sex ][ $event ][ $ag ] ?? null;
								$perf  = $r ? esc_html( $r->performance_raw ) : '';
								$ath   = $r ? esc_html( $r->athlete_name ) : '';
								$venue = $r ? esc_html( $r->venue ) : '';
								$date  = $r && $r->perf_date ? esc_html( date_i18n( 'd/m/Y', strtotime( $r->perf_date ) ) ) : '';
								$cls   = 'acr-row';
								if ( $r && $r->is_verified ) {
									$cls .= ' acr-verified';
								}
								if ( ! $r ) {
									$cls .= ' acr-empty';
								}
								?>
								<tr class="<?php echo esc_attr( $cls ); ?>" data-sex="<?php echo esc_attr( $sex ); ?>" data-empty="<?php echo $r ? '0' : '1'; ?>">
									<?php if ( $show_sex_col ) : ?>
										<td class="ninja_column_0"><?php echo esc_html( $sex === 'F' ? 'W' : 'M' ); ?></td>
									<?php endif; ?>
									<td class="ninja_column_1"><?php echo esc_html( $event ); ?></td>
									<td class="ninja_column_2"><?php echo esc_html( $ag_def['label'] ); ?></td>
									<td class="ninja_column_5" style="color: <?php echo esc_attr( $settings['record_colour'] ); ?>; font-weight:bold;"><?php echo $perf; ?></td>
									<td class="ninja_column_6"><?php echo $ath; ?></td>
									<td class="ninja_column_7"><?php echo $venue; ?></td>
									<td class="ninja_column_8"><?php echo $date; ?></td>
								</tr>
								<?php
							endforeach;
						endforeach;
					endforeach;
					?>
				</tbody>
			</table>
			<?php if ( $last ) : ?>
				<p class="acr-meta">Last verified from Power of 10: <?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $last ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<script>
		(function(){
			var w = document.currentScript.previousElementSibling;
			if (!w) return;
			var rows = w.querySelectorAll('tbody tr');
			var sexRadios = w.querySelectorAll('.acr-sex-filter input[type=radio]');
			var hideEmptyCb = w.querySelector('.acr-hide-empty');
			var searchInput = w.querySelector('.acr-filter-input');

			function getSelectedSex(){
				for (var i=0; i<sexRadios.length; i++) if (sexRadios[i].checked) return sexRadios[i].value;
				return 'A';
			}

			function applyFilters(){
				var sex   = getSelectedSex();           // 'A' | 'F' | 'M'
				var hide  = hideEmptyCb ? hideEmptyCb.checked : false;
				var q     = searchInput ? searchInput.value.toLowerCase() : '';
				rows.forEach(function(tr){
					var rSex   = tr.getAttribute('data-sex');
					var rEmpty = tr.getAttribute('data-empty') === '1';
					var show = true;
					if (sex !== 'A' && rSex !== sex) show = false;
					if (hide && rEmpty)             show = false;
					if (show && q && tr.innerText.toLowerCase().indexOf(q) === -1) show = false;
					tr.style.display = show ? '' : 'none';
				});
			}

			sexRadios.forEach(function(r){ r.addEventListener('change', applyFilters); });
			if (hideEmptyCb) hideEmptyCb.addEventListener('change', applyFilters);
			if (searchInput) searchInput.addEventListener('input', applyFilters);
			applyFilters();
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
