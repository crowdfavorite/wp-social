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
		$this->_keys = $account->keys;
		$this->_personal = $account->personal;
		$this->_universal = $account->universal;
	}

	/**
	 * Returns an array object of the account.
	 *
	 * @return object
	 */
	public function as_array() {
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
		return $this->_keys->private;
	}

} // End Social_Service_Account
