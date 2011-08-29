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
			$url = admin_url('profile.php#social-networks');
		}
		else {
			$url = (is_admin() ? admin_url('options-general.php?page=social.php') : site_url('?authorized=true&p='.$post->ID));
		}

		return apply_filters('social_authorize_url', Social::$api_url.$this->_key.'/authorize?redirect_to='.urlencode($url), $this->_key);
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
	 * @param  int  $id  account id
	 * @return bool
	 */
	public function account($id) {
		if ($this->account_exists($id)) {
			return $this->_accounts[$id];
		}

		return false;
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
			$class = get_class($this).'_Account';
			$account = new $class($account);
			if (!$this->account_exists($account->id())) {
				$this->_accounts[$account->id()] = $account;
			}
		}
		return $this;
	}

} // End Social_Service
