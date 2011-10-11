<?php
/**
 * Facebook implementation for the service.
 *
 * @package    Social
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
		return 420;
	}

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Account  $account  account to broadcast to
	 * @param  string                  $message  message to broadcast
	 * @param  array                   $args     extra arguments to pass to the request
	 * @param  int                     $post_id  post ID being broadcasted
	 *
	 * @return Social_Response
	 */
	public function broadcast($account, $message, array $args = array(), $post_id = null) {
		global $post;
		if (has_post_thumbnail($post_id)) {
			$post = get_post($post_id);
			$image = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'single-post-thumbnail');
			$args = $args + array(
				'link' => get_post_permalink($post_id),
				'title' => $post->post_title,
				'picture' => $image[0],
				'description' => get_the_excerpt(),
			);
		}

		$args = $args + array(
			'message' => $message,
		);
		$args = apply_filters($this->key().'_broadcast_args', $args, $post_id);
		return $this->request($account, 'feed', $args, 'POST');
	}

	/**
	 * Aggregates comments by URL.
	 *
	 * @param  object  $post
	 * @param  array   $urls
	 *
	 * @return void
	 */
	public function aggregate_by_url(&$post, array $urls) {
		foreach ($urls as $url) {
			if (!empty($url)) {
				$url = 'https://graph.facebook.com/search?type=post&q='.$url;
				Social::log('Searching by URL(s) for post #:post_id. (Query: :url)', array(
					'post_id' => $post->ID,
					'url' => $url,
				));
				$response = wp_remote_get($url);
				if (!is_wp_error($response)) {
					$response = json_decode($response['body']);

					if (isset($response->data) and is_array($response->data) and count($response->data)) {
						foreach ($response->data as $result) {
							if (in_array($result->id, $post->aggregated_ids[$this->_key])) {
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', true);
								continue;
							}
							else {
								if ($this->is_original_broadcast($post, $result->id)) {
									continue;
								}
							}

							Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url');
							$post->aggregated_ids[$this->_key][] = $result->id;
							$post->results[$this->_key][$result->id] = $result;
						}
					}
				}
				else {
					Social::log('URL search failed for post #:post_id.', array(
						'post_id' => $post->ID,
					));
				}
			}
		}
	}

	/**
	 * Aggregates comments by the service's API.
	 *
	 * @param  object  $post
	 *
	 * @return array
	 */
	public function aggregate_by_api(&$post) {
		$accounts = $this->get_aggregation_accounts($post);

		if (isset($accounts[$this->_key]) and count($accounts[$this->_key])) {
			$like_count = 0;
			foreach ($accounts[$this->_key] as $account) {
				if (isset($post->broadcasted_ids[$this->_key][$account->id()])) {
					foreach ($post->broadcasted_ids[$this->_key][$account->id()] as $broadcasted_id => $data) {
						$id = explode('_', $broadcasted_id);
						$response = $this->request($account, $id[1].'/comments')->body();
						if ($response !== false and isset($response->response) and isset($response->response->data) and is_array($response->response->data) and count($response->response->data)) {
							foreach ($response->response->data as $result) {
								$data = array(
									'parent_id' => $broadcasted_id,
								);
								if (in_array($result->id, $post->aggregated_ids[$this->_key])) {
									Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', true, $data);
									continue;
								}
								else {
									if ($this->is_original_broadcast($post, $result->id)) {
										continue;
									}
								}

								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', false, $data);
								$post->aggregated_ids[$this->_key][] = $result->id;

								$result->status_id = $broadcasted_id;
								$post->results[$this->_key][$result->id] = $result;
							}
						}

						$this->search_for_likes($account, $id[1], $id[0], $post, $like_count);
					}
				}
			}

			if (count($like_count)) {
				Social_Aggregation_Log::instance($post->ID)->add($this->_key, $post->ID.time(), 'like', !$like_count, array('total' => $like_count));
			}
		}
	}

	/**
	 * Searches for likes on the post.
	 *
	 * @param  object       $account
	 * @param  string       $id
	 * @param  int          $parent_id
	 * @param  WP_Post      $post
	 * @param  int          $like_count
	 * @param  bool|string  $next
	 * @return void
	 */
	private function search_for_likes(&$account, $id, $parent_id, &$post, &$like_count, $next = false) {
		$url = $id.'/likes';
		if ($next !== false) {
			$url .= $next;
		}

		$response = $this->request($account, $url, array('limit' => '100'))->body();
		if ($response !== false and isset($response->response) and isset($response->response->data) and is_array($response->response->data) and count($response->response->data)) {
			foreach ($response->response->data as $result) {
				if ((isset($post->results) and isset($post->results[$this->_key]) and isset($post->results[$this->_key][$result->id])) or
					(in_array($result->id, $post->aggregated_ids[$this->_key]))
				) {
					continue;
				}
				$post->aggregated_ids[$this->_key][] = $result->id;
				$post->results[$this->_key][$result->id] = (object) array_merge(array(
					'like' => true,
					'status_id' => $parent_id.'_'.$id,
					'raw' => $result,
				), (array) $result);
				++$like_count;
			}
		}

		if (isset($response->paging) and isset($response->paging->next)) {
			$next = explode('/likes', $response->paging->next);
			$this->search_for_likes($account, $id, $parent_id, $post, $like_count, $next[1]);
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
			global $wpdb;

			foreach ($post->results[$this->_key] as $result) {
				$commentdata = array(
					'comment_post_ID' => $post->ID,
					'comment_author_email' => $wpdb->escape($this->_key.'.'.$result->id.'@example.com'),
					'comment_author_IP' => $_SERVER['SERVER_ADDR'],
					'comment_agent' => 'Social Aggregator'
				);
				if (!isset($result->like)) {
					$url = 'http://graph.facebook.com/'.$result->from->id;
					$request = wp_remote_get($url);
					if (!is_wp_error($request)) {
						$response = json_decode($request['body']);

						$account = (object) array(
							'user' => $response
						);
						$class = 'Social_Service_'.$this->_key.'_Account';
						$account = new $class($account);

						$commentdata = array_merge($commentdata, array(
							'comment_type' => 'social-'.$this->_key,
							'comment_author' => $wpdb->escape($result->from->name),
							'comment_author_url' => $account->avatar(),
							'comment_content' => $wpdb->escape($result->message),
							'comment_date' => date('Y-m-d H:i:s', strtotime($result->created_time) + (get_option('gmt_offset') * 3600)),
							'comment_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($result->created_time)),
						));
					}
				}
				else {
					$url = 'http://facebook.com/profile.php?id='.$result->id;
					$commentdata = array_merge($commentdata, array(
						'comment_type' => 'social-'.$this->_key.'-like',
						'comment_author' => $wpdb->escape($result->name),
						'comment_author_url' => $url,
						'comment_content' => $wpdb->escape('<a href="'.$url.'" target="_blank">'.$result->name.'</a> liked this on Facebook.'),
						'comment_date' => current_time('mysql'),
						'comment_date_gmt' => current_time('mysql', 1),
					));
				}

				if (count($commentdata)) {
					$user_id = (isset($result->like) ? $result->id : $result->from->id);
					$commentdata = array_merge($commentdata, array(
						'comment_post_ID' => $post->ID,
						'comment_author_email' => $this->_key.'.'.$user_id.'@example.com',
					));
					$commentdata['comment_approved'] = wp_allow_comment($commentdata);
					$comment_id = wp_insert_comment($commentdata);

					update_comment_meta($comment_id, 'social_account_id', $user_id);
					update_comment_meta($comment_id, 'social_profile_image_url', 'http://graph.facebook.com/'.$user_id.'/picture');
					update_comment_meta($comment_id, 'social_status_id', (isset($result->status_id) ? $result->status_id : $result->id));

					if (!isset($result->raw)) {
						$result = (object) array_merge((array) $result, array('raw' => $result));
					}
					update_comment_meta($comment_id, 'social_raw_data', base64_encode(json_encode($result->raw)));

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
	 * Hook to allow services to define their aggregation row items based on the passed in type.
	 *
	 * @param  string  $type
	 * @param  object  $item
	 * @param  string  $username
	 * @param  int     $id
	 * @return string
	 */
	public function aggregation_row($type, $item, $username, $id) {
		if ($type == 'like') {
			return sprintf(__('Found %s additional likes.', Social::$i18n), $item->data['total']);
		}
		return '';
	}

	/**
	 * Checks the response to see if the broadcast limit has been reached.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function limit_reached($response) {
		return ($response == '(#341) Feed action request limit reached');
	}

	/**
	 * Checks the response to see if the broadcast is a duplicate.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function duplicate_status($response) {
		return ($response == '(#506) Duplicate status message');
	}

	/**
	 * Checks the response to see if the account has been deauthorized.
	 *
	 * @param  string  $response
	 * @return bool
	 */
	public function deauthorized($response) {
		return ($response == 'Error validating access token');
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
	 * @return string|null
	 */
	public function status_url($username, $id) {
		if (strpos($id, '_') === false) {
			return null;
		}

		$ids = explode('_', $id);
		return 'http://facebook.com/permalink.php?story_fbid='.$ids[1].'&id='.$ids[0];
	}

	/**
	 * Show full comment?
	 *
	 * @param  string  $type
	 * @return bool
	 */
	public function show_full_comment($type) {
		return ($type !== 'social-facebook-like');
	}

	/**
	 * Displays the auth item output.
	 *
	 * @param  Social_Service_Account  $account
	 * @return Social_View
	 */
	public function auth_output(Social_Service_Account $account) {
		$profile_url = esc_url($account->url());
		$profile_name = esc_html($account->name());
		$disconnect = $this->disconnect_url($account, true);
		$name = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);

		// Facebook pages?
		$pages = array();
		if ($account->use_pages()) {
			$response = $this->request($account, 'accounts');
		}

		return Social_View::factory('wp-admin/parts/facebook/auth_output', array(
			'account' => $account,
			'key' => $this->key(),
			'name' => $name,
			'disconnect' => $disconnect,
		));
	}

} // End Social_Service_Facebook
