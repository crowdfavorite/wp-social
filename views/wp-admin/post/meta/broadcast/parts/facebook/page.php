<?php
$avatar = esc_url($service->page_image_url($account));
$name = esc_html($account->name);
?>
<li>
	<img src="<?php echo $avatar; ?>" width="24" height="24" />
	<span>
		<?php
			echo $name;
			if (isset($broadcasted_id)) {
				echo ' <a href="'.esc_url($service->status_url(null, $broadcasted_id)).'" target="_blank">'.__('View', 'social').'</a>';
			}
		?>
	</span>
</li>
