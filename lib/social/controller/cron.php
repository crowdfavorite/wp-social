<?php
/**
 * CRON Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_CRON extends Social_Controller {

	/**
	 * Handles the CRON 15 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_15() {
		if (!wp_verify_nonce($this->request->query('_wpnonce'))) {
			wp_die('Oops, please try again.');
		}

		Social::log('CRON 15: Initiated.');
		if ($this->lock()) {
			try {
				Social::log('CRON 15: Lock set.');

				do_action('social_cron_15');

				Social::log('CRON 15: Finished');
			}
			catch (Exception $e) {
				Social::log('CRON 15: Failed.');
				throw $e;
			}

			if ($this->unlock()) {
				Social::log('CRON 15: Lock removed.');
			}
		}
		else {
			Social::log('CRON 15: Failed.');
		}
	}

	/**
	 * Handles the CRON 60 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_60() {
		if (!wp_verify_nonce($this->request->query('_wpnonce'))) {
			wp_die('Oops, please try again.');
		}

		Social::log('CRON 60: Initiated.');
		if ($this->lock()) {
			try {
				Social::log('CRON 60: Lock set.');

				do_action('social_cron_60');

				Social::log('CRON 60: Finished');
			}
			catch (Exception $e) {
				Social::log('CRON 60: Failed.');
				throw $e;
			}

			if ($this->unlock()) {
				Social::log('CRON 60: Lock removed.');
			}
		}
		else {
			Social::log('CRON 60: Failed.');
		}
	}

	/**
	 * Makes sure Social CRONs are not scheduled more than once.
	 *
	 * @return void
	 */
	public function action_check_crons() {
		if (!wp_verify_nonce($this->request->query('_wpnonce'))) {
			wp_die('Oops, please try again.');
		}

		$crons = _get_cron_array();
		$social_crons = array(
			'15' => false,
			'60' => false
		);
		foreach ($crons as $timestamp => $_crons) {
			foreach ($_crons as $key => $cron) {
				foreach ($social_crons as $cron_key => $status) {
					$event_key = 'social_cron_'.$cron_key.'_core';
					if ($key == $event_key and $social_crons[$cron_key]) {
						wp_unschedule_event($timestamp, $event_key);
						Social::log('Unscheduled extra event: '.$event_key);
					}
					else {
						$social_crons[$cron_key] = true;
					}
				}
			}
		}
	}

	/**
	 * Creates the CRON lock.
	 *
	 * @return bool
	 */
	private function lock() {
		$locked = false;
		$file = trailingslashit(Social::$cron_lock_dir).$this->request->action().'.txt';

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
			Social::log('Failed to set lock for '.$this->request->action());
		}

		return $locked;
	}

	/**
	 * Removes the CRON lock.
	 *
	 * @return bool
	 */
	private function unlock() {
		$unlocked = false;
		$file = trailingslashit(Social::$cron_lock_dir).$this->request->action().'.txt';

		try {
			$fp = fopen($file, 'r+');
			flock($fp, LOCK_UN);
			ftruncate($fp, 0);
			fclose($fp);

			$unlocked = true;
		}
		catch (Exception $e) {
			Social::log('Failed to unlock lock for '.$this->request->action());
		}

		return $unlocked;
	}

} // End Social_Controller_CRON
