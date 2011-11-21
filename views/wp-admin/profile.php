<?php

$buttons_services = $accounts_connected = $accounts_default = array();

foreach ($services as $key => $service) {
	foreach ($service->accounts() as $account) {
		if ($account->personal()) {
			$accounts_connected[] = $service->auth_output($account);
		}
	}

	$button = '<div class="social-connect-button cf-clearfix"><a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', 'social'), esc_html($service->title())).'</span></a></div>';
	$button = apply_filters('social_service_button', $button, $service);
	$buttons_services[] = $button;
}

?>
<h3 id="social-accounts"><?php _e('My Social Accounts', 'social'); ?></h3>
<p><?php _e('Only I can broadcast to these accounts.', 'social'); ?></p>
<div id="social-accounts" class="social-accounts">
	<ul>
<?php
if (count($accounts_connected)) {
	echo implode("\n", $accounts_connected);
}
else {
?>
		<li class="social-accounts-item none">
			<div class="social-facebook-icon"><i style="background: url(http://www.gravatar.com/avatar/a06082e4f876182b547f635d945e744e?s=16&d=mm) no-repeat;"></i></div>
			<span class="name"><?php _e('No Accounts', 'social'); ?></span>
		</li>
<?php
}
?>
	</ul>
</div>
<?php

echo '<div>'.implode("\n", $buttons_services).'</div>';

if (count($accounts_connected)) {

?>
<p><?php _e('<b>My Default Accounts</b> (pre-selected when broadcasting)', 'social'); ?></p>
<ul id="social-default-accounts" class="profile-page">
<?php
	foreach ($services as $key => $service) {
		foreach ($service->accounts() as $account_id => $account) {
			if ($key != 'pages') {
				if ($account->personal()) {
?>
	<li class="social-accounts-item">
		<label class="social-broadcastable" for="<?php echo esc_attr($key.$account->id()); ?>">
			<input type="checkbox" name="social_default_accounts[]" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($key.'|'.$account->id()); ?>"<?php echo ((isset($default_accounts[$key]) and in_array($account->id(), array_values($default_accounts[$key]))) ? ' checked="checked"' : ''); ?> />
			<img src="<?php echo esc_url($account->avatar()); ?>" width="24" height="24" />
			<span class="name">
			<?php
					$show_pages = false;
					$pages_output = '';
					if ($service->key() == 'facebook') {
						if ($account->use_pages(true) and count($pages)) {
							$pages_output .= '<h5>'.__('Account Pages', 'social').'</h5><ul>';
							foreach ($pages as $page) {
								$checked = '';
								if (isset($default_accounts['facebook']) and
									isset($default_accounts['facebook']['pages']) and
									isset($default_accounts['facebook']['pages'][$account->id()]) and
									in_array($page->id, $default_accounts['facebook']['pages'][$account->id()])
								) {
									$show_pages = true;
									$checked = ' checked="checked"';
								}
								$pages_output .= '<li>'
									.'    <input type="checkbox" name="social_default_pages['.esc_attr($account->id()).'][]" value="'.esc_attr($page->id).'"'.$checked.' />'
									.'    <img src="'.esc_url($service->page_image_url($page)).'" width="24" height="24" />'
									.'    <span>'.esc_html($page->name).'</span>'
									.'</li>';
							}
							$pages_output .= '</ul>';
						}
					}

					echo esc_html($account->name());
					if ($service->key() == 'facebook') {
						$pages = $account->pages(null, true);
						if (!$show_pages and $account->use_pages(true) and count($pages)) {
							echo '<span> - <a href="#" class="social-show-facebook-pages">'.__('Show Pages', 'social').'</a></span>';
						}
					}
			?>
			</span>
		</label>
		<?php
					if (!empty($pages_output)) {
						echo '<div class="social-facebook-pages"'.($show_pages ? ' style="display:block"' : '').'>'
						   . $pages_output
						   . '</div>';
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
