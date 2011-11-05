<div class="misc-pub-section">
<?php
if (empty($accounts)) {
	echo '<p class=="mar-top-none">'
	   . __('This post is scheduled to be published at a later date. However, it is not scheduled to be broadcasted to any of your social accounts.', 'social')
	   . '</p>';
}
else {
	echo '<h4>'. __('Scheduled for:', 'social').'</h4>';
	foreach ($accounts as $service => $_accounts) {
		if (isset($services[$service])) {
			$service = $services[$service];

			$output = '';
			foreach ($_accounts as $account) {
				$_account = $service->account($account->id);
				if ($_account !== false) {
					$account = $_account;
				}
				
				$output .= Social_View::factory('wp-admin/post/meta/broadcast/parts/account', array(
					'account' => $account,
					'service' => $service
				));
			}

			if (!empty($output)) {
				echo '<ul class="social-broadcasted">'.$output.'</ul>';
			}
		}
	}
}
?>
</div>