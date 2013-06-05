<?php
/**
 * Core for Social accounts.
 *
 * @package Social
 * @subpackage services
 */
abstract class Social_Service_Account {

	/**
	 * @var  object  user object
	 */
	protected $_user = array();

	/**
	 * @var  object  access keys
	 */
	protected $_keys = array();

	/**
	 * @var  bool  personal account flag
	 */
	protected $_personal = false;

	/**
	 * @var  bool  universal account flag
	 */
	protected $_universal = false;

	/**
	 * Populates the account object.
	 *
	 * @param  object  $account
	 */
	public function __construct($account) {
		$this->_user = $account->user;

		if (isset($account->keys)) {
			$this->_keys = $account->keys;
		}

		if (isset($account->personal)) {
			$this->_personal = $account->personal;
		}

		if (isset($account->universal)) {
			$this->_universal = $account->universal;
		}
	}

	/**
	 * Returns an array object of the account.
	 *
	 * @return object
	 */
	public function as_object() {
		return (object) array(
			'user' => $this->_user,
			'keys' => $this->_keys,
			'personal' => $this->_personal,
			'universal' => $this->_universal,
		);
	}

	/**
	 * Returns whether the account is public or not.
	 *
	 * @abstract
	 * @param  bool|null  $personal
	 * @return Social_Service_Account|bool
	 */
	public function personal($personal = null) {
		if ($personal === null) {
			return $this->_personal;
		}

		$this->_personal = $personal;
		return $this;
	}

	/**
	 * Returns whether the account is universal or not.
	 *
	 * @abstract
	 * @param  bool|null  $universal
	 * @return Social_Service_Account|bool
	 */
	public function universal($universal = null) {
		if ($universal === null) {
			return $this->_universal;
		}

		$this->_universal = $universal;
		return $this;
	}

	/**
	 * Returns the account's public key.
	 *
	 * @return string
	 */
	public function public_key() {
		return $this->_keys->public;
	}

	/**
	 * Returns the account's private key.
	 *
	 * @return string
	 */
	public function private_key() {
		return $this->_keys->secret;
	}

	/**
	 * Does this account have a user object with it?
	 *
	 * [!!] This is for pre-2.0 accounts. Used to help keep views clean.
	 *
	 * @return bool
	 */
	public function has_user() {
		return $this->_user !== null;
	}

	/**
	 * Default avatar.
	 *
	 * @return string
	 */
	public function _avatar() {
		return 'https://www.gravatar.com/avatar/a06082e4f876182b547f635d945e744e?s=32&d=mm';
	}

	/**
	 * Default name
	 *
	 * @return string
	 */
	public function _name() {
		return __('Removed Account', 'social');
	}

	/**
	 * Default username
	 *
	 * @return string
	 */
	public function _username() {
		return __('Removed Account', 'social');
	}
	
	/**
	 * Return child accounts (Facebook pages, for example)
	 *
	 * @return array
	 */
	public function child_accounts($update = false) {
		if ($update) {
			$this->fetch_child_accounts();
		}
		return array();
	}

	/**
	 * Get child account list from service.
	 *
	 * @return void
	 */
	public function fetch_child_accounts() {
	}

	/**
	 * Child account key.
	 *
	 * @return string
	 */
	public function child_account_key() {
		return '';
	}

	/**
	 * Child account avatar.
	 *
	 * @return string
	 */
	public function child_account_avatar() {
		return $this->_avatar();
	}

	/**
	 * Update child accounts of a service account
	 * @abstract
	 * @param array $enabled_child_ids - array of enabled child account ids
	 */
	abstract public function update_enabled_child_accounts($enabled_child_ids);

} // End Social_Service_Account
