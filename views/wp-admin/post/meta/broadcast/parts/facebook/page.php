<?php
$avatar = esc_attr('http://graph.facebook.com/'.$data['page']->id.'/picture');
$name = esc_html($data['page']->name);
?>
<li style="clear:both;">
	<img src="<?php echo $avatar; ?>" width="24" height="24" style="float:left;" />
	<span style="position:relative;top:5px;left:5px;">
		<?php
			echo $name;
			if (isset($broadcasted_id)) {
				echo ' <a href="'.esc_url($service->status_url(null, $broadcasted_id)).'" target="_blank">View</a>';
			}
		?>
	</span>
</li>
