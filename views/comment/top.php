<div class="social-sign-in-links social-clearfix">
	<?php foreach ($services as $key => $service): ?>
	<a class="social-<?php echo esc_attr($key); ?> social-imr social-login comments" href="<?php echo esc_url($service->authorize_url()); ?>" id="<?php echo esc_attr($key); ?>_signin" target="_blank"><?php printf(__('Sign in with %s', 'social'), esc_html($service->title())); ?></a>
	<?php endforeach; ?>
</div>
<div class="social-divider">
	<span><?php _e('or', 'social'); ?></span>
</div>
