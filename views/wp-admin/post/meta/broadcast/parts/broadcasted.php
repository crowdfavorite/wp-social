<div class="misc-pub-section">
<?php
$header_shown = false;
if (is_array($ids) and count($ids)) {
	foreach ($services as $key => $service) {
		if (isset($ids[$key]) and count($ids[$key])) {
			$broadcasted = true;
			if (!$header_shown) {
				$header_shown = true;
				echo '<h4>'.__('Sent to:', 'social').'</h4>';
			}

			$broadcasts = array();
			foreach ($ids[$key] as $user_id => $broadcasted) {
				$account = $service->account($user_id);
				if (empty($output)) {
					$accounts_output = '<ul class="social-broadcasted">';
				}

				foreach ($broadcasted as $broadcasted_id => $data) {
					if ($account === false) {
						$class = 'Social_Service_'.$key.'_Account';
						$account = new $class($data['account']);

						if (!$account->has_user() and $key == 'twitter') {
							$recovered = $service->recover_broadcasted_tweet_data($broadcasted_id, $post->ID);

							if (isset($recovered->user)) {
								$data['account']->user = $recovered->user;
								$account = new $class($data['account']);
							}
						}
					}

					$broadcasts[] = Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
						'account' => $account,
						'service' => $service,
						'broadcasted_id' => $broadcasted_id,
						'data' => $data
					));
				}
			}

			if (count($broadcasts)) {
				echo '<ul class="social-broadcasted">'.implode("\n", $broadcasts).'</ul>';
			}
		}
	}
}
?>
</div>
