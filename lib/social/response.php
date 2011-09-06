<?php
/**
 * Handles the response from wp_remote_post|get requests.
 *
 * @package Social
 */
final class Social_Response {

	/**
	 * Initializes a response object.
	 *
	 * @static
	 * @param  Social_Service  $service
	 * @param  array  $request
	 * @return Social_Response
	 */
	public static function factory(&$service, array $request) {
		return new Social_Response($service, $request);
	}

	/**
	 * @var  Social_Service  service object
	 */
	private $_service = null;

	/**
	 * @var  array  response body
	 */
	private $_body = array();

	/**
	 * @param  Social_Service  $service
	 * @param array $request
	 */
	public function __construct(&$service, array $request) {
		$this->_service = $service;
		$this->_body = $request['body'];
	}

	public function limit_reached() {
		
	}

} // End Social_Response
