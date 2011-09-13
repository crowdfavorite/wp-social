<?php if (!defined('SOCIAL_UPGRADE')) die('Direct script access not allowed.');
/**
 * Upgrades Social to 1.5.
 */

// Find old social_notify and update to _social_notify.
$meta_keys = array(
	'social_aggregated_replies',
	'social_broadcast_error',
	'social_broadcast_accounts',
	'social_broadcasted_ids',
	'social_aggregation_log',
	'social_twitter_content',
	'social_notify_twitter',
	'social_facebook_content',
	'social_notify_facebook',
	'social_notify',
	'social_broadcasted'
);
if (count($meta_keys)) {
	foreach ($meta_keys as $key) {
		$wpdb->query("
			UPDATE $wpdb->postmeta
			   SET meta_key = '_$key'
			 WHERE meta_key = '$key'
		");
	}
}

// Delete old useless meta
$meta_keys = array(
	'_social_broadcasted'
);
if (count($meta_keys)) {
	foreach ($meta_keys as $key) {
		$wpdb->query("
			DELETE
			  FROM $wpdb->postmeta
			 WHERE meta_key = '$key'
		");
	}
}

// De-auth Facebook accounts for new permissions.
if (version_compare($installed_version, '1.5', '<')) {
	// Global accounts
	$accounts = get_option('social_accounts', array());
	if (isset($accounts['facebook'])) {
		$accounts['facebook'] = array();
		update_option('social_accounts', $accounts);
	}

	// Personal accounts
	$users = get_users(array('role' => 'subscriber'));
	$ids = array(0);
	if (is_array($users)) {
		foreach ($users as $user) {
			$ids[] = $user->ID;
		}
	}

	$results = $wpdb->get_results("
		SELECT user_id, meta_value
		  FROM $wpdb->usermeta
		 WHERE meta_key = 'social_accounts'
	");
	foreach ($results as $result) {
		$accounts = maybe_unserialize($result->meta_value);
		if (is_array($accounts) and isset($accounts['facebook'])) {
			$accounts['facebook'] = array();
			update_user_meta($result->user_id, 'social_accounts', $accounts);

			if (!in_array($result->user_id, $ids)) {
				update_user_meta($result->user_id, 'social_1.5_upgrade', true);
			}
		}
	}
}