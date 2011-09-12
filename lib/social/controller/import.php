<?php
/**
 * Importer
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Import extends Social_Controller {

	/**
	 * Imports a tweet by URL.
	 *
	 * @return void
	 */
	public function action_from_url() {
		if (!wp_verify_nonce($this->request->query('_wpnonce'))) {
			wp_die('Oops, please try again.');
		}

		Social::log('Import tweet by URL started.');

		$service = $this->social->service('twitter');
		if ($service !== false) {
			$service->import_tweet_by_url($this->request->query('post_id'), $this->request->query('url'));
			Social::log('Import tweet by URL finished.');
		}
		else {
			Social::log('Import tweet by URL failed, Twitter class not found.');
		}

		echo Social_Aggregation_Log::instance($this->request->query('post_id'));
		exit;
	}

} // End Social_Controller_Import
