<?php
/**
 * Twitter implementation for Social.
 *
 * @package Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Facebook')) {

final class Social_Facebook {

	/**
	 * Registers Facebook to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 * @param  array  $services
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

			$url = explode('redirect_to', $url);
			$url = $url[0].'req_perms='.$perms.'&redirect_to'.$url[1];
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
		return array_merge($types, array('social-facebook', 'social-facebook-like'));
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
		if (is_object($comment) and $comment->comment_type == 'facebook-like') {
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

} // End Social_Facebook

define('SOCIAL_FACEBOOK_FILE', __FILE__);
	
// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));
add_filter('social_authorize_url', array('Social_Facebook', 'social_authorize_url'), 10, 2);
add_filter('social_comment_type_to_service', array('Social_Facebook', 'comment_type_to_service'));
add_filter('get_avatar', array('Social_Facebook', 'get_avatar'), 10, 5);
add_filter('get_avatar_comment_types', array('Social_Facebook', 'get_avatar_comment_types'));

}