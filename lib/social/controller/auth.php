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

		$save = true;
		$service = $this->social->service($data->service)->account($account);
		if (is_admin()) {
			if (defined('IS_PROFILE_PAGE')) {
				$account->personal(true);
			}
			else {
				$account->universal(true);
			}
		}
		else {
			if (!is_user_logged_in() and !$service->create_user($account)) {
				$save = false;
			}
			$account->personal(true);
		}

		// Save the service
		if ($save) {
			$service->save($account);
		}

		// Remove the service from the errors?
		$deauthed = get_option('social_deauthed');
		if (isset($deauthed[$data->service][$account->id()])) {
			unset($deauthed[$data->service][$account->id()]);
			update_option('social_deauthed', $deauthed);

			// Remove from the global broadcast content as well.
			$this->remove_from_xmlrpc($data->service, $account->id());
		}

		// 1.1 Upgrade
		if ($data->service == 'facebook') {
			delete_user_meta(get_current_user_id(), 'social_1.1_upgrade');
		}

		$pages = array();
		$view = Social_View::factory('connect/authorized', array(
			'save' => $save,
		));
		if ($this->request->post('with_manage_pages') === 'true') {
			$data = array(
				'title' => '',
				'show_pages' => true,
			);
		}
		else {
			$data = array(
				'title' => 'Authorized',
				'show_pages' => false,
			);
		}
		echo $view->set($data);
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
			$this->social->remove_from_xmlrpc($service_key, $id);
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
		
		if (!is_user_logged_in()) {
			echo json_encode(array(
				'result' => 'error',
				'html' => 'not logged in',
			));
		}
		else {
			$post_id = $this->request->query('post_id');
			$form = trim(Social_Comment_Form::instance($post_id)->render());
			echo json_encode(array(
				'result' => 'success',
				'html' => $form,
				'disconnect_url' => wp_loginout('', false)
			));
		}
		exit;
	}

} // End Social_Controller_Auth
