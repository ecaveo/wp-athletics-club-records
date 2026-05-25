<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap acr-wrap">
	<h1>Records</h1>
	<?php ACR_Admin::notice(); ?>

	<p>
		<a class="button <?php echo $sex_filter === 'F' ? 'button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=acr-records&sex=F' ) ); ?>">Women</a>
		<a class="button <?php echo $sex_filter === 'M' ? 'button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=acr-records&sex=M' ) ); ?>">Men</a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th>Event</th><th>Age</th><th>Performance</th><th>Athlete</th><th>Venue</th><th>Date</th><th>Source</th><th>Override?</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><?php echo esc_html( $r->event ); ?></td>
				<td><?php echo esc_html( $r->age_group ); ?></td>
				<td><?php echo esc_html( $r->performance_raw ); ?></td>
				<td><?php echo esc_html( $r->athlete_name ); ?></td>
				<td><?php echo esc_html( $r->venue ); ?></td>
				<td><?php echo $r->perf_date ? esc_html( date_i18n( 'd M Y', strtotime( $r->perf_date ) ) ) : ''; ?></td>
				<td><?php echo esc_html( $r->source ); ?></td>
				<td><?php echo $r->is_manual_override ? '✓' : ''; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
