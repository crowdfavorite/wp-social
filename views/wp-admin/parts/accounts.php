<?php

// expects the following variables to be passed in:
// $services (full list of services)
// $accounts (list of accounts to show for this screen)
// $defaults (accounts that are checked for default broadcast)

foreach ($services as $key => $service) {
?>
<div class="social-accounts">
<?php
	$button = '<div class="social-connect-button cf-clearfix"><a href="'.esc_url($service->authorize_url()).'" id="'.esc_attr($key).'_signin" class="social-login" target="_blank"><span>'.sprintf(__('Sign in with %s.', 'social'), esc_html($service->title())).'</span></a></div>';
	echo apply_filters('social_service_button', $button, $service);
?>
	<ul>
<?php
	$i = 0;
	foreach ($service->accounts() as $account) {
		if (in_array($account->id(), $accounts[$key])) {
			$profile_url = esc_url($account->url());
			$profile_name = esc_html($account->name());
			$disconnect = $service->disconnect_url($account, true);
			$name = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);
?>
		<li class="social-accounts-item">
			<img src="<?php echo esc_url($account->avatar()); ?>" width="24" height="24" />
			<span class="name"><?php echo $name; ?></span>
			<label for="<?php echo esc_attr($key.$account->id()); ?>">
				<input type="checkbox" name="social_default_accounts[]" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($key.'|'.$account->id()); ?>"<?php echo ((isset($defaults[$key]) and in_array($account->id(), array_values($defaults[$key]))) ? ' checked="checked"' : ''); ?> />
				<?php _e('Default', 'social'); ?>
			</label>
			<span class="disconnect"><?php echo $disconnect; ?></span>
<?php
			if (count($account->child_accounts())) {
?>
			<ul>
<?php
				foreach ($account->child_accounts() as $child_account) {
					if (isset($defaults[$service->key()]) and
						isset($defaults[$service->key()][$account->child_account_key()]) and
						isset($defaults[$service->key()][$account->child_account_key()][$account->id()]) and
						in_array($child_account->id, $defaults[$service->key()][$account->child_account_key()][$account->id()])
					) {
						$checked = ' checked="checked"';
					}
					else {
						$checked = '';
					}
?>
				<li class="social-accounts-item">
					<img src="<?php echo esc_url($account->child_account_avatar($child_account)); ?>" width="24" height="24" />
					<span class="name"><?php echo esc_html($child_account->name); ?></span>
					<label for="<?php echo esc_attr($key.$child_account->id); ?>">
						<input type="checkbox" name="social_default_pages[<?php echo esc_attr($account->id()); ?>][]" id="<?php echo esc_attr($key.$child_account->id); ?>" value="<?php echo esc_attr($child_account->id); ?>"<?php echo $checked; ?> />
						<?php _e('Default', 'social'); ?>
					</label>
				</li>
<?php
				}
?>
			</ul>
<?php
			}
			$i++;
?>
		</li><!-- /li.social-accounts-item -->
<?php
		}
	}
	if ($i == 0) {
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
}
?>
<p class="description" style="max-width: 450px;"><?php _e('Default accounts will auto-broadcast when you publish via XML-RPC or email.', 'social'); ?></p>
