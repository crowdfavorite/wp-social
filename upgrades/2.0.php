<?php if (!defined('SOCIAL_UPGRADE')) { die('Direct script access not allowed.'); }
/**
 * Upgrades Social to 2.0.
 */

if (Social_CRON::instance('upgrade')->lock()) {

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
			$new = '_'.$key;
			if ($key == 'social_aggregated_replies') {
				$new = '_social_aggregated_ids';
			}
			$wpdb->query("
				UPDATE $wpdb->postmeta
				   SET meta_key = '$new'
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
	if (version_compare($installed_version, '2.0', '<')) {
		// Fix aggregated IDs
		$results = $wpdb->get_results("
			SELECT post_id, meta_value
			  FROM $wpdb->postmeta
			 WHERE meta_key = '_social_aggregated_ids'
		");
		if (is_array($results)) {
			foreach ($results as $result) {
				$result->meta_value = maybe_unserialize($result->meta_value);
				if (is_array($result->meta_value)) {
					$meta_value = array();
					foreach ($result->meta_value as $id) {
						if (strpos($id, '_') !== false) {
							if (!isset($meta_value['facebook'])) {
								$meta_value['facebook'] = array();
							}

							$meta_value['facebook'][] = $id;
						}
						else {
							if (!isset($meta_value['twitter'])) {
								$meta_value['twitter'] = array();
							}

							$meta_value['twitter'][] = $id;
						}
					}

					if (!empty($meta_value)) {
						update_post_meta($result->post_id, '_social_aggregated_ids', $meta_value);
					}
					else {
						delete_post_meta($result->post_id, '_social_aggregated_ids');
				    }
				}
			}
		}

		// Global accounts
		$set_meta = false;
		$accounts = get_option('social_accounts', array());
		if (count($accounts)) {
			if (isset($accounts['facebook'])) {
				$set_meta = true;
				$accounts['facebook'] = array();
			}

			if (isset($accounts['twitter'])) {
				foreach ($accounts['twitter'] as $account_id => $account) {
					if (!isset($account->universal)) {
						$accounts['twitter'][$account_id]->universal = true;
					}
				}
			}

			update_option('social_accounts', $accounts);
		}

		$results = $wpdb->get_results("
			SELECT user_id, meta_value
			  FROM $wpdb->usermeta
			 WHERE meta_key = 'social_accounts'
		");
		if (is_array($results)) {
			foreach ($results as $result) {
				$accounts = maybe_unserialize($result->meta_value);
				if (is_array($accounts)) {
					if (isset($accounts['facebook'])) {
						$set_meta = true;
						$accounts['facebook'] = array();
					}

					if (isset($accounts['twitter'])) {
						foreach ($accounts['twitter'] as $account_id => $account) {
							if (!isset($account->personal)) {
							    $accounts['twitter'][$account_id]->personal = true;
							}
						}
					}

					update_user_meta($result->user_id, 'social_accounts', $accounts);
				}
			}
		}

		if ($set_meta) {
			$results = $wpdb->get_results("
				SELECT ID
				  FROM $wpdb->users
			");
			if (is_array($results)) {
				foreach ($results as $result) {
					update_user_meta($result->ID, 'social_2.0_upgrade', true);
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

		// Make sure all commenter accounts have the commenter flag
		$results = $wpdb->get_results("
			SELECT m.user_id
			  FROM $wpdb->users AS u
			  JOIN $wpdb->usermeta AS m
			    ON m.user_id = u.ID
			 WHERE m.meta_key = 'social_accounts'
			   AND u.user_email LIKE '%@example.com'
	    ");
		if (count($results)) {
			foreach ($results as $result) {
				update_user_meta($result->user_id, 'social_commenter', 'true');
			}
		}

		// Rename the XMLRPC option
		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'social_default_accounts'
			 WHERE option_name = 'social_xmlrpc_accounts'
	    ");

		// Fix the broadcasted IDs format
		$results = $wpdb->get_results("
			SELECT pm.meta_value, pm.post_id, p.post_content
			  FROM $wpdb->postmeta AS pm
			  JOIN $wpdb->posts AS p
			    ON pm.post_id = p.ID
			 WHERE meta_key = '_social_broadcasted_ids'
	    ");
		if (is_array($results)) {
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

							if (is_array($broadcasted)) {
								foreach ($broadcasted as $id => $data) {
									if ((int) $data) {
										$_meta_value[$service_key][$account_id][$data] = array(
											'message' => ''
										);
									}
									else {
										$_meta_value[$service_key][$account_id][$id] = $data;
									}
								}
							}
							else {
								$_meta_value[$service_key][$account_id][$broadcasted] = array(
									'message' => ''
								);
							}
						}
					}

					if (!empty($_meta_value)) {
						update_post_meta($result->post_id, '_social_broadcasted_ids', $_meta_value);
					}
				}
			}
		}

		// Add broadcast by default
		Social::option('broadcast_by_default', '1');

		// Reschedule posts for aggregation
		$results = $wpdb->get_results("
			SELECT post_id
			  FROM $wpdb->postmeta
			 WHERE meta_key = '_social_broadcasted_ids'
		");
		if ($results !== null) {
			$count = 0;
			$queue = Social_Aggregation_Queue::factory();
			foreach ($results as $result) {
				if (!$queue->find($result->post_id)) {
					$queue->add($result->post_id);
				}

				++$count;
				if ($count >= 50) {
					break;
				}
			}
			$queue->save();
		}
	}

	// Flush the cache
	wp_cache_flush();

	Social_CRON::instance('upgrade')->unlock();
}
else {
	// CRON already locked
}
