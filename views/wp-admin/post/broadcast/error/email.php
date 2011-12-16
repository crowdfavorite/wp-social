<?php
$types = array();
$deauthed = false;
echo __('Hello', 'social').','."\n\n";
echo wordwrap(sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', 'social'), $post->post_title), 60)."\n\n";
foreach ($accounts as $key => $items) {
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
		echo $social->service($key)->title().': '.$item->account->name().' ('.$item->reason.')'."\n";
	}
	echo "\n";
}

$total_deauthed = count($deauthed);
if ($total_deauthed or count($types)) {
	echo __('Possible fixes:', 'social')."\n\n";
}

if ($total_deauthed) {
	if ($total_deauthed == 1) {
		$key = array_keys($deauthed);
		$key = explode('-', $key[0]);
		$service = $social->service($key[0])->title();

		$message = __('To reauthorize the deauthorized %s account above, please login and edit your accounts.', 'social');
	}
	else {
		$message = __('To reauthorize the deauthorized accounts above, please login and edit your accounts.', 'social');
	}

	$message .= "\n    ".__('Personal accounts:', 'social').' '.admin_url('profile.php#social-accounts');
	if (current_user_can('manage_options')) {
		$message .= "\n    ".__('Global accounts:', 'social').' '.Social::settings_url();
	}

	echo '- '.$message."\n";
}

if (count($types)) {
	foreach ($types as $type => $total) {
		switch ($type) {
			case 'limit_reached':
				$message = __('It is possible you have reached your broadcast limit, please try to broadcast again in an hour.', 'social');
			break;
			case 'duplicate_status':
				$message = __('It is possible you have broadcasted a duplicate message, please tweak your content a little and try again.', 'social');
			break;
			default:
				$message = __('Social was not successful in broadcasting this post (perhaps the service is down?), please try broadcasting again. If you receive this message repeatedly, you can try the support forums.', 'social')
				         . '    '.__('Support forums:', 'social').' http://wordpress.org/tags/social?forum_id=10';
			break;
		}

		echo '- '.$message;
	}
}
