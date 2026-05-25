<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap acr-wrap">
	<h1>Agent Queue</h1>
	<?php ACR_Admin::notice(); ?>

	<p>The plugin produces a queue of Power of 10 URLs to visit. You drive the queue manually by opening <strong>Claude in Chrome</strong> on a machine that's open, pasting the SOP prompt below, and letting the agent work through them. When it hits an hCaptcha, you solve it once — the session cookie does the rest.</p>

	<h2>1. Plan a refresh</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 1em;">
		<?php wp_nonce_field( 'acr_plan' ); ?>
		<input type="hidden" name="action" value="acr_plan" />
		<label>
			Strategy:
			<select name="strategy">
				<option value="stale_first">Stale first (refresh athletes &gt;14 days)</option>
				<option value="bootstrap">Bootstrap (all known athletes)</option>
				<option value="full">Full reconciliation (every event × age × sex)</option>
			</select>
		</label>
		<label style="margin-left:1em;">
			Max jobs: <input type="number" name="max" value="25" min="1" max="500" style="width:6em;" />
		</label>
		<button class="button button-primary" type="submit">Plan refresh</button>
	</form>

	<h2>2. Copy the SOP prompt to Claude in Chrome</h2>
	<details open style="background:#fff; border:1px solid #ccd0d4; padding:1em; max-width: 900px;">
		<summary><strong>SOP prompt — copy this whole block</strong></summary>
		<pre id="acr-sop" style="white-space:pre-wrap; background:#f6f7f7; padding:1em; border-radius:4px;"><?php
$sop = file_get_contents( ACR_PLUGIN_DIR . 'docs/claude-in-chrome-prompt.md' );
$sop = str_replace( '{{SITE_URL}}', site_url(), $sop );
$sop = str_replace( '{{AGENT_TOKEN}}', $settings['agent_token'], $sop );
echo esc_html( $sop );
?></pre>
		<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('acr-sop').innerText); this.innerText='Copied!';">Copy to clipboard</button>
	</details>

	<h2>3. Watch the queue drain</h2>
	<p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<?php wp_nonce_field( 'acr_clear_completed' ); ?>
			<input type="hidden" name="action" value="acr_clear_completed" />
			<button class="button" type="submit">Clear done/failed jobs</button>
		</form>
	</p>
	<table class="widefat striped">
		<thead>
			<tr><th>ID</th><th>Type</th><th>Status</th><th>URL</th><th>Attempts</th><th>Created</th><th>Completed</th><th>Error</th></tr>
		</thead>
		<tbody>
		<?php foreach ( $jobs as $j ) : ?>
			<tr>
				<td><?php echo (int) $j->id; ?></td>
				<td><?php echo esc_html( $j->job_type ); ?></td>
				<td><span class="acr-status acr-status-<?php echo esc_attr( $j->status ); ?>"><?php echo esc_html( $j->status ); ?></span></td>
				<td style="word-break:break-all; max-width: 400px;"><a href="<?php echo esc_url( $j->target_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $j->target_url ); ?></a></td>
				<td><?php echo (int) $j->attempts; ?></td>
				<td><?php echo esc_html( $j->created_at ); ?></td>
				<td><?php echo esc_html( $j->completed_at ?: '' ); ?></td>
				<td><?php echo esc_html( $j->last_error ?: '' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
