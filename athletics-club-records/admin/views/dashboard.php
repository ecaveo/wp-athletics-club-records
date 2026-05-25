<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap acr-wrap">
	<h1>Athletics Club Records — Dashboard</h1>
	<?php ACR_Admin::notice(); ?>

	<div class="acr-stat-grid">
		<div class="acr-stat"><span class="num"><?php echo (int) $stats['records']; ?></span><span>Records</span></div>
		<div class="acr-stat"><span class="num"><?php echo (int) $stats['verified']; ?></span><span>Verified from Po10</span></div>
		<div class="acr-stat"><span class="num"><?php echo (int) $stats['performances']; ?></span><span>Performances captured</span></div>
		<div class="acr-stat"><span class="num"><?php echo (int) $stats['athletes']; ?></span><span>Athletes tracked</span></div>
	</div>

	<h2>Jobs</h2>
	<table class="widefat striped" style="max-width: 600px;">
		<thead><tr><th>Pending</th><th>Claimed</th><th>Done</th><th>Failed</th></tr></thead>
		<tbody><tr>
			<td><?php echo (int) $stats['jobs']['pending']; ?></td>
			<td><?php echo (int) $stats['jobs']['claimed']; ?></td>
			<td><?php echo (int) $stats['jobs']['done']; ?></td>
			<td><?php echo (int) $stats['jobs']['failed']; ?></td>
		</tr></tbody>
	</table>

	<h2>Quick actions</h2>
	<p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 1em;">
			<?php wp_nonce_field( 'acr_seed' ); ?>
			<input type="hidden" name="action" value="acr_seed" />
			<button class="button button-secondary" type="submit">Import existing Ninja Tables records</button>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 1em;">
			<?php wp_nonce_field( 'acr_recompute' ); ?>
			<input type="hidden" name="action" value="acr_recompute" />
			<button class="button button-primary" type="submit">Recompute records now</button>
		</form>

		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=acr-agent' ) ); ?>">Go to Agent Queue →</a>
	</p>

	<p class="acr-meta">
		Last seed: <?php echo esc_html( $stats['last_seed'] ?: 'never' ); ?> ·
		Last recompute: <?php echo esc_html( $stats['last_recompute'] ?: 'never' ); ?>
	</p>

	<h2>Shortcode</h2>
	<p>Drop these onto your records pages:</p>
	<pre><code>[acr_records gender="women"]
[acr_records gender="men"]</code></pre>
</div>
