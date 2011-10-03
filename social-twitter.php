<?php
/**
 * Twitter implementation for Social.
 *
 * @package Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Twitter')) {

final class Social_Twitter {

	/**
	 * Registers Twitter to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 * @param  array  $services
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
            $comment_ids[] = $comment->comment_ID;
        }

        if (count($comment_ids)) {
	        $results = $wpdb->get_results("
                SELECT meta_key, meta_value, comment_id
                  FROM $wpdb->commentmeta
                 WHERE comment_id IN (".implode(',', $comment_ids).")
                   AND meta_key = 'social_in_reply_to_status_id'
                    OR meta_key = 'social_status_id'
                    OR meta_key = 'social_raw_data'
                    OR meta_key = 'social_profile_image_url'
            ");

			$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
	        if (empty($broadcasted_ids)) {
		        $broadcasted_ids = array();
	        }

	        $_results = array();
            $in_reply_ids = array();
            foreach ($results as $result) {
				if ($result->meta_key == 'social_in_reply_to_status_id') {
					if (!isset($in_reply_ids[$result->meta_value])) {
						$in_reply_ids[$result->meta_value] = array();
					}
					$in_reply_ids[$result->meta_value][] = $result->comment_id;
				}
				else if ($result->meta_key == 'social_raw_data') {
					$raw = json_decode(base64_decode($result->meta_value));
					if (isset($broadcasted_ids['twitter']) and isset($raw->retweeted_status) and isset($raw->retweeted_status->id)) {
						foreach ($broadcasted_ids['twitter'] as $account_id => $_broadcasted_ids) {
							if (in_array($raw->retweeted_status->id, $_broadcasted_ids)) {
								Social_Plugin::add_to_social_items('twitter', $result->comment_id, $comments, true);
							}
						}
					}
				}
				else {
					$_results[] = $result;
				}
            }

			if (count($_results)) {
				$parents = array();
				foreach ($_results as $result) {
					if (in_array($result->meta_key, array('social_status_id', 'social_profile_image_url')) and isset($comments['social_items'])) {
                        if (isset($comments['social_items']['parent']) and
                            isset($comments['social_items']['parent']['twitter']) and
                            isset($comments['social_items']['parent']['twitter'][$result->comment_id])) {
                            $comments['social_items']['parent']['twitter'][$result->comment_id]->{$result->meta_key} = $result->meta_value;
                        }
                    }
                    else if (isset($in_reply_ids[$result->meta_value])) {
                        foreach ($in_reply_ids[$result->meta_value] AS $comment_id) {
                            $parents[$comment_id] = $result->comment_id;
                        }
                    }
				}
			}

            $_comments = array();
            if (!empty($parents)) {
                foreach ($comments as $comment) {
                    if (isset($parents[$comment->comment_ID])) {
                        $comment->comment_parent = $parents[$comment->comment_ID];
                    }

                    $_comments[] = $comment;
                }

                $comments = $_comments;
            }
        }
		return $comments;
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

} // End Social_Twitter

define('SOCIAL_TWITTER_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));
add_filter('get_avatar_comment_types', array('Social_Twitter', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Twitter', 'comments_array'), 10, 2);
add_action('wp_enqueue_scripts', array('Social_Twitter', 'enqueue_assets'));

}