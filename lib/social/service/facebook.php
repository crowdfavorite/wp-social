<?php
/**
 * Facebook implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
final class Social_Service_Facebook extends Social_Service implements Social_Interface_Service {

	/**
	 * @var  string  access key
	 */
	public static $key = 'facebook';

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

} // End Social_Service_Facebook
