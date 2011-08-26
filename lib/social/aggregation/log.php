<?php
/**
 * Aggregation Logger
 *
 * @package Social
 * @subpackage aggregation
 */
final class Social_Aggregation_Log {

	/**
	 * Initializes the aggregation logger for the defined post ID.
	 *
	 * [!!!] If the post ID is invalid an Exception will be thrown.
	 *
	 * @static
	 * @throws Exception
	 * @param  int  $post_id
	 * @return Social_Aggregation_Log
	 */
	public static function factory($post_id) {
		$post = get_post($post_id);
		if ($post === null) {
			throw new Exception(sprintf(__('Social failed to initialize the Aggregation_Log for post #%s.', Social::$i18n), $post_id));
		}

		return new Social_Aggregation_Log($post_id);
	}

	/**
	 * Loads the current log for the defined post ID.
	 *
	 * [!] For 1.0.2 we changed the logger to have a manual flag for each pull. This will
	 *     apply the update if the loaded log is from older versions of the plugin.
	 *
	 * @param  int  $post_id
	 */
	public function __construct($post_id) {
		// Load the current log for the post
		$this->log = get_post_meta($post_id, '_social_aggregation_log', true);

		// Upgrade?
		if (!empty($this->log) and !isset($this->log['items'])) {
			$this->log = array(
				'manual' => false,
				'items' => $this->log
			);
			update_post_meta($post_id, '_social_aggregation_log', $this->log);
		}
	}

} // End Social_Aggregation_Log
