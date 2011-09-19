<?php
$broadcasted = false;
$header_shown = false;
if (is_array($ids) and count($ids)) {
	foreach ($services as $key => $service) {
		if (isset($ids[$key]) and count($ids[$key])) {
			$broadcasted = true;
			if (!$header_shown) {
				$header_shown = true;
				echo '
					<p class="mar-top-none">'.__('This post has been broadcasted to the following accounts. You may broadcast to more accounts by clicking on the "Broadcast" button below.', Social::$i18n).'</p>
					<input type="hidden" name="social_notify" value="1" />
				';
			}

			$output = '';
			foreach ($ids[$key] as $user_id => $broadcasted_ids) {
				if (($account = $service->account($user_id)) !== false) {
					if (empty($output)) {
						$accounts_output = '<h4>'.$service->title().'</h4><ul style="margin:0 0 25px 0;">';
					}

					$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
						'account' => $account,
						'broadcasted_ids' => $broadcasted_ids,
						'service' => $service
					));
				}
			}

			if (!empty($output)) {
				echo '<h4>'.$service->title().'</h4><ul style="margin:0 0 25px 0;">'.$output.'</ul>';
			}
		}
	}
}

if (!$broadcasted) {
	echo '
		<p class="mar-top-none">'.__('This post has not been broadcasted to any accounts yet. You may do so by clicking the "Broadcast" button below.', Social::$i18n).'</p>
		<input type="hidden" name="social_notify" value="1" />
	';

	if (!is_array($ids) or !count($ids)) {
		echo '<p>'.__('Would you like to broadcast this post?', Social::$i18n).'</p>';
	}
}
?>
<p class="submit" style="clear:both;padding:0;margin:20px 0 0;">
	<input type="submit" name="social_broadcast" value="<?php _e('Broadcast', Social::$i18n); ?>" />
	<input type="hidden" name="social_notify" value="1" />

	<a href="<?php echo esc_url(admin_url('profile.php#social-networks')); ?>" style="float:right;padding-top:8px;"><?php _e('My Accounts', Social::$i18n); ?></a>
</p>
