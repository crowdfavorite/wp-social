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
	 * @var  Social_Service  service object
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
		if ($this->body()->response == 'error' and isset($this->body()->response)) {
			return $this->_service->limit_reached($this->body()->response);
		}

		return false;
	}

	/**
	 * Checks to see if the status is a duplicate.
	 *
	 * @return bool
	 */
	public function duplicate_status() {
		if ($this->body()->response == 'error' and isset($this->body()->response)) {
			return $this->_service->duplicate_status($this->body()->response);
		}

		return false;
	}

	/**
	 * Checks to see if the broadcasting account has been deauthorized.
	 *
	 * @return bool
	 */
	public function deauthorized() {
		if ($this->body()->response == 'error' and
		    isset($this->body()->response) and
		    $this->_service->deauthorized($this->body()->response))
		{
			if ($this->_account->personal()) {
				$url = Social_Helper::settings_url(array(), true);
			}
			else {
				$url = Social_Helper::settings_url();
			}

			$deauthorized = get_option('social_deauthorized', array());
			if (!isset($deauthorized[$this->_service->key()])) {
				$deauthorized[$this->_service->key()] = array();
			}
			$deauthorized[$this->_service->key()][$this->_account->id()] = sprintf(__('Unable to publish to %s with account %s. Please <a href="%">re-authorize</a> this account.', Social::$i18n), $this->_service->title(), $this->_account->name(), $url);
			update_option('social_deauthorized', $deauthorized);

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
		if (is_wp_error($this->_request) or $this->body()->result == 'error') {
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
		return $this->body()->response->{$this->_service->response_id_key()};
	}

	/**
	 * Returns the request body.
	 *
	 * @return bool|object
	 */
	public function body() {
		if (isset($this->body()->response)) {
			return $this->body()->response;
		}

		return false;
	}

} // End Social_Response
