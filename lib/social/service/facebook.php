<?php
/**
 * Facebook implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
final class Social_Service_Facebook extends Social_Service implements Social_Interface_Service {

	/**
	 * @var  string  service key
	 */
	protected $_key = 'facebook';

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 400;
	}

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string  $message  message to broadcast
	 * @param  array   $args  extra arguments to pass to the request
	 * @return Social_Response
	 */
	public function broadcast($account, $message, array $args = array()) {
		$args = $args + array(
			'message' => $message,
		);
		return $this->request($account, 'feed', $args, 'POST');
	}

	/**
	 * Aggregates comments by URL.
	 *
	 * @param  object  $post
	 * @param  array   $urls
	 * @return void
	 */
	public function aggregate_by_url(&$post, array $urls) {
		foreach ($urls as $url) {
			if (!empty($url)) {
				$url = 'https://graph.facebook.com/search?type=post&q='.$url;
				Social::log('Searching by URL(s) for post #:post_id. (Query: :url)', array(
					'post_id' => $post->ID,
					'url' => $url
				));
				$response = wp_remote_get($url);
				if (!is_wp_error($response)) {
					$response = json_decode($response['body']);

					if (isset($response->data) and is_array($response->data) and count($response->data)) {
						foreach ($response->data as $result) {
							if (in_array($result->id, $post->aggregated_ids[$this->_key]) or
							   (isset($post->broadcasted_ids[$this->_key]) and in_array($result->id, $post->broadcasted_ids[$this->_key])))
							{
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', true);
								continue;
							}

							Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url');
							$post->aggregated_ids[$this->_key][] = $result->id;
							$post->results[$this->_key][$result->id] = $result;
						}
					}
				}
				else {
					Social::log('URL search failed for post #:post_id.', array(
						'post_id' => $post->ID
					));
				}
			}
		}
	}

	/**
	 * Aggregates comments by the service's API.
	 *
	 * @param  object  $post
	 * @return array
	 */
	public function aggregate_by_api(&$post) {
		$accounts = $this->get_aggregation_accounts($post);

		if (isset($accounts[$this->_key]) and count($accounts[$this->_key])) {
			foreach ($accounts[$this->_key] as $account) {
				if (isset($post->broadcasted_ids[$this->_key][$account->id()])) {
					$id = explode('_', $post->broadcasted_ids[$this->_key][$account->id()]);
					$response = $this->request($account, $id[1].'/comments')->response;
					if (isset($response->data) and is_array($response->data) and count($response->data)) {
						foreach ($response->data as $result) {
							$data = array(
								'parent_id' => $id[0],
							);

							if (in_array($result->id, $post->aggregated_ids[$this->_key]) or
								(isset($post->broadcasted_ids[$this->_key]) and in_array($result->id, $post->broadcasted_ids[$this->_key])))
							{
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', true, $data);
								continue;
							}

							Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', false, $data);
							$post->aggergated_ids[$this->_key] = $result->id;

							$result->status_id = $post->broadcasted_ids[$this->_key][$account->id()];
							$post->results[$this->_key][$result->id] = $result;
						}
					}
				}
			}
		}
	}

	/**
	 * Saves the aggregated comments.
	 *
	 * @param  object  $post
	 * @return void
	 */
	public function save_aggregated_comments(&$post) {
		if (isset($post->results[$this->_key])) {
			foreach ($post->results[$this->_key] as $result) {
				$url = 'http://graph.facebook.com/'.$result->from->id;
				$request = wp_remote_get($url);
				if (!is_wp_error($result)) {
					$response = json_decode($request['body']);
					$account = (object) array(
						'user' => $response,
					);
					$class = 'Social_Service_'.$this->_key.'_Account';
					$account = new $class($account);

					$commentdata = array(
						'comment_post_ID' => $post->ID,
						'comment_type' => $this->_key,
						'comment_author' => $account->name(),
						'comment_author_email' => $this->_key.'.'.$account->id().'@example.com',
						'comment_author_url' => $account->url(),
						'comment_content' => $result->message,
						'comment_date' => date('Y-m-d H:i:s', strtotime($result->created_time) + (get_option('gmt_offset') * 3600)),
						'comment_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($result->created_time)),
						'comment_author_IP' => $_SERVER['SERVER_ADDR'],
						'comment_agent' => 'Social Aggregator'
					);
					$commentdata['comment_approved'] = wp_allow_comment($commentdata);
					$comment_id = wp_insert_comment($commentdata);
					update_comment_meta($comment_id, 'social_account_id', $account->id());
					update_comment_meta($comment_id, 'social_profile_image_url', 'http://graph.facebook.com/'.$account->id().'/picture');
					update_comment_meta($comment_id, 'social_status_id', (isset($result->status_id) ? $result->status_id : $result->id));

					if ($commentdata['comment_approved'] !== 'spam') {
						if ($commentdata['comment_approved'] == '0') {
							wp_notify_moderator($comment_id);
						}

						if (get_option('comments_notify') and $commentdata['comment_approved'] and (!isset($commentdata['user_id']) or $post->post_author != $commentdata['user_id'])) {
							wp_notify_postauthor($comment_id, isset($commentdata['comment_type']) ? $commentdata['comment_type'] : '');
						}
					}
				}
			}
		}
	}

	/**
	 * Checks the response to see if the broadcast limit has been reached.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function limit_reached($response) {
		if ($response == '(#341) Feed action request limit reached') {
			return true;
		}

		return false;
	}

	/**
	 * Checks the response to see if the broadcast is a duplicate.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function duplicate_status($response) {
		if ($response == '(#506) Duplicate status message') {
			return true;
		}

		return false;
	}

	/**
	 * Checks the response to see if the account has been deauthorized.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function deauthorized($response) {
		if (strpos($response, 'Error validating access token') !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the key to use on the request response to pull the ID.
	 *
	 * @return string
	 */
	public function response_id_key() {
		return 'id';
	}

	/**
	 * Returns the status URL to a broadcasted item.
	 *
	 * @param  string      $username
	 * @param  string|int  $id
	 * @return string
	 */
	public function status_url($username, $id) {
		$ids = explode('_', $id);
		return 'http://facebook.com/permalink.php?story_fbid='.$ids[1].'&id='.$ids[0];
	}

} // End Social_Service_Facebook
