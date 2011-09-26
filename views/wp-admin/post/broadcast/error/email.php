<?php

_e('Hello', Social::$i18n).','."\n\n";

echo wordwrap(sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', Social::$i18n), esc_html($post->post_title)), 60)."\n\n";

foreach ($accounts as $key => $items) {
	echo $social->service($key)->title().':'."\n";
	foreach ($items as $item) {
		echo '- '.esc_html($item->account->name())."\n";
	}
	echo "\n";
}

echo wordwrap(__('Please login and reauthenticate the above accounts if you wish to continue using them.', Social::$i18n), 60)."\n\n";
_e('Global accounts:', Social::$i18n)."\n";
echo esc_url(Social_Helper::settings_url())."\n\n";
_e('Personal accounts:', Social::$i18n)."\n";
echo esc_url(admin_url('profile.php#social-networks'))."\n\n";
