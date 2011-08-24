<?php
/*
Plugin Name: Social - Twitter
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Enabled Twitter functionality for Social.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com/
*/
if (!class_exists('Social_Facebook')) {

final class Social_Twitter {

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

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));

}