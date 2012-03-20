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
			$file = Social::$plugins_path.'debug_log.txt';

			// Attempt to create the file if it doesn't exist.
			if (is_writable(Social::$plugins_path)) {
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
	 * Add a message to the log.
	 *
	 * @static
	 * @param  string  $message    message to add to the log
	 * @param  array   $args       arguments to pass to the writer
	 * @param  string  $context    context of the log message
	 * @param  bool    $backtrace  show the backtrace
	 * @return void
	 */
	public function write($message, array $args = null, $context = null, $backtrace = false) {
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

		$error_str = $context.'[SOCIAL - '.current_time('mysql', 1).' - '.$_SERVER['REMOTE_ADDR'].'] '.$message;

		if ($backtrace) {
			ob_start();
			debug_print_backtrace();
			$trace = ob_get_contents();
			ob_end_clean();

			$error_str .= "\n\n".$trace."\n\n";
		}

		if (is_writable($this->_file)) {
			error_log($error_str."\n", 3, $this->_file);
		}
		else {
			error_log($error_str);
		}
	}

} // End Social_Log
