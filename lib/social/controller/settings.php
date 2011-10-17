<?php
/**
 * Settings Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Settings extends Social_Controller {

	/**
	 * Handles the request for Social's settings.
	 *
	 * @return void
	 */
	public function action_index() {
		if ($this->request->post('submit')) {
			Social::option('broadcast_format', $this->request->post('social_broadcast_format'));
			Social::option('debug', $this->request->post('social_debug'));

			if (!Social::option('debug')) {
				delete_option('social_log_write_error');
			}

			// Store the default accounts
			if (is_array($this->request->post('social_default_accounts'))) {
				$accounts = array();
				foreach ($this->request->post('social_default_accounts') as $account) {
					$account = explode('|', $account);
					$accounts[$account[0]][] = $account[1];
				}
				Social::option('default_accounts', $accounts);
			}
			else {
				delete_option('social_default_accounts');
			}

			// Anywhere key
			if ($this->request->post('social_twitter_anywhere_api_key') !== null) {
				Social::option('twitter_anywhere_api_key', $this->request->post('social_twitter_anywhere_api_key'));
			}

			// System CRON
			if ($this->request->post('social_fetch_comments') !== null) {
				Social::option('fetch_comments', $this->request->post('social_fetch_comments'));

				// Unschedule the CRONs
				if (($timestamp = wp_next_scheduled('social_cron_15_core')) !== false) {
					wp_unschedule_event($timestamp, 'social_cron_15_core');
				}
				if (($timestamp = wp_next_scheduled('social_cron_60_core')) !== false) {
					wp_unschedule_event($timestamp, 'social_cron_60_core');
				}
			}

			do_action('social_settings_save');

			wp_redirect(Social::settings_url(array('saved' => 'true')));
			exit;
		}

		echo Social_View::factory('wp-admin/options', array(
			'services' => $this->social->services(),
		));
	}

	/**
	 * Suppresses the enable notice.
	 *
	 * @return void
	 */
	public function action_suppress_enable_notice() {
		update_user_meta(get_current_user_id(), 'social_suppress_enable_notice', 'true');
	}

	/**
	 * Clears the deauthorized notice.
	 *
	 * @return void
	 */
	public function action_clear_deauth() {
		$id = $_GET['clear_deauth'];
		$service = $_GET['service'];
		$deauthed = get_option('social_deauthed', array());
		if (isset($deauthed[$service][$id])) {
			unset($deauthed[$service][$id]);
			update_option('social_deauthed', $deauthed);

			$this->social->remove_from_default_accounts($service, $id);
		}
	}

	/**
	 * Clears the log write error notice.
	 *
	 * @return void
	 */
	public function action_clear_log_write_error() {
		delete_option('social_log_write_error');
	}

	/**
	 * Clears the 2.0 upgrade notice.
	 *
	 * @return void
	 */
	public function action_clear_2_0_upgrade() {
		delete_user_meta(get_current_user_id(), 'social_2.0_upgrade');
	}

	/**
	 * Loads the account's Facebook pages.
	 *
	 * @return void
	 */
	public function action_get_facebook_pages() {
		if (!$this->request->is_ajax()) {
			wp_die('Oops, this method can only be accessed via an AJAX request');
		}

		$account_id = $this->request->query('account_id');
		$is_profile = ($this->request->query('profile') == 'true');
		$service = $this->social->service('facebook');
		if ($service !== false) {
			$accounts = $service->accounts();
			if (isset($accounts[$account_id])) {
				$pages = $service->get_pages($accounts[$account_id], $is_profile,false);
				if (count($pages)) {
					$html = Social_View::factory('wp-admin/parts/facebook/page/settings', array(
						'account' => $accounts[$account_id],
						'pages' => $pages,
						'is_profile' => $is_profile,
					));
					echo json_encode(array(
						'result' => 'success',
						'html' => $html->render()
					));
					exit;
				}
			}
		}

		echo json_encode(array(
			'result' => 'error',
			'html' => 'No Pages Found'
		));
		exit;
	}

	/**
	 * Save Facebook Pages
	 *
	 * @return void
	 */
	public function action_save_facebook_pages() {
		if (!$this->request->is_ajax()) {
			wp_die('Oops, this method can only be accessed via an AJAX request.');
		}

		$account_id = $this->request->query('account_id');
		$is_profile = ($this->request->query('profile') == 'true');
		$page_ids = $this->request->post('page_ids');

		$service = $this->social->service('facebook');
		if ($service !== false) {
			$accounts = $service->accounts();
			if (isset($accounts[$account_id])) {
				$pages = $service->get_pages($accounts[$account_id], $is_profile);
				$accounts[$account_id]->pages(array(), $is_profile);
				foreach ($page_ids as $page_id) {
					if (isset($pages[$page_id])) {
						$accounts[$account_id]->page($pages[$page_id], $is_profile);
					}
				}
			}

			foreach ($accounts as $account_id => $account) {
				$accounts[$account_id] = $account->as_object();
			}

			$service->accounts($accounts)->save($is_profile);
		}
	}

	/**
	 * Regenerates the API key.
	 * 
	 * @return void
	 */
	public function action_regenerate_api_key() {
		if (!$this->request->is_ajax()) {
			wp_die('Oops, this method can only be accessed via an AJAX request.');
		}

		$key = wp_generate_password(16, false);
		Social::option('system_cron_api_key', $key, true);
		echo $key;
		exit;
	}

} // End Social_Controller_Settings
