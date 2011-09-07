<?php
// Service Filters
add_filter('social_response_body', array('Social_Service_Twitter', 'response_body'));

/**
 * Twitter implementation for the service.
 *
 * @package Social
 * @subpackage services
 */
final class Social_Service_Twitter extends Social_Service implements Social_Interface_Service {

	/**
	 * @var  string  service key
	 */
	protected $_key = 'twitter';

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140;
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
			'status' => $message
		);

		return $this->request($account, 'statuses/update', $args, 'POST');
	}

	/**
	 * Aggregates comments by URL.
	 *
	 * @param  object  $post
	 * @param  array   $urls
	 * @return void
	 */
	public function aggregate_by_url(&$post, array $urls) {
		$url = 'http://search.twitter.com/search.json?q='.implode('+OR+', $urls);
		Social::log('Searching by URL(s) for post #:post_id. (Query: :url)', array(
			'post_id' => $post->ID,
			'url' => $url
		));
		$request = wp_remote_get($url);
		if (!is_wp_error($request)) {
			$response = apply_filters('social_response_body', $request['body'], $this->_key);
			$response = json_decode($response);
			if (isset($response->results) and is_array($response->results) and count($response->results)) {
				foreach ($response->results as $result) {
					$data = array(
						'username' => $result->from_user,
					);

					if (in_array($result->id, $post->aggregated_ids[$this->_key]) or
					   (isset($post->broadcasted_ids[$this->_key]) and in_array($result->id, $post->broadcasted_ids[$this->_key])))
					{
						Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', true, $data);
						continue;
					}

					Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', false, $data);
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
					// Retweets
					$response = $this->request($account, 'statuses/retweets/'.$post->broadcasted_ids[$this->_key][$account->id()]);
					if (isset($response->response) and is_array($response->response) and count($response->response)) {
						foreach ($response->response as $result) {
							$data = array(
								'username' => $result->user->screen_name,
							);

							if (in_array($result->id, $post->aggregated_ids[$this->_key]) or
								(isset($post->broadcasted_ids[$this->_key]) and in_array($result->id, $post->broadcasted_ids[$this->_key])))
							{
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'retweet', true, $data);
								continue;
							}

							Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'retweet', false, $data);
							$post->aggergated_ids[$this->_key] = $result->id;
							$post->results[$this->_key][$result->id] = (object) array(
								'id' => $result->id,
								'from_user_id' => $result->user->id,
								'from_user' => $result->user->screen_name,
								'text' => $result->text,
								'created_at' => $result->created_at,
								'profile_image_url' => $result->user->profile_image_url,
							);
						}
					}

					// Mentions
					$response = $this->request($account, 'statuses/mentions', array(
						'since_id' => $post->broadcasted_ids[$this->_key][$account->id()],
						'count' => 200
					));
					if (isset($response->response) and is_array($response->response) and count($response->response)) {
						foreach ($response->response as $result) {
							$data = array(
								'username' => $result->user->screen_name,
							);

							if (in_array($result->id, $post->aggregated_ids[$this->_key]) or
								(isset($post->broadcasted_ids[$this->_key]) and in_array($result->id, $post->broadcasted_ids[$this->_key])))
							{
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', true, $data);
								continue;
							}

							Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', false, $data);
							$post->aggergated_ids[$this->_key] = $result->id;
							$post->results[$this->_key][$result->id] = (object) array(
								'id' => $result->id,
								'from_user_id' => $result->user->id,
								'from_user' => $result->user->screen_name,
								'text' => $result->text,
								'created_at' => $result->created_at,
								'profile_image_url' => $result->user->profile_image_url,
							);
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
				$account = (object) array(
					'user' => (object) array(
						'id' => $result->from_user_id,
						'screen_name' => $result->from_user,
					),
				);
				$class = 'Social_Service_'.$this->_key.'_Account';
				$account = new $class($account);

				$commentdata = array(
					'comment_post_ID' => $post->ID,
					'comment_type' => $this->_key,
					'comment_author' => $account->username(),
					'comment_author_email' => $this->_key.'.'.$account->id().'@example.com',
					'comment_author_url' => $account->url(),
					'comment_content' => $result->text,
					'comment_date' => date('Y-m-d H:i:s', strtotime($result->created_at) + (get_option('gmt_offset') * 3600)),
					'comment_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($result->created_at)),
					'comment_author_IP' => $_SERVER['SERVER_ADDR'],
					'comment_agent' => 'Social Aggregator',
				);
				$commentdata['comment_approved'] = wp_allow_comment($commentdata);
				$comment_id = wp_insert_comment($commentdata);

				update_comment_meta($comment_id, 'social_account_id', $result->from_user_id);
				update_comment_meta($comment_id, 'social_profile_image_url', $result->profile_image_url);
				update_comment_meta($comment_id, 'social_status_id', $result->id);

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

	/**
	 * Checks the response to see if the broadcast limit has been reached.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function limit_reached($response) {
		return false;
	}

	/**
	 * Checks the response to see if the broadcast is a duplicate.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function duplicate_status($response) {
		if ($response == 'Status is duplicate.') {
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
		if ($response == 'Could not authenticate with OAuth.') {
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
		return 'id_str';
	}

	/**
	 * Returns the status URL to a broadcasted item.
	 *
	 * @param  string      $username
	 * @param  string|int  $id
	 * @return string
	 */
	public function status_url($username, $id) {
		return 'http://twitter.com/'.$username.'/status/'.$id;
	}

	/**
	 * Hack to fix the "Twitpocalypse" bug on 32-bit systems.
	 *
	 * @static
	 * @param  string  $body
	 * @return object
	 */
	public static function request_body($body) {
		return json_decode(preg_replace('/"id":(\d+)/', '"id":"$1"', $body));
	}

} // End Social_Service_Twitter
