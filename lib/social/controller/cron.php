<?php
/**
 * CRON Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_CRON extends Social_Controller {

	public function __construct(Social_Request $request) {
		parent::__construct($request);

		// Social system cron?
	}

	/**
	 * Handles the CRON 15 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_15() {
		Social_CRON::instance('cron_15')->execute();
	}

	/**
	 * Handles the CRON 60 logic.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function action_cron_60() {
		Social_CRON::instance('cron_60')->execute();
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
