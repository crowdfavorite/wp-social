<?php
/**
 * CRON
 *
 * Handles the process of running CRONs.
 *
 * @package Social
 */
final class Social_CRON {

	/**
	 * @var  array  Social_CRON singleton objects.
	 */
	public static $instances = array();

	/**
	 * Loads the requested singleton instance.
	 *
	 * @param string  $key
	 * @return Social_CRON
	 */
	public static function instance($key) {
		if (!isset(self::$instances[$key])) {
			self::$instances[$key] = new Social_CRON($key);
		}

		return self::$instances[$key];
	}

	/**
	 * @var  string  CRON lock directory
	 */
	protected $_cron_lock_dir = SOCIAL_PATH;

	/**
	 * @var  string  CRON key
	 */
	protected $_key = '';

	/**
	 * @var  bool  enabled flag
	 */
	protected $_enabled = true;

	/**
	 * Sets the CRON lock directory.
	 *
	 * @param  string  $key
	 */
	public function __construct($key) {
		// CRON Lock Location
		if (!is_writable($this->_cron_lock_dir)) {
			$upload_dir = wp_upload_dir();
			if (is_writable($upload_dir['basedir'])) {
				$this->_cron_lock_dir = $upload_dir['basedir'];
			}
			else if (isset($_GET['page']) and $_GET['page'] == 'social.php') {
				add_action('admin_notices', array($this, 'display_cron_lock_write_error'));
			}

			if (!is_writable($this->_cron_lock_dir)) {
				$this->_enabled = false;
				Social::option('cron_lock_error', true);
			}
		}

		$this->_key = $key;
	}

	/**
	 * Displays the CRON lock directory error.
	 *
	 * @return void
	 */
	public function display_cron_lock_write_error() {
		$upload_dir = wp_upload_dir();
		if (isset($upload_dir['basedir'])) {
			$message = sprintf(__('Social requires that either %s or %s be writable for CRON jobs.', Social::$i18n), SOCIAL_PATH, $upload_dir['basedir']);
		}
		else {
			$message = sprintf(__('Social requires that %s is writable for CRON jobs.', Social::$i18n), SOCIAL_PATH);
		}
		echo '<div class="error"><p>'.esc_html($message).'</p></div>';
	}

	/**
	 * Gets the CRON key.
	 *
	 * @return string
	 */
	public function key() {
		return $this->_key;
	}

	/**
	 * Executes the CRON job.
	 *
	 * @return void
	 */
	public function execute() {
		$prefix = strtoupper(str_replace('_', ' ', $this->_key)).': ';

		if (!$this->_enabled) {
			Social::log($prefix.'Failed to write lock.');
			return;
		}

		Social::log($prefix.'Initiated.');
		if ($this->lock()) {
			try {
				Social::log($prefix.'Lock set.');

				do_action('social_'.$this->_key);

				Social::log($prefix.'Finished');
			}
			catch (Exception $e) {
				Social::log($prefix.'Failed.');
				throw $e;
			}

			if ($this->unlock()) {
				Social::log($prefix.'Lock removed.');
			}
		}
		else {
			Social::log($prefix.'Failed.');
		}
	}

	/**
	 * Creates the file lock.
	 *
	 * @return bool
	 */
	private function lock() {
		$locked = false;
		$file = trailingslashit(Social::$cron_lock_dir).$this->_key.'.txt';

		$timestamp = 0;
		if (is_file($file)) {
			$timestamp = file_get_contents($file);
		}

		try {
			$fp = fopen($file, 'w+');
			if (flock($fp, LOCK_EX)) {
				$locked = true;
				fwrite($fp, time());
				fclose($fp);
			}
			else if (!empty($timestamp) and time() - $timestamp >= 3600) {
				$this->unlock();
				$this->lock();
			}
		}
		catch (Exception $e) {
			Social::log('Failed to set lock for '.$this->_key);
		}

		return $locked;
	}

	/**
	 * Unlocks the file.
	 *
	 * @return bool
	 */
	private function unlock() {
		$unlocked = false;
		$file = trailingslashit(Social::$cron_lock_dir).$this->_key.'.txt';

		try {
			$fp = fopen($file, 'r+');
			flock($fp, LOCK_UN);
			ftruncate($fp, 0);
			fclose($fp);

			$unlocked = true;
		}
		catch (Exception $e) {
			Social::log('Failed to unlock lock for '.$this->_key);
		}

		return $unlocked;
	}

} // End Social_CRON
