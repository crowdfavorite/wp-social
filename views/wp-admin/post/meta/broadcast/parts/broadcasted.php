<?php
$header_shown = false;
if (is_array($ids) and count($ids)) {
	foreach ($services as $key => $service) {
		if (isset($ids[$key]) and count($ids[$key])) {
			$broadcasted = true;
			if (!$header_shown) {
				$header_shown = true;
				$message = __('Broadcasted To', 'social');
				echo '<h4>'.$message.'</h4>';
			}

			$output = '';
			foreach ($ids[$key] as $user_id => $broadcasted) {
				if (($account = $service->account($user_id)) !== false) {
					if (empty($output)) {
						$accounts_output = $service->title().'<ul style="margin:0 0 25px 0;">';
					}

					foreach ($broadcasted as $broadcasted_id => $data) {
						$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
							'account' => $account,
							'service' => $service,
							'broadcasted_id' => $broadcasted_id,
							'data' => $data
						));
					}
				}
			}

			if (!empty($output)) {
				echo $service->title().'<ul style="margin:0 0 25px 0;">'.$output.'</ul>';
			}
		}
	}
}
