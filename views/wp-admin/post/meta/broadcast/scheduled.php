<?php
if (empty($accounts)) {
	echo '<p class=="mar-top-none">'
	   . __('This post is scheduled to be published at a later date. However, it is not scheduled to be broadcasted to any of your social accounts.', 'social')
	   . '</p>';
}
else {
	echo '<p class="mar-top-none">'
	   . __('This post is scheduled to be broadcasted to the following accounts.', 'social')
	   . '</p>';
	foreach ($accounts as $service => $_accounts) {
		if (isset($services[$service])) {
			$service = $services[$service];

			$output = '';
			foreach ($_accounts as $account) {
				$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
					'account' => $account,
					'service' => $service
				));
			}

			if (!empty($output)) {
				echo '<h4>'.$service->title().'</h4><ul style="margin:0 0 25px;">'.$output.'</ul>';
			}
		}
	}
}
