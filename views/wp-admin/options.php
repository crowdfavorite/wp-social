<form id="setup" method="post" action="<?php echo esc_url(admin_url('?social_controller=settings&social_action=index')); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="social_action" value="settings" />
<?php if (isset($_GET['saved'])): ?>
<div id="message" class="updated">
	<p><strong><?php _e('Social settings have been updated.', Social::$i18n); ?></strong></p>
</div>
<?php endif; ?>
<div class="wrap" id="social_options_page">
	<div class="social-view-header cf-clearfix">
		<h2><?php _e('Social', Social::$i18n); ?></h2>
		<div class="social-view-subtitle"><?php printf(__('Compliments of <a class="social-mailchimp-link" href="%s">MailChimp</a>', Social::$i18n), 'http://mailchimp.com/'); ?></div>
	</div>
	<div class="social-view">
		<table class="form-table">
			<tr>
				<th><?php _e('Accounts', Social::$i18n); ?></th>
				<td nowrap="nowrap">
					<?php
						$have_accounts = false;
						$items = $service_buttons = '';
						foreach ($services as $key => $service) {
							foreach ($service->accounts() as $account) {
								if ($account->universal()) {
									$have_accounts = true;
									$items .= $service->auth_output($account);
								}
							}

							$button = '<div class="social-connect-button cf-clearfix"><a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', Social::$i18n), $service->title()).'</span></a></div>';
							$button = apply_filters('social_service_button', $button, $service);
							$service_buttons .= $button;
						}

						echo '<p">'.__('Before blog authors can broadcast to social networks you need to connect some accounts:', Social::$i18n).'</p>'
						   . '<div>'.$service_buttons.'</div>'
						   . '<p class="description">'.__('Connected accounts are available to all blog authors.', Social::$i18n).'</p>';

						if (!empty($items)) {
							echo '
								<div class="social-accounts">
									<strong>'.__('Connected accounts:', Social::$i18n).'</strong>
									<ul>
										'.$items.'
									</ul>
								</div>
							';
						}
					?>
				</td>
			</tr>
			<?php if ($have_accounts): ?>
			<tr>
				<th><?php _e('Default accounts', Social::$i18n); ?></th>
				<td>
					<ul id="social-default-accounts" class="social-broadcastables">
						<?php
							$accounts = Social::option('default_accounts');
							foreach ($services as $key => $service) {
								foreach ($service->accounts() as $account) {
									if ($account->universal()) {
						?>
						<li>
							<label class="social-broadcastable" for="<?php echo esc_attr($key.$account->id()); ?>" style="cursor:pointer">
								<input type="checkbox" name="social_default_accounts[]" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($key.'|'.$account->id()); ?>"<?php echo ((isset($accounts[$key]) and in_array($account->id(), array_values($accounts[$key]))) ? ' checked="checked"' : ''); ?> />
								<img src="<?php echo esc_attr($account->avatar()); ?>" width="24" height="24" />
								<span><?php echo esc_html($account->name()); ?></span>
							</label>
						</li>
						<?php
									}
								}
							}
						?>
					</ul>
					<p class="description"><?php _e('Accounts that will be selected by default; and will auto-broadcast in the default teaser format when you publish via XML-RPC or email.', Social::$i18n); ?></p>
				</td>
			</tr>
			<?php endif ?>
			<tr>
				<th>
					<label for="social_broadcast_format"><?php _e('Post broadcast format', Social::$i18n); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="social_broadcast_format" id="social_broadcast_format" value="<?php echo esc_attr(Social::option('broadcast_format')); ?>" />
					<p class="description"><?php _e('How you would like posts to be formatted when broadcasting to Twitter or Facebook?'); ?></p>

					<div class="description">
						<?php _e('Tokens:', Social::$i18n); ?>
						<ul>
							<?php foreach (Social::broadcast_tokens() as $token => $description): ?>
							<li><b><?php echo esc_html($token); ?></b> - <?php echo esc_html($description); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</td>
			</tr>
			<tr>
				<th>
					<label for="social_comment_broadcast_format"><?php _e('Comment broadcast format', Social::$i18n); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="social_comment_broadcast_format" id="social_comment_broadcast_format" value="<?php echo esc_attr(Social::option('comment_broadcast_format')); ?>" />
					<p class="description"><?php _e('How you would like comments to be formatted when broadcasting to Twitter or Facebook?'); ?></p>

					<div class="description">
						<?php _e('Tokens:', Social::$i18n); ?>
						<ul>
							<?php foreach (Social::comment_broadcast_tokens() as $token => $description): ?>
							<li><b><?php echo esc_html($token); ?></b> - <?php echo esc_html($description); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</td>
			</tr>
			<tr>
				<th><?php _e('Twitter @anywhere', Social::$i18n); ?></th>
				<td>
					<label for="social_twitter_anywhere_api_key"><?php _e('Consumer API Key', Social::$i18n); ?></label><br />
					<input type="text" class="regular-text" name="social_twitter_anywhere_api_key" id="social_twitter_anywhere_api_key" value="<?php echo esc_attr(Social::option('twitter_anywhere_api_key')); ?>" />
					<p class="description"><?php printf(__('To enable Twitter\'s @anywhere hovercards for Twitter usernames, enter your application\'s Consumer API key here. (<a href="%1$s" target="_blank">Click here to get an API key</a>)', Social::$i18n), 'https://dev.twitter.com/docs/anywhere'); ?></p>
				</td>
			</tr>
		</table>
		<?php
			$fetch = Social::option('fetch_comments');
			$toggle = (!empty($fetch) or Social::option('debug') == '1') ? ' social-open' : '';
		?>
		<div class="social-collapsible<?php echo $toggle; ?>">
			<h3 class="social-title"><a href="#social-advanced"><?php _e('Advanced Options', Social::$i18n); ?></a></h3>
			<div class="social-content">
				<table id="social-advanced" class="form-table">
					<tr>
						<th><?php _e('Fetch new comments&hellip;', Social::$i18n); ?></th>
						<td>
							<ul>
								<li>
									<label for="fetch_comments_never">
										<input type="radio" name="social_fetch_comments" value="0" id="fetch_comments_never" style="position:relative;top:-1px"<?php echo !in_array(Social::option('fetch_comments'), array('1', '2')) ? ' checked="checked"' : ''; ?> />
										<?php _e('Never', Social::$i18n); ?>
										<span class="description"><?php _e('(disables fetching of comments)', Social::$i18n); ?></span>
									</label>
								</li>
								<li>
									<label for="fetch_comments_auto">
										<input type="radio" name="social_fetch_comments" value="1" id="fetch_comments_auto" style="position:relative;top:-1px"<?php echo Social::option('fetch_comments') == '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('Automatically', Social::$i18n); ?>
										<span class="description"><?php _e('(easiest)', Social::$i18n); ?></span>
									</label>
								</li>
								<li>
									<label for="fetch_comments_cron">
										<input type="radio" name="social_fetch_comments" value="2" id="fetch_comments_cron" style="position:relative;top:-1px"<?php echo Social::option('fetch_comments') == '2' ? ' checked="checked"' : ''; ?> />
										<?php _e('Using a custom CRON job <span class="description">(advanced)</span>', Social::$i18n); ?>
									</label>
									<p class="description"><?php _e('If you select this option, new tweets and Facebook posts will not be fetched unless you set up a system CRON job or fetch new items manually from the post edit screen. More help is also available in&nbsp;<code>readme.txt</code>.', Social::$i18n); ?></p>
									<?php if (Social::option('fetch_comments') == '2'): ?>
									<div class="social-callout">
										<h3 class="social-title"><?php _e('CRON Setup', Social::$i18n); ?></h3>
										<dl class="social-kv">
											<dt><?php _e('CRON API Key', Social::$i18n); ?> <small>(<a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=social.php&'.'social_action=regenerate_api_key'), 'regenerate_api_key')); ?>" rel="social_api_key" id="social_regenerate_api_key"><?php _e('regenerate', Social::$i18n); ?></a>)</small></dt>
											<dd>
												<code class="social_api_key"><?php echo esc_html(Social::option('system_cron_api_key')); ?></code>
											</dd>
										</dl>
										<p><?php _e('For your system CRON to run correctly, make sure it is pointing towards a URL that looks something like the following:', Social::$i18n); ?></p>
										<code><?php echo esc_url(site_url('?'.'social_cron=cron_15&api_key='.Social::option('system_cron_api_key'))); ?></code>
										<?php endif; ?>
									</div>
								</li>
							</ul>
						</td>
					</tr>
					<tr>
						<th>
							<?php _e('Debug Mode', Social::$i18n); ?>
							<span class="description"><?php _e('(nerds only)', Social::$i18n); ?></span>
						</th>
						<td>
							<p style="margin-top:0"><?php _e('If you turn debug on, Social will save additional information in the social/log.txt file. Not recommended for production environments.', Social::$i18n); ?></p>
							<ul>
								<li>
									<label for="debug_mode_no">
										<input type="radio" name="social_debug" id="debug_mode_no" value="0"<?php echo Social::option('debug') != '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('Off <span class="description">(recommended)</span>', Social::$i18n); ?>
									</label>
								</li>
								<li>
									<label for="debug_mode_yes">
										<input type="radio" name="social_debug" id="debug_mode_yes" value="1"<?php echo Social::option('debug') == '1' ? ' checked="checked"' : ''; ?> />
										<?php _e('On <span class="description">(for troubleshooting)</span>', Social::$i18n); ?>
									</label>
								</li>
							</ul>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<p class="submit" style="clear:both">
			<input type="submit" name="submit" value="Save Settings" class="button-primary" />
		</p>
	</div>
</div>
</form>
