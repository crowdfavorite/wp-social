<?php
/**
 * @package Social
 * @subpackage interfaces
 */
interface Social_Interface_Service_Account {

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

} // End Social_Interface_Service_Account