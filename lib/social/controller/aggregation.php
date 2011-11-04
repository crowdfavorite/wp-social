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
		$fetch = Social::option('fetch_comments');
		if (empty($fetch)) {
			Social::log('Aggregation has been disabled, exiting.');
			return;
		}
		
		$post = get_post($this->request->query('post_id'));
		if ($post === null) {
			return;
		}

		Social::log('Begin aggregation for post #:post_id.', array(
			'post_id' => $post->ID,
		));

		// Get URLs to query
		$url = wp_get_shortlink($post->ID);
		if (empty($url)) {
			$url = site_url('?p='.$post->ID);
		}
		$urls = array(
			urlencode($url)
		);

		// Add the permalink?
		$permalink = urlencode(get_permalink($post->ID));
		if ($urls[0] != $permalink) {
			$urls[] = $permalink;
		}

		$broadcasted_ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}

		$aggregated_ids = get_post_meta($post->ID, '_social_aggregated_ids', true);
		if (empty($aggregated_ids)) {
			$aggregated_ids = array();
		}

		$post->broadcasted_ids = $broadcasted_ids;
		$post->aggregated_ids = $aggregated_ids;
		$post->results = array();
		foreach ($this->social->services() as $key => $service) {
			$post->results[$key] = array();
			if (!isset($post->aggregated_ids[$key])) {
				$post->aggregated_ids[$key] = array();
			}
			
			if (isset($broadcasted_ids[$key]) and count($broadcasted_ids[$key])) {
				$service->aggregate_by_api($post);
			}

			// URL Search
			$urls = apply_filters('social_search_urls', $urls, $key);
			$service->aggregate_by_url($post, $urls);
		}

		if (count($post->results)) {
			update_post_meta($post->ID, '_social_aggregated_ids', $post->aggregated_ids);

			foreach ($post->results as $key => $results) {
				if (count($results)) {
					$this->social->service($key)->save_aggregated_comments($post);
				}
			}
		}

		Social::log('Aggregation for post #:post_id complete.', array(
			'post_id' => $post->ID,
		));

		// Some cleanup...
		unset($post->broadcasted_ids);
		unset($post->aggregated_ids);
		unset($post->results);

		if ($this->request->is_ajax()) {
			// Re-add to the queue?
			$queue = Social_Aggregation_Queue::factory();
			if (!$queue->find($post->ID)) {
				$queue->add($post->ID, '24hour')->save();
			}

			$log = Social_Aggregation_Log::instance($post->ID);
			$log->save(true);
			if (isset($_GET['render']) and $_GET['render'] == 'false') {
				$total = 0;
				$log = $log->current();
				if (isset($log->items)) {
					foreach ($log->items as $service => $items) {
						foreach ($items as $item) {
							if (!$item->ignored) {
								++$total;
							}
						}
					}
				}

				$awaiting_mod = wp_count_comments();
				$awaiting_mod = $awaiting_mod->moderated;

				$link = esc_url(admin_url('edit-comments.php?p='.$post->ID));

				$html = '';
				if (!isset($_GET['hide_li']) or $_GET['hide_li'] == 'false') {
					$html = '<li id="wp-adminbar-comments-social">';
				}
				$html .= '<a href="'.$link.'"><span class="social-aggregation-results">'.sprintf(__('(%s New)', 'social'), $total).'</span></a>';
				if (!isset($_GET['hide_li']) or $_GET['hide_li'] == 'false') {
					$html .= '</li>';
				}

				$response = array(
					'total' => number_format_i18n($awaiting_mod),
					'link' => $link,
					'html' => $html,
				);
				echo json_encode($response);
			}
			else {
				echo $log;
			}
			exit;
		}
		else {
			Social_Aggregation_Log::instance($post->ID)->save();
		}
	}

	/**
	 * Retrieves missing Twitter content.
	 *
	 * @return void
	 */
	public function action_retrieve_twitter_content() {
		$broadcasted_id = $this->request->query('broadcasted_id');
		if ($broadcasted_id === null) {
			exit;
		}

		$post_id = $this->request->query('post_id');
		if ($post_id !== null) {
			$recovered = false;
			$run = get_post_meta('_social_run_twitter_retrieval', true);
			if (empty($run) or (int) $run <= current_time('timestamp', 1)) {
				// Do we have accounts to use?
				$service = Social::instance()->service('twitter');
				if ($service !== false) {
					$accounts = $service->accounts();
					if (count($accounts)) {
						foreach ($accounts as $account) {
							// Run the request to the find Tweet
							$response = $service->request($account, 'statuses/show/'.$broadcasted_id);
							if ($response !== false and !isset($response->body()->response->error)) {
								$recovered = $service->recovered_meta($post_id, $broadcasted_id, $response->body()->response);
							}
						}
					}
					else {
						$response = wp_remote_get('http://api.twitter.com/1/statuses/show/'.$broadcasted_id.'.json');
						if (!is_wp_error($response) and !isset($response->error)) {
							$recovered = $service->recovered_meta($post_id, $broadcasted_id, $response);
						}
					}
				}
			}

			if (!$recovered) {
				// Something went wrong, retry again in 15 minutes.
				update_post_meta($post_id, '_social_run_twitter_retrieval', (current_time('timestamp', 1) + 54000));
			}
			else if (!empty($run)) {
				delete_post_meta($post_id, '_social_run_twitter_retrieval');
			}
		}
	}

} // End Social_Controller_Aggregation
