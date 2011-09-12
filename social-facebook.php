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

} // End Social_Facebook

define('SOCIAL_FACEBOOK_FILE', __FILE__);
	
// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));
add_filter('social_authorize_url', array('Social_Facebook', 'social_authorize_url'), 10, 2);

}