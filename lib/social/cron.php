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
	 * Sets the CRON lock directory.
	 *
	 * @param  string  $key
	 */
	public function __construct($key) {
		// CRON Lock Location
		if (!is_writable(SOCIAL_PATH)) {
			$upload_dir = wp_upload_dir();
			if (is_writable($upload_dir['basedir'])) {
				$this->_cron_lock_dir = $upload_dir['basedir'];
			}
			else if (isset($_GET['page']) and $_GET['page'] == 'social.php') {
				add_action('admin_notices', array($this, 'display_cron_lock_write_error'));
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
		echo '<div class="error"><p>'.$message.'</p></div>';
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
		// TODO Social_CRON::execute()
	}

	/**
	 * Creates the file lock.
	 *
	 * @param  string  $cron
	 * @return bool
	 */
	private function cron_lock($cron) {
		$locked = false;
		$file = trailingslashit($this->cron_lock_dir).$cron.'.txt';

		$timestamp = 0;
		if (is_file($file)) {
			$timestamp = file_get_contents($file);
		}

		$fp = fopen($file, 'w+');
		if (flock($fp, LOCK_EX)) {
			$locked = true;
			fwrite($fp, time());
		}
		else if (!empty($timestamp) and time() - $timestamp >= 3600) {
			$locked = true;
			$this->cron_unlock($cron);
		}

		fclose($fp);

		if (Social::option('debug') == '1') {
			$this->log('CRON '.$cron.' LOCK COMPLETE.');
		}

		return $locked;
	}

	/**
	 * Unlocks the file.
	 *
	 * @param  string  $cron
	 * @return bool
	 */
	private function cron_unlock($cron) {
		$file = trailingslashit($this->cron_lock_dir).$cron.'.txt';
		$fp = fopen($file, 'r+');
		ftruncate($fp, 0);
		flock($fp, LOCK_UN);
		fclose($fp);

		if (Social::option('debug') == '1') {
			$this->log('CRON '.$cron.' UNLOCK COMPLETE.');
		}
	}

} // End Social_CRON
