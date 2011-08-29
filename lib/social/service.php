<?php
/**
 * @package Social
 * @subpackage services
 */
abstract class Social_Service {

	/**
	 * @var  string  service key
	 */
	protected $_key = '';

	/**
	 * @var  array  collection of account objects
	 */
	protected $_accounts = array();

	/**
	 * Instantiates the
	 *
	 * @param  array  $accounts
	 */
	public function __construct(array $accounts = array()) {
		$this->accounts($accounts);
	}

	/**
	 * Gets the title for the service.
	 *
	 * @return string
	 */
	public function title() {
		return ucwords(str_replace('_', ' ', $this->_key));
	}

	/**
	 * Builds the authorize URL for the service.
	 *
	 * @return string
	 */
	public function authorize_url() {
		global $post;

		if (defined('IS_PROFILE_PAGE')) {
			$url = admin_url('profile.php?social_controller=connect&social_action=authorized#social-networks');
		}
		else if (is_admin()) {
			$url = admin_url('options-general.php?page=social.php&social_controller=connect&social_action=authorized');
		}
		else {
			$url = site_url('?social_controller=connect&social_action=authorized&p='.$post->ID);
		}

		return apply_filters('social_authorize_url', Social::$api_url.$this->_key.'/authorize?redirect_to='.urlencode($url), $this->_key);
	}

	/**
	 * Creates a WordPress user with the passed in account.
	 *
	 * @param  Social_Service_Account  $account
	 * @return void
	 */
	public function create_user($account) {
		$user = get_userdatabylogin($this->_key.'_'.$account->username());
		if ($user === false) {
			$id = wp_create_user($this->_key.'_'.$account->username(), wp_generate_password(20, false), $this->_key.'.'.$account->username.'@example.com');

			$role = 'subscriber';
			if (get_option('users_can_register') == '1') {
				$role = get_option('default_role');
			}

			$user = new WP_User($id);
			$user->set_role($role);
			$user->show_admin_bar_front = 'false';
			wp_update_user(get_object_vars($user));
		}
		else {
			$id = $user->ID;
		}

		// Log the user in
		wp_set_current_user($id);
		add_filter('auth_cookie_expiration', array($this, 'auth_cookie_expiration'));
		wp_set_auth_cookie($id, true);
		remove_filter('auth_cookie_expiration', array($this, 'auth_cookie_expiration'));

		return $id;
	}

	/**
	 * Auth cookie expriation
	 *
	 * @param  int  $expiration
	 * @return int
	 */
	public function auth_cookie_expiration($expiration = 31536000) {
		return 31536000;
	}

	/**
	 * Saves the accounts on the service.
	 *
	 * @return void
	 */
	public function save() {
		$accounts = array();
		if (!is_admin() or defined('IS_PROFILE_PAGE')) {
			foreach ($this->_accounts AS $account) {
				if ($account->is_personal()) {
					$accounts[] = $account;
				}
			}

			if (count($accounts)) {
				$current = get_user_meta(get_current_user_id(), 'social_accounts', true);
				$current[$this->_key] = $accounts;
				update_user_meta(get_current_user_id(), 'social_accounts', $current);
			}
			else {
				delete_user_meta(get_current_user_id(), 'social_accounts');
			}
		}
		else {
			foreach ($this->_accounts AS $account) {
				if ($account->is_global()) {
					$accounts[$account->id()] = $account;
				}
			}

			if (count($accounts)) {
				$current = get_option('social_accounts', array());
				$current[$this->_key] = $accounts;
				update_option('social_accounts', $current);
			}
			else {
				delete_option('social_accounts');
			}
		}
	}

	/**
	 * Checks to see if the account exists on the object.
	 *
	 * @param  int  $id  account id
	 * @return bool
	 */
	public function account_exists($id) {
		return isset($this->_accounts[$id]);
	}

	/**
	 * Gets the requested account.
	 *
	 * @param  int|Social_Service_Account  $account  account id/object
	 * @return Social_Service_Account|Social_Service
	 */
	public function account($account) {
		if ($account instanceof Social_Service_Account) {
			$this->_accounts[$account->id()] = $account;
		}
		else if ($this->account_exists($account)) {
			return $this->_accounts[$account];
		}

		return $this;
	}

	/**
	 * Acts as a getter and setter for service accounts.
	 *
	 * @param  array  $accounts  accounts to add to the service
	 * @return array|Social_Service
	 */
	public function accounts(array $accounts = null) {
		if ($accounts === null) {
			return $this->_accounts;
		}

		foreach ($accounts as $account) {
			if (!$this->account_exists($account->id())) {
				$this->_accounts[$account->id()] = $account;
			}
		}
		return $this;
	}

} // End Social_Service
