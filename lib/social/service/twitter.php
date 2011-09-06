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
	 * @var  string  service key
	 */
	protected $_key = 'twitter';

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140;
	}

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string  $message  message to broadcast
	 * @param  array   $args  extra arguments to pass to the request
	 * @return int
	 */
	public function broadcast($account, $message, array $args = array()) {
		$args = $args + array(
			'status' => $message
		);

		return $this->request($account, 'statuses/update', $args, 'POST');
	}

	/**
	 * Aggregates to-be WordPress comments from the service.
	 *
	 * @return array
	 */
	public function aggregate() {
		// TODO: Implement aggregate() method.
	}

} // End Social_Service_Twitter
