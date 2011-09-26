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
	 * Pre-processor to the comments to match up in_reply_to_status_ids.
	 *
	 * @wp-filter comments_array
	 * @static
	 * @param  array  $comments
	 * @param  int    $post_id
	 * @return array
	 */
	public static function comments_array(array $comments, $post_id) {
		global $wpdb;

		$results = $wpdb->get_results($wpdb->prepare("
			SELECT m.meta_value AS in_reply_to_id, m.comment_id
			  FROM $wpdb->comments AS c
			  JOIN $wpdb->commentmeta AS m
			    ON c.comment_ID = m.comment_id
			 WHERE c.comment_post_ID = %s
			   AND m.meta_key = 'social_in_reply_to_status_id'
		", $post_id));
		if (!empty($results)) {
			$in_reply_ids = array();
			foreach ($results as $result) {
				if (!isset($in_reply_ids[$result->in_reply_to_id])) {
					$in_reply_ids[$result->in_reply_to_id] = array();
				}
				$in_reply_ids[$result->in_reply_to_id][] = $result->comment_id;
			}

			// Find all the parent posts
			$wheres = array();
			foreach ($in_reply_ids as $item) {
				$wheres[] = "(`meta_key` = 'social_status_id' AND `meta_value` = '%s')";
			}
			$results = $wpdb->get_results($wpdb->prepare("
				SELECT comment_id, meta_value
				  FROM $wpdb->commentmeta
				 WHERE ".implode(' OR ', $wheres), array_keys($in_reply_ids)));

			$parents = array();
			if (!empty($results)) {
				foreach ($results as $result) {
					if (isset($in_reply_ids[$result->meta_value])) {
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
add_filter('comments_array', array('Social_Twitter', 'comments_array'), 10, 2);
add_action('wp_enqueue_scripts', array('Social_Twitter', 'enqueue_assets'));

}