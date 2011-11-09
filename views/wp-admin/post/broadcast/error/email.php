<?php
_e('Hello', 'social').','."\n\n";
echo wordwrap(sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', 'social'), $post->post_title), 60)."\n\n";
foreach ($accounts as $key => $items) {
	foreach ($items as $item) {
		echo $social->service($key)->title().': '.$item->account->name().' ('.$item->reason.')'."\n";
	}
	echo "\n";
}

echo wordwrap(__('Please login and reauthenticate the above accounts if you wish to continue using them.', 'social'), 60)."\n\n";
_e('Global accounts:', 'social')."\n";
echo esc_url(Social::settings_url())."\n";
_e('Personal accounts:', 'social')."\n";
echo esc_url(admin_url('profile.php#social-accounts'))."\n\n";
