<?php
/**
 * @package Social
 * @subpackage interfaces
 */
interface Social_Interface_Service_Account {

	/**
	 * Returns an array object of the account.
	 *
	 * @return object
	 */
	function as_object();

	/**
	 * Returns whether the account is public or not.
	 *
	 * @abstract
	 * @param  bool|null  $personal
	 * @return Social_Service_Account|bool
	 */
	function personal($personal = null);

	/**
	 * Returns whether the account is universal or not.
	 *
	 * @abstract
	 * @param  bool|null  $universal
	 * @return Socail_Service_Account|bool
	 */
	function universal($universal = null);

	/**
	 * Gets the ID of the account.
	 *
	 * @abstract
	 * @return string
	 */
	function id();

	/**
	 * Gets the name of the account.
	 *
	 * @abstract
	 * @return string
	 */
	function name();

	/**
	 * Gets the URL of the account.
	 * 
	 * @abstract
	 * @return string
	 */
	function url();

	/**
	 * Gets the avatar of the account.
	 *
	 * @abstract
	 * @return string
	 */
	function avatar();

	/**
	 * Gets the username of the account.
	 *
	 * @abstract
	 * @return string
	 */
	function username();

	/**
	 * Returns the account's public key.
	 *
	 * @abstract
	 * @return string
	 */
	function public_key();

	/**
	 * Returns the account's private key.
	 *
	 * @abstract
	 * @return string
	 */
	function private_key();

} // End Social_Interface_Service_Account
