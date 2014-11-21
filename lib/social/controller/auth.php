<?php
/**
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Auth extends Social_Controller {

	private function auth_nonce_key($salt = null) {
		if (is_null($salt)) {
			$salt = $this->auth_nonce_salt();
		}
		return md5('social_authentication'.AUTH_KEY.$salt);
	}

	private function auth_nonce_salt() {
		return md5(microtime().$_SERVER['SERVER_ADDR']);
	}

	/**
	 * Sets the nonce cookie then redirects to Sopresto.
	 *
	 * @return void
	 */
	public function action_authorize() {
		$proxy = apply_filters('social_authorize_url', Social::$api_url.$this->request->query('key').'/authorize/', $this->request->query('key'));
		if (strpos($proxy, Social::$api_url) !== false) {
			$salt = $this->auth_nonce_salt();
			$id = Social::wp39_create_nonce($this->auth_nonce_key($salt));
			$url = home_url('index.php');
			$args = array(
				'social_controller' => 'auth',
				'social_action' => 'authorized',
				'salt' => $salt,
			);

			if (is_admin()) {
				$args['is_admin'] = 'true';
				$args['user_id'] = get_current_user_id();
				if (defined('IS_PROFILE_PAGE')) {
					$args['personal'] = 'true';
					$url = add_query_arg('personal', 'true', $url);
				}
			}
			else {
				$post_id = $this->request->query('post_id');
				if ($post_id !== null) {
					$args['p'] = $post_id;
				}

				// Set the nonce cookie
				setcookie('social_auth_nonce', $id, 0, '/');
			}

			$proxy = add_query_arg(array(
				'v' => '2',
				'id' => $id,
				'response_url' => urlencode(add_query_arg($args, $url))
			), $proxy);

			$proxy = apply_filters('social_proxy_url', $proxy);
		}

		nocache_headers();
		Social::log('Authorizing with URL: '.$proxy);
		wp_redirect($proxy);
		exit;
	}

	/**
	 * Handles the authorized response.
	 *
	 * @return void
	 */
	public function action_authorized() {
		// User ID on the request? Must be set before nonce comparison
		$user_id = stripslashes($this->request->query('user_id'));
		if ($user_id !== null) {
			wp_set_current_user($user_id);
		}

		$nonce = stripslashes($this->request->post('id'));
		$salt = stripslashes($this->request->query('salt'));
		if (Social::wp39_verify_nonce($nonce, $this->auth_nonce_key($salt)) === false) {
			Social::log('Failed to verify authentication nonce.');
			echo json_encode(array(
				'result' => 'error',
				'message' => 'Invalid nonce',
			));
			exit;
		}

		Social::log('Authorizing with nonce :nonce.', array('nonce' => $nonce));

		$response = stripslashes_deep($this->request->post('response'));
		$account = (object) array(
			'keys' => (object) $response['keys'],
			'user' => (object) $response['user'],
		);
		$account->user = $this->social->kses($account->user);

		$class = 'Social_Service_'.$response['service'].'_Account';
		$account = new $class($account);

		$service = $this->social->service($response['service'])->account($account);
		$is_personal = false;
		$is_admin = $this->request->query('is_admin');
		if ($is_admin == 'true') {
			$user_id = get_current_user_id();

			$personal = $this->request->query('personal');
			if ($personal === 'true') {
				$is_personal = true;
				$account->personal(true);
			}
			else {
				$account->universal(true);
			}

			$use_pages = $this->request->query('use_pages');
			if ($use_pages == 'true') {
				$account->use_pages($is_personal, true);
			}
		}
		else {
			$user_id = $service->create_user($account, $nonce);
			$account->personal(true);
			$is_personal = true;

			// Store avatar
			update_user_meta($user_id, 'social_avatar', $account->avatar());
			update_user_meta($user_id, 'show_admin_bar_front', 'false');
		}

		if ($user_id !== false) {
			Social::log('Saving account #:id.', array(
				'id' => $account->id(),
			));
			$service->save($is_personal);

			// Remove the service from the errors?
			$deauthed = get_option('social_deauthed');
			if (isset($deauthed[$response['service']][$account->id()])) {
				unset($deauthed[$response['service']][$account->id()]);
				update_option('social_deauthed', $deauthed);

				// Remove from the global broadcast content as well.
				$this->social->remove_from_default_accounts($response['service'], $account->id());
			}

			// 2.0 Upgrade
			if ($response['service'] == 'facebook') {
				delete_user_meta(get_current_user_id(), 'social_2.0_upgrade');
			}

			echo json_encode(array(
				'result' => 'success',
				'message' => 'User created',
			));
		}
		else {
			echo json_encode(array(
				'result' => 'error',
				'message' => 'Failed to create user',
			));
		}
		exit;
	}

	/**
	 * Disconnects an account.
	 *
	 * @return void
	 */
	public function action_disconnect() {
		$id = $this->request->query('id');
		$service_key = $this->request->query('service');
		$personal = false;
		if (defined('IS_PROFILE_PAGE')) {
			Social::log('Disconnecting a personal account #:id', array('id' => $id));
			$personal = true;
		}
		else {
			Social::log('Disconnecting a universal account #:id', array('id' => $id));
		}

		$this->social->service($service_key)->disconnect($id);
		$this->social->remove_from_default_accounts($service_key, $id);

		// Flush the cache
		wp_cache_delete('services', 'social');

		if (is_admin()) {
			wp_redirect(Social::settings_url(array(), $personal));
		}
		else {
			wp_logout();
			wp_redirect($this->request->query('redirect_to'));
		}
		exit;
	}

	/**
	 * Renders the new comment form.
	 *
	 * @return void
	 */
	public function action_reload_form() {
		if (!$this->request->is_ajax()) {
			exit;
		}

		if (isset($_COOKIE['social_auth_nonce'])) {
			$cookie_nonce = stripslashes($_COOKIE['social_auth_nonce']);
			// Find the user by NONCE.
			global $wpdb;
			$user_id = $wpdb->get_var($wpdb->prepare("
				SELECT user_id
				  FROM $wpdb->usermeta
				 WHERE meta_key = %s
			", 'social_auth_nonce_'.$cookie_nonce));

			if ($user_id !== null) {
				Social::log('Found user #:id using nonce :nonce.', array(
					'id' => $user_id,
					'nonce' => $cookie_nonce
				));

				// Log the user in
				wp_set_current_user($user_id);
				add_filter('auth_cookie_expiration', array($this->social, 'auth_cookie_expiration'));
				wp_set_auth_cookie($user_id, true);
				remove_filter('auth_cookie_expiration', array($this->social, 'auth_cookie_expiration'));

				delete_user_meta($user_id, 'social_auth_nonce_'.$cookie_nonce);
				setcookie('social_auth_nonce', '', -3600, '/');

				$post_id = $this->request->query('post_id');
				$form = trim(Social_Comment_Form::instance($post_id)->render());
				echo json_encode(array(
					'result' => 'success',
					'html' => $form,
					'disconnect_url' => wp_loginout('', false)
				));
			}
			else {
				Social::log('Failed to find the user using nonce :nonce.', array(
					'nonce' => $_COOKIE['social_auth_nonce']
				));

				echo json_encode(array(
					'result' => 'error',
					'html' => 'not logged in',
				));
			}
		}
		else {
			echo json_encode(array(
				'result' => 'error',
				'html' => 'not logged in',
			));
		}
		exit;
	}

} // End Social_Controller_Auth
