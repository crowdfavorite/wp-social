<form id="setup" class="social-view" method="post" action="<?php echo esc_url(admin_url()); ?>">
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
	<table class="form-table">
		<tr>
			<th><?php _e('Connect accounts', Social::$i18n); ?></th>
			<td nowrap="nowrap">
				<?php
					$have_accounts = false;
					$items = $service_buttons = '';
					foreach ($services as $key => $service) {
						foreach ($service->accounts() as $account) {
							if ($account->universal()) {
								$have_accounts = true;

								$profile_url = esc_url($account->url());
								$profile_name = esc_html($account->name());
								$disconnect = '';

								$name = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);

								$items .= '
									<li>
										<div class="social-'.$key.'-icon"><i></i></div>
										<span class="name">'.$name.'</span>
										<span class="disconnect">'.$disconnect.'</span>
									</li>
								';
							}
						}

						$service_buttons .= '<a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', Social::$i18n), $service->title()).'</span></a>';
					}

					echo '<p>'.__('Before blog authors can broadcast to social networks you need to connect some accounts:', Social::$i18n).'</p>'
					   . '<div>'.$service_buttons.'</div>'
					   . '<p class="description"><strong>'.__('Connected accounts will be accessible by every blog author.', Social::$i18n).'</strong></p>';

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
		<tr>
			<th>
				<label for="<?php echo 'social_broadcast_format'; ?>"><?php _e('Broadcast teaser format', Social::$i18n); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="<?php echo 'social_broadcast_format'; ?>" id="<?php echo 'social_broadcast_format'; ?>" value="<?php echo esc_attr(Social::option('broadcast_format')); ?>" />
				<p class="description"><?php _e('How you would like posts to be formatted when broadcasting to Twitter or Facebook?'); ?></p>

				<div class="description">
					<?php _e('Tokens:', Social::$i18n); ?>
					<ul>
						<?php foreach (Social::broadcast_tokens() as $token => $description): ?>
						<li><b><?php echo esc_html($token); ?></b>: <?php echo esc_html($description); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</td>
		</tr>
		<tr>
			<th><?php _e('Twitter @anywhere', Social::$i18n); ?></th>
			<td>
				<label for="<?php echo 'social_twitter_anywhere_api_key'; ?>"><?php _e('Consumer API Key', Social::$i18n); ?></label><br />
				<input type="text" class="regular-text" name="<?php echo 'social_twitter_anywhere_api_key'; ?>" id="<?php echo 'social_twitter_anywhere_api_key'; ?>" value="<?php echo esc_attr(Social::option('twitter_anywhere_api_key')); ?>" />
				<p class="description"><?php printf(__('To enable Twitter\'s @anywhere hovercards for Twitter usernames, enter your application\'s Consumer API key here. (<a href="%1$s" target="_blank">Click here to get an API key</a>)', Social::$i18n), 'https://dev.twitter.com/docs/anywhere'); ?></p>
			</td>
		</tr>
	</table>
	<?php $toggle = (Social::option('system_crons') == '1' or Social::option('debug') == '1') ? ' social-open' : ''; ?>
	<div class="social-collapsible<?php echo $toggle; ?>">
		<h3 class="social-title"><a href="#social-advanced"><?php _e('Advanced Options', Social::$i18n); ?></a></h3>
		<div class="social-content">
			<table id="social-advanced" class="form-table">
				<?php if ($have_accounts): ?>
				<tr>
					<th><?php _e('When posting via XML-RPC or email, broadcast teasers to&hellip;', Social::$i18n); ?></th>
					<td>
						<ul id="social_xmlrpc" class="social-broadcastables">
							<?php
								$accounts = get_option('social_xmlrpc_accounts', array());
								foreach ($services as $key => $service):
									foreach ($service->accounts() as $account):
										if ($account->universal()):
							?>
							<li>
								<label class="social-broadcastable" for="<?php echo esc_attr($key.$account->id()); ?>" style="cursor:pointer">
									<input type="checkbox" name="<?php echo 'social_xmlrpc_accounts[]'; ?>" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($key.'|'.$account->id()); ?>"<?php echo ((isset($accounts[$key]) and in_array($account->id(), array_values($accounts[$key]))) ? ' checked="checked"' : ''); ?> />
									<img src="<?php echo esc_attr($account->avatar()); ?>" width="24" height="24" />
									<span><?php echo esc_html($account->name()); ?></span>
								</label>
							</li>
							<?php
										endif;
									endforeach;
								endforeach;
							?>
							</ul>
							<p class="description"><?php _e('Select'.' accounts above to have them auto-broadcast a teaser whenever you publish a post via XML-RPC or email. This only affects posts published remotely; if you&rsquo;re publishing from the post edit screen, you can handle broadcasting settings from there.', Social::$i18n); ?></p>
					</td>
				</tr>
				<?php endif ?>
				<tr>
					<th>Fetch new comments&hellip;</th>
					<td>
						<ul>
							<li>
								<label for="system_crons_no">
									<input type="radio" name="<?php echo 'social_system_crons'; ?>" value="0" id="system_crons_no" style="position:relative;top:-1px"<?php echo Social::option('system_crons') != '1' ? ' checked="checked"' : ''; ?> />
									<?php _e('Automatically', Social::$i18n); ?>
									<span class="description"><?php _e('(easiest)', Social::$i18n); ?></span>
								</label>
							</li>
							<li>
								<label for="system_crons_yes">
									<input type="radio" name="<?php echo 'social_system_crons'; ?>" value="1" id="system_crons_yes" style="position:relative;top:-1px"<?php echo Social::option('system_crons') == '1' ? ' checked="checked"' : ''; ?> />
									<?php _e('Using a custom CRON job <span class="description">(advanced)</span>', Social::$i18n); ?>
								</label>
								<p class="description"><?php _e('If you select this option, new tweets and Facebook posts will not be fetched unless you set up a system CRON job or fetch new items manually from the post edit screen. More help is also available in&nbsp;<code>readme.txt</code>.', Social::$i18n); ?></p>
								<?php if (Social::option('system_crons') == '1'): ?>
								<div class="social-callout">
									<h3 class="social-title"><?php _e('CRON Setup', Social::$i18n); ?></h3>
									<dl class="social-kv">
										<dt><?php _e('CRON API Key', Social::$i18n); ?> <small>(<a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=social.php&'.'social_action=regenerate_api_key'), 'regenerate_api_key')); ?>" rel="<?php echo 'social_api_key'; ?>" id="<?php echo 'social_regenerate_api_key'; ?>"><?php _e('regenerate', Social::$i18n); ?></a>)</small></dt>
										<dd>
											<code class="<?php echo 'social_api_key'; ?>"><?php echo esc_html(Social::option('system_cron_api_key')); ?></code>
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
									<input type="radio" name="<?php echo 'social_debug'; ?>" id="debug_mode_no" value="0"<?php echo Social::option('debug') != '1' ? ' checked="checked"' : ''; ?> />
									<?php _e('Off <span class="description">(recommended)</span>', Social::$i18n); ?>
								</label>
							</li>
							<li>
								<label for="debug_mode_yes">
									<input type="radio" name="<?php echo 'social_debug'; ?>" id="debug_mode_yes" value="1"<?php echo Social::option('debug') == '1' ? ' checked="checked"' : ''; ?> />
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
</form>