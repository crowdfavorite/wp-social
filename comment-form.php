<?php
$use_twitter_reply = 0;
if (is_user_logged_in() and !current_user_can('manage_options')) {
	foreach (Social::$services as $key => $service) {
		if (count($service->accounts())) {
			if ($key == 'twitter') {
				$use_twitter_reply = 1;
			}
			break;
		}
	}
}
?>
<div id="respond" class="social-respond">
	<form class="social-respond-inner" action="<?php echo site_url('/wp-comments-post.php'); ?>" method="post" id="<?php echo esc_attr($args['id_form']); ?>">
	<?php if (!is_user_logged_in()): ?>
	<div class="social-sign-in-links social-clearfix">
		<?php foreach (Social::$services as $key => $service): ?>
		<a class="social-<?php echo $key; ?> social-imr social-login comments" href="<?php echo Social_Helper::authorize_url($key); ?>" id="<?php echo $key; ?>_signin"><?php _e('Sign in with '.$service->title(), Social::$i18n); ?></a>
		<?php endforeach; ?>
	</div>
	<div class="social-divider">
		<span><?php _e('or', Social::$i18n); ?></span>
	</div>
	<?php endif; ?>
	<div class="social-post-form">
		<?php
			if (is_user_logged_in()):
				echo get_avatar(get_current_user_id(), 40);

				if (current_user_can('manage_options')):
					// We'll use this in a sec, next to the submit button
					$post_to = '<label id="post_to" for="post_to_service" style="display:none;"><input type="checkbox" name="post_to_service" id="post_to_service" value="1" /> '.sprintf(__('Also post to %s'), '<span></span>').'</label>';
		?>
				<div class="social-input-row">
					<select id="post_accounts" name="<?php echo Social::$prefix; ?>post_account">
						<option value=""><?php _e('WordPress Account', Social::$i18n); ?></option>
						<?php foreach (array_merge(Social::$services, Social::$global_services) as $key => $service): ?>
							<?php
								$accounts = Social::$services[$key]->accounts();
								if (isset(Social::$global_services[$key])) {
									foreach (Social::$global_services[$key]->accounts() as $id => $account) {
										$accounts[$id] = $account;
									}
								}
							?>
							<?php if (count($accounts)): ?>
							<optgroup label="<?php _e(ucfirst($key), Social::$i18n); ?>">
								<?php foreach ($accounts as $account): ?>
								<option value="<?php echo $account->user->id; ?>" rel="<?php echo $service->profile_avatar($account); ?>"><?php echo $service->profile_name($account); ?></option>
								<?php endforeach; ?>
							</optgroup>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</div>
			<?php
			else:
				foreach (Social::$services as $key => $service):
					if (count($service->accounts())):
						$account = reset($service->accounts());

						// We'll use this in a sec, next to the submit button
						$post_to = '<label id="post_to" for="post_to_service"><input type="checkbox" name="post_to_service" id="post_to_service" value="1" /> '.sprintf(__('Also post to %s'), $service->title()).'</label>';
				?>
				<div class="social-input-row">
						<span class="social-<?php echo $key; ?>-icon">
							<i></i>
							<?php echo $service->profile_name($account); ?>.
							(<?php echo $service->disconnect_url($account); ?>)
						</span>
					</div>
					<input type="hidden" name="<?php echo Social::$prefix; ?>post_account" value="<?php echo $account->user->id; ?>" />
					<?php
					endif;
				endforeach;
			endif;
		else: // If not logged in... ?>
		<div class="social-input-row">
			<label class="social-label" for="social-sign-in-name"><?php _e('Name', Social::$i18n); ?></label>
			<input class="social-input-text" type="text" id="social-sign-in-name" name="author" />
		</div>
		<div class="social-input-row">
			<label class="social-label" for="social-sign-in-email"><?php _e('Email', Social::$i18n); ?></label>
			<input class="social-input-text" type="text" id="social-sign-in-email" name="email" />
			<em id="social-email-notice" class="social-quiet">We'll keep this private</em>
		</div>
		<div class="social-input-row">
			<label class="social-label" for="social-sign-in-website"><?php _e('Website', Social::$i18n); ?></label>
			<input class="social-input-text" type="text" id="social-sign-in-website" name="url" />
		</div>
		<?php
		endif; ?>

		<div class="social-input-row">
			<textarea id="social-sign-in-comment" name="comment"></textarea>
		</div>
		<div class="social-input-row social-input-row-submit">
			<button type="submit" class="social-input-submit"><span><?php _e('Post It', Social::$i18n); ?></span></button>
			<?php
			echo $post_to;
			cancel_comment_reply_link(__('Cancel reply', Social::$i18n)); ?>
		</div>
	</div>
	<input type="hidden" id="use_twitter_reply" name="use_twitter_reply" value="0" />
	<input type="hidden" id="in_reply_to_status_id" name="in_reply_to_status_id" value="" />
	<?php comment_id_fields(); ?>
	</form>
</div>
