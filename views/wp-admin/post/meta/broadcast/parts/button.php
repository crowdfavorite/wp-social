<div class="misc-pub-section broadcast-button">
	<p class="submit cf-clearfix social-meta-broadcast-button <?php echo ($broadcasted ? 'broadcasted' : ''); ?>">
		<input type="submit" name="social_broadcast" value="<?php _e($button_text, 'social'); ?>" class="button" />
		<input type="hidden" name="social_notify" value="1" />
		<a href="<?php echo esc_url(admin_url('profile.php#social-accounts')); ?>"><?php _e('My Accounts', 'social'); ?></a>
	</p>
</div>
