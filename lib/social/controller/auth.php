<?php
/**
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Auth extends Social_Controller {

	/**
	 * Handles the authorized response.
	 *
	 * @return void
	 */
	public function action_authorized() {
		if (!wp_verify_nonce($this->request->query('_nonce', 'social_authentication'))) {
			echo json_encode(array(
				'result' => 'error',
				'message' => 'Invalid nonce',
			));
			exit;
		}

		// Need to call stripslashes as Sopresto is adding slashes onto the payload.
		$data = stripslashes($this->request->post('data'));
		if (strpos($data, "\r") !== false) {
			$data = str_replace(array("\r\n", "\r"), "\n", $data);
		}
		$data = json_decode($data);

		$account = (object) array(
			'keys' => $data->keys,
			'user' => $data->user
		);
		$account->user = $this->social->kses($account->user);

		$class = 'Social_Service_'.$data->service.'_Account';
		$account = new $class($account);
		$account->personal(true);

		$service = $this->social->service($data->service)->account($account);
		if (is_admin()) {
			if (defined('IS_PROFILE_PAGE')) {
				$account->personal(true);
			}
			else {
				$account->universal(true);
			}
		}

		$user_id = $service->create_user($account, $this->request->query('_nonce'));
		if ($user_id !== false) {
			$service->save($account);

			// Remove the service from the errors?
			$deauthed = get_option('social_deauthed');
			if (isset($deauthed[$data->service][$account->id()])) {
				unset($deauthed[$data->service][$account->id()]);
				update_option('social_deauthed', $deauthed);

				// Remove from the global broadcast content as well.
				$this->remove_from_xmlrpc($data->service, $account->id());
			}

			// 1.5 Upgrade
			if ($data->service == 'facebook') {
				delete_user_meta($user_id, 'social_1.5_upgrade');
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
		if (defined('IS_PROFILE_PAGE')) {
			$service = $this->social->service($service_key);
		}
		else {
			$service = $this->social->service($service_key);
			$this->social->remove_from_xmlrpc($service_key, $id);
		}
		$service->disconnect($id);

		if (is_admin()) {
			wp_redirect(Social_Helper::settings_url());
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
