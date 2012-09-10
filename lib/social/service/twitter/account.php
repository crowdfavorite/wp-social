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
		if ($this->has_user()) {
			return $this->_user->screen_name;
		}

		return parent::_name();
	}

	/**
	 * Gets the URL of the account.
	 *
	 * @return string
	 */
	public function url() {
		$url = 'http://twitter.com/';
		if ($this->has_user()) {
			$url .= $this->_user->screen_name;
		}

		return $url;
	}

	/**
	 * Gets the avatar of the account.
	 *
	 * @return string
	 */
	public function avatar() {
		if ($this->has_user()) {
			return $this->_user->profile_image_url;
		}

		return parent::_avatar();
	}

	/**
	 * Gets the username of the account.
	 *
	 * @return string
	 */
	public function username() {
		if ($this->has_user()) {
			return $this->_user->screen_name;
		}

		return parent::_username();
	}

	/**
	 * Update the enabled child accounts
	 * ( Currently not in use by twitter )
	 *
	 * @param array $enabled_child_ids Array of enabled child account ids
	 */
	public function update_enabled_child_accounts($enabled_child_ids) {
	}

} // End Social_Service_Twitter_Account
