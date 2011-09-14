<?php
/**
 * Twitter implementation for Social.
 *
 * @package Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Twitter')) {

final class Social_Twitter {

	/**
	 * Registers Twitter to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'twitter';
		return $services;
	}

} // End Social_Twitter

define('SOCIAL_TWITTER_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));

}