<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php _e('Social Broadcasting Options', 'social'); ?></title>
	<?php
		wp_admin_css('install', true);
		// Need to do this because we are enqueuing some styles for the admin in social.php
		do_action('admin_enqueue_scripts');
		do_action('admin_print_styles');
	?>
</head>
<body>
<h1 id="logo"><?php _e('Social Broadcasting Options', 'social'); ?></h1>
<p><?php __('You have chosen to broadcast this blog post to your social accounts. Use the form below to edit your broadcasted messages.', 'social'); ?></p>
<form id="setup" method="post" action="<?php echo esc_url(admin_url('post.php?social_controller=broadcast&social_action=options')); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
<input type="hidden" name="location" value="<?php echo $location; ?>" />
<div class="form-table social-broadcast-options">
<?php
	$counters = array();
	foreach ($services as $key => $service) {
		// Default Content
		$content = $service->format_content($post, Social::option('broadcast_format'));

		$counter = $service->max_broadcast_length();
		$counters[$service->key()] = $counter;
		if (!empty($content)) {
			$counter = $counter - strlen($content);
		}

		$accounts = $service->accounts();
		$total_accounts = count($accounts);
		$heading = sprintf(__('Publish to %s:', 'social'), ($total_accounts == '1' ? 'this account' : 'these accounts'));

		if ($total_accounts) {
?>
<section class="social-accounts broadcast-interstitial">
	<header>
		<h2><?php _e($service->title(), 'social'); ?></h2>
	</header>
	<ul>
		<?php
			foreach ($accounts as $account) {
				$checked = true;
				$checked_pages = array();
				if (!empty($default_accounts) and !isset($_POST['social_accounts'][$key])) {
					if (!isset($default_accounts[$key]) or !in_array($account->id(), $default_accounts[$key])) {
						$checked = false;
					}

					if ($key == 'facebook') {
						$pages = $account->pages(null, 'combined');
						if (isset($default_accounts['facebook']) and isset($default_accounts['facebook']['pages']) and isset($default_accounts['facebook']['pages'][$account->id()])) {
							$checked_pages[$account->id()] = $default_accounts['facebook']['pages'][$account->id()];
						}
					}
				}
				else {
					if (isset($_POST['social_accounts'][$key])) {
						if (!in_array($account->id(), $_POST['social_accounts'][$key]) and !in_array($account->id().'|true', $_POST['social_accounts'][$key])) {
							$checked = false;
						}
					}
					else if (count($errors)) {
						if (!isset($_POST['social_accounts'][$key])) {
							$checked = false;
						}

						if ($key == 'facebook' and isset($_POST['social_facebook_pages']) and isset($_POST['social_facebook_pages'][$account->id()])) {
							$pages = $account->pages(null, 'combined');
							foreach ($pages as $page) {
								if (in_array($page->id, $_POST['social_facebook_pages'][$account->id()])) {
									$checked_pages[] = $page->id;
								}
							}
						}
					}
					else if (!empty($broadcasted_ids) and empty($default_accounts)) {
						if (!isset($default_accounts[$key]) or (isset($default_accounts[$key]) and !in_array($account->id(), $default_accounts[$key]))) {
							$checked = false;
						}
					}
					else if (count($broadcasted_ids)) {
						if (isset($_POST['social_action']) or (isset($_POST['social_broadcast'])) and isset($broadcasted_ids[$key]) and isset($broadcasted_ids[$key][$account->id()])) {
							$checked = false;
						}
					}
					else if (isset($_POST['social_broadcast'])) {
						if ($_POST['social_broadcast'] == 'Edit') {
							if (!empty($broadcast_accounts) and isset($broadcast_accounts[$key])) {
								$found = false;
								foreach ($broadcast_accounts[$key] as $account_id => $data) {
									if ($account_id == $account->id()) {
										$found = true;
									}

									if ($key == 'facebook') {
										$pages = $account->pages(null, 'combined');
										foreach ($pages as $page) {
											if ($page->id == $account_id) {
												$checked_pages[$account->id()][] = $page->id;
												break;
											}
										}
									}
								}

								if (!$found) {
									$checked = false;
								}
							}
							else if (empty($broadcasted_ids)) {
								$checked = false;
							}
						}
					}
					else if (!empty($broadcast_accounts) and (!isset($broadcast_accounts[$key]) or !isset($broadcast_accounts[$key][$account->id()]))) {
						$checked = false;
					}
				}

				$_content = stripslashes((isset($_POST['social_account_content'][$service->key()][$account->id()])) ? $_POST['social_account_content'][$service->key()][$account->id()] : '');
				if (empty($_content) and isset($broadcast_content[$key][$account->id()])) {
					$content = esc_textarea($broadcast_content[$key][$account->id()]);
				}
				else {
					$content = esc_textarea($content);
				}
		?>
		<li class="social-accounts-item<?php echo (isset($errors[$key][$account->id()]) ? ' error' : ''); ?>">
			<label class="social-broadcastable cf-clearfix" for="<?php echo esc_attr($key.$account->id()); ?>" style="cursor:pointer">
				<a href="#" class="social-broadcast-edit button"<?php echo (isset($errors[$key][$account->id()]) ? ' style="display:none"' : ''); ?>><?php _e('Edit Content', 'social'); ?></a>
				<input type="checkbox" name="<?php echo esc_attr('social_accounts['.$key.'][]'); ?>" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($account->id().($account->universal() ? '|true' : '')); ?>"<?php echo ($checked ? ' checked="checked"' : ''); ?> />
				<img src="<?php echo esc_attr($account->avatar()); ?>" width="24" height="24" />
				<div class="broadcast-content">
					<span class="name"><?php echo esc_html($account->name()); ?></span>

					<p><?php echo $content; ?></p>
					<div class="social-broadcast-editable"<?php echo (isset($errors[$key][$account->id()]) ? ' style="display:block"' : ''); ?>>
						<input type="hidden" value="<?php echo $content; ?>" />
						<textarea name="social_account_content[<?php echo esc_attr($service->key()); ?>][<?php echo esc_attr($account->id()); ?>]" class="social-preview-content" cols="40" rows="2"><?php echo $content; ?></textarea><br />

						<?php
							if (isset($errors[$key][$account->id()])) {
								echo '<span>'.$errors[$key][$account->id()].'</span><br />';
							}
						?>

						<a href="#" class="social-broadcast-save button"><?php _e('Save Content', 'social'); ?></a>
						<a href="#" class="social-broadcast-cancel button"><?php _e('Cancel', 'social'); ?></a>
					</div>
				</div>
			</label>
		</li>
		<?php
				if ($service->key() == 'facebook') {
					if (($account->use_pages() or $account->use_pages(true)) and count($pages)) {
						foreach ($pages as $page) {
							$_checked = $checked;
							if (!empty($checked_pages)) {
								if (in_array($page->id, $checked_pages[$account->id()])) {
									$checked = ' checked="checked"';
								}
							}

							$_content = stripslashes((isset($_POST['social_account_content'][$service->key()][$page->id])) ? $_POST['social_account_content'][$service->key()][$page->id] : '');
							if (empty($_content) and isset($broadcast_content[$key][$page->id])) {
								$content = esc_textarea($broadcast_content[$key][$page->id]);
							}
							else {
								$content = esc_textarea($content);
							}
		?>
		<li class="social-accounts-item<?php echo (isset($errors[$key][$page->id]) ? ' error' : ''); ?>">
			<label class="social-broadcastable cf-clearfix" for="<?php echo esc_attr($key.$page->id); ?>" style="cursor:pointer">
				<a href="#" class="social-broadcast-edit button"<?php echo (isset($errors[$key][$page->id]) ? ' style="display:none"' : ''); ?>><?php _e('Edit Content', 'social'); ?></a>
				<input type="checkbox" name="social_facebook_pages[<?php echo esc_attr($account->id()); ?>][]" id="<?php echo esc_attr($key.$page->id); ?>" value="<?php echo esc_attr($page->id); ?>"<?php echo ($checked ? ' checked="checked"' : ''); ?> />
				<img src="<?php echo esc_url($service->page_image_url($page)); ?>" width="24" height="24" />
				<div class="broadcast-content"<?php echo (isset($errors[$key][$account->id()]) ? ' style="display:block"' : ''); ?>>
					<span class="name"><?php echo esc_html($page->name); ?></span>

					<p><?php echo $content; ?></p>
					<div class="social-broadcast-editable"<?php echo (isset($errors[$key][$page->id]) ? ' style="display:block"' : ''); ?>>
						<input type="hidden" value="<?php echo $content; ?>" />
						<textarea name="social_account_content[facebook][<?php echo $page->id; ?>]" class="social-preview-content" cols="40" rows="2"><?php echo $content; ?></textarea><br />

						<?php
							if (isset($errors[$key][$page->id])) {
								echo '<span>'.$errors[$key][$page->id].'</span><br />';
							}
						?>

						<a href="#" class="social-broadcast-save button"><?php _e('Save Content', 'social'); ?></a>
						<a href="#" class="social-broadcast-cancel button"><?php _e('Cancel', 'social'); ?></a>
					</div>
				</div>
			</label>
		</li>
		<?php
							$checked = $_checked;
						}
					}
				}
			}
		?>
	</ul>
</section>
<?php
		}
	}
?>
</div>
<p class="step">
	<input type="submit" name="social_action" value="<?php _e($step_text, 'social'); ?>" class="button" />
	<a href="<?php echo esc_url(get_edit_post_link($post->ID, 'url')); ?>" class="button"><?php _e('Cancel', 'social'); ?></a>
</p>
</form>
<script type="text/javascript">
	<?php
	$output = array();
	foreach ($counters as $key => $max) {
		$output[] = '"'.$key.'":'.$max;
	}
	echo 'var maxLength = {'.implode(',', $output).'};';
	?>
</script>
<script type="text/javascript" src="<?php echo esc_url(includes_url('/js/jquery/jquery.js')); ?>"></script>
<script type="text/javascript" src="<?php echo esc_url(SOCIAL_ADMIN_JS); ?>"></script>
</body>
</html>
