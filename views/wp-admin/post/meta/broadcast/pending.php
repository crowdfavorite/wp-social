<?php
echo '<div class="misc-pub-section">';
if (empty($accounts)) {
	_e('This post will not be broadcasted to any of your social accounts.', 'social');
}
else {
	echo '<p class="mar-top-none">'
	   . __('This post will be broadcasted to the following accounts.', 'social')
	   . '</p>';

	foreach ($accounts as $service => $_accounts) {
		if (isset($services[$service])) {
			$service = $services[$service];

			$output = '';
			foreach ($_accounts as $account) {
				if (($account = $service->account($account->id)) !== false) {
					$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
						'account' => $account,
						'service' => $service
					));
				}
			}

			if (!empty($output)) {
				echo '<ul class="social-broadcasted">'.$output.'</ul>';
			}
		}
	}
}
echo '</div>';
