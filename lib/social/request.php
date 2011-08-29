<?php
/**
 * Social_Request
 *
 * Handles Social's requests. Inspired by Kohana's request class.
 * @link https://github.com/kohana/core/blob/v3.2.0/classes/kohana/request.php
 *
 * @package Social
 */
final class Social_Request {

	/**
	 * @var  Social_Request  singleton instance
	 */
	public static $instance;

	/**
	 * Returns the singleton request object.
	 *
	 * @static
	 * @return Social_Request
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * @var  string  request controller
	 */
	protected $_controller = '';

	/**
	 * @var  string  request action
	 */
	protected $_action = '';

	/**
	 * @var  string  request params
	 */
	protected $_params = '';

	/**
	 * @var  string  the x-requested-with header which most likely will be xmlhttprequest
	 */
	protected $_requested_with;

	/**
	 * @var  string  method: GET, POST
	 */
	protected $_method = 'GET';

	/**
	 * @var  array  query parameters
	 */
	protected $_get = array();

	/**
	 * @var  array  post parameters
	 */
	protected $_post = array();

	/**
	 * @var  string  request user agent
	 */
	protected $_user_agent = 'social';

	/**
	 * @var  string  client's IP address
	 */
	protected $_client_ip = '0.0.0.0';

	/**
	 * @var  string  trusted proxy server IPs
	 */
	protected $_trusted_proxies = array('127.0.0.1', 'localhost', 'localhost.localdomain');

	/**
	 * @var  mixed  response
	 */
	protected $_response = null;

