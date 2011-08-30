<?php
/**
 * Social logger
 *
 * @package Social
 */
final class Social_Log {

	/**
	 * Returns the instance of Social_Log.
	 *
	 * @static
	 * @param  string  $file  file to log to
	 * @return Social_Log
	 */
	public static function factory($file = null) {
		if ($file == null) {
			$file = SOCIAL_PATH.'log.txt';
		}
		return new Social_Log;
	}

	/**
	 * Adds a message to the error log.
	 *
	 * @param  string  $message  message to be logged
	 * @return void
	 */
	public function write($message) {
		if (!Social::option('debug')) {
			return;
		}

		error_log('[SOCIAL] '.$message);
	}

} // End Social_Log
