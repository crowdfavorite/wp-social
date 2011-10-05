<h3 id="social-networks"><?php _e('Connect to Social Networks', Social::$i18n); ?></h3>
<p><?php _e('To broadcast to social networks, you&rsquo;ll need to connect an account or two.', Social::$i18n); ?></p>
<?php
	$items = $service_buttons = '';
	foreach ($services as $key => $service) {
		foreach ($service->accounts() as $account) {
			if ($account->personal()) {
				$profile_url = esc_url($account->url());
				$profile_name = esc_html($account->name());

				$name = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);
				$disconnect = $service->disconnect_url($account, true);
				$items .= '
					<li>
						<div class="social-'.$key.'-icon"><i></i></div>
						<span class="name">'.$name.'</span>
						<span class="disconnect">'.$disconnect.'</span>
					</li>
				';
			}
		}

		$service_buttons .= '<a href="'.esc_url($service->authorize_url()).'" id="'.$key.'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s', Social::$i18n), $service->title()).'</span></a>';
	}

	echo '<div>'.$service_buttons.'</div>';

	if ($items) {
?>
<div class="social-accounts">
	<b><?php _e('Connected accounts:', Social::$i18n); ?></b>
	<ul>
		<?php echo $items; ?>
	</ul>
</div>
<?php
	}
