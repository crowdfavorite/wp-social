<?php
// Service Filters
add_filter('social_response_body', array('Social_Service_Twitter', 'response_body'));
add_filter('get_comment_author_link', array('Social_Service_Twitter', 'get_comment_author_link'));

/**
 * Twitter implementation for the service.
 *
 * @package    Social
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
	 * @param  string                  $message  message to broadcast
	 * @param  array                   $args     extra arguments to pass to the request
	 * @param  int                     $post_id  post ID being broadcasted
	 *
	 * @return Social_Response
	 */
	public function broadcast($account, $message, array $args = array(), $post_id = null) {
		$args = $args + array(
			'status' => $message
		);

		$args = apply_filters($this->key().'_broadcast_args', $args, $post_id);
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
			'url' => $url,
		));
		$request = wp_remote_get($url);
		if (!is_wp_error($request)) {
			$response = apply_filters('social_response_body', $request['body'], $this->_key);
			if (isset($response->results) and is_array($response->results) and count($response->results)) {
				foreach ($response->results as $result) {
					$data = array(
						'username' => $result->from_user,
					);

					if (in_array($result->id, $post->aggregated_ids[$this->_key])) {
						Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', true, $data);
						continue;
					}
					else {
						if ($this->is_original_broadcast($post, $result->id)) {
							continue;
						}
					}

					Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'url', false, $data);
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
					$broadcasted_ids = $post->broadcasted_ids[$this->_key][$account->id()];

					foreach ($broadcasted_ids as $broadcasted_id => $data) {
						// Retweets
						Social::log('Aggregating Twitter via statuses/retweets');
						$response = $this->request($account, 'statuses/retweets/'.$broadcasted_id);
						if ($response !== false and is_array($response->body()->response) and count($response->body()->response)) {
							foreach ($response->body()->response as $result) {
								$data = array(
									'username' => $result->user->screen_name,
								);

								if (in_array($result->id, $post->aggregated_ids[$this->_key])) {
									Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'retweet', true, $data);
									continue;
								}
								else {
									if ($this->is_original_broadcast($post, $result->id)) {
										continue;
									}
								}

								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'retweet', false, $data);
								$post->aggregated_ids[$this->_key][] = $result->id;
								$post->results[$this->_key][$result->id] = (object) array(
									'id' => $result->id,
									'from_user_id' => $result->user->id,
									'from_user' => $result->user->screen_name,
									'text' => $result->text,
									'created_at' => $result->created_at,
									'profile_image_url' => $result->user->profile_image_url,
									'in_reply_to_status_id' => $result->in_reply_to_status_id,
									'raw' => $result,
								);
							}
						}

						// Mentions
						Social::log('Aggregating Twitter via statuses/mentions');
						$response = $this->request($account, 'statuses/mentions', array(
							'since_id' => $broadcasted_id,
							'count' => 200,
						));
						if ($response !== false and is_array($response->body()->response) and count($response->body()->response)) {
							foreach ($response->body()->response as $result) {
								if ($this->is_original_broadcast($post, $result->id)) {
									continue;
								}
								$data = array(
									'username' => $result->user->screen_name,
								);
								// existing comment
								if (in_array($result->id, $post->aggregated_ids[$this->_key])) {
									Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', true, $data);
									continue;
								}
								// not a reply to a broadcast
								if (!isset($broadcasted_ids[$result->in_reply_to_status_id])) {
									continue;
								}
								Social_Aggregation_Log::instance($post->ID)->add($this->_key, $result->id, 'reply', false, $data);
								$post->aggregated_ids[$this->_key][] = $result->id;
								$post->results[$this->_key][$result->id] = (object) array(
									'id' => $result->id,
									'from_user_id' => $result->user->id,
									'from_user' => $result->user->screen_name,
									'text' => $result->text,
									'created_at' => $result->created_at,
									'profile_image_url' => $result->user->profile_image_url,
									'in_reply_to_status_id' => $result->in_reply_to_status_id,
									'raw' => $result,
								);
							}
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
	 * @param  bool    $skip_approval
	 * @return void
	 */
	public function save_aggregated_comments(&$post, $skip_approval = false) {
		if (isset($post->results[$this->_key])) {
			global $wpdb;

			foreach ($post->results[$this->_key] as $result) {
				if (!isset($result->user->protected) or $result->user->protected == false) {
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
						'comment_type' => 'social-'.$this->_key,
						'comment_author' => $wpdb->escape($account->username()),
						'comment_author_email' => $wpdb->escape($this->_key.'.'.$account->id().'@example.com'),
						'comment_author_url' => $account->url(),
						'comment_content' => $wpdb->escape($result->text),
						'comment_date' => date('Y-m-d H:i:s', strtotime($result->created_at) + (get_option('gmt_offset') * 3600)),
						'comment_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($result->created_at)),
						'comment_author_IP' => $_SERVER['SERVER_ADDR'],
						'comment_agent' => 'Social Aggregator',
					);

					if ($skip_approval) {
						$commentdata['comment_approved'] = '1';
					}
					else {
						add_filter('wp_die_handler', array('Social', 'wp_die_handler'));
						try {
							$commentdata['comment_approved'] = wp_allow_comment($commentdata);
							remove_filter('wp_die_handler', array('Social', 'wp_die_handler'));
						} catch (Exception $e) {
							remove_filter('wp_die_handler', array('Social', 'wp_die_handler'));
							if ($e->getMessage() == Social::$duplicate_comment_message) {
								// Remove the aggregation ID from the stack
								unset($post->results[$this->_key][$result->id]);
								$aggregated_ids = array();
								foreach ($post->aggregated_ids[$this->_key] as $id) {
									if ($id != $result->id) {
										$aggregated_ids[] = $id;
									}
								}
								$post->aggregated_ids[$this->_key] = $aggregated_ids;

								// Mark the result as ignored
								Social_Aggregation_Log::instance($post->ID)->ignore($result->id);

								// ... continue looping
								continue;
							}
						}
					}

					// sanity check to make sure this comment is not a duplicate
					if ($this->is_duplicate_comment($post, $result->id)) {
						Social::log('Result #:result_id already exists, skipping.', array(
							'result_id' => $result->id
						), 'duplicate-comment');
						continue;
					}

					Social::log('Saving #:result_id for account :account_id.', array(
						'result_id' => $result->id,
						'account_id' => $account->id()
					));

					$comment_id = 0;
					try
					{
						$comment_id = wp_insert_comment($commentdata);

						update_comment_meta($comment_id, 'social_account_id', $result->from_user_id);
						update_comment_meta($comment_id, 'social_profile_image_url', $result->profile_image_url);
						update_comment_meta($comment_id, 'social_status_id', $result->id);

						// Attempt to see if the comment is in response to an existing Tweet.
						if (!isset($result->in_reply_to_status_id)) {
							// This "should" only happen on tweets found on the URL search
							foreach ($this->accounts() as $account) {
								$response = $this->request($account, 'statuses/show/'.$result->id)->body();

								if (isset($response->in_reply_to_status_id)) {
									if (!empty($response->in_reply_to_status_id)) {
										$result->in_reply_to_status_id = $response->in_reply_to_status_id;
									}
									break;
								}
							}
						}

						if (isset($result->in_reply_to_status_id)) {
							update_comment_meta($comment_id, 'social_in_reply_to_status_id', $result->in_reply_to_status_id);
						}

						if (!isset($result->raw)) {
							$result = (object) array_merge((array) $result, array('raw' => $result));
						}
						update_comment_meta($comment_id, 'social_raw_data', base64_encode(json_encode($result->raw)));

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
						if (($key = array_search($result->id, $post->aggregated_ids['twitter'])) !== false) {
							unset($post->aggregated_ids['twitter'][$key]);
						}

						if ((int) $comment_id) {
							// Delete the comment in case it wasn't the insert that failed.
							wp_delete_comment($comment_id);
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
		if ($type == 'retweet') {
			$link = $this->status_url($username, $id);
			$output = '<a href="'.esc_url($link).'" target="_blank">#'.$item->id.'</a> ('.__('Retweet Search', 'social').')';

			if ($item->ignored) {
				$output .= ' ('.__('Existing Comment', 'social').')';
			}

			return $output;
		}
		return '';
	}

	/**
	 * Imports a Tweet by URL.
	 *
	 * @param  int     $post_id
	 * @param  string  $url
	 * @return bool|string
	 */
	public function import_tweet_by_url($post_id, $url) {
		$post = get_post($post_id);

		$post->broadcasted_ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
		if (empty($post->broadcasted_ids)) {
			$post->broadcasted_ids = array();
		}

		$invalid = false;
		$url = explode('/', $url);
		$id = end($url);
		if (!empty($id) and !$this->is_original_broadcast($post, $id)) {
			Social::log('Importing tweet. -- ID: :id -- URL: :url', array(
				'id' => $id,
				'url' => implode('/', $url)
			));
			$url = 'http://api.twitter.com/1/statuses/show.json?id='.$id;
			$request = wp_remote_get($url);
			if (!is_wp_error($request)) {
				$response = apply_filters('social_response_body', $request['body'], $this->_key);
				if ($response !== null and !isset($response->error)) {
					$logger = Social_Aggregation_Log::instance($post->ID);

					$post->aggregated_ids = get_post_meta($post->ID, '_social_aggregated_ids', true);
					if (empty($post->aggregated_ids)) {
						$post->aggregated_ids = array();
					}

					if (!isset($post->aggregated_ids[$this->_key])) {
						$post->aggregated_ids[$this->_key] = array();
					}

					if (in_array($id, $post->aggregated_ids[$this->_key])) {
						$logger->add($this->_key, $response->id, 'Imported', true, array(
							'username' => $response->user->screen_name,
						));
					}
					else {
						$logger->add($this->_key, $response->id, 'Imported', false, array(
							'username' => $response->user->screen_name,
						));

						$post->aggregated_ids[$this->_key][] = $response->id;
						$post->results[$this->_key][$response->id] = (object) array(
							'id' => $response->id,
							'from_user_id' => $response->user->id,
							'from_user' => $response->user->screen_name,
							'text' => $response->text,
							'created_at' => $response->created_at,
							'profile_image_url' => $response->user->profile_image_url,
							'in_reply_to_status_id' => $response->in_reply_to_status_id,
							'raw' => $response,
						);

						$this->save_aggregated_comments($post, true);

						// Some cleanup...
						unset($post->aggregated_ids);
						unset($post->results);
					}
					$logger->save(true);
				}
				else {
					Social::log('Something went wrong... -- :response', array(
						'response' => print_r($response, true)
					));

					if (isset($response->error)) {
						if ($response->error == 'Sorry, you are not authorized to see this status.') {
							return 'protected';
						}
						else {
							$invalid = true;
						}
					}
				}
			}
			else {
				Social::log('Something went wrong... -- :response', array(
					'response' => print_r($request, true)
				));

				$invalid = true;
			}
		}
		else {
			Social::log('Something went wrong... -- ID: :id -- URL: :url', array(
				'id' => $id,
				'url' => implode('/', $url)
			));

			$invalid = true;
		}
		unset($post->broadcasted_ids);

		if ($invalid) {
			return 'invalid';
		}

		return true;
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
		return ($response == 'Status is a duplicate.');
	}

	/**
	 * Checks the response to see if the account has been deauthorized.
	 *
	 * @param  string  $response
	 * @param  bool    $check_invalid_key
	 * @return bool
	 */
	public function deauthorized($response, $check_invalid_key = false) {
		if (($check_invalid_key and $response = 'invalid key') or $response == 'Could not authenticate with OAuth.') {
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
	 * Returns the response message.
	 *
	 * @param  object  $body
	 * @param  string  $default
	 * @return mixed
	 */
	public function response_message($body, $default) {
		if (isset($body->response) and isset($body->response->text)) {
			return $body->response->text;
		}

		return $default;
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
	 * Stores the recovered meta.
	 *
	 * @param  int     $post_id
	 * @param  int     $broadcasted_id
	 * @param  object  $response
	 * @return bool
	 */
	public function recovered_meta($post_id, $broadcasted_id, $response) {
		// Load the broadcasted IDs meta
		$broadcasted = get_post_meta($post_id, '_social_broadcasted_ids', true);
		foreach ($broadcasted['twitter'] as $account_id => $broadcasted_items) {
			// Loop through the broadcasted items until we find a match.
			foreach ($broadcasted_items as $id => $data) {
				if ($broadcasted_id == $id) {
					// Store the recovered data.
					$data = array(
						'message' => $response->text,
						'account' => (object) array(
							'user' => $response->user
						)
					);
					$broadcasted['twitter'][$account_id][$id] = $data;
					update_post_meta($post_id, '_social_broadcasted_ids', $broadcasted);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Attempts to recover the tweet data for the broadcasted post.
	 *
	 * @param  int  $broadcasted_id
	 * @param  int  $post_id
	 * @return array|bool|mixed
	 */
	public function recover_broadcasted_tweet_data($broadcasted_id, $post_id) {
		$response = wp_remote_get('http://api.twitter.com/1/statuses/show/'.$broadcasted_id.'.json?include_entities=true');
		if (!is_wp_error($response)) {
			$body = json_decode($response['body']);
			if (!isset($body->error)) {
				$post_meta = get_post_meta($post_id, '_social_broadcasted_ids', true);
				if (!empty($post_meta) and isset($post_meta['twitter']) and isset($post_meta['twitter'][$body->user->id]) and isset($post_meta['twitter'][$body->user->id][$broadcasted_id])) {
					$post_meta['twitter'][$body->user->id][$broadcasted_id] = array(
						'message' => $body->text,
						'account' => $body->user
					);

					update_post_meta($post_id, '_social_broadcasted_ids', $post_meta);
				}
				return $body;
			}
		}

		return false;
	}

	/**
	 * Hack to fix the "Twitpocalypse" bug on 32-bit systems.
	 *
	 * @static
	 * @param  string  $body
	 * @return object
	 */
	public static function response_body($body) {
		$body = preg_replace('/"id":(\d+)/', '"id":"$1"', $body);
		$body = preg_replace('/"in_reply_to_status_id":(\d+)/', '"in_reply_to_status_id":"$1"', $body);
		return json_decode($body);
	}

	/**
	 * Adds the account ID to the rel for the author link.
	 *
	 * @static
	 * @param  string  $url
	 * @return string
	 */
	public static function get_comment_author_link($url) {
		global $comment;
		if ($comment->comment_type == 'social-twitter') {
			$status_id = get_comment_meta($comment->comment_ID, 'social_status_id', true);
			$output = str_replace("rel='", "rel='".$status_id." ", $url);

			$api_key = Social::option('twitter_anywhere_api_key');
			if (!empty($api_key)) {
				$output = str_replace("'>", "' style='display:none'>@", $output);
				$output .= '@'.get_comment_author($comment->comment_ID);
			}
			else {
				$output = str_replace("'>", "'>@", $output);
			}

			return $output;
		}

		return $url;
	}

} // End Social_Service_Twitter
