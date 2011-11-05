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
				if (($account = $service->account($user_id)) !== false) {
					if (empty($output)) {
						$accounts_output = '<ul class="social-broadcasted">';
					}

					foreach ($broadcasted as $broadcasted_id => $data) {
						$broadcasts[] = Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
							'account' => $account,
							'service' => $service,
							'broadcasted_id' => $broadcasted_id,
							'data' => $data
						));
					}
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