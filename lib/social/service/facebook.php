<?php
/**
 * Facebook implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
final class Social_Service_Facebook extends Social_Service implements Social_Interface_Service {

	/**
	 * @var  string  service key
	 */
	protected $_key = 'facebook';

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 400;
	}

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string  $message  message to broadcast
	 * @param  array   $args  extra arguments to pass to the request
	 * @return Social_Response
	 */
	public function broadcast($account, $message, array $args = array()) {
		$args = $args + array(
			'message' => $message,
		);
		return $this->request($account, 'feed', $args, 'POST');
	}

	/**
	 * Aggregates to-be WordPress comments from the service.
	 *
	 * @return array
	 */
	public function aggregate() {
		// TODO: Implement aggregate() method.
	}

	/**
	 * Checks the response to see if the broadcast limit has been reached.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function limit_reached($response) {
		if ($response == '(#341) Feed action request limit reached') {
			return true;
		}

		return false;
	}

	/**
	 * Checks the response to see if the broadcast is a duplicate.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function duplicate_status($response) {
		if ($response == '(#506) Duplicate status message') {
			return true;
		}

		return false;
	}

	/**
	 * Checks the response to see if the account has been deauthorized.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function deauthorized($response) {
		if (strpos($response, 'Error validating access token') !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the key to use on the request response to pull the ID.
	 *
	 * @return string
	 */
	public function response_id_key() {
		return 'id_str';
	}

} // End Social_Service_Facebook
