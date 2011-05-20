<?php
/**
 * Handles the different services that can be connected to Social.
 *
 * @author Crowd Favorite
 * @copyright (c) 2010 Crowd Favorite. All Rights Reserved.
 * @package Social
 */
abstract class Social_Service {

	/**
	 * @var  string  the service
	 */
	public $service = '';

	/**
	 * @var string  the UI display value
	 */
	public $title = '';

	/**
	 * @var  array  service's accounts
	 */
	protected $accounts = array();

	/**
	 * @var  WP_User  current user
	 */
	private $user = false;

	/**
	 * Initializes the service, and loads a user by ID.
	 *
	 * @param  int  $user_id
	 */
	public function __construct($user_id = null) {
		if ($user_id === null) {
			$this->user = wp_get_current_user();
		}
		else {
			$this->user = get_userdata($user_id);

			// Load the users account(s)
			$accounts = get_user_meta($user_id, Social::$prefix.'accounts', true);
			if (!empty($accounts) and isset($accounts[$this->service])) {
				$this->accounts = $accounts[$this->service];
			}
		}
	}

	/**
	 * Sets the service accounts. Returns all of the service's accounts.
	 *
	 * @param  array  $accounts
	 * @return array|IService
	 */
	public function accounts(array $accounts = null) {
		if ($accounts === null) {
			return $this->accounts;
		}

		$this->accounts = $accounts;
		return $this;
	}

	/**
	 * Adds an account to the service. Returns an account by ID.
	 *
	 * @param  int|object  $account
	 * @return array|bool|IService
	 */
	public function account($account) {
		if (is_int($account) or is_string($account)) {
			return (isset($this->accounts[$account]) ? $this->accounts[$account] : false);
		}

		$this->accounts[$account->user->id] = $account;
		return $this;
	}

	/**
	 * Returns the UI-friendly version of the service.
	 *
	 * @return string
	 */
	public function title() {
		$title = $this->title;
		if (empty($title)) {
			$title = ucwords(str_replace('_', ' ', $this->service));
		}

		return $title;
	}

	/**
	 * Checks to see if the WP_User object is loaded.
	 *
	 * @return bool
	 */
	public function loaded() {
		return ($this->user !== false and $this->user->ID) ? true : false;
	}

	/**
	 * Disconnects an account from the user's account.
	 *
	 * @param  int  $id
	 * @return void
	 */
	public function disconnect($id) {
		$accounts = get_user_meta($this->user->ID, Social::$prefix.'accounts', true);;
		if (isset($accounts[$this->service][$id])) {
			unset($accounts[$this->service][$id]);
			update_user_meta($this->user->ID, Social::$prefix.'accounts', $accounts);
		}
	}

	/**
	 * Saves a WP_User object.
	 *
	 * @param  int|object  $account  the account ID
	 * @return void
	 */
	public function save($account) {
		if (is_int($account)) {
			$account = $this->account($account);
		}
		$accounts = get_user_meta(get_current_user_id(), Social::$prefix.'accounts', true);
		$accounts[$this->service][$account->user->id] = $account;
		update_user_meta(get_current_user_id(), Social::$prefix.'accounts', $accounts);
	}

	/**
	 * Performs an API request.
	 *
	 * @param  string      $service  service to use
	 * @param  int|object  $account  account to use
	 * @param  string      $api      API endpoint to request
	 * @param  array       $params   parameters to pass to the API
	 * @param  string      $method   GET|POST, default: GET
	 * @return mixed
	 */
	public function do_request($service, $account, $api, array $params = array(), $method = 'GET') {
		if (!is_object($account)) {
			$account = $this->account($account);
		}
		return Social_Helper::request($service, $api, $account->keys->public, $account->keys->secret, $params, $method);
	}

	/**
	 * Returns the disconnect URL.
	 *
	 * @static
	 * @param  object  $account
	 * @param  bool    $is_admin
	 * @param  string  $before
	 * @param  string  $after
	 * @return string
	 */
	public function disconnect_url($account, $is_admin = false, $before = '', $after = '') {
		$params = array(
			Social::$prefix.'disconnect' => 'true',
			'id' => $account->user->id,
			'service' => $this->service
		);
		if ($is_admin) {
			$url = Social::settings_url($params);
			$text = '<img src="'.plugins_url('/assets/delete.png', SOCIAL_FILE).'" alt="'.__('Disconnect', Social::$i10n).'" />';
		}
		else {
			$path = array();
			foreach ($params as $key => $value) {
				$path[] = $key.'='.urlencode($value);
			}
			$url = site_url('?'.implode('&', $path));
			$text = 'Disconnect';
		}

		return sprintf('%s<a href="%s">%s</a>%s', $before, $url, $text, $after);
	}

} // End Social_Service
