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

	public function aggregate() {

	}

} // End Social_Service_Twitter
