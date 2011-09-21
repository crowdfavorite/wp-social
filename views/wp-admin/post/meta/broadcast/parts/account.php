<?php
$avatar = esc_attr($account->avatar());
$name = esc_html($account->name());
?>
<li style="clear:both;">
	<img src="<?php echo $avatar; ?>" width="24" height="24" style="float:left;" />
	<span style="position:relative;top:5px;left:5px;">
		<?php
			echo $name;
			if (isset($broadcasted_id)) {
				echo ' <a href="'.esc_url($service->status_url($account->username(), $broadcasted_id)).'" target="_blank">View</a>';
			}
		?>
	</span>
</li>