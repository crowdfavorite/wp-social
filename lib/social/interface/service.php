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
	 * Returns the service key.
	 *
	 * @abstract
	 * @return string
	 */
	function key();

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
	 * @return array|Social_Service
	 */
	function accounts(array $accounts = null);

	/**
	 * @abstract
	 * @param  int|Social_Service_Account  $account
	 * @return Social_Service
	 */
	function remove_account($account);

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
	 * Formats the provided content to the defined tokens.
	 *
	 * @abstract
	 * @param  object  $comment
	 * @param  string  $format
	 * @return string
	 */
	function format_comment_content($comment, $format);

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
	 * @param  int     $post_id  post ID being broadcasted
	 * @return Social_Response
	 */
	function broadcast($account, $message, array $args = array(), $post_id = null);

	/**
	 * Aggregates comments by URL.
	 *
	 * @abstract
	 * @param  object  $post
	 * @param  array   $urls
	 * @return array
	 */
	function aggregate_by_url(&$post, array $urls);

	/**
	 * Aggregates comments by the service's API.
	 *
	 * @abstract
	 * @param  object  $post
	 * @return array
	 */
	function aggregate_by_api(&$post);

	/**
	 * Saves the aggregated comments.
	 *
	 * @abstract
	 * @param  object  $post
	 * @return void
	 */
	function save_aggregated_comments(&$post);

	/**
	 * Hook to allow services to define their aggregation row items based on the passed in type.
	 *
	 * @param  string  $type
	 * @param  object  $item
	 * @param  string  $username
	 * @param  int     $id
	 * @return string
	 */
	function aggregation_row($type, $item, $username, $id);

	/**
	 * Checks the response to see if the broadcast limit has been reached.
	 *
	 * @abstract
	 * @param  string  $response
	 * @return bool
	 */
	function limit_reached($response);

	/**
	 * Checks the response to see if the broadcast is a duplicate.
	 *
	 * @abstract
	 * @param  string  $response
	 * @return bool
	 */
	function duplicate_status($response);

	/**
	 * Checks the response to see if the account has been deauthorized.
	 *
	 * @abstract
	 * @param  string  $response
	 * @param  bool    $check_invalid_key
	 * @return bool
	 */
	function deauthorized($response, $check_invalid_key = false);

	/**
	 * Returns the key to use on the request response to pull the ID.
	 *
	 * @abstract
	 * @return string
	 */
	function response_id_key();

	/**
	 * Returns the response message.
	 *
	 * @abstract
	 * @param  object  $body
	 * @param  string  $default
	 * @return string
	 */
	function response_message($body, $default);

	/**
	 * Returns the status URL to a broadcasted item.
	 *
	 * @abstract
	 * @param  string      $username
	 * @param  string|int  $id
	 * @return string
	 */
	function status_url($username, $id);

} // End Social_Interface_Service
