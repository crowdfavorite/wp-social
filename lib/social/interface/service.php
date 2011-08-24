<?php
/**
 * All services that are registered to Social should implement this interface.
 *
 * @package Social
 */
interface Social_Interface_Service {

	/**
	 * Method that is caught by the social_services_to_load filter. Use this to
	 * register your service with Social.
	 *
	 * @static
	 * @abstract
	 * @param  array  $services
	 * @return array
	 */
	static function register_service(array $services);

	/**
	 * Use the construct to load all of the accounts for this service.
	 *
	 * @abstract
	 */
	function __construct();

	/**
	 * Method to get or set all accounts associated with the service.
	 *
	 * @abstract
	 * @param  array|null  $accounts
	 * @return void
	 */
	function accounts(array $accounts = null);

} // End Social_Interface_Service
