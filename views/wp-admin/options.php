<form id="setup" method="post" action="<?php echo esc_url(admin_url('options-general.php?social_controller=settings&social_action=index')); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="social_action" value="settings" />
<?php if (isset($_GET['saved'])): ?>
<div id="message" class="updated">
	<p><strong><?php _e('Social settings have been updated.', 'social'); ?></strong></p>
</div>
<?php endif; ?>
<div class="wrap" id="social_options_page">
	<div class="social-view-header cf-clearfix">
		<h2><?php _e('Social', 'social'); ?></h2>
		<div class="social-view-subtitle"><?php printf(__('Compliments of <a class="social-mailchimp-link" href="%s">MailChimp</a>', 'social'), 'http://mailchimp.com/'); ?></div>
	</div>
	<div class="social-view">
		<table class="form-table">
			<tr id="social-accounts">
				<th>
					<?php _e('Accounts', 'social'); ?>
					<p class="description" style="padding-top: 40px;"><?php printf(__('Available to all blog authors. Add accounts that only you can use in <a href="%s">your profile</a>.', 'social'), esc_url(admin_url('profile.php#social-accounts'))); ?></p>
				</th>
				<td>
<?php
echo Social_View::factory(
	'wp-admin/parts/accounts',
	compact('services', 'accounts', 'defaults')
);
?>
				</td>
			</tr>
			<tr>
				<th><?php _e('Broadcasting enabled for', 'social'); ?></th>
<?php
$available_post_types = Social::broadcasting_available_post_types();
$enabled_post_types = Social::broadcasting_enabled_post_types();
?>
				<td>
<?php foreach ($available_post_types as $type) { ?>
					<div>
						<input type="checkbox" id="social_enabled_post_types[<?php echo esc_attr($type) ?>]" name="social_enabled_post_types[<?php echo esc_attr($type) ?>]" value="1" <?php echo checked(in_array($type, $enabled_post_types)) ?>/> <label for="social_enabled_post_types[<?php echo $type ?>]"><?php echo esc_html(ucwords(str_replace('_', ' ', $type))) ?></label>
					</div>
<?php } ?>
				</td>
			</tr>
			<tr>
				<th><?php _e('Broadcasting is on by default', 'social'); ?></th>
				<td>
					<input type="radio" name="social_broadcast_by_default" id="social-broadcast-by-default-yes" value="1"<?php checked('1', Social::option('broadcast_by_default'), true); ?>
					<label for="social-broadcast-by-default-yes"><?php _e('Yes', 'social'); ?></label>

					<input type="radio" name="social_broadcast_by_default" id="social-broadcast-by-default-no" value="0"<?php checked('0', Social::option('broadcast_by_default'), true); ?>
					<label for="social-broadcast-by-default-no"><?php _e('No', 'social'); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php _e('Pull in social comments from Facebook and Twitter', 'social'); ?></th>
				<td>
					<input type="radio" name="social_aggregate_comments" id="social-aggregate-comments-yes" value="1"<?php checked('1', Social::option('aggregate_comments'), true); ?>
					<label for="social-aggregate-comments-yes"><?php _e('Yes', 'social'); ?></label>

					<input type="radio" name="social_aggregate_comments" id="social-aggregate-comments-no" value="0"<?php checked('0', Social::option('aggregate_comments'), true); ?>
					<label for="social-aggregate-comments-no"><?php _e('No', 'social'); ?></label>
				</td>
			</tr>
			<tr>
				<th>
					<label for="social_broadcast_format"><?php _e('Post broadcast format', 'social'); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="social_broadcast_format" id="social_broadcast_format" value="<?php echo esc_attr(Social::option('broadcast_format')); ?>" />
					<p class="description social-description"><?php _e('How you would like posts to be formatted when broadcasting to Twitter or Facebook?', 'social'); ?></p>

					<div class="description">
						<?php _e('Tokens:', 'social'); ?>
						<ul>
<?php
foreach (Social::broadcast_tokens() as $token => $description) {
	if (!empty($description)) {
		$description = ' - '.$description;
	}
?>
							<li><b><?php echo esc_html($token); ?></b><?php echo esc_html($description); ?></li>
<?php
}
?>
						</ul>
					</div>
				</td>
			</tr>
			<tr>
				<th>
					<label for="social_comment_broadcast_format"><?php _e('Comment broadcast format', 'social'); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="social_comment_broadcast_format" id="social_comment_broadcast_format" value="<?php echo esc_attr(Social::option('comment_broadcast_format')); ?>" />
					<p class="description social-description"><?php _e('How you would like comments to be formatted when broadcasting to Twitter or Facebook.', 'social'); ?></p>

					<div class="description">
						<?php _e('Tokens:', 'social'); ?>
						<ul>
<?php
foreach (Social::comment_broadcast_tokens() as $token => $description) {
	if (!empty($description)) {
		$description = ' - '.$description;
	}
?>
							<li><b><?php echo esc_html($token); ?></b><?php echo esc_html($description); ?></li>
<?php
}
?>
						</ul>
					</div>
				</td>
			</tr>
		</table>
