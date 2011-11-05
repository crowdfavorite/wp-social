<li>
	<img src="<?php echo esc_url($account->avatar()); ?>" width="24" height="24" />
	<span>
<?php

$service = (isset($broadcasted_id) ? '<a href="'.esc_url($service->status_url($account->username(), $broadcasted_id)).'" target="_blank">'.$service->title().'</a>' : $service->title());

echo esc_html($account->name()).' &middot; '.$service;

?>
	</span>
</li>
