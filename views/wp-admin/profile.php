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

		$button = '<div class="social-connect-button cf-clearfix"><a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', 'social'), $service->title()).'</span></a></div>';
		$button = apply_filters('social_service_button', $button, $service);
		$service_buttons .= $button;
	}

	echo '<div>'.$service_buttons.'</div>';

	if ($items) {
?>
<div class="social-accounts">
	<b><?php _e('Connected accounts:', 'social'); ?></b>
	<ul>
		<?php echo $items; ?>
	</ul>
</div>
<?php
	}
