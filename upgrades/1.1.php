<?php if (!defined('SOCIAL_UPGRADE')) die('Direct script access not allowed.');
/**
 * Upgrades Social to 1.1.
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

// Flush the cache
wp_cache_flush();

// De-auth Facebook accounts for new permissions.
if (version_compare($installed_version, '1.1', '<')) {
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
				update_user_meta($result->user_id, 'social_1.1_upgrade', true);
			}
		}
	}

	// Upgrade system_cron to fetch_comments
	$fetch = $wpdb->get_var("
		SELECT option_value
		  FROM $wpdb->options
		 WHERE option_name = 'social_system_crons'
	");

    if (empty($fetch)) {
        $fetch = '1';
    }
    
    $wpdb->query("
        INSERT
          INTO $wpdb->options (option_name, option_value)
        VALUES('social_fetch_comments', '$fetch')
            ON DUPLICATE KEY UPDATE option_id = option_id
    ");

	// Update all comment types
	$keys = array();
	foreach (Social::instance()->services() as $service) {
		$keys[] = $service->key();
		if ($service->key() == 'facebook') {
			$keys[] = 'facebook-like';
		}
	}

	foreach ($keys as $key) {
		$query = $wpdb->query("
			UPDATE $wpdb->comments
			   SET comment_type = 'social-$key'
			 WHERE comment_type = '$key'
		");
	}
}

// Make sure all commenter accounts have the commenter flag
$results = $wpdb->get_results("
SELECT m.user_id
  FROM $wpdb->users AS u
  JOIN $wpdb->usermeta AS m
    ON m.user_id = u.ID
 WHERE m.meta_key = 'social_accounts'
   AND u.user_email LIKE '%@example.com'
");
foreach ($results as $result) {
	update_user_meta($result->user_id, 'social_commenter', 'true');
}

// Rename the XMLRPC option
$wpdb->query("
UPDATE $wpdb->options
   SET option_name = 'social_default_accounts'
 WHERE option_name = 'social_xmlrpc_accounts'
");

// Fix the broadcasted IDs format
$results = $wpdb->get_results("
SELECT meta_value, post_id
  FROM $wpdb->postmeta
 WHERE meta_key = '_social_broadcasted_ids'
");
foreach ($results as $result) {
    $meta_value = maybe_unserialize($result->meta_value);
    if (is_array($meta_value)) {
        $_meta_value = array();
        foreach ($meta_value as $service_key => $accounts) {
            if (!isset($_meta_value[$service_key])) {
                $_meta_value[$service_key] = array();
            }

            foreach ($accounts as $account_id => $broadcasted) {
                if (!isset($_meta_value[$service_key][$account_id])) {
                    $_meta_value[$service_key][$account_id] = array();
                }

                foreach ($broadcasted as $id => $data) {
                    if ((int) $data) {
                        $_meta_value[$service_key][$account_id][$data] = '';
                    }
                    else {
                        $_meta_value[$service_key][$account_id][$id] = $data;
                    }
                }
            }
        }

        if (!empty($_meta_value)) {
            update_post_meta($result->post_id, '_social_broadcasted_ids', $_meta_value);
        }
    }
}
