<?php
/*
Plugin Name: Social - Twitter
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Enabled Twitter functionality for Social.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com/
*/
if (!class_exists('Social_Twitter')) {

final class Social_Twitter {

	/**
	 * Checks to make sure Social is activated before activating itself.
	 *
	 * @static
	 * @return void
	 */
// TODO - talk through this
	public static function activation_hook() {
		if (!class_exists('Social')) {
			deactivate_plugins(SOCIAL_TWITTER_FILE); // Deactivate ourself
			wp_die(__('Social must be activated before Social - Twitter can be activated', Social::$i18n));
		}

		Social::instance()->activate_plugin(SOCIAL_TWITTER_FILE);
	}

	/**
	 * Deactivates the plugin from Social.
	 *
	 * @static
	 * @return void
	 */
	public static function deactivation_hook() {
		if (class_exists('Social')) {
			Social::instance()->deactivate_plugin(SOCIAL_TWITTER_FILE);
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
			deactivate_plugins(SOCIAL_TWITTER_FILE); // Deactivate ourself
		}
	}

	/**
	 * Registers Twitter to Social.
	 *
	 * @static
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'twitter';
		return $services;
	}

} // End Social_Twitter

// TODO - should this be in if/else checks?
// Add support for mu-plugins
$social_twitter_file = __FILE__;
if (isset($network_plugin)) {
	$social_twitter_file = $network_plugin;
}
if (isset($plugin)) {
	$social_twitter_file = $plugin;
}
define('SOCIAL_TWITTER_FILE', $social_twitter_file);

// Activation
register_activation_hook(SOCIAL_TWITTER_FILE, array('Social_Twitter', 'activation_hook'));
register_deactivation_hook(SOCIAL_TWITTER_FILE, array('Social_Twitter', 'deactivation_hook'));

// Actions
add_action('init', array('Social_Twitter', 'init'));

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));

}