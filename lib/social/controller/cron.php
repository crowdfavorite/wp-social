<?php
/**
 * CRON Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_CRON extends Social_Controller {

	/**
	 * @var  bool  system cron
	 */
	protected $system_cron = false;

	/**
	 * Initializes the CRON controller.
	 *
	 * @param  Social_Request  $request
	 */
	public function __construct(Social_Request $request) {
		parent::__construct($request);

		// Social system cron?
		if (Social::option('fetch_comments') == '2') {
			$api_key = $this->request->query('api_key');
			if ($api_key != Social::option('system_cron_api_key')) {
				wp_die('Oops, you have provided an invalid API key.');
				exit;
			}

			$this->system_cron = true;
		}
		else if (!$this->nonce_verified) {
			wp_die('Oops, invalid request.');
			exit;
		}
	}

	/**
	 * Handles the CRON 15 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_15() {
		Social_CRON::instance('cron_15')->execute($this->system_cron);
	}

	/**
	 * Handles the CRON 60 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_60() {
		Social_CRON::instance('cron_60')->execute($this->system_cron);
	}

	/**
	 * Makes sure Social CRONs are not scheduled more than once.
	 *
	 * @return void
	 */
	public function action_check_crons() {
		if (!wp_verify_nonce($this->request->query('_wpnonce'))) {
			wp_die('Oops, please try again.');
		}

		$crons = _get_cron_array();
		$social_crons = array(
			'15' => false,
			'60' => false
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
