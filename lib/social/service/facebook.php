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

} // End Social_Service_Facebook
