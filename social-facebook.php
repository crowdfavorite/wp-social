<?php
/**
 * Twitter implementation for Social.
 *
 * @package    Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Facebook')) {

final class Social_Facebook {

	/**
	 * Registers Facebook to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 *
	 * @param  array  $services
	 *
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'facebook';
		return $services;
	}

	/**
	 * Adds the permissions stuff in for Facebook.
	 *
	 * @static
	 * @wp-filter  social_authorize_url
	 * @param  string  $url  authorization url
	 * @param  string  $key  service key
	 * @return string
	 */
	public static function social_authorize_url($url, $key) {
		if ($key == 'facebook') {
			$perms = 'publish_stream';
			if (is_admin()) {
				$perms .= ',read_stream,offline_access';
			}

			$url = $url.'?req_perms='.$perms;
		}

		return $url;
	}

	/**
	 * Quick hook to fix the comment type to service.
	 *
	 * @static
	 * @wp-filter  social_comment_type_to_service
	 * @param  string  $type
	 * @return string
	 */
	public static function comment_type_to_service($type) {
		if ($type == 'facebook-like') {
			$type = 'facebook';
		}

		return $type;
	}

	/**
	 * Adds to the avatar comment types array.
	 *
	 * @static
	 * @param  array  $types
	 * @return array
	 */
	public static function get_avatar_comment_types(array $types) {
		return array_merge($types, array(
			'social-facebook',
			'social-facebook-like'
		));
	}

	/**
	 * Gets the avatar based on the comment type.
	 *
	 * @static
	 * @wp-filter  get_avatar
	 * @param  string  $avatar
	 * @param  object  $comment
	 * @param  int     $size
	 * @param  string  $default
	 * @param  string  $alt
	 * @return string
	 */
	public static function get_avatar($avatar, $comment, $size, $default, $alt) {
		if (is_object($comment) and $comment->comment_type == 'social-facebook-like') {
			$image = get_comment_meta($comment->comment_ID, 'social_profile_image_url', true);
			if ($image !== null) {
				$type = '';
				if (is_object($comment)) {
					$type = $comment->comment_type;
				}
				return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$type}' height='25' width='25' />";
			}
		}
		return $avatar;
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
			if (is_object($comment) and $comment->comment_type == 'social-facebook-like') {
				$comment_ids[] = $comment->comment_ID;
			}
		}

		if (count($comment_ids)) {
			$results = $wpdb->get_results("
				SELECT meta_key, meta_value, comment_id
				  FROM $wpdb->commentmeta
				 WHERE comment_id IN (".implode(',', $comment_ids).")
				   AND meta_key = 'social_status_id'
				    OR meta_key = 'social_profile_image_url'
				    OR meta_key = 'social_comment_type'
			");

			$social_items = array();
			if (isset($comments['social_items'])) {
				$social_items = $comments['social_items'];
			    unset($comments['social_items']);
			}

			foreach ($comments as $key => $comment) {
				if (is_object($comment)) {
					if ($comment->comment_type == 'social-facebook-like') {
						foreach ($results as $result) {
							if ($result->comment_id == $comment->comment_ID) {
								$comment->{$result->meta_key} = $result->meta_value;
							}
						}
						
						if (!isset($social_items['facebook'])) {
							$social_items['facebook'] = array();
						}

						$social_items['facebook'][] = $comment;
					    unset($comments[$key]);
					}
				    else {
					    $comments[$key] = $comment;
				    }
				}
			    else {
				    $comments[$key] = $comment;
			    }
			}

			if (count($social_items)) {
				sort($comments);
			    $comments['social_items'] = $social_items;
			}
		}

		return $comments;
	}

	/**
	 * Adds the Facebook Pages checkbox to the button.
	 *
	 * @static
	 * @param  string                   $button
	 * @param  Social_Service_Facebook  $service
	 * @param  bool                     $profile_page
	 * @return string
	 */
	public static function social_service_button($button, $service, $profile_page = false) {
		if ($service->key() == 'facebook') {
			$label = '<label for="social-facebook-pages">'
			       . '    <input type="checkbox" id="social-facebook-pages" value="true" />'
			       . '    Connect with Pages support'
			       . '</label>';

			if (!$profile_page) {
				$button = explode('</div>', $button);
				$button = $button[0].$label.'</div>';
			}
		}
		return $button;
	}

	/**
	 * Adds the manage pages permission onto the URL.
	 *
	 * @static
	 * @param  string  $url
	 * @return array|string
	 */
	public static function social_proxy_url($url) {
		if (isset($_GET['use_pages']) and strpos($url, 'req_perms') !== false) {
			$url = explode('req_perms=', $url);
		    $url = $url[0].'req_perms=manage_pages,'.$url[1];
		}
		return $url;
	}

} // End Social_Facebook

define('SOCIAL_FACEBOOK_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));
add_filter('social_authorize_url', array('Social_Facebook', 'social_authorize_url'), 10, 2);
add_filter('social_comment_type_to_service', array('Social_Facebook', 'comment_type_to_service'));
add_filter('get_avatar', array('Social_Facebook', 'get_avatar'), 10, 5);
add_filter('get_avatar_comment_types', array('Social_Facebook', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Facebook', 'comments_array'), 10, 2);
add_filter('social_service_button', array('Social_Facebook', 'social_service_button'), 10, 3);
add_filter('social_proxy_url', array('Social_Facebook', 'social_proxy_url'));

}
