<?php
/**
 * Twitter implementation for Social.
 *
 * @package Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Facebook')) {

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

define('SOCIAL_FACEBOOK_FILE', __FILE__);
	
// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));

}