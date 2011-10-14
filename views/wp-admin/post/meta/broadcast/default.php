<h4 class="mar-top-none"><?php _e('Broadcast Post', 'social'); ?></h4>
<p><?php _e('Would you like to broadcast this post?', 'social'); ?></p>
<p>
	<input type="radio" name="social_notify" id="social_notify_yes" class="social-toggle" value="1"<?php echo ($notify ? ' checked="checked"' : '').($post->post_status == 'private' ? ' disabled="disabled"' : ''); ?> />
	<label for="social_notify_yes" class="social-toggle-label"><?php _e('Yes', 'social'); ?></label>

	<input type="radio" name="social_notify" id="social_notify_no" class="social-toggle" value="0"<?php echo ((!$notify or $post->post_status == 'private') ? ' checked="checked"' : '').($post->post_status == 'private' ? ' disabled="disabled"' : ''); ?> />
	<label for="social_notify_no" class="social-toggle-label"><?php _e('No', 'social'); ?></label>
</p>
