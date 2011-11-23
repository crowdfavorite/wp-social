<?php
/**
 * Semaphore Lock Management
 *
 * @package Social
 */
final class Social_Semaphore {

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return Social_Semaphore
	 */
	public static function factory() {
		return new self;
	}

	/**
	 * @var bool
	 */
	protected $lock_broke = false;

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb;

		// Attempt to set the lock
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'social_locked'
			 WHERE option_name = 'social_unlocked'
		");

		if ($affected == '0' and !$this->stuck_check()) {
			Social::log('Semaphore lock failed. (Line '.__LINE__.')');
			return false;
		}

		// Check to see if all processes are complete
		$affected = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = 'social_semaphore'
			   AND option_value = '0'
		");
		if ($affected != '1') {
			if (!$this->stuck_check()) {
				Social::log('Semaphore lock failed. (Line '.__LINE__.')');
				return false;
			}

			// Reset the semaphore to 1
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = '1'
				 WHERE option_name = 'social_semaphore'
			");

			Social::log('Semaphore reset to 1.');
		}

		// Set the lock time
		$wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'social_last_lock_time'
		", current_time('mysql', 1)));
		Social::log('Set semaphore last lock time to '.current_time('mysql', 1));

		Social::log('Semaphore lock complete.');
		return true;
	}

	/**
	 * Increment the semaphore.
	 *
	 * @param  array  $filters
	 * @return Social_Semaphore
	 */
	public function increment(array $filters = array()) {
		global $wpdb;

		if (count($filters)) {
			// Loop through all of the filters and increment the semaphore
			foreach ($filters as $priority) {
				for ($i = 0, $j = count($priority); $i < $j; ++$i) {
					$this->increment();
				}
			}
		}
		else {
			$wpdb->query("
				UPDATE $wpdb->options
				   SET option_value = CAST(option_value AS UNSIGNED) + 1
				 WHERE option_name = 'social_semaphore'
			");
			Social::log('Incremented the semaphore by 1.');
		}

		return $this;
	}

	/**
	 * Decrements the semaphore.
	 *
	 * @return void
	 */
	public function decrement() {
		global $wpdb;

		$wpdb->query("
			UPDATE $wpdb->options
			   SET option_value = CAST(option_value AS UNSIGNED) - 1
			 WHERE option_name = 'social_semaphore'
			   AND CAST(option_value AS UNSIGNED) > 0
		");
		Social::log('Decremented the semaphore by 1.');
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb;

		// Decrement for the master process.
		$this->decrement();

		$result = $wpdb->query("
			UPDATE $wpdb->options
			   SET option_name = 'social_unlocked'
			 WHERE option_name = 'social_locked'
		");

		if ($result == '1') {
			Social::log('Semaphore unlocked.');
			return true;
		}

		Social::log('Semaphore still locked.');
		return false;
	}

	/**
	 * Attempts to jiggle the stuck lock loose.
	 *
	 * @return bool
	 */
	private function stuck_check() {
		global $wpdb;

		// Check to see if we already broke the lock.
		if ($this->lock_broke) {
			return true;
		}

		$current_time = current_time('mysql', 1);
		$affected = $wpdb->query($wpdb->prepare("
			UPDATE $wpdb->options
			   SET option_value = %s
			 WHERE option_name = 'social_last_lock_time'
			   AND option_value <= DATE_SUB(%s, INTERVAL 30 MINUTE)
		", $current_time, $current_time));

		if ($affected == '1') {
			Social::log('Semaphore was stuck, set lock time to '.$current_time);

			$this->lock_broke = true;
			return true;
		}

		return false;
	}

} // End Social_Semaphore
