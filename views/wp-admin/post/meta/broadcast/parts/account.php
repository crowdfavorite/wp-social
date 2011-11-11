<li>
	<img src="<?php echo esc_url($account->avatar()); ?>" width="24" height="24" />
	<span>
		<?php
			$broadcasted = $service->title();
			if (isset($broadcasted_id)) {
				if ($account->has_user() or $service->key() != 'twitter') {
					$broadcasted = '<a href="'.esc_url($service->status_url($account->username(), $broadcasted_id)).'" target="_blank">'.$service->title().'</a>';
				}
			}
			echo esc_html($account->name()).' &middot; '.$broadcasted;
		?>
	</span>
</li>
