<?php if (!defined('SOCIAL_UPGRADE')) { die('Direct script access not allowed.'); }
/**
 * Upgrades Social to 2.5.
 */

// Force the lock
$semaphore = Social_Semaphore::factory();
$semaphore->lock();

// check for duplicate users
$duplicate_user_check = $wpdb->get_var("
	SELECT count(*) c
	FROM $wpdb->users
	GROUP BY user_login
	HAVING c > 1
	LIMIT 1
");
if ($duplicate_user_check > 0) {
	// find duplicate usernames
	$dup_users = $wpdb->query("
		SELECT user_login, count(*) c
		FROM $wpdb->users
		GROUP BY user_login
		HAVING c > 1
	");
	d($dup_users);
	foreach ($dup_users as $data) {
		// remove additional users
		

	}
}

// Flush the cache
wp_cache_flush();

// Decrement the semaphore and unlock
$semaphore->unlock();
