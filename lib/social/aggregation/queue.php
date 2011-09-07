<?php
/**
 * Social's Aggregation Queue
 *
 * @package Social
 * @subpackage aggregation
 */
final class Social_Aggregation_Queue {

	/**
	 * Initializes the queue.
	 *
	 * @static
	 * @return Social_Aggregation_Queue
	 */
	public static function factory() {
		return new Social_Aggregation_Queue;
	}

	/**
	 * @var  array  queue of posts
	 */
	private $_queue = array();

	/**
	 * Populates Social_Aggregation_Queue with the queue from the database.
	 */
	public function __construct() {
		$queue = Social::instance()->option('aggregation_queue');
		if (!empty($queue)) {
			$this->_queue = $queue;
		}
	}

	/**
	 * Returns an array of queue items that can be run.
	 *
	 * @return array
	 */
	public function runnable() {
		$queue = array();
		$current_timestamp = current_time('timestamp');
		foreach ($this->_queue as $timestamp => $posts) {
			if ($timestamp <= $current_timestamp) {
				$queue[$timestamp] = $posts;
			}
		}

		return $queue;
	}

	/**
	 * Adds a post to the queue based on interval, if it's not already set.
	 *
	 * @param  int     $post_id   post id
	 * @param  string  $interval  schedule key
	 * @return Social_Aggregation_Queue
	 */
	public function add($post_id, $interval = null) {
		// Find the next interval to schedule
		$next_run = 0;
		if ($interval === null) {
			foreach ($this->schedule() as $interval => $next_run) {
				break;
			}
		}
		else {
			$schedule = $this->schedule();
			$found = false;
			foreach ($schedule as $key => $timestamp) {
				if (!$found) {
					if ($key == $interval) {
						$found = true;
						continue;
					}
				}
				else {
					$next_run = $timestamp;
				}
			}

			if (!$found) {
				$this->remove($post_id);
				return $this;
			}
		}

		if ($next_run) {
			if (!isset($this->_queue[$next_run])) {
				$this->_queue[$next_run] = array();
			}

			if (!isset($this->_queue[$next_run][$post_id])) {
				$this->_queue[$next_run][$post_id] = $interval;
				update_post_meta($post_id, '_social_aggregation_next_run', $next_run);
			}
		}

		return $this;
	}

	/**
	 * Removes a post from the queue completely, or by timestamp.
	 *
	 * @param  int  $post_id    post id
	 * @param  int  $timestamp  (optional) timestamp to remove by
	 * @return Social_Aggregation_Queue
	 */
	public function remove($post_id, $timestamp = null) {
		if ($timestamp === null) {
			$queue = array();
			foreach ($this->_queue as $timestamp => $posts) {
				foreach ($posts as $id => $interval) {
					if ($id !== $post_id) {
						if (!isset($queue[$timestamp])) {
							$queue[$timestamp] = array();
						}

						$queue[$timestamp][$id] = $interval;
					}
				}
			}
			$this->_queue = $queue;
		}
		else {
			if (isset($this->_queue[$timestamp]) and isset($this->_queue[$timestamp][$post_id])) {
				unset($this->_queue[$timestamp][$post_id]);

				if (empty($this->_queue[$timestamp])) {
					unset($this->_queue[$timestamp]);
				}
			}
		}

		delete_post_meta($post_id, '_social_aggregation_next_run');

		return $this;
	}

	/**
	 * Saves the queue.
	 *
	 * @return void
	 */
	public function save() {
		Social::instance()->option('aggregation_queue', $this->_queue, true);
	}

	/**
	 * Returns a filterable list of schedules and their timestamp.
	 *
	 * @return mixed|void
	 */
	protected function schedule() {
		$current_time = current_time('timestamp');
		return apply_filters('social_aggregation_schedule', array(
			'15min' => $current_time + 54000,
			'30min' => $current_time + 108000,
			'45min' => $current_time + 162000,
		));
	}

} // End Social_Aggregation_Queue
