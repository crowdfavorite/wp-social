<?php
/**
 * Aggregation Logger
 *
 * @package Social
 * @subpackage aggregation
 */
final class Social_Aggregation_Log {

	/**
	 * @var  array  array of singleton instances
	 */
	private static $instances = array();

	/**
	 * Initializes the aggregation logger for the defined post ID.
	 *
	 * [!!!] If the post ID is invalid an Exception will be thrown.
	 *
	 * @static
	 * @param  int  $post_id
	 * @return Social_Aggregation_Log
	 */
	public static function instance($post_id) {
		if (!isset(self::$instances[$post_id])) {
			self::$instances[$post_id] = new self($post_id);
		}
		return self::$instances[$post_id];
	}

	/**
	 * @var  array  array of log items
	 */
	private $_log = array();

	/**
	 * @var  int  log timestamp
	 */
	private $_timestamp = 0;

	/**
	 * @var  int  post ID
	 */
	private $_post_id = 0;

	/**
	 * Loads the current log for the defined post ID.
	 *
	 * [!] For 1.0.2 we changed the logger to have a manual flag for each pull. This will
	 *     apply the update if the loaded log is from older versions of the plugin.
	 *
	 * @param  int  $post_id
	 */
	public function __construct($post_id) {
		$this->_post_id = $post_id;
		$this->_timestamp = current_time('timestamp', 1);

		$post = get_post($this->_post_id);
		if ($post === null) {
			throw new Exception(sprintf(__('Social failed to initialize the Aggregation_Log for post #%s.', 'social'), $this->_post_id));
		}

		// Load the current log for the post
		$this->_log = get_post_meta($post_id, '_social_aggregation_log', true);

		if (!isset($this->_log[$this->_timestamp])) {
			$this->_log[$this->_timestamp] = (object) array(
				'manual' => false,
				'items' => array(),
			);
		}
	}

	/**
	 * Hook caught by echoing Social_Aggregate_Log.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}

	/**
	 * Adds an item to the log.
	 *
	 * Log format:
	 *
	 *     array(
	 *         '1234567890' => (object) array( // current_time('timestamp')
	 *             'manual' => false, // Can be "true" or "false"
	 *             'items' => array(
	 *                 'twitter' => array(
	 *                     (object) array(
	 *                         'id' => '1234567890', // Broadcasted ID
	 *                         'type' => 'url', // url|reply|retweet or whatever you want to group this item by
	 *                         'ignored' => false, // Can be "true" or "false"
	 *                         'data' => array(), // Extra data you want to use when displaying this item in the View
	 *                     ),
	 *                     // ... More items
	 *                 ),
	 *                 // ... Other registered services in aggregation
	 *             )
	 *         )
	 *     )
	 *
	 * @param  string  $service  service key (twitter, facebook, etc.)
	 * @param  string  $id       object id
	 * @param  string  $type     type of response (reply, retweet, url)
	 * @param  bool    $ignored  comment ignored?
	 * @param  array   $data     extra data for output
	 * @return Social_Aggregation_Log
	 */
	public function add($service, $id, $type, $ignored = false, array $data = null) {
		if (!isset($this->_log[$this->_timestamp]->items[$service])) {
			$this->_log[$this->_timestamp]->items[$service] = array();
		}

		foreach ($this->_log[$this->_timestamp]->items[$service] as $item) {
			if ($item->id === $id) {
				// Bail! Item already exists.
				return $this;
			}
		}

		$this->_log[$this->_timestamp]->items[$service][] = (object) array(
			'id' => $id,
			'type' => $type,
			'ignored' => $ignored,
			'data' => $data,
		);

		return $this;
	}

	/**
	 * Force an item to be flagged as ignored.
	 *
	 * @param  int  $id
	 * @return $this
	 */
	public function ignore($id) {
		foreach ($this->_log[$this->_timestamp]->items as $service => $items) {
			foreach ($items as $key => $item) {
				if ($item->id == $id) {
					$item->ignored = true;
					$this->_log[$this->_timestamp]->items[$service][$key] = $item;

					return $this;
				}
			}
		}

		return $this;
	}

	/**
	 * Saves the log.
	 *
	 * @param  bool  $manual
	 * @return void
	 */
	public function save($manual = false) {
		$this->_log[$this->_timestamp]->manual = $manual;
		update_post_meta($this->_post_id, '_social_aggregation_log', $this->_log);
	}

	/**
	 * Returns the current log.
	 *
	 * @return array
	 */
	public function current() {
		return isset($this->_log[$this->_timestamp]) ? $this->_log[$this->_timestamp] : array();
	}

	/**
	 * Renders the log to HTML.
	 *
	 * @return Social_View
	 */
	public function render() {
		return Social_View::factory('wp-admin/post/meta/log/output', array(
			'log' => $this->_log,
			'services' => Social::instance()->services(),
		))->render();
	}

} // End Social_Aggregation_Log
