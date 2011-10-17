<?php
/**
 * Core Controller
 *
 * @package Social
 */
abstract class Social_Controller {

	/**
	 * @var  Social_Request
	 */
	protected $request;

	/**
	 * @var  Social
	 */
	protected $social;

	/**
	 * Initializes the controller with the request and Social objects. Also verifies
	 * the NONCE if it is on the request.
	 *
	 * @param  Social_Request  $request
	 */
	public function __construct(Social_Request $request) {
		$this->request = $request;
		$this->social = Social::instance();

		$nonce = $request->query('_wpnonce');
		if ($nonce !== null and !wp_verify_nonce($nonce, $this->request->action())) {
			wp_die('Oops, please try again.');
			exit;
		}
	}

} // End Social_Controller
