<?php
$avatar = esc_url($account->avatar());
$name = esc_html($account->name());
?>
<li>
	<img src="<?php echo $avatar; ?>" width="24" height="24" />
	<span>
		<?php
			echo $name;
			if (isset($broadcasted_id)) {
				echo ' <a href="'.esc_url($service->status_url($account->username(), $broadcasted_id)).'" target="_blank">'.__('View', 'social').'</a>';
			}
		?>
	</span>
</li>
