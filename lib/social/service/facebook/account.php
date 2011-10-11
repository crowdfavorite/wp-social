<?php
/**
 * @package Social
 * @subpackge services
 */
final class Social_Service_Facebook_Account extends Social_Service_Account implements Social_Interface_Service_Account {

	/**
	 * @var  bool  use pages flag
	 */
	protected $_use_pages = false;

	/**
	 * Sets the use pages flag.
	 *
	 * @param  object  $account
	 */
	public function __construct($account) {
		parent::__construct($account);

		if (isset($account->use_pages)) {
			$this->_use_pages = true;
		}
	}

	/**
	 * Returns whether the account uses pages as well.
	 *
	 * @abstract
	 * @param  bool|null  $use_pages
	 * @return Social_Service_Account|bool
	 */
	public function personal($use_pages = null) {
		if ($use_pages === null) {
			return $this->_use_pages;
		}

		$this->_use_pages = $use_pages;
		return $this;
	}

	/**
	 * Gets the ID of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function id() {
		return $this->_user->id;
	}

	/**
	 * Gets the name of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function name() {
		return $this->_user->name;
	}

	/**
	 * Gets the URL of the account.
	 *
	 * @return string
	 */
	public function url() {
		return 'http://facebook.com/profile.php?id='.$this->_user->id;
	}

	/**
	 * Gets the avatar of the account.
	 *
	 * @return string
	 */
	public function avatar() {
		return 'http://graph.facebook.com/'.$this->_user->id.'/picture';
	}

	/**
	 * Gets the username of the account.
	 *
	 * @return string
	 */
	public function username() {
		if (!isset($this->_user->username)) {
			$this->_user->username = $this->_user->name.'.'.$this->_user->id;
		}

		return $this->_user->username;
	}

} // End Social_Service_Facebook_Account
