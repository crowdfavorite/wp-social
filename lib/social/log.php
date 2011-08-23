<?php
/**
 * Social logger
 *
 * @package Social
 */
final class Social_Log {

	/**
	 * @var Social_Log $instance singleton instance
	 */
	public static $instance = null;

	/**
	 * Returns the instance of Social_Log.
	 *
	 * @static
	 * @return Social_Log
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function add() {
		if (!Social::option('debug')) {
			return;
		}

		// TODO Social_Log::add()
	}

	public function delete() {
		// TODO Social_Log::delete()
	}

	public function delete_all() {
		// TODO Social_Log::delete_all()
	}

	public function find() {
		// TODO Social_Log::find()
	}

	public function find_all() {
		// TODO Social_Log::find_all()
	}

} // End Social_Log
