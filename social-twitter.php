<?php
/**
 * Twitter implementation for Social.
 *
 * @package    Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Twitter')) {

final class Social_Twitter {

	/**
	 * Registers Twitter to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 *
	 * @param  array  $services
	 *
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'twitter';
		return $services;
	}

	/**
	 * Adds to the avatar comment types array.
	 *
	 * @static
	 * @param  array  $types
	 * @return array
	 */
	public static function get_avatar_comment_types(array $types) {
		return array_merge($types, array('social-twitter'));
	}

	/**
	 * Pre-processor to the comments.
	 *
	 * @wp-filter social_comments_array
	 * @static
	 * @param  array  $comments
	 * @param  int    $post_id
	 * @return array
	 */
	public static function comments_array(array $comments, $post_id) {
		global $wpdb;

		$comment_ids = array();
		foreach ($comments as $comment) {
			if ($comment->comment_type == 'social-twitter') {
				$comment_ids[] = $comment->comment_ID;
			}
		}

		$working_comments = $comments;
		if (count($comment_ids)) {
			$working_comments = array();
			$comment_hashes = array();
			$broadcasted_retweets = array();
			$in_reply_to_ids = array();

			// Store the broadcasted hashses
			$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
			if (empty($broadcasted_ids)) {
				$broadcasted_ids = array();
			}

			if (isset($broadcasted_ids['twitter'])) {
				$update_broadcasted = false;

				foreach ($broadcasted_ids['twitter'] as $account_id => $broadcasted) {
					foreach ($broadcasted as $id => $data) {
						// Empty message? (pre-2.0 Twitter comment)
						if (empty($data['message'])) {
							$twitter = Social::instance()->service('twitter');
							if ($twitter !== false and count($twitter->accounts())) {
								foreach ($twitter->accounts() as $account) {
									$response = $twitter->request($account, 'statuses/show/'.$id);
									if ($response !== false and isset($response->body()->response)) {
										$update_broadcasted = true;
										$message = $response->body()->response->text;
										$broadcasted_ids['twitter'][$account_id][$id] = array(
											'message' => $message
										);
									}
								}
							}
						}

						$hash = self::build_retweet_hash($data['message'], false);
						// This is stored as broadcasted and not the ID so we can easily store broadcasted retweets
						// instead of attaching retweets to non-existent comments.
						$comment_hashes[$hash] = 'broadcasted';
					}
				}

				if ($update_broadcasted) {
					update_post_meta($post_id, '_social_broadcasted_ids', $broadcasted_ids);
				}
			}

			// Load the comment meta
			$results = $wpdb->get_results("
				SELECT meta_key, meta_value, comment_id
				  FROM $wpdb->commentmeta
				 WHERE comment_id IN (".implode(',', $comment_ids).")
				   AND meta_key = 'social_in_reply_to_status_id'
				    OR meta_key = 'social_status_id'
				    OR meta_key = 'social_raw_data'
				    OR meta_key = 'social_profile_image_url'
				    OR meta_key = 'social_comment_type'
			");

			// Store meta and comment hashses
			foreach ($comments as $comment) {
				if (is_object($comment)) {
					if ($comment->comment_type == 'social-twitter') {
						foreach ($results as $result) {
							if ($comment->comment_ID == $result->comment_id) {
								if ($result->meta_key == 'social_raw_data') {
									$result->meta_value = json_decode(base64_decode($result->meta_value));
								}
								else {
									if ($result->meta_key == 'social_status_id') {
										$in_reply_to_ids[$result->meta_value] = $result->comment_id;
									}
								}

								$comment->{$result->meta_key} = $result->meta_value;
							}
						}

						// Comment a retweet?
						if (substr($comment->comment_content, 0, 4) != 'RT @') {
							if (isset($comment->social_status_id)) {
								// Hash
								if (isset($comment->social_raw_data)) {
									$hash = self::build_retweet_hash($comment->comment_content, false);
									$comment_hashes[$hash] = $comment->social_status_id;
								}

								$comment->social_items = array();
								$working_comments[$comment->social_status_id] = $comment;
							}
						}
						else {
							$comment->social_retweet_hash = self::build_retweet_hash($comment->comment_content);
						}
					}
				}
			}

			// Loop through the comments again and see if they're a retweet of anything
			foreach ($comments as $comment) {
				if (is_object($comment)) {
					if ($comment->comment_type == 'social-twitter') {
						// Match comments up to their parents, if they're a reply.
						if (isset($comment->social_in_reply_to_status_id) and isset($in_reply_to_ids[$comment->social_in_reply_to_status_id])) {
							$comment->comment_parent = $in_reply_to_ids[$comment->social_in_reply_to_status_id];
						}

						if (isset($comment->social_retweet_hash) and isset($comment_hashes[$comment->social_retweet_hash])) {
							if (isset($working_comments[$comment_hashes[$comment->social_retweet_hash]])) {
								$working_comments[$comment_hashes[$comment->social_retweet_hash]]->social_items[] = $comment;
							}
							else if ($comment_hashes[$comment->social_retweet_hash] == 'broadcasted') {
								$broadcasted_retweets[] = $comment;
							}
						}
					}
				    else {
						$working_comments[] = $comment;
					}
				}
			}

			// Merge social items
			$working_comments['social_items'] = array();
			if (isset($comments['social_items'])) {
				$working_comments['social_items'] = $comments['social_items'];
			}

			if (!isset($working_comments['social_items']['twitter'])) {
				$working_comments['social_items']['twitter'] = $broadcasted_retweets;
			}
		}

		return $working_comments;
	}

	/**
	 * Enqueues the @Anywhere script.
	 *
	 * @static
	 * @return void
	 */
	public static function enqueue_assets() {
		$api_key = Social::option('twitter_anywhere_api_key');
		if (!empty($api_key)) {
			wp_enqueue_script('twitter_anywhere', 'http://platform.twitter.com/anywhere.js?id='.$api_key, array('social_js'), Social::$version, true);
		}
	}

	/**
	 * Strips extra retweet data before comparing.
	 *
	 * @static
	 * @param  string  $text
	 * @param  bool    $retweet   is this a reply comment?
	 * @return string
	 */
	private static function build_retweet_hash($text, $retweet = true) {
		$text = explode(' ', trim($text));
		$content = '';
		foreach ($text as $_content) {
			if (!empty($_content) and strpos($_content, 'http://') === false) {
				if ($retweet and ($_content == 'RT' or preg_match('/@([\w_]+):/i', $_content))) {
					continue;
				}

				$content .= $_content.' ';
			}
		}

		return md5(trim($content));
	}

	/**
	 * Adds a retweet to the original broadcasted post social items stack.
	 *
	 * @static
	 * @param  int    $comment_id
	 * @param  array  $comments
	 * @param  array  $social_items
	 */
	private static function add_to_social_items($comment_id, &$comments, &$social_items) {
		$object = null;
		$_comments = array();
		foreach ($comments as $id => $comment) {
			if (is_int($id)) {
				if ($comment->comment_ID == $comment_id) {
					$object = $comment;
				}
				else {
					$_comments[] = $comment;
				}
			}
			else {
				if (isset($_comments[$id])) {
					$_comments[$id] = array_merge($_comments[$id], $comment);
				}
				else {
					$_comments[$id] = $comment;
				}
			}
		}
		$comments = $_comments;

		if ($object !== null) {
			if (!isset($social_items['twitter'])) {
				$social_items['twitter'] = array();
			}

			$social_items['twitter'][$comment_id] = $object;
		}
	}

} // End Social_Twitter

define('SOCIAL_TWITTER_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));
add_filter('get_avatar_comment_types', array('Social_Twitter', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Twitter', 'comments_array'), 10, 2);
add_action('wp_enqueue_scripts', array('Social_Twitter', 'enqueue_assets'));

}
