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
	 * @var  bool
	 */
	protected $nonce_verified = false;

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
		if ($nonce !== null) {
			if (!wp_verify_nonce($nonce, $this->request->action())) {
				Social::log('NONCE Failure', array(), null, true);
				wp_die('Oops, please try again.');
			}

			$this->nonce_verified = true;
		}
	}
	
	public function request() {
		return $this->request;
	}

	public function social() {
		return $this->social;
	}

} // End Social_Controller
