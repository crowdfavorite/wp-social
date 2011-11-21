<?php
/**
 * CRON Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_CRON extends Social_Controller {

	/**
	 * Initializes the CRON controller.
	 *
	 * @param  Social_Request  $request
	 */
	public function __construct(Social_Request $request) {
		parent::__construct($request);

		// Social system cron?
		if (Social::option('fetch_comments') == '2' or $this->request->query('api_key') !== null) {
			$api_key = $this->request->query('api_key');
			if ($api_key != Social::option('system_cron_api_key')) {
				wp_die('Oops, you have provided an invalid API key.');
			}
		}
		else if (!$this->nonce_verified) {
			wp_die('Oops, invalid request.');
		}
	}

	/**
	 * Handles the CRON 15 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_15() {
		$semaphore = Social_Semaphore::factory();
		if ($semaphore->lock()) {
			do_action('social_cron_15');
			$semaphore->unlock();
		}
	}

	/**
	 * Makes sure Social CRONs are not scheduled more than once.
	 *
	 * @return void
	 */
	public function action_check_crons() {
		if (urldecode($this->request->query('social_api_key')) != Social::option('system_cron_api_key')) {
			wp_die('Oops, invalid API key.');
		}
		
		$crons = _get_cron_array();
		$social_crons = array(
			'15' => false,
		);
		foreach ($crons as $timestamp => $_crons) {
			foreach ($_crons as $key => $cron) {
				foreach ($social_crons as $cron_key => $status) {
					$event_key = 'social_cron_'.$cron_key.'_core';
					if ($key == $event_key and $social_crons[$cron_key]) {
						wp_unschedule_event($timestamp, $event_key);
						Social::log('Unscheduled extra event: '.$event_key);
					}
					else {
						$social_crons[$cron_key] = true;
					}
				}
			}
		}
	}

} // End Social_Controller_CRON
