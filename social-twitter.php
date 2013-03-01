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
		return array_merge($types, Social_Service_Twitter::comment_types());
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
		// pre-load the hashes for broadcasted tweets
		$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}
		global $wpdb;

		// we need comments to be keyed by ID, check for Tweet comments
		$tweet_comments = $_comments = $comment_ids = array();
		foreach ($comments as $key => $comment) {
			if (is_object($comment)) {
				$_comments['id_'.$comment->comment_ID] = $comment;
				if (in_array($comment->comment_type, Social_Service_Twitter::comment_types())) {
					$comment_ids[] = $comment->comment_ID;
					$tweet_comments['id_'.$comment->comment_ID] = $comment;
				}
			}
			else { // social items
				$_comments[$key] = $comment;
			}
		}

		// if no tweet comments, get out now
		if (!count($tweet_comments)) {
			return $comments;
		}

		// use our keyed array
		$comments = $_comments;
		unset($_comments);

		$social_map = array(); // key = social id, value = comment_ID
		$hash_map = array(); // key = hash, value = comment_ID
		$broadcasted_social_ids = array();
 		$broadcast_retweets = array(); // array of comments

		if (isset($broadcasted_ids['twitter'])) {
			foreach ($broadcasted_ids['twitter'] as $account_id => $broadcasted) {
				foreach ($broadcasted as $id => $data) {
					$broadcasted_social_ids[] = $id;
					// if we don't have a message saved for a tweet, try to get it so that we can use it next time
					if (empty($data['message'])) {
						$url = wp_nonce_url(home_url('index.php?social_controller=aggregation&social_action=retrieve_twitter_content&broadcasted_id='.$id.'&post_id='.$post_id), 'retrieve_twitter_content');
						wp_remote_get(str_replace('&amp;', '&', $url), array(
							'timeout' => 0.01,
							'blocking' => false,
						));
					}
					else {
						// create a hash from the broadcast so we can match retweets to it
						$hash = self::build_hash($data['message']);

						// This is stored as broadcasted and not the ID so we can easily store broadcasted retweets
						// instead of attaching retweets to non-existent comments.
						$hash_map[$hash] = 'broadcasted';
					}
				}
			}
		}

		// Load the comment meta
		$results = $wpdb->get_results("
			SELECT meta_key, meta_value, comment_id
			  FROM $wpdb->commentmeta
			 WHERE comment_id IN (".implode(',', $comment_ids).")
			   AND (
			       meta_key = 'social_in_reply_to_status_id'
			    OR meta_key = 'social_status_id'
			    OR meta_key = 'social_raw_data'
			    OR meta_key = 'social_profile_image_url'
			    OR meta_key = 'social_comment_type'
			)
		");

		// Set up social data for twitter comments
		foreach ($tweet_comments as $key => &$comment) {
			$comment->social_items = array();

			// Attach meta
			foreach ($results as $result) {
				if ($comment->comment_ID == $result->comment_id) {
					switch ($result->meta_key) {
						case 'social_raw_data':
							$comment->social_raw_data = json_decode(base64_decode($result->meta_value));
							break;
						case 'social_status_id':
							$social_map[$result->meta_value] = $result->comment_id;
						default:
							$comment->{$result->meta_key} = $result->meta_value;
					}
				}
			}

			// Attach hash
			if (isset($comment->social_raw_data) and isset($comment->social_raw_data->text)) {
				$text = trim($comment->social_raw_data->text);
			}
			else {
				$text = trim($comment->comment_content);
			}
			$comment->social_hash = self::build_hash($text);

			if (!isset($hash_map[$comment->social_hash])) {
				$hash_map[$comment->social_hash] = $comment->comment_ID;
			}
		}

		// merge data so that $comments has the data we've set up
		$comments = array_merge($comments, $tweet_comments);

		// set-up replies and retweets
		foreach ($tweet_comments as $key => &$comment) {
			if (is_object($comment)) {
				// set reply/comment parent
				if (!empty($comment->social_in_reply_to_status_id) and isset($social_map[$comment->social_in_reply_to_status_id])) {
					$comments[$key]->comment_parent = $social_map[$comment->social_in_reply_to_status_id];
				}

				// set retweets
				$rt_matched = false;
				if (isset($comment->social_raw_data) and isset($comment->social_raw_data->retweeted_status)) {
					// explicit match via API data
					$rt_id = $comment->social_raw_data->retweeted_status->id_str;
					if (in_array($rt_id, $broadcasted_social_ids)) {
						$broadcast_retweets[] = $comment;
						unset($comments[$key]);
						$rt_matched = true;
					}
					else if (isset($social_map[$rt_id]) and isset($comments['id_'.$social_map[$rt_id]])) {
						$comments['id_'.$social_map[$rt_id]]->social_items[$key] = $comment;
						unset($comments[$key]);
						$rt_matched = true;
					}
				}

				if (!$rt_matched) {
					// best guess via hashes
					$hash_match = $hash_map[$comment->social_hash];
					if ($hash_match != $comment->comment_ID) { // hash match to own tweet is expected, at minimum - set above
						if ($hash_match == 'broadcasted') {
							$broadcast_retweets[] = $comment;
						}
						else if (isset($comments['id_'.$hash_match])) {
							$comments['id_'.$hash_match]->social_items[$key] = $comment;
						}
						else {
							// Loop through the broadcasted retweets and see if this is a retweet of one of those.
							foreach ($broadcast_retweets as $retweet) {
								if ($retweet->comment_ID == $hash_match) {
									$broadcast_retweets[] = $comment;
									break;
								}
							}
						}
						unset($comments[$key]);
					}
				}
			}
		}

		if (!isset($comments['social_items'])) {
			$comments['social_items'] = array();
		}

		if (count($broadcast_retweets)) {
			$comments['social_items']['twitter'] = $broadcast_retweets;
		}

		return $comments;
	}


	/**
	 * Sets the raw data for the broadcasted post.
	 *
	 * @wp-filter social_broadcast_response
	 * @static
	 * @param  array                   $data
	 * @param  Social_Service_Account  $account
	 * @param  string                  $service_key
	 * @param  int                     $post_id
	 * @param  Social_Response         $response
	 * @return array
	 */
	public static function social_save_broadcasted_ids_data(array $data, Social_Service_Account $account, $service_key, $post_id, Social_Response $response = null) {
		if ($service_key == 'twitter') {
			if (!empty($response)) {
				$data['message'] = base64_encode(json_encode($response->body()->response));
			}
			$data['account'] = (object) array(
				'user' => $account->as_object()->user
			);
		}

		return $data;
	}

	/**
	 * Strips extra retweet data before comparing.
	 *
	 * @static
	 * @param  string  $text
	 * @return string
	 */
	private static function build_hash($text) {
		$text = explode(' ', $text);
		$content = '';
		foreach ($text as $_content) {
			if (!empty($_content) and strpos($_content, 'http://') === false) {
				if ($_content == 'RT' or preg_match('/@([\w_]+):/i', $_content)) {
					continue;
				}

				$content .= $_content.' ';
			}
		}

		return md5(trim($content));
	}

	/**
	 * Checks for a retweet via twitter API data and user perception.
	 *
	 * @static
	 * @param  stdClass  $comment
	 * @return bool
	 */
	public static function is_retweet($comment = null, $tweet = null) {
		$is_retweet = false;
		if (!is_null($comment)) {
			if (isset($comment->social_raw_data) and !empty($comment->social_raw_data->retweeted_status)) {
				$is_retweet = true;
			}
			if (social_substr($comment->comment_content, 0, 4) == 'RT @') {
				$is_retweet = true;
			}
		}
		else if (!is_null($tweet)) {
			if (!empty($tweet->retweeted_status)) {
				$is_retweet = true;
			}
			if (social_substr($tweet->text, 0, 4) == 'RT @') {
				$is_retweet = true;
			}
		}
		return $is_retweet;
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

	/**
	 * Adds messaging to the title.
	 *
	 * @static
	 * @param  string  $title
	 * @param  string  $key
	 * @return string
	 */
	public static function social_item_output_title($title, $key) {
		if ($key == 'twitter') {
			$title .= __(' retweeted this', 'social');
		}

		return $title;
	}

	/**
	 * Add a "reply to" field to broadcast form.
	 *
	 * @static
	 * @param  obj  $post
	 * @param  obj  $service
	 * @param  obj  $account
	 * @return void
	 */
	public static function social_broadcast_form_item_edit($post, $service, $account) {
		if ($service->key() != 'twitter') {
			return;
		}
		$field_name = str_replace('_content', '_in_reply_to', $account['field_name_content']);
?>
<a href="#" class="tweet-reply-link"><?php _e('Send as a reply', 'social'); ?></a>
<div class="tweet-reply-fields">
	<label for="<?php echo esc_attr($field_name); ?>"><?php _e('URL of Tweet (to reply to)', 'social'); ?></label>
	<input type="text" class="tweet-reply-field" name="<?php echo esc_attr($field_name); ?>" value="" id="<?php echo esc_attr($field_name); ?>" />
</div>
<?php
	}

} // End Social_Twitter

define('SOCIAL_TWITTER_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));
add_filter('get_avatar_comment_types', array('Social_Twitter', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Twitter', 'comments_array'), 10, 2);
add_filter('social_save_broadcasted_ids_data', array('Social_Twitter', 'social_save_broadcasted_ids_data'), 10, 5);
add_filter('social_item_output_title', array('Social_Twitter', 'social_item_output_title'), 10, 2);
add_action('social_broadcast_form_item_edit', array('Social_Twitter', 'social_broadcast_form_item_edit'), 10, 3);

}
