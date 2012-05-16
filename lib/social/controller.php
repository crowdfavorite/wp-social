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
	 * Initializes the controller with the request and Social objects.
	 *
	 * @param  Social_Request  $request
	 */
	public function __construct(Social_Request $request) {
		$this->request = $request;
		$this->social = Social::instance();
	}
	
	public function request() {
		return $this->request;
	}

	public function social() {
		return $this->social;
	}
	
	protected function verify_nonce() {
		$nonce = $this->request->query('_wpnonce');
		if (!wp_verify_nonce($nonce, $this->request->action())) {
			Social::log('NONCE Failure', array(), null, true);
			wp_die('Oops, please try again.');
		}
	}

} // End Social_Controller
