<?php
/**
 * @package Social
 * @subpackge services
 */
final class Social_Service_Facebook_Account extends Social_Service_Account implements Social_Interface_Service_Account {

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