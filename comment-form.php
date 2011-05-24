<form action="<?php echo site_url('/wp-comments-post.php'); ?>" method="post" id="<?php echo esc_attr($args['id_form']); ?>">
<?php comment_id_fields(); ?>
<?php if (!is_user_logged_in()): ?>
<div class="social-sign-in-links social-clearfix">
	<?php foreach (Social::$services as $key => $service): ?>
	<a class="social-<?php echo $key; ?> social-imr social-login comments" href="<?php echo Social_Helper::authorize_url($key); ?>" id="<?php echo $key; ?>_signin"><?php _e('Sign in with '.$service->title(), Social::$i10n); ?></a>
	<?php endforeach; ?>
</div>
<div class="social-divider">
	<span><?php _e('or', Social::$i10n); ?></span>
</div>
<?php endif; ?>
<div class="social-sign-in-form">
	<?php if (!is_user_logged_in()): ?>
	<div class="social-input-row">
		<label for="social-sign-in-name"><?php _e('Name', Social::$i10n); ?></label>
		<input class="social-input-text" type="text" id="social-sign-in-name" name="author" />
	</div>
	<div class="social-input-row">
		<label for="social-sign-in-email"><?php _e('Email', Social::$i10n); ?></label>
		<input class="social-input-text" type="text" id="social-sign-in-email" name="email" />
	</div>
	<div class="social-input-row">
		<label for="social-sign-in-website"><?php _e('Website', Social::$i10n); ?></label>
		<input class="social-input-text" type="text" id="social-sign-in-website" name="url" />
	</div>
	<?php endif; ?>
	<div class="social-input-row">
		<label for="social-sign-in-comment"><?php _e('Comment', Social::$i10n); ?></label>
		<textarea id="social-sign-in-comment" name="comment"></textarea>
	</div>
	<div class="social-input-row">
		<button type="submit" class="social-input-submit" style="float:left;"><span><?php _e('Post It', Social::$i10n); ?></span></button>
		<?php if (is_user_logged_in()): ?>
			<?php if (current_user_can('manage_options')): ?>
				<span style="float:left;margin:4px 10px;">via</span>
				<select name="<?php echo Social::$prefix; ?>post_account">
					<option value=""><?php _e('WordPress Account', Social::$i10n); ?></option>
					<?php foreach (Social::$services as $key => $service): ?>
					<optgroup label="<?php _e(ucfirst($key), Social::$i10n); ?>">
						<?php foreach ($service->accounts() as $account): ?>
						<option value="<?php echo $account->user->id; ?>"><?php echo ($service == 'twitter' ? $account->user->screen_name : $account->user->name); ?></option>
						<?php endforeach; ?>
					</optgroup>
					<?php endforeach; ?>
				</select>
			<?php else: ?>
				<?php foreach (Social::$services as $key => $service): ?>
					<?php if (count($service->accounts())): ?>
					<?php $account = reset($service->accounts()); ?>
					<span style="float:left;margin:4px 10px;"><?php _e('via', Social::$i10n); ?></span>
					<div style="float:left;margin-top:5px;">
						<span class="social-<?php echo $key; ?>-icon">
							<i></i>
							<?php echo $service->profile_name($account); ?>.
							(<?php echo $service->disconnect_url($account); ?>)
						</span>
					</div>
					<input type="hidden" name="<?php echo Social::$prefix; ?>post_account" value="<?php echo $account->user->id; ?>" />
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php endif; ?>
		<div style="clear:both;"></div>
	</div>
</div>
</form>
