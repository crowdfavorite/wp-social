<?php
$header_shown = false;
if (is_array($ids) and count($ids)) {
	foreach ($services as $key => $service) {
		if (isset($ids[$key]) and count($ids[$key])) {
			$broadcasted = true;
			if (!$header_shown) {
				$header_shown = true;
				echo '<p class="mar-top-none">'
				   . __('This post has been broadcasted to the following accounts. You may broadcast to more accounts by clicking on the "Broadcast" button above.', Social::$i18n)
				   . '</p>';
			}

			$output = '';
			foreach ($ids[$key] as $user_id => $broadcasted) {
				if (($account = $service->account($user_id)) !== false) {
					if (empty($output)) {
						$accounts_output = '<h4>'.$service->title().'</h4><ul style="margin:0 0 25px 0;">';
					}

					foreach ($broadcasted as $broadcasted_id => $data) {
						$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
							'account' => $account,
							'service' => $service,
							'broadcasted_id' => $broadcasted_id
						));
					}
				}
			}

			if (!empty($output)) {
				echo '<h4>'.$service->title().'</h4><ul style="margin:0 0 25px 0;">'.$output.'</ul>';
			}
		}
	}
}