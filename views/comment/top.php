<div class="social-sign-in-links social-clearfix">
	<?php foreach ($services as $key => $service): ?>
	<a class="social-<?php echo $key; ?> social-imr social-login comments" href="<?php echo $service->authorize_url(); ?>" id="<?php echo $key; ?>_signin" target="_blank"><?php printf(__('Sign in with %s', Social::$i18n), $service->title()); ?></a>
	<?php endforeach; ?>
</div>
<div class="social-divider">
	<span><?php _e('or', Social::$i18n); ?></span>
</div>