<h3 id="social-networks"><?php _e('Connect to Social Networks', 'social'); ?></h3>
<p><?php _e('To broadcast to social networks, you&rsquo;ll need to connect an account or two.', 'social'); ?></p>
<?php
	$items = $service_buttons = '';
	foreach ($services as $key => $service) {
		foreach ($service->accounts() as $account) {
			if ($account->personal()) {
				$items .= $service->auth_output($account);
			}
		}

		$button = '<div class="social-connect-button cf-clearfix"><a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', 'social'), esc_html($service->title())).'</span></a></div>';
		$button = apply_filters('social_service_button', $button, $service);
		$service_buttons .= $button;
	}

	echo '<div>'.$service_buttons.'</div>';

	if (!empty($items)) {
?>
<div id="social-accounts" class="social-accounts">
	<strong><?php _e('Connected accounts:', 'social'); ?></strong>
	<ul>
		<?php echo $items; ?>
	</ul>
</div>

<h3><?php _e('Default Accounts', 'social'); ?></h3>
<p><?php _e('These are the accounts that will be selected by default when broadcasting.', 'social'); ?></p>
<ul id="social-default-accounts" class="profile-page">
<?php
		foreach ($services as $key => $service) {
			foreach ($service->accounts() as $account_id => $account) {
				if ($key != 'pages') {
					if ($account->personal()) {
?>
	<li class="social-accounts-item">
		<label class="social-broadcastable" for="<?php echo esc_attr($key.$account->id()); ?>" style="cursor:pointer">
			<input type="checkbox" name="social_default_accounts[]" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($key.'|'.$account->id()); ?>"<?php echo ((isset($default_accounts[$key]) and in_array($account->id(), array_values($default_accounts[$key]))) ? ' checked="checked"' : ''); ?> />
			<img src="<?php echo esc_url($account->avatar()); ?>" width="24" height="24" />
			<span class="name">
				<?php
				echo esc_html($account->name());
				if ($service->key() == 'facebook') {
					$pages = $account->pages(null, true);
					if ($account->use_pages(true) and count($pages)) {
						echo '<span> - <a href="#" class="social-show-facebook-pages">'.__('Show Pages', 'social').'</a></span>';
					}
				}
				?>
			</span>
		</label>
		<?php
			if ($service->key() == 'facebook') {
				if ($account->use_pages(true) and count($pages)) {
					echo '<div class="social-facebook-pages">'
						.'    <h5>'.__('Account Pages', 'social').'</h5>'
						.'    <ul>';
					foreach ($pages as $page) {
						$checked = '';
						if (isset($default_accounts['facebook']) and
							isset($default_accounts['facebook']['pages']) and
							isset($default_accounts['facebook']['pages'][$account->id()]) and
							in_array($page->id, $default_accounts['facebook']['pages'][$account->id()])
						) {
							$checked = ' checked="checked"';
						}
						echo '<li>'
							.'    <input type="checkbox" name="social_default_pages['.esc_attr($account->id()).'][]" value="'.esc_attr($page->id.'"'.$checked).' />'
							.'    <img src="http://graph.facebook.com/'.esc_attr($page->id).'/picture" width="16" height="16" />'
							.'    <span>'.esc_html($page->name).'</span>'
							.'</li>';
					}
					echo '    </ul>'
						.'</div>';
				}
			}
		?>
	</li>
<?php
					}
				}
			}
		}
?>
</ul>
<?php
	}
?>
