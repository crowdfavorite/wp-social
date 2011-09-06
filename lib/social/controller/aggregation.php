<?php
/**
 * Aggregation controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Aggregation extends Social_Controller {

	/**
	 * Runs the aggregation for the requested post ID.
	 *
	 * @return void
	 */
	public function action_run() {
		$post = get_post($this->request->query('post_id'));
		if ($post === null) {
			return;
		}

		// Load aggregated IDs

		// Process IDs

		// Send to service for aggregation
		// $service->aggregate()

		// Verify responses

		// Store comments
	}

} // End Social_Controller_Aggregation