<?php
$cron = Social::option('cron');
$toggle = (
	(empty($cron)) or
	Social::option('debug') == '1' or
	Social::option('use_standard_comments') == 1 or
	Social::option('disable_broadcasting') == 1
) ? ' social-open' : '';
?>
		<div class="social-collapsible<?php echo $toggle; ?>">
			<h3 class="social-title"><a href="#social-advanced"><?php _e('Advanced Options', 'social'); ?></a></h3>
			<div class="social-content">
				<table id="social-advanced" class="form-table">
					<tr>
						<th>
							<?php _e('Misc.', 'social'); ?>
						</th>
						<td>
							<ul>
								<li>
									<label for="social_use_standard_comments">
										<input type="checkbox" name="social_use_standard_comments" id="social_use_standard_comments" value="1" <?php checked(Social::option('use_standard_comments'), '1'); ?> />
										<?php _e("Disable Social's comment display (use standard theme output instead).", 'social'); ?>
									</label>
								</li>
								<li>
									<label for="social_disable_broadcasting">
										<input type="checkbox" name="social_disable_broadcasting" id="social_disable_broadcasting" value="1" <?php checked(Social::option('disable_broadcasting'), '1'); ?> />
										<?php _e("Disable Social's broadcasting feature.", 'social'); ?>
									</label>
								</li>
								<li>&nbsp;</li>
								<li>
									<?php
										$twitter_accounts = Social::instance()->service('twitter')->accounts();
										$social_api_accounts = Social::option('social_api_accounts');
										$selected_id = $social_api_accounts['twitter'];
									?>
									<div class="twitter-api-account">
										<label><?php _e('Twitter Default API Account', 'social'); ?></label>
										<select id="social_api_accounts-twitter" name="social_api_accounts[twitter]">
											<?php foreach ($twitter_accounts as $account): $acct_id = $account->id() ?>
												<?php if ($account->personal()) { continue; } ?>
												<option value="<?php echo $acct_id ?>" <?php selected($acct_id, $selected_id) ?>><?php echo esc_html($account->name()) ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<p class="description social-description" style="max-width: 450px;"><?php _e('Account for general (non account specific) Twitter API interaction.', 'social'); ?></p>
								</li>
							</ul>
						</td>
					</tr>
					<tr>
						<th><?php _e('Cron settings', 'social'); ?></th>
						<td>
							<ul>
								<li>
									<label for="cron_auto">
										<input type="radio" name="social_cron" value="1" id="cron_auto" style="position:relative;top:-1px"<?php echo Social::option('cron') == '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('Automatic (WP Cron)', 'social'); ?>
										<span class="description social-description"><?php _e('(easiest)', 'social'); ?></span>
									</label>
								</li>
								<li>
									<label for="cron_manual">
										<input type="radio" name="social_cron" value="0" id="cron_manual" style="position:relative;top:-1px"<?php echo Social::option('cron') == '0' ? ' checked="checked"' : ''; ?> />
										<?php _e('Manual <span class="description">(advanced)</span>', 'social'); ?>
									</label>
									<p class="description social-description"><?php _e('If you select this option, new tweets and Facebook posts will not be fetched unless you set up a system CRON job or fetch new items manually from the post edit screen. More help is also available in&nbsp;<code>README.txt</code>.', 'social'); ?></p>
<?php
if (Social::option('cron') === '0') {
?>
									<div class="social-callout">
										<h3 class="social-title"><?php _e('CRON Setup', 'social'); ?></h3>
										<dl class="social-kv">
											<dt><?php _e('CRON API Key', 'social'); ?> <small>(<a href="<?php echo esc_url(Social::wp39_nonce_url(admin_url('options-general.php?page=social.php&social_controller=settings&social_action=regenerate_api_key'), 'regenerate_api_key')); ?>" rel="social_api_key" id="social_regenerate_api_key"><?php _e('regenerate', 'social'); ?></a>)</small></dt>
											<dd>
												<code class="social_api_key"><?php echo esc_html(Social::option('system_cron_api_key')); ?></code>
											</dd>
										</dl>
										<p><?php _e('For your system CRON to run correctly, make sure it is pointing towards a URL that looks something like the following:', 'social'); ?></p>
										<code><?php echo esc_url(home_url('index.php?social_controller=cron&social_action=cron_15&api_key='.Social::option('system_cron_api_key'))); ?></code>
<?php
}
?>
									</div>
								</li>
							</ul>
						</td>
					</tr>
					<tr>
						<th>
							<?php _e('Debug Mode', 'social'); ?>
							<span class="description"><?php _e('(nerds only)', 'social'); ?></span>
						</th>
						<td>
							<p style="margin-top:0"><?php _e('If you turn debug on, Social will save additional information in <code>debug_log.txt</code> file. Not recommended for production environments.', 'social'); ?></p>
							<ul>
								<li>
									<label for="debug_mode_no">
										<input type="radio" name="social_debug" id="debug_mode_no" value="0"<?php echo Social::option('debug') != '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('Off <span class="description">(recommended)</span>', 'social'); ?>
									</label>
								</li>
								<li>
									<label for="debug_mode_yes">
										<input type="radio" name="social_debug" id="debug_mode_yes" value="1"<?php echo Social::option('debug') == '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('On <span class="description">(for troubleshooting)</span>', 'social'); ?>
									</label>
								</li>
							</ul>

							<strong><?php _e('Debug log location:', 'social'); ?></strong> <code><?php echo Social::$plugins_path.'debug_log.txt'; ?></code>
						</td>
					</tr>
				</table>
			</div>
<?php
do_action('social_advanced_options');
?>
		</div>
		<p class="submit" style="clear:both">
			<input type="submit" name="submit" value="Save Settings" class="button-primary" />
		</p>
	</div>
</div>
</form>
