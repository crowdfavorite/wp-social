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
	 * @param  Social_Service_Account $account
	 * @return Social_Response
	 */
	public static function factory(&$service, array $request, &$account) {
		return new Social_Response($service, $request, $account);
	}

	/**
	 * @var  Social_Service|Social_Service_Facebook|Social_Service_Twitter  service object
	 */
	private $_service = null;

	/**
	 * @var  array  request object
	 */
	private $_request = array();

	/**
	 * @var Social_Service_Account  account object
	 */
	private $_account = null;

	/**
	 * @param  Social_Service  $service
	 * @param  array  $request
	 * @param  Social_Service_Account $account
	 */
	public function __construct(&$service, array $request, &$account) {
		$this->_service = $service;
		$this->_request = $request;
		$this->_account = $account;
	}

	/**
	 * Checks to see if the response has reached it's limit.
	 *
	 * @return bool
	 */
	public function limit_reached() {
		$body = $this->body();
		if (isset($body->result) and $body->result == 'error' and isset($body->response)) {
			return $this->_service->limit_reached($body->response);
		}

		return false;
	}

	/**
	 * Checks to see if the status is a duplicate.
	 *
	 * @return bool
	 */
	public function duplicate_status() {
		$body = $this->body();
		if (isset($body->result) and $body->result == 'error' and isset($body->response)) {
			return $this->_service->duplicate_status($body->response);
		}

		return false;
	}

	/**
	 * Checks to see if the broadcasting account has been deauthorized.
	 *
	 * @param  bool  $check_invalid_key
	 * @return bool
	 */
	public function deauthorized($check_invalid_key = FALSE) {
		$body = $this->body();
		if ((isset($body->result) and $body->result == 'error') and isset($body->response) and $this->_service->deauthorized($body->response, $check_invalid_key)) {
			if ($this->_account->personal()) {
				$url = Social::settings_url(array(), true);
			}
			else {
				$url = Social::settings_url();
			}

			$deauthorized = get_option('social_deauthorized', array());
			if (!isset($deauthorized[$this->_service->key()])) {
				$deauthorized[$this->_service->key()] = array();
			}
			$deauthorized[$this->_service->key()][$this->_account->id()] = sprintf(__('Unable to publish to %s with account %s. Please <a href="%">re-authorize</a> this account.', 'social'), esc_html($this->_service->title()), esc_html($this->_account->name()), esc_url($url));
			update_option('social_deauthorized', $deauthorized);

			Social::log('Removing deauthorized account: :account', array(
				'account' => print_r($this->_account, true)
			));
			$this->_service->remove_account($this->_account)->save();

			return true;
		}

		return false;
	}

	/**
	 * Checks the response to see if an unknown error occurred.
	 *
	 * @return bool
	 */
	public function general_error() {
		$body = $this->body();
		if (is_wp_error($this->_request) or isset($body->result) and $body->result == 'error' and isset($body->response)) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the request response ID.
	 *
	 * @return string
	 */
	public function id() {
		if ($body = $this->body()) {
			if (isset($body->response) && isset($body->response->{$this->_service->response_id_key()})) {
				return $body->response->{$this->_service->response_id_key()};
			}
		}
		return '0';
	}

	/**
	 * Returns the request body.
	 *
	 * @return bool|object
	 */
	public function body() {
		if (isset($this->_request['body'])) {
			return $this->_request['body'];
		}

		return false;
	}

	/**
	 * Returns the response message.
	 *
	 * @param  string  $default  default message to use
	 * @return mixed
	 */
	public function message($default) {
		return $this->_service->response_message($this->body(), $default);
	}

} // End Social_Response
