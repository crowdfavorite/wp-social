<div class="misc-pub-section">
	<h4 class="mar-top-none"><?php _e('Broadcast Post', 'social'); ?></h4>
	<p>
		<input type="radio" name="social_notify" id="social_notify_yes" value="1"<?php checked(true, (bool) ($notify and $post->post_status != 'private'), true); disabled('private', $post->post_status, true); ?> />
		<label for="social_notify_yes" class="social-toggle-label"><?php _e('Yes', 'social'); ?></label>
	
		<input type="radio" name="social_notify" id="social_notify_no" value="0"<?php checked(true, (bool) (!$notify or $post->post_status == 'private'), true); disabled('private', $post->post_status, true); ?> />
		<label for="social_notify_no" class="social-toggle-label"><?php _e('No', 'social'); ?></label>
	</p>
</div>