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

	}

	/**
	 * Gets the name of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function name() {

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
		// TODO: Implement avatar() method.
	}

} // End Social_Service_Facebook_Account