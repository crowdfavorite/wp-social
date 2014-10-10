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
		return 50000;
	}

	/**
	 * Handles the requests to the proxy.
	 *
	 * @param  Social_Service_Account|int  $account
	 * @param  string                      $api
	 * @param  array                       $args
	 * @param  string                      $method
	 * @return Social_Response|bool
	 */
	public function request($account, $api, array $args = array(), $method = 'GET') {
		$api = urlencode($api);
		return parent::request($account, $api, $args, $method);
	}

	/**
	 * Any additional parameters that should be passed with a broadcast.
	 *
	 * @static
	 * @return array
	 */
	public function get_broadcast_extras($account_id, $post, $args = array()) {
		if (get_post_format($post->ID) !== 'status') {
			setup_postdata($post);
			$link_args = array(
				'link' => social_get_shortlink($post->ID),
				'title' => get_the_title($post->ID),
				'description' => get_the_excerpt(),
			);
			if (function_exists('has_post_thumbnail') and has_post_thumbnail($post->ID)) {
				$image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'single-post-thumbnail');
				$link_args = $link_args + array(
					'picture' => $image[0],
				);
			}
			wp_reset_postdata();
			$args = $args + $link_args;
		}
		return parent::get_broadcast_extras($account_id, $post, $args);
	}

	/**
	 * Broadcasts the message to the specified account. Returns the broadcasted ID.
	 *
	 * @param  Social_Service_Facebook_Account|object  $account     account to broadcast to
	 * @param  string                                  $message     message to broadcast
	 * @param  array                                   $args        extra arguments to pass to the request
	 * @param  int                                     $post_id     post ID being broadcasted
	 * @param  int                                     $comment_id  comment ID being broadcasted
	 *
	 * @return Social_Response
	 */
	public function broadcast($account, $message, array $args = array(), $post_id = null, $comment_id = null) {
		global $post;
		// if post ID is set, this is a broadcast of a post,
		// if the comment ID is set it is a broadcast of a comment
		// TODO - add wrapper functions that abstract these actions out to separate methods

		// check comment being replied to, if it is a facebook comment on a post then
		// send the comment as a reply on the same post.
		// If that fails, then send as posting a link with a comment.

		$args = $args + array(
			'message' => $message,
		);

		if ($comment_id && ($comment = get_comment($comment_id))) {

			// Check for facebook comment reply
			if ($comment->comment_parent
				&& ($parent_comment = get_comment($comment->comment_parent))
				&& in_array($parent_comment->comment_type, self::comment_types())) {

				if ($status_id = get_comment_meta($parent_comment->comment_ID, 'social_reply_to_id', true)) {
					$parent_status_id = get_comment_meta($comment->comment_parent, 'social_status_id', true);
					$args = apply_filters($this->key().'_broadcast_args', $args, $post_id, $comment_id);
					$response = $this->request($account, $status_id.'/comments', $args, 'POST');
					if ($response !== false && $response->body()->result == 'success') {
						// post succeeded, return response
						update_comment_meta($comment->comment_ID, 'social_reply_to_id', addslashes_deep($status_id));
						update_comment_meta($comment->comment_ID, 'social_status_id', addslashes_deep($parent_status_id));
						update_comment_meta($comment->comment_ID, 'social_broadcast_id', addslashes_deep($response->body()->response->id));
						return $response;
					}
				}
			}

			$broadcasted_ids = get_post_meta($comment->comment_post_ID, '_social_broadcasted_ids', true);

			// If only 1 account has been posted to
			if (count($broadcasted_ids[$this->key()]) === 1)  {
				$broadcast_account = array_shift($broadcasted_ids[$this->key()]);
				// And only one broadcast has been made from that account
				if (count($broadcast_account) === 1) {
					reset($broadcast_account);
					$status_id = key($broadcast_account);
					$args = apply_filters($this->key().'_broadcast_args', $args, $post_id, $comment_id);
					$response = $this->request($account, $status_id.'/comments', $args, 'POST');
					if ($response !== false && $response->body()->result == 'success') {
						// post succeeded, return response
						$broadcasted_id = $response->body()->response->id;
						$response = $this->request($account, $broadcasted_id, array('fields' => 'can_comment'));
						if ($response !== false && $response->body()->result == 'success' && $response->body()->response->can_comment) {
							update_comment_meta($comment->comment_ID, 'social_reply_to_id', addslashes_deep($response->body()->response->id));
						}
						else {
							update_comment_meta($comment->comment_ID, 'social_reply_to_id', addslashes_deep($status_id));
						}
						update_comment_meta($comment->comment_ID, 'social_broadcast_id', addslashes_deep($broadcasted_id));
						update_comment_meta($comment->comment_ID, 'social_status_id', addslashes_deep($status_id));
						return $response;
					}
				}
			}

			// Continuing on to simply post on user's wall

			// posting with a link, do not include URL in comment.
			$format = trim(str_replace('{url}', '', Social::option('comment_broadcast_format')));
			$message = $this->format_comment_content($comment, $format);
			$args['message'] = $message;

			// prep data
			$post = get_post($comment->comment_post_ID);
			setup_postdata($post);
			$link_args = array(
				'link' => social_get_shortlink($post->ID),
				'title' => get_the_title($post->ID),
				'description' => get_the_excerpt(),
			);
			if (function_exists('has_post_thumbnail') and has_post_thumbnail($post->ID)) {
				$image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'single-post-thumbnail');
				$link_args = $link_args + array(
					'picture' => $image[0],
				);
			}
			wp_reset_postdata();
			$args = $args + $link_args;
		}

		// Set access token?
		$broadcast_account = $account->broadcast_page();
		if ($broadcast_account !== null) {
			$args = $args + array(
				'access_token' => $broadcast_account->access_token,
				'page_id' => $broadcast_account->id,
			);
		}

		$args = apply_filters($this->key().'_broadcast_args', $args, $post_id, $comment_id);
		$request = apply_filters($this->key().'_broadcast_request', array(
			'url' => 'me/feed',
			'args' => $args,
			'post_id' => $post_id,
			'comment_id' => $comment_id,
		));
		$response = $this->request($account, $request['url'], $request['args'], 'POST');
		if ($response !== false && $response->body()->result == 'success') {
			// post succeeded, return response
			update_comment_meta($comment->comment_ID, 'social_reply_to_id', addslashes_deep($response->body()->response->id));
			update_comment_meta($comment->comment_ID, 'social_broadcast_id', addslashes_deep($response->body()->response->id));
		}
		return $response;
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
								else if ($this->is_duplicate_comment($post, $result->id)) {
									$post->aggregated_ids[$this->_key][] = $result->id;
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
		// find broadcasts for service
		$accounts = $this->get_aggregation_accounts($post);

		if (isset($accounts[$this->_key]) and count($accounts[$this->_key])) {
			$like_count = 0;
			foreach ($accounts[$this->_key] as $account) {
				if (isset($post->broadcasted_ids[$this->_key][$account->id()])) {
					foreach ($post->broadcasted_ids[$this->_key][$account->id()] as $broadcasted_id => $data) {
						$id = explode('_', $broadcasted_id);
						$request = $this->request($account, $broadcasted_id.'/comments', array('filter' => 'stream', 'fields' => 'parent,message,from,created_time,can_comment', 'limit'=>'500'));
						if ($request !== false && isset($request->body()->response)) {
							$response = $request->body()->response;
							if (isset($response->data) and is_array($response->data) and count($response->data)) {
								foreach ($response->data as $result) {
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
										else if ($this->is_duplicate_comment($post, $result->id)) {
											$post->aggregated_ids[$this->_key][] = $result->id;
											continue;
										}
									}

									Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', false, $data);

									if ($result->can_comment) {
										$result->reply_to_id = $result->id;
									}
									else {
										$result->reply_to_id = $broadcasted_id;
									}
									$result->status_id = $broadcasted_id;
									$post->aggregated_ids[$this->_key][] = $result->id;
									$post->results[$this->_key][$result->id] = $result;
								}
							}
						}

						$this->search_for_likes($account, $broadcasted_id, $id[0], $post, $like_count);
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
		$args = array(
			'limit' => '100'
		);
		if ($next !== false) {
			$args['offset'] = $next;
		}

		$request = $this->request($account, $url, $args);
		if ($request !== false && isset($request->body()->response)) {
			$response = $request->body()->response;
			if (isset($response->data) && is_array($response->data) && count($response->data)) {
				foreach ($response->data as $result) {
					if ((isset($post->results) && isset($post->results[$this->_key]) && isset($post->results[$this->_key][$result->id])) ||
						(in_array($result->id, $post->aggregated_ids[$this->_key]))
					) {
						continue;
					}
					$post->aggregated_ids[$this->_key][] = $result->id;
					$result = (object) array_merge(array(
						'like' => true,
						'from_id' => $result->id,
						'raw' => $result,
					), (array) $result);
					$result->status_id = $id;
					$post->results[$this->_key][$result->id] = $result;
					++$like_count;
				}
			}

			if (isset($response->paging) && isset($response->paging->next)) {
				$url = parse_url($response->paging->next);
				if (!empty($url['query'])) {
					parse_str($url['query'], $query);
					if (!empty($query['offset'])) {
						$this->search_for_likes($account, $id, $parent_id, $post, $like_count, $query['offset']);
					}
				}
			}
		}
	}


	/**
	 * Retrieves the WordPress ID of a comment based on the Facebook ID
	 *
	 * @param  string  Facebook Id
	 * @return string|false
	 */
	private function get_comment_from_fb_id($fb_parent_id) {
		global $wpdb;

		return $wpdb->get_row($wpdb->prepare("
			SELECT comment_id
			  FROM $wpdb->commentmeta AS cm
			 WHERE cm.meta_key = 'social_broadcast_id'
			   AND cm.meta_value = %s
		", $fb_parent_id));
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

				if (isset($result->parent)) {
					if ($wp_parent = $this->get_comment_from_fb_id($result->parent->id)) {
						$commentdata['comment_parent'] = $wp_parent->comment_id;
					}
				}

				if (!isset($result->like)) {
					$url = 'https://graph.facebook.com/'.$result->from->id;
					$request = wp_remote_get($url);
					if (!is_wp_error($request)) {
						$response = json_decode($request['body']);

						$account = (object) array(
							'user' => $response
						);
						$class = 'Social_Service_'.$this->_key.'_Account';
						$account = new $class($account);

						$commentdata = array_merge($commentdata, array(
							'comment_type' => 'social-facebook',
							'comment_author' => $wpdb->escape($result->from->name),
							'comment_author_url' => $account->url(),
							'comment_content' => $wpdb->escape($result->message),
							'comment_date' => date('Y-m-d H:i:s', strtotime($result->created_time) + (get_option('gmt_offset') * 3600)),
							'comment_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($result->created_time)),
						));

					}
				}
				else {
					$url = 'https://facebook.com/profile.php?id='.$result->id;
					$commentdata = array_merge($commentdata, array(
						'comment_type' => 'social-facebook-like',
						'comment_author' => $wpdb->escape($result->name),
						'comment_author_url' => $url,
						'comment_content' => $wpdb->escape('<a href="'.$url.'" target="_blank">'.$result->name.'</a> liked this on Facebook.'),
						'comment_date' => current_time('mysql'),
						'comment_date_gmt' => current_time('mysql', 1),
					));
				}

				$user_id = (isset($result->like) ? $result->from_id : $result->from->id);
				$commentdata = array_merge($commentdata, array(
					'comment_post_ID' => $post->ID,
					'comment_author_email' => $this->_key.'.'.$user_id.'@example.com',
				));

				if (apply_filters('social_approve_likes_and_retweets', false) && isset($result->like)) {
					$commentdata['comment_approved'] = 1;
				}
				else if (($commentdata = $this->allow_comment($commentdata, $result->id, $post)) === false) {
					continue;
				}

				Social::log('Saving #:result_id.', array(
					'result_id' => $result->id
				));

				$comment_id = 0;
				try
				{
					Social::Log('Attempting to save commentdata: :commentdata', array(
						'commentdata' => print_r($commentdata, true)
					));
					$comment_id = wp_insert_comment($commentdata);

					update_comment_meta($comment_id, 'social_account_id', addslashes_deep($user_id));
					update_comment_meta($comment_id, 'social_profile_image_url', addslashes_deep('https://graph.facebook.com/'.$user_id.'/picture'));
					update_comment_meta($comment_id, 'social_status_id', addslashes_deep($result->status_id));
					update_comment_meta($comment_id, 'social_broadcast_id', addslashes_deep($result->id));

					if ($result->reply_to_id) {
						update_comment_meta($comment_id, 'social_reply_to_id', addslashes_deep($result->reply_to_id));
					}

					if (!isset($result->raw)) {
						$result = (object) array_merge((array) $result, array('raw' => $result));
					}
					update_comment_meta($comment_id, 'social_raw_data', addslashes_deep(base64_encode(json_encode($result->raw))));

					if ($commentdata['comment_approved'] !== 'spam') {
						if ($commentdata['comment_approved'] == '0') {
							wp_notify_moderator($comment_id);
						}

						if (get_option('comments_notify') and $commentdata['comment_approved'] and (!isset($commentdata['user_id']) or $post->post_author != $commentdata['user_id'])) {
							wp_notify_postauthor($comment_id, 'comment');
						}
					}
				}
				catch (Exception $e) {
					// Something went wrong, remove the aggregated ID.
					if (($key = array_search($result->id, $post->aggregated_ids['facebook'])) !== false) {
						unset($post->aggregated_ids['facebook'][$key]);
					}

					if ((int) $comment_id) {
						// Delete the comment in case it wasn't the insert that failed.
						wp_delete_comment($comment_id);
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
			return sprintf(__('Found %s additional likes.', 'social'), $item->data['total']);
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
	 * @param  bool    $check_invalid_key
	 * @return bool
	 */
	public function deauthorized($response, $check_invalid_key = false) {
		if (($check_invalid_key and $response == 'invalid key') or $response == 'Error validating access token') {
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
	 * Returns the response message.
	 *
	 * @param  object  $body
	 * @param  string  $default
	 *
	 * @return mixed
	 */
	public function response_message($body, $default) {
		if (isset($body->response) and isset($body->response->message)) {
			return $body->response->message;
		}

		return $default;
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
		return 'https://facebook.com/permalink.php?story_fbid='.$ids[1].'&id='.$ids[0];
	}

	/**
	 * Loads the pages for the account.
	 *
	 * @param  Social_Service_Account  $account
	 * @param  bool                    $is_profile
	 * @param  bool                    $save
	 * @return array
	 */
	public function get_pages(Social_Service_Account $account, $is_profile = false, $save = true) {
		$pages = array();
		if ($account->use_pages() or $account->use_pages(true)) {
			$response = $this->request($account, $account->id().'/accounts');
			if ($response !== false and isset($response->body()->response)) {
				if (isset($response->body()->response->data)) {
					foreach ($response->body()->response->data as $item) {
						if ($item->category != 'Application') {
							$pages[$item->id] = $item;
						}
					}
				}
			    else if ($response->body()->response == 'incorrect method') {
					// Account no longer has page permissions.
					$service = Social::instance()->service('facebook');
					$accounts = $service->accounts();
					foreach ($accounts as $account_id => $_account) {
						if ($account_id == $account->id()) {
							$_account->use_pages(false, false);
							$_account->use_pages(true, false);
							$_account->pages(array(), $is_profile);
						}

						$accounts[$account_id] = $account->as_object();
					}

					if ($save) {
						$service->accounts($accounts)->save($is_profile);
					}
				}
			}
		}
		return $pages;
	}

	/**
	 * Builds the page's image URL.
	 *
	 * @param  object  $account
	 * @return string
	 */
	public function page_image_url($account) {
		return apply_filters('social_facebook_page_image_url', 'https://graph.facebook.com/'.$account->id.'/picture', $account);
	}

	/**
	 * Comment types for this service.
	 *
	 * @static
	 * @return array
	 */
	public static function comment_types() {
		return array(
			'social-facebook',
			'social-facebook-like',
		);
	}

	/**
	 * Comment types that are "meta". In this case, Likes (and perhaps Shares in the future).
	 *
	 * @static
	 * @return array
	 */
	public static function comment_types_meta() {
		return array(
			'social-facebook-like',
		);
	}

	public static function social_settings_save($controller) {
		// Save Facebook pages
		$is_profile = ($controller->request()->post('social_profile') == 'true');
		if ($is_profile and !defined('IS_PROFILE_PAGE')) {
			define('IS_PROFILE_PAGE', true);
		}

		$enabled_child_accounts = $controller->request()->post('social_enabled_child_accounts');
		if (!is_array($enabled_child_accounts)) {
			$enabled_child_accounts = array();
		}
		$service = $controller->social()->service('facebook');
		if ($service !== false) {
			foreach ($service->accounts() as $account) {
				$updated_accounts = array();
				foreach ($service->accounts() as $account) {
					//default service to empty array in case it is not set
					$enabled_child_accounts[$service->key()] = isset($enabled_child_accounts[$service->key()]) ? $enabled_child_accounts[$service->key()] : array();

					$account->update_enabled_child_accounts($enabled_child_accounts[$service->key()]);
					$updated_accounts[$account->id()] = $account->as_object();
				}
				$service->accounts($updated_accounts)->save($is_profile);
			}
		}
	}

	public static function social_settings_default_accounts($accounts, $controller) {
		if (is_array($controller->request()->post('social_default_pages'))) {
			if (!isset($accounts['facebook'])) {
				$accounts['facebook'] = array(
					'pages' => array()
				);
			}
			$accounts['facebook']['pages'] = $controller->request()->post('social_default_pages');
		}
		else {
			$accounts['facebook']['pages'] = array();
		}
		return $accounts;
	}

} // End Social_Service_Facebook
