<?php
/**
 * @package Social
 * @subpackage interfaces
 */
interface Social_Interface_Service_Account {

	/**
	 * Returns whether the account is public or not.
	 *
	 * @abstract
	 * @return bool
	 */
	function is_personal();

	/**
	 * Returns whether the account is global or not.
	 *
	 * @abstract
	 * @return bool
	 */
	function is_global();

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

} // End Social_Interface_Service_Account