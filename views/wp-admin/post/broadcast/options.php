<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php _e('Social Broadcasting Options', 'social'); ?></title>
	<?php
	wp_admin_css('install', true);
	do_action('wp_enqueue_scripts');
	do_action('admin_enqueue_scripts');
	do_action('admin_print_styles');
	?>
</head>
<body>
<h1 id="logo"><?php _e('Social Broadcasting Options', 'social'); ?></h1>
<?php if (count($errors)): ?>
<div id="social_error">
	<?php
	foreach ($errors as $error) {
		echo esc_html($error).'<br />';
	}
	?>
</div>
	<?php endif; ?>
<p><?php __('You have chosen to broadcast this blog post to your social accounts. Use the form below to edit your broadcasted messages.', 'social'); ?></p>

<form id="setup" method="post" action="<?php echo esc_url(admin_url('post.php?social_controller=broadcast&social_action=options')); ?>">
	<?php wp_nonce_field(); ?>
	<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
	<input type="hidden" name="location" value="<?php echo $location; ?>" />
	<table class="form-table social-broadcast-options">
	<?php
		$counters = array();
		foreach ($services as $key => $service) {
			if (isset($_POST['social_'.$key.'_content'])) {
				$content = $_POST['social_'.$key.'_content'];
			}
			else {
				$content = get_post_meta($post->ID, '_social_'.$key.'_content', true);
				if (empty($content)) {
					$content = $service->format_content($post, Social::option('broadcast_format'));
				}
			}
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
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr($key.'_preview'); ?>"><?php _e($service->title(), 'social'); ?></label><br />
				<span id="<?php echo esc_attr($key.'_counter'); ?>" class="social-preview-counter<?php echo ($counter < 0 ? ' social-counter-limit' : ''); ?>"><?php echo $counter; ?></span>
			</th>
			<td>
				<textarea id="<?php echo esc_attr($key.'_preview'); ?>" name="<?php echo esc_attr('social_'.$key.'_content'); ?>" class="social-preview-content" cols="40" rows="5"><?php echo stripslashes((isset($_POST['social_'.$key.'_content'])) ? $_POST['social_'.$key.'_content'] : esc_textarea($content)); ?></textarea><br />

				<div class="social-accounts">
					<strong><?php echo $heading; ?></strong>
					<ul>
						<?php
							foreach ($accounts as $account) {
								$checked = true;
								$checked_pages = array();
								if (isset($_POST['social_'.$key.'_accounts'])) {
									if (!in_array($account->id(), $_POST['social_'.$key.'_accounts']) and !in_array($account->id().'|true', $_POST['social_'.$key.'_accounts'])) {
										$checked = false;
									}
								}
								else if (count($errors) and !isset($_POST['social_'.$key.'_accounts'])) {
									$checked = false;
								}
								else if (!empty($broadcasted_ids) and empty($default_accounts)) {
									if (!isset($default_accounts[$key]) or (isset($default_accounts[$key]) and !in_array($account->id(), $default_accounts[$key]))) {
										$checked = false;
									}
								}
								else if (count($broadcasted_ids)) {
									if (isset($_POST['social_action']) and isset($broadcasted_ids[$key]) and isset($broadcasted_ids[$key][$account->id()])) {
										$checked = false;
									}
								}
								else if (isset($_POST['social_broadcast'])) {
									if ($_POST['social_broadcast'] == 'Edit') {
										if (empty($broadcasted_ids) and empty($broadcast_accounts)) {
											$checked = false;
										}
										else if (isset($broadcast_accounts[$key])) {
											$found = false;
											foreach ($broadcast_accounts[$key] as $account_id => $data) {
												if ($key == 'facebook') {
													if ($account_id == $account->id()) {
														$found = true;
													}

													$pages = $account->pages(null, 'combined');
													foreach ($pages as $page) {
														if ($page->id == $account_id) {
															$checked_pages[] = $page->id;
															break;
														}
													}
												}
											}

											if (!$found) {
												$checked = false;
											}
										}
									}
								}
								else if (!empty($broadcast_accounts) and (!isset($broadcast_accounts[$key]) or !isset($broadcast_accounts[$key][$account->id()]))) {
									$checked = false;
								}
						?>
						<li class="social-accounts-item">
							<label class="social-broadcastable" for="<?php echo esc_attr($key.$account->id()); ?>" style="cursor:pointer">
								<input type="checkbox" name="<?php echo esc_attr('social_'.$key.'_accounts[]'); ?>" id="<?php echo esc_attr($key.$account->id()); ?>" value="<?php echo esc_attr($account->id().($account->universal() ? '|true' : '')); ?>"<?php echo ($checked ? ' checked="checked"' : ''); ?> />
								<img src="<?php echo esc_attr($account->avatar()); ?>" width="24" height="24" />
								<span class="name">
									<?php
										echo esc_html($account->name());
										if ($service->key() == 'facebook' and empty($checked_pages)) {
											// TODO if there are accounts checked, hide this.
											$pages = $account->pages(null, 'combined');
											if ($account->use_pages() and count($pages)) {
												echo '<span> - <a href="#" class="social-show-facebook-pages">Show Pages</a></span>';
											}
										}
									?>
								</span>
							</label>
							<?php
								if ($service->key() == 'facebook') {
									if ($account->use_pages() and count($pages)) {
										// TODO if there are accounts checked, show this.
										echo '<div class="social-facebook-pages"'.(!empty($checked_pages) ? ' style="display:block"' : '').'>'
											.'    <h5>Account Pages</h5>'
											.'    <ul>';
										foreach ($pages as $page) {
											$_checked = $checked;
											if (!empty($checked_pages)) {
												if (in_array($page->id, $checked_pages)) {
													$checked = ' checked="checked"';
												}
											}
											echo '<li>'
												.'    <input type="checkbox" name="social_facebook_pages_'.$account->id().'[]" value="'.$page->id.'"'.$checked.' />'
												.'    <img src="http://graph.facebook.com/'.$page->id.'/picture" width="16" height="16" />'
												.'    <span>'.$page->name.'</span>'
												.'</li>';

											$checked = $_checked;
										}
										echo '    </ul>'
											.'</div>';
									}
								}
							?>
						</li>
						<?php
							}
						?>
					</ul>
				</div>
			</td>
		</tr>
	<?php
			}
		}
	?>
	</table>
	<p class="step">
		<input type="submit" name="social_action" value="<?php _e($step_text, 'social'); ?>" class="button" />
		<a href="<?php echo esc_url(get_edit_post_link($post->ID, 'url')); ?>" class="button">Cancel</a>
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
