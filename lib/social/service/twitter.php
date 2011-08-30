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
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string  $message  message to broadcast
	 * @return int
	 */
	public function broadcast($account, $message) {
		// TODO: Implement broadcast() method.
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