	/**
	 * Initializes the request.
	 */
	public function __construct() {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			// Use the server request method
			$method = $_SERVER['REQUEST_METHOD'];
		}
		else {
			// Default to GET requests
			$method = 'GET';
		}

		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			// Browser type
			$user_agent = $_SERVER['HTTP_USER_AGENT'];
		}

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			// Typically used to denote AJAX requests
			$requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'];
		}

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
			and isset($_SERVER['REMOTE_ADDR'])
			and in_array($_SERVER['REMOTE_ADDR'], $this->_trusted_proxies))
		{
			// Use the forwarded IP address, typically set when the
			// client is using a proxy server.
			// Format: "X-Forwarded-For: client1, proxy1, proxy2"
			$client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

			$client_ip = array_shift($client_ips);

			unset($client_ips);
		}
		else if (isset($_SERVER['HTTP_CLIENT_IP'])
			and isset($_SERVER['REMOTE_ADDR'])
			and in_array($_SERVER['REMOTE_ADDR'], $this->_trusted_proxies))
		{
			// Use the forwarded IP address, typically set when the
			// client is using a proxy server.
			$client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);

			$client_ip = array_shift($client_ips);

			unset($client_ips);
		}
		else if (isset($_SERVER['REMOTE_ADDR'])) {
			// The remote IP address
			$client_ip = $_SERVER['REMOTE_ADDR'];
		}

		$params = array();
		foreach (array_merge($_POST, $_GET) as $key => $value) {
			if (strpos($key, 'social_') !== false or
			   ($method == 'POST' and $key == 'data')) // Hack for the Sopresto API. Would like to get this named to "social_data".
			{
				if ($key == 'social_controller') {
					$params['controller'] = $value;
				}
				else if ($key == 'social_action') {
					$params['action'] = $value;
				}
				else {
					$params[$key] = $value;
				}

				if ($method == 'POST') {
					unset($_POST[$key]);
				}
				else {
					unset($_GET[$key]);
				}
			}
		}

		if (isset($params['controller'])) {
			$this->controller($params['controller']);
			unset($params['controller']);
		}

		if (isset($params['action'])) {
			$this->action($params['action']);
			unset($params['action']);
		}

		if (count($params)) {
			foreach ($params as $key => $value) {
				$this->param($key, $value);
			}
		}

		$this->query($_GET)
			->post($_POST);

		if (isset($method)) {
			$this->method($method);
		}

		if (isset($requested_with)) {
			// Apply the requested with variable
			$this->requested_with($requested_with);
		}

		if (isset($user_agent)) {
			$this->user_agent($user_agent);
		}

		if (isset($client_ip)) {
			$this->client_ip($client_ip);
		}
	}

	/**
	 * Executes the request.
	 *
	 * @throws Exception
	 * @return Social_Request
	 */
	public function execute() {
		require 'controller.php';
		$controller = apply_filters('social_controller', SOCIAL_PATH.'lib/social/controller/'.$this->controller());
		if (file_exists($controller.'.php')) {
			require $controller.'.php';

			$controller = 'Social_Controller_'.$this->controller();
			$controller = new $controller($this);

			$action = 'action_'.$this->action();
			if (method_exists($controller, $action)) {
				$controller->{$action}();
			}
			else {
				throw new Exception(sprintf(__('Invalid action %s called on controller %s.', Social::$i18n), $this->action(), $this->controller()));
			}
		}
		else {
			throw new Exception(sprintf(__('Controller %s does not exist.', Social::$i18n), 'Social_Controller_'.$this->controller()));
		}
	}

	/**
	 * Gets and sets the requested controller.
	 *
	 * @param  string  $value
	 * @return Social_Request|string
	 */
	public function controller($value = null) {
		if ($value === null) {
			return $this->_controller;
		}

		$this->_controller = $value;
		return $this;
	}

	/**
	 * Gets and sets the requested action.
	 *
	 * @param  string  $value
	 * @return Social_Request|string
	 */
	public function action($value = null) {
		if ($value === null) {
			return $this->_action;
		}

		$this->_action = $value;
		return $this;
	}

	/**
	 * Gets and sets the requested parameter.
	 *
	 * @param  string  $key
	 * @param  string  $value
	 * @return Social_Request|string
	 */
	public function param($key, $value = null) {
		if (strpos($key, 'social_') === false) {
			$key = 'social_'.$key;
		}
		if ($value === null) {
			return (isset($this->_params[$key]) ? $this->_params[$key] : null);
		}
		$this->_params[$key] = $value;
		return $this;
	}

	/**
	 * Gets and sets the requested with property, which should
	 * be relative to the x-requested-with pseudo header.
	 *
	 * @param   string    $requested_with Requested with value
	 * @return  mixed|Social_Request
	 */
	public function requested_with($requested_with = null) {
		if ($requested_with === null) {
			// Act as a getter
			return $this->_requested_with;
		}

		// Act as a setter
		$this->_requested_with = strtolower($requested_with);

		return $this;
	}

	/**
	 * Gets or sets the HTTP method.
	 *
	 * @param   string   $method  Method to use for this request
	 * @return  mixed|Social_Request
	 */
	public function method($method = null) {
		if ($method === null) {
			// Act as a getter
			return $this->_method;
		}

		// Act as a setter
		$this->_method = strtoupper($method);

		return $this;
	}

	/**
	 * Gets or sets the user agent.
	 *
	 * @param   string   $user_agent
	 * @return  mixed|Social_Request
	 */
	public function user_agent($user_agent = null) {
		if ($user_agent === null) {
			// Act as a getter
			return $this->_user_agent;
		}

		// Act as a setter
		$this->_user_agent = $user_agent;

		return $this;
	}

	/**
	 * Gets or sets the client IP.
	 *
	 * @param   string   $client_ip
	 * @return  mixed|Social_Request
	 */
	public function client_ip($client_ip = null) {
		if ($client_ip === null) {
			// Act as a getter
			return $this->_client_ip;
		}

		// Act as a setter
		$this->_client_ip = $client_ip;

		return $this;
	}

	/**
	 * Gets or sets HTTP query string.
	 *
	 * @param   mixed   $key    Key or key value pairs to set
	 * @param   string  $value  Value to set to a key
	 * @return  mixed|Social_Request
	 */
	public function query($key = null, $value = null) {
		if (is_array($key)) {
			// Act as a setter, replace all query strings
			$this->_get = $key;

			return $this;
		}

		if ($key === null) {
			// Act as a getter, all query strings
			return $this->_get;
		}
		else if ($value === null) {
			// Act as a getter, single query string
			return isset($this->_get[$key]) ? $this->_get[$key] : null;
		}

		// Act as a setter, single query string
		$this->_get[$key] = $value;

		return $this;
	}

	/**
	 * Gets or sets HTTP POST parameters to the request.
	 *
	 * @param   mixed  $key    Key or key value pairs to set
	 * @param   string $value  Value to set to a key
	 * @return  mixed|Social_Request
	 */
	public function post($key = null, $value = null) {
		if (is_array($key))
		{
			// Act as a setter, replace all fields
			$this->_post = $key;

			return $this;
		}

		if ($key === null) {
			// Act as a getter, all fields
			return $this->_post;
		}
		else if ($value === null) {
			// Act as a getter, single field
			return isset($this->_post[$key]) ? $this->_post[$key] : null;
		}

		// Act as a setter, single field
		$this->_post[$key] = $value;

		return $this;
	}

	/**
	 * Sets the response.
	 *
	 * @param  mixed  $value
	 * @return mixed|Social_Request
	 */
	public function response($value = null) {
		if ($value === null) {
			return $this->_response;
		}

		if (is_array($value)) {
			$value = json_encode($value);
		}

		$this->_response = $value;
		return $this;
	}

} // End Social_Request
