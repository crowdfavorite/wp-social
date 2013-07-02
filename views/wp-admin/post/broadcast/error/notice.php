<?php
$types = array();
$deauthed = array();
echo '<p>'.sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', 'social'), esc_html($post->post_title)).'</p>';
foreach ($accounts as $key => $items) {
	echo '<ul class="social-posting-errors">';
	foreach ($items as $item) {
		if (isset($item->deauthed)) {
			$deauthed[$key.'-'.$item->account->id()] = $item;
		}
		else {
			if (!isset($types[$item->type])) {
				$types[$item->type] = 0;
			}
			++$types[$item->type];
		}

		echo '<li>'.esc_html($social->service($key)->title()).': '.esc_html($item->account->name()).' ('.esc_html($item->reason).')</li>';
	}
	echo '</ul>';
}

$total_deauthed = count($deauthed);
if ($total_deauthed or count($types)) {
	echo '<h4>'.__('Possible fixes:', 'social').'</h4><ul class="social-posting-errors">';
}

if ($total_deauthed) {
	echo '<li>';
	if ($total_deauthed == 1) {
		$key = array_keys($deauthed);
		$key = explode('-', $key[0]);
		$service = $social->service($key[0])->title();

		if (current_user_can('manage_options')) {
			echo sprintf(__('To reauthorize the deauthorized %s account above, please edit your <a href="%s">global accounts</a> or your <a href="%s">personal accounts</a>.', 'social'), esc_html($service), esc_url(Social::settings_url()), esc_url(admin_url('profile.php#social-accounts')));
		}
		else {
			echo sprintf(__('To reauthorize the deauthorized %s account above, please edit your <a href="%s">personal accounts</a>.', 'social'), esc_html($service), esc_url(admin_url('profile.php#social-accounts')));
		}
	}
	else {
		if (current_user_can('manage_options')) {
			echo sprintf(__('To reauthorize the deauthorized accounts above, please edit your <a href="%s">global accounts</a> or your <a href="%s">personal accounts</a>.', 'social'), esc_url(Social::settings_url()), esc_url(admin_url('profile.php#social-accounts')));
		}
		else {
			echo sprintf(__('To reauthorize the deauthorized account above, please edit your <a href="%s">personal accounts</a>.', 'social'), esc_url(admin_url('profile.php#social-accounts')));
		}
	}
	echo '</li>';
}

if (count($types)) {
	foreach ($types as $type => $total) {
		switch ($type) {
			case 'limit_reached':
				echo '<li>'.__('It is possible you have reached your broadcast limit, please try to broadcast again in an hour.', 'social').'</li>';
			break;
			case 'duplicate_status':
				echo '<li>'.__('It is possible you have broadcasted a duplicate message, please tweak your content a little and try again.', 'social').'</li>';
			break;
			default:
				echo '<li>'.sprintf(__('Social was not successful in broadcasting this post (perhaps the service is down?), please try broadcasting again. If you receive this message repeatedly, you can try the <a href="%s">support forums</a>.', 'social'), 'http://wordpress.org/support/plugin/social').'</li>';
			break;
		}
	}
}

if ($total_deauthed or count($types)) {
	echo '</ul>';
}
