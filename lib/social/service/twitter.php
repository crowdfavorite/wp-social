<?php
/**
 * Twitter implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
add_filter('social_services_to_load', array('Social_Service_Twitter', 'services_to_load'));
final class Social_Service_Twitter implements Social_IService {

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
	public static function services_to_load(array $services) {
		$services[] = self::$key;
		return $services;
	}

	/**
	 * @var  array  service accounts
	 */
	protected $_accounts = array();

	/**
	 * Checks to see if the status update was a duplicate.
	 *
	 * @param  object  $response
	 * @return bool
	 */
	function duplicate_status($response) {
		// TODO: Implement duplicate_status() method.
	}

	/**
	 * Checks to see if the user's limit has been reached.
	 *
	 * @param  object  $response
	 * @return bool
	 */
	function limit_reached($response) {
		// TODO: Implement limit_reached() method.
	}

	function aggregation_row($type, array $item, $username, $id) {
		// TODO: Implement aggregation_row() method.
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return void
	 */
	function max_broadcast_length() {
		// TODO: Implement max_broadcast_length() method.
	}

	/**
	 * Adds multiple accounts to the service.
	 *
	 * @param  array  $accounts
	 * @return array
	 */
	function accounts(array $accounts = null) {
		// TODO: Implement accounts() method.
	}

	/**
	 * The account to us for this service.
	 *
	 * @param  object  $account  user's account
	 * @return void
	 */
	function account($account) {
		// TODO: Implement account() method.
	}

	/**
	 * Executes the request for the service.
	 *
	 * @param  int|object  $account  account to use
	 * @param  string      $api      API endpoint to request
	 * @param  array       $params   parameters to pass to the API
	 * @param  string      $method   GET|POST, default: GET
	 * @return array
	 */
	function request($account, $api, array $params = array(), $method = 'GET') {
		// TODO: Implement request() method.
	}

	/**
	 * Creates a WordPress User
	 *
	 * @param  int|object  $account  account to use to create the WP account
	 * @return int
	 */
	function create_user($account) {
		// TODO: Implement create_user() method.
	}

	/**
	 * Returns the disconnect URL.
	 *
	 * @static
	 * @param  object  $account
	 * @param  bool    $is_admin
	 * @param  string  $before
	 * @param  string  $after
	 * @return string
	 */
	function disconnect_url($account, $is_admin = false, $before = '', $after = '') {
		// TODO: Implement disconnect_url() method.
	}

	/**
	 * Formats the provided content to the defined tokens.
	 *
	 * @param  object  $post
	 * @param  string  $format
	 * @return string
	 */
	function format_content($post, $format) {
		// TODO: Implement format_content() method.
	}

	/**
	 * Updates a user's status on the service.
	 *
	 * @param  int|object  $account
	 * @param  string      $status  status message
	 * @return void
	 */
	function status_update($account, $status) {
		// TODO: Implement status_update() method.
	}

	/**
	 * Returns the URL to the user's account.
	 *
	 * @param  object  $account
	 * @return string
	 */
	function profile_url($account) {
		// TODO: Implement profile_url() method.
	}

	/**
	 * Returns the user's display name.
	 *
	 * @param  object  $account
	 * @return string
	 */
	function profile_name($account) {
		// TODO: Implement profile_name() method.
	}

	/**
	 * Builds the user's avatar.
	 *
	 * @param  int|object  $account
	 * @param  int         $comment_id
	 * @return string
	 */
	function profile_avatar($account, $comment_id = null) {
		// TODO: Implement profile_avatar() method.
	}

	/**
	 * Searches the service to find any replies to the blog post.
	 *
	 * @param  object      $post
	 * @param  array       $urls
	 * @param  array|null  $broadcasted_ids
	 * @return array|bool
	 */
	function search_for_replies($post, array $urls, $broadcasted_ids = null) {
		// TODO: Implement search_for_replies() method.
	}

	/**
	 * Saves the replies as comments.
	 *
	 * @param  int    $post_id
	 * @param  array  $replies
	 * @return void
	 */
	function save_replies($post_id, array $replies) {
		// TODO: Implement save_replies() method.
	}

	/**
	 * Builds the status URL.
	 *
	 * @param  string  $username
	 * @param  int     $status_id
	 * @return string
	 */
	function status_url($username, $status_id) {
		// TODO: Implement status_url() method.
	}

} // End Social_Service_Twitter
