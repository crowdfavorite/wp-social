<?php
/**
 * All services that are registered to Social should implement this interface.
 *
 * @package Social
 */
interface Social_Interface_Service {

	/**
	 * Use the construct to load all of the accounts for this service.
	 *
	 * @abstract
	 */
	function __construct();

	/**
	 * Gets the title for the service.
	 *
	 * @abstract
	 * @return string
	 */
	function title();

	/**
	 * Builds the authorize URL for the service.
	 *
	 * @abstract
	 * @return string
	 */
	function authorize_url();

	/**
	 * Method to get or set all accounts associated with the service.
	 *
	 * @abstract
	 * @param  array|null  $accounts
	 * @return void
	 */
	function accounts(array $accounts = null);

} // End Social_Interface_Service
