<?php
/*
Plugin Name: Social - Facebook
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Enabled Facebook functionality for Social.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com/
*/
if (!class_exists('Social_Facebook')) {

final class Social_Facebook {

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

// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));

}