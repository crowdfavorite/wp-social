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

	/**
	 * Formats the provided content to the defined tokens.
	 *
	 * @abstract
	 * @param  object  $post
	 * @param  string  $format
	 * @return string
	 */
	function format_content($post, $format);

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @abstract
	 * @return int
	 */
	function max_broadcast_length();

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @abstract
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string  $message  message to broadcast
	 * @param  array   $args  extra arguments to pass to the request
	 * @return int
	 */
	function broadcast($account, $message, array $args = array());

	/**
	 * Aggregates to-be WordPress comments from the service.
	 *
	 * @abstract
	 * @return array
	 */
	function aggregate();

} // End Social_Interface_Service
