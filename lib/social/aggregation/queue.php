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
	public function runable() {
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
		if ($interval === null) {
			$interval = reset($this->schedule());
		}
		else {
			$schedule = $this->schedule();
			if (($key = array_search($interval, $schedule)) !== false) {
				++$key;
				if (isset($schedule[$key])) {
					$interval = $schedule[$key];
				}
				else {
					// No more scheduled times... Remove the post from the queue.
					$this->remove($post_id);
				}
			}
		}

		$timestamp = current_time('timestamp');
		if (!isset($this->_queue[$timestamp])) {
			$this->_queue[$timestamp] = array();
		}

		if (!isset($this->_queue[$timestamp][$post_id])) {
			$this->_queue[$timestamp][$post_id] = (object) array(
				'interval' => $interval,
				'next_run' => ''
			);
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
				foreach ($posts as $id => $post) {
					if ($id !== $post_id) {
						if (!isset($queue[$timestamp])) {
							$queue[$timestamp] = array();
						}

						$queue[$timestamp][$id] = $post;
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
		// TODO Talk to Alex about timestamps here, or elsewhere?
		return apply_filters('social_aggregation_schedule', array(
			'15min',
			'30min',
			'45min',
		));
	}

} // End Social_Aggregation_Queue
