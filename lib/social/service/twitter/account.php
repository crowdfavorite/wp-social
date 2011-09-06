<?php
/**
 * @package Social
 * @subpackge services
 */
final class Social_Service_Twitter_Account extends Social_Service_Account implements Social_Interface_Service_Account {

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
		return 'http://twitter.com/'.$this->_user->screen_name;
	}

	/**
	 * Gets the avatar of the account.
	 *
	 * @return string
	 */
	public function avatar() {
		return $this->_user->profile_image_url;
	}

	/**
	 * Gets the username of the account.
	 *
	 * @return string
	 */
	public function username() {
		return $this->_user->screen_name;
	}

} // End Social_Service_Twitter_Account