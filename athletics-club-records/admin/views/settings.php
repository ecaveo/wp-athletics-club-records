<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap acr-wrap">
	<h1>Settings</h1>
	<?php ACR_Admin::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'acr_save_settings' ); ?>
		<input type="hidden" name="action" value="acr_save_settings" />
		<table class="form-table">
			<tr>
				<th><label>Club name</label></th>
				<td><input type="text" name="club_name" value="<?php echo esc_attr( $settings['club_name'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label>Short name</label></th>
				<td><input type="text" name="club_short" value="<?php echo esc_attr( $settings['club_short'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label>Power of 10 club UUID</label></th>
				<td>
					<input type="text" name="po10_club_uuid" value="<?php echo esc_attr( $settings['po10_club_uuid'] ); ?>" class="regular-text" />
					<p class="description">Found in the URL of your club's page on powerof10.uk/Home/Club/&lt;uuid&gt;</p>
				</td>
			</tr>
			<tr>
				<th><label>Power of 10 club name (for athlete search)</label></th>
				<td>
					<input type="text" name="po10_club_name" value="<?php echo esc_attr( $settings['po10_club_name'] ); ?>" class="regular-text" />
					<p class="description">As typed into Po10's athlete search Club field. e.g. "Brentwood Beagles".</p>
				</td>
			</tr>
			<tr>
				<th><label>Performances since</label></th>
				<td>
					<input type="date" name="performances_since" value="<?php echo esc_attr( $settings['performances_since'] ); ?>" />
					<p class="description">Records ignore performances before this date. Default 2022-01-01 (BBAC founded).</p>
				</td>
			</tr>
			<tr>
				<th><label>Ninja Tables ID — Women</label></th>
				<td><input type="number" name="ninja_women_id" value="<?php echo (int) $settings['ninja_women_id']; ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label>Ninja Tables ID — Men</label></th>
				<td><input type="number" name="ninja_men_id" value="<?php echo (int) $settings['ninja_men_id']; ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label>Record colour</label></th>
				<td><input type="text" name="record_colour" value="<?php echo esc_attr( $settings['record_colour'] ); ?>" class="small-text" /> <span class="description">Hex code used for the record performance text colour.</span></td>
			</tr>
		</table>
		<p><button class="button button-primary" type="submit">Save</button></p>
	</form>

	<hr>
	<h2>Agent token</h2>
	<p>The Claude-in-Chrome agent uses this bearer token to read jobs and post results to the plugin's REST API.</p>
	<p><code style="background:#fff; padding:0.25em 0.5em; user-select:all;"><?php echo esc_html( $settings['agent_token'] ); ?></code></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Rotate the agent token? You will need to re-paste the SOP prompt into Claude in Chrome.');">
		<?php wp_nonce_field( 'acr_rotate_token' ); ?>
		<input type="hidden" name="action" value="acr_rotate_token" />
		<button class="button" type="submit">Rotate token</button>
	</form>

	<hr>
	<h2>REST API base</h2>
	<p>Endpoints: <code><?php echo esc_html( esc_url_raw( rest_url( 'acr/v1/' ) ) ); ?></code></p>
</div>
