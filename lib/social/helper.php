<?php
/**
 * A generic helper for social services.
 *
 * @author Crowd Favorite
 * @copyright (c) 2010 Crowd Favorite. All Rights Reserved.
 * @package Social
 */
abstract class Social_Helper {

	/**
	 * Executes the request for the service.
	 *
	 * @abstract
	 * @param  string  $service  the service to user
	 * @param  string  $api      API endpoint to request
	 * @param  string  $public   the public key for the account
	 * @param  string  $private  the private key for the account
	 * @param  array   $params   parameters to pass to the API
	 * @param  string  $method   GET|POST, default: GET
	 * @return mixed
	 */
	public static function request($service, $api, $public, $private, array $params = array(), $method = 'GET') {
		$request = wp_remote_post(Social::$api_url.$service, array(
			'sslverify' => false,
			'body' => array(
				'api' => $api,
				'method' => $method,
				'public_key' => $public,
				'hash' => sha1($public.$private),
				'params' => json_encode($params)
			)
		));

		if (!is_wp_error($request)) {
			$body = $request['body'];
			if ($service == 'twitter') {
				$body = preg_replace('/"id":(\d+)/', '"id":"$1"', $body); // Hack for json_decode on 32-bit systems
			}
			$body = json_decode($body);
			if ($body->result != 'error') {
				return $body->response;
			}
		}

		// TODO handle errors
		return false;
	}

	/**
	 * Creates an account using the account information.
	 *
	 * @param  string  $service
	 * @param  string  $username
	 * @return int
	 */
	public static function create_user($service, $username) {
		// Make sure the user doesn't exist
		$user = get_userdatabylogin($username);
		if ($user === false) {
			$id = wp_create_user($username, wp_generate_password(20, false), self::create_email($service, $username));
			update_user_meta($id, Social::$prefix.'commenter', '1');
			update_user_option($id, 'show_admin_bar_front', 'false');
		}
		else {
			$id = $user->ID;
		}

		// Log the user in
		wp_set_current_user($id);
		add_filter('auth_cookie_expiration', array('Social', 'auth_cookie_expiration'));
		wp_set_auth_cookie($id, true);
		remove_filter('auth_cookie_expiration', array('Social', 'auth_cookie_expiration'));

		return $id;
	}

	/**
	 * Builds the authorize URL for the provided service.
	 *
	 * @static
	 * @param  string  $service
	 * @param  bool    $admin
	 * @return string
	 */
	public static function authorize_url($service, $admin = false) {
		$url = ($admin ? admin_url('options-general.php?page=social.php') : site_url());
		return Social::$api_url.$service.'/authorize?redirect_to='.urlencode($url).'&t='.time();
	}

	/**
	 * Builds the email for user creation.
	 *
	 * @param  string  $service
	 * @param  string  $alias
	 * @return string
	 */
	private static function create_email($service, $alias) {
		return $service.'.'.$alias.'@example.com';
	}

	/**
	 * Builds the settings URL for the plugin.
	 *
	 * @param  array  $params
	 * @return string
	 */
	public static function settings_url(array $params = null) {
		$path = 'options-general.php?page='.basename(SOCIAL_FILE);

		if ($params !== null) {
			foreach ($params as $key => $value) {
				$path .= '&'.$key.'='.urlencode($value);
			}
		}

		return admin_url($path);
	}

} // End Social_Helper
