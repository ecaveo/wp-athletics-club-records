<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap acr-wrap">
	<h1>Athletes</h1>
	<?php ACR_Admin::notice(); ?>
	<p>First-claim toggle controls whether performances count towards club records. Defaults to ON for all imported athletes — adjust as needed.</p>
	<table class="widefat striped">
		<thead>
			<tr><th>Name</th><th>Sex</th><th>DOB</th><th>Po10 ID</th><th>First claim?</th><th>Last profile scrape</th></tr>
		</thead>
		<tbody>
		<?php foreach ( $athletes as $a ) : ?>
			<tr>
				<td>
					<?php echo esc_html( $a->name ); ?>
					<?php if ( $a->profile_url ) : ?>
						<br><a href="<?php echo esc_url( $a->profile_url ); ?>" target="_blank" rel="noopener" style="font-size:0.85em;">View on Po10 ↗</a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $a->sex ); ?></td>
				<td><?php echo esc_html( $a->dob ?: '—' ); ?></td>
				<td><?php echo esc_html( $a->po10_id ?: '—' ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'acr_toggle_claim' ); ?>
						<input type="hidden" name="action" value="acr_toggle_claim" />
						<input type="hidden" name="id" value="<?php echo (int) $a->id; ?>" />
						<input type="hidden" name="to" value="<?php echo $a->first_claim ? 0 : 1; ?>" />
						<button class="button" type="submit"><?php echo $a->first_claim ? '✓ First claim (click to disable)' : '✗ Not first claim (click to enable)'; ?></button>
					</form>
				</td>
				<td><?php echo esc_html( $a->last_profile_scrape ?: '—' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
