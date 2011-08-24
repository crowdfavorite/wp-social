<?php
/*
Plugin Name: Social - Facebook
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Enabled Twitter functionality for Social.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com/
*/
if (!class_exists('Social_Facebook')) {

final class Social_Facebook {

	/**
	 * Checks to make sure Social is activated before activating itself.
	 *
	 * @static
	 * @return void
	 */
	public static function activation_hook() {
		if (!class_exists('Social')) {
			deactivate_plugins(SOCIAL_FACEBOOK_FILE); // Deactivate ourself
			wp_die(__('Social must be activated before Social - Facebook can be activated', Social::$i18n));
		}

		Social::instance()->activate_plugin(SOCIAL_FACEBOOK_FILE);
	}

	/**
	 * Deactivates the plugin from Social.
	 *
	 * @static
	 * @return void
	 */
	public static function deactivation_hook() {
		if (class_exists('Social')) {
			Social::instance()->deactivate_plugin(SOCIAL_FACEBOOK_FILE);
		}
	}

	/**
	 * Adds a reference for Social so if Social is deactivate this plugin is deactivated as well.
	 *
	 * @static
	 * @return void
	 */
	public static function init() {
		if (!class_exists('Social')) {
			include_once ABSPATH.'wp-admin/includes/plugin.php';
			deactivate_plugins(SOCIAL_FACEBOOK_FILE); // Deactivate ourself
		}
	}

	/**
	 * Registers Facebook to Social.
	 *
	 * @static
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'facebook';
		return $services;
	}

} // End Social_Facebook

$social_facebook_file = __FILE__;
if (isset($network_plugin)) {
	$social_facebook_file = $network_plugin;
}
if (isset($plugin)) {
	$social_facebook_file = $plugin;
}
define('SOCIAL_FACEBOOK_FILE', $social_facebook_file);

// Activation
register_activation_hook(SOCIAL_FACEBOOK_FILE, array('Social_Facebook', 'activation_hook'));
register_deactivation_hook(SOCIAL_FACEBOOK_FILE, array('Social_Facebook', 'deactivation_hook'));

// Actions
add_action('init', array('Social_Facebook', 'init'));

// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));

}