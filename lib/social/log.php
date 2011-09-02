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
	 * @param  array   $args     arguments to add to the message.
	 * @return void
	 */
	public function write($message, array $args = null) {
		if (!Social::option('debug')) {
			return;
		}

		if ($args !== null) {
			foreach ($args as $key => $value) {
				$message = str_replace(':'.$key, $value, $message);
			}
		}

		error_log('[SOCIAL] '.$message);
	}

} // End Social_Log
