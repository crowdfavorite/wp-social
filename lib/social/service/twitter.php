<?php
// Service Filters
add_filter('social_register_service', array('Social_Service_Twitter', 'register_service'));

/**
 * Twitter implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
final class Social_Service_Twitter extends Social_Service implements Social_Interface_Service {

	/**
	 * @var  string  access key
	 */
	public static $key = 'twitter';

	/**
	 * Registers the service.
	 *
	 * @static
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = self::$key;
		return $services;
	}

} // End Social_Service_Twitter
