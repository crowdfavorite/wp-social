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
			$file = SOCIAL_PATH.'debug_log.txt';

			// Attempt to create the file if it doesn't exist.
			if (is_writable(SOCIAL_PATH)) {
				try {
					$fh = fopen($file, 'a');
					fclose($fh);
				}
				catch (Exception $e) {
					// Failed to create the file, oh well...
					$file = null;
				}
			}
		}
		return new Social_Log($file);
	}

	/**
	 * @var  string  log file
	 */
	private $_file = '';

	/**
	 * Sets the log pile path.
	 *
	 * @param  string  $file
	 */
	public function __construct($file = null) {
		$this->_file = $file;
	}

	/**
	 * Adds a message to the error log.
	 *
	 * @param  string  $message  message to be logged
	 * @param  array   $args     arguments to add to the message.
	 * @param  string  $context  context of the log message
	 * @return void
	 */
	public function write($message, array $args = null, $context = null) {
		if (!Social::option('debug') and !in_array($context, apply_filters('social_log_contexts', array()))) {
			return;
		}

		if ($args !== null) {
			foreach ($args as $key => $value) {
				$message = str_replace(':'.$key, $value, $message);
			}
		}

		if ($context !== null) {
			$context = '['.strtoupper(str_replace('-', ' ', $context)).'] ';
		}

		$error_str = $context.'[SOCIAL - '.current_time('mysql').' - '.$_SERVER['REMOTE_ADDR'].'] '.$message;

		if (is_writable($this->_file)) {
			error_log($error_str."\n", 3, $this->_file);
		}
		else {
			error_log($error_str);
		}
	}

} // End Social_Log
