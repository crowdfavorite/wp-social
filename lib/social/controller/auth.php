<?php
/**
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Auth extends Social_Controller {

	/**
	 * Sets the nonce cookie then redirects to Sopresto.
	 *
	 * @return void
	 */
	public function action_authorize() {
		$proxy = urldecode($this->request->query('target'));
		if (strpos($proxy, Social::$api_url) !== false) {
			$id = wp_create_nonce('social_authentication');
			$url = '?social_controller=auth&social_action=authorized';
			if (is_admin()) {
				if (defined('IS_PROFILE_PAGE')) {
					$url .= '&personal=true';
				}
				$url = admin_url($url.'&user_id='.get_current_user_id());
			}
			else {
				$post_id = $this->request->query('post_id');
				if ($post_id !== null) {
					$url .= '&p='.$post_id;
				}
				$url = site_url($url);

				// Set the nonce cookie
				setcookie('social_auth_nonce', $id, 0, '/');
			}

			if (strpos('?', $proxy) === false) {
				$proxy .= '?';
			}
			else {
				$proxy .= '&';
			}
			$proxy .= 'v=2&response_url='.urlencode($url).'&id='.$id;
		}

		wp_redirect($proxy);
	}

	/**
	 * Handles the authorized response.
	 *
	 * @return void
	 */
	public function action_authorized() {
		// User ID on the request?
		$user_id = $this->request->query('user_id');
		if ($user_id !== null) {
			wp_set_current_user($user_id);
		}

		$nonce = $this->request->post('id');
		if (wp_verify_nonce($nonce, 'social_authentication') === false) {
			Social::log('Failed to verify authentication nonce.');
			echo json_encode(array(
				'result' => 'error',
				'message' => 'Invalid nonce',
			));
			exit;
		}

		Social::log('Authorizing with nonce :nonce.', array('nonce' => $nonce));

		$response = $this->request->post('response');
		$account = (object) array(
			'keys' => (object) $response['keys'],
			'user' => (object) $response['user'],
		);
		$account->user = $this->social->kses($account->user);

		$class = 'Social_Service_'.$response['service'].'_Account';
		$account = new $class($account);

		$service = $this->social->service($response['service'])->account($account);
		if (is_admin()) {
			$user_id = get_current_user_id();

			$personal = $this->request->query('personal');
			if ($personal === 'true') {
				$account->personal(true);
			}
			else {
				$account->universal(true);
			}
		}
		else {
			$user_id = $service->create_user($account, $nonce);
			$account->personal(true);
		}

		if ($user_id !== false) {
			$service->save($account);

			// Remove the service from the errors?
			$deauthed = get_option('social_deauthed');
			if (isset($deauthed[$response['service']][$account->id()])) {
				unset($deauthed[$response['service']][$account->id()]);
				update_option('social_deauthed', $deauthed);

				// Remove from the global broadcast content as well.
				$this->social->remove_from_default_accounts($response['service'], $account->id());
			}

			// 1.1 Upgrade
			if ($response['service'] == 'facebook') {
				delete_user_meta(get_current_user_id(), 'social_1.1_upgrade');
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
			$personal = true;
			$service = $this->social->service($service_key);
		}
		else {
			$service = $this->social->service($service_key);
			$this->social->remove_from_default_accounts($service_key, $id);
		}
		$service->disconnect($id);

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

		// Find the user by NONCE.
		global $wpdb;
		$user_id = $wpdb->get_var($wpdb->prepare("
			SELECT user_id
			  FROM $wpdb->usermeta
			 WHERE meta_key = %s
		", 'social_auth_nonce_'.$_COOKIE['social_auth_nonce']));

		if ($user_id !== null) {
			// Log the user in
			wp_set_current_user($user_id);
			add_filter('auth_cookie_expiration', array($this->social, 'auth_cookie_expiration'));
			wp_set_auth_cookie($user_id, true);
			remove_filter('auth_cookie_expiration', array($this->social, 'auth_cookie_expiration'));

			$post_id = $this->request->query('post_id');
			$form = trim(Social_Comment_Form::instance($post_id)->render());
			echo json_encode(array(
				'result' => 'success',
				'html' => $form,
				'disconnect_url' => wp_loginout('', false)
			));

			delete_user_meta($user_id, 'social_auth_nonce_'.$_COOKIE['social_auth_nonce']);
			setcookie('social_auth_nonce', '', -3600, '/');
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
