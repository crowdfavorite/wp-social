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
			$this->social->option('broadcast_format', $this->request->post('social_broadcast_format'), true);
			$this->social->option('debug', $this->request->post('social_debug'), true);

			if (!$this->social->option('debug')) {
				delete_option('social_log_write_error');
			}

			// Store the XML-RPC accounts
			if (is_array($this->request->post('social_xmlrpc_accounts'))) {
				$accounts = array();
				foreach ($this->request->post('social_xmlrpc_accounts') as $account) {
					$account = explode('|', $account);
					$accounts[$account[0]][] = $account[1];
				}
				$this->social->option('xmlrpc_accounts', $accounts, true);
			}
			else {
				delete_option('social_xmlrpc_accounts');
			}

			// Anywhere key
			if ($this->request->post('social_twitter_anywhere_api_key') !== null) {
				$this->social->option('twitter_anywhere_api_key', $this->request->post('social_twitter_anywhere_api_key'), true);
			}

			// System CRON
			if ($this->request->post('social_system_crons') !== null) {
				$this->social->option('system_crons', $this->request->post('social_system_crons'), true);

				// Unschedule the CRONs
				if (($timestamp = wp_next_scheduled('social_cron_15_core')) !== false) {
					wp_unschedule_event($timestamp, 'social_cron_15_core');
				}
				if (($timestamp = wp_next_scheduled('social_cron_60_core')) !== false) {
					wp_unschedule_event($timestamp, 'social_cron_60_core');
				}
			}

			wp_redirect(Social_Helper::settings_url(array('saved' => 'true')));
			exit;
		}

		echo Social_View::factory('wp-admin/options', array(
			'services' => $this->social->services(),
		));
	}

} // End Social_Controller_Settings
