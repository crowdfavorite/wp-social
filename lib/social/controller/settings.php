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
			Social::option('comment_broadcast_format', $this->request->post('social_comment_broadcast_format'));
			Social::option('debug', $this->request->post('social_debug'));

			if (!Social::option('debug')) {
				delete_option('social_log_write_error');
			}

			if (isset($_POST['social_broadcast_by_default'])) {
				Social::option('broadcast_by_default', $_POST['social_broadcast_by_default']);
			}
			else {
				delete_option('social_broadcast_by_default');
			}

			if (isset($_POST['social_aggregate_comments'])) {
				Social::option('aggregate_comments', $_POST['social_aggregate_comments']);
			}
			else {
				delete_option('social_aggregate_comments');
			}

			// Store the default accounts
			$accounts = array();
			if (is_array($this->request->post('social_default_accounts'))) {
				foreach ($this->request->post('social_default_accounts') as $account) {
					$account = explode('|', $account);
					$accounts[$account[0]][] = $account[1];
				}
			}

			$accounts = apply_filters('social_settings_default_accounts', $accounts, $this);

			if (count($accounts)) {
				Social::option('default_accounts', $accounts);
			}
			else {
				delete_option('social_default_accounts');
			}

			// API accounts
			if ($this->request->post('social_api_accounts')) {
				Social::option('social_api_accounts', $this->request->post('social_api_accounts'));
			}

			// System CRON
			if ($this->request->post('social_cron') !== null) {
				Social::option('cron', $this->request->post('social_cron'));

				// Unschedule the CRONs
				if ($this->request->post('social_cron') != '1' and ($timestamp = wp_next_scheduled('social_cron_15_init')) !== false) {
					wp_unschedule_event($timestamp, 'social_cron_15_init');
				}
			}

			// Disable Social's comment display feature
			if (isset($_POST['social_use_standard_comments'])) {
				Social::option('use_standard_comments', '1');
			}
			else {
				delete_option('social_use_standard_comments');
			}

			// Disable Social's broadcast feature
			if (isset($_POST['social_disable_broadcasting'])) {
				Social::option('disable_broadcasting', '1');
			}
			else {
				delete_option('social_disable_broadcasting');
			}

			do_action('social_settings_save', $this);

			wp_redirect(Social::settings_url(array('saved' => 'true')));
			exit;
		}

		$accounts = array();
		foreach ($this->social->services() as $key => $service) {
			if (!isset($accounts[$key])) {
				$accounts[$key] = array();
			}
			foreach ($service->accounts() as $account) {
				if ($account->universal()) {
					$accounts[$key][] = $account->id();
				}
			}
		}

		echo Social_View::factory('wp-admin/options', array(
			'services' => $this->social->services(),
			'accounts' => $accounts,
			'defaults' => Social::option('default_accounts'),
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
	 * Supress the no accounts notice.
	 *
	 * @return void
	 */
	public function action_suppress_no_accounts_notice() {
		update_user_meta(get_current_user_id(), 'social_suppress_no_accounts_notice', 'true');
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
	 * Regenerates the API key.
	 *
	 * @return void
	 */
	public function action_regenerate_api_key() {

		$this->verify_nonce();

		if (!$this->request->is_ajax()) {
			wp_die('Oops, this method can only be accessed via an AJAX request.');
		}

		$key = wp_generate_password(16, false);
		Social::option('system_cron_api_key', $key, true);
		echo $key;
		exit;
	}

} // End Social_Controller_Settings
