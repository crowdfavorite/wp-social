<?php
/**
 * @package Social
 * @subpackage interfaces
 */
interface Social_Interface_Service_Account {

	/**
	 * @abstract
	 * @return string
	 */
	function id();

	/**
	 * @abstract
	 * @return string
	 */
	function name();

	/**
	 * @abstract
	 * @return string
	 */
	function link();

	/**
	 * @abstract
	 * @return string
	 */
	function avatar();

} // End Social_Interface_Service_Account