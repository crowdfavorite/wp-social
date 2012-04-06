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
<h1 id="logo"><?php _e('Social Broadcasts', 'social'); ?></h1>
<form id="setup" method="post" class="broadcast-interstitial" action="<?php echo esc_url(admin_url('post.php?social_controller=broadcast&social_action=options')); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
<input type="hidden" name="location" value="<?php echo $location; ?>" />
<div class="form-table social-broadcast-options">
<?php
foreach ($_services as $key => $accounts) {
	$service = $services[$key];
	if (count($accounts)) {
?>
<section>
	<header>
		<h2><?php _e($service->title(), 'social'); ?></h2>
	</header>
	<ul class="accounts">
<?php
		foreach ($accounts as $account) {
?>
		<li class="account <?php echo (!empty($account['error']) ? ' error' : ''); ?>">
			<label for="<?php echo esc_attr($account['field_name_checked']); ?>">
				<img src="<?php echo esc_attr($account['avatar']); ?>" width="32" height="32" />
				<span class="name"><?php echo esc_html($account['name']); ?></span>
			</label>
			<div class="broadcast-content">
<?php
			if (count($account['broadcasts'])) {
?>
				<h3><?php _e('Previous Broadcasts', 'social'); ?></h3>
				<ul class="broadcasts">
<?php
				foreach ($account['broadcasts'] as $broadcast) {
					// already escaped in controller
?>
					<li><?php echo $broadcast; ?></li>
<?php
				}
?>
				</ul>
<?php
			}
			if (!empty($account['error'])) {
				echo '<span>'.esc_html($account['error']).'</span>';
			}
?>
				<div class="broadcast-edit<?php echo ($account['edit'] ? ' edit' : ''); ?>">
					<input type="checkbox" name="<?php echo esc_attr($account['field_name_checked']); ?>" id="<?php echo esc_attr($account['field_name_checked']); ?>" value="<?php echo esc_attr($account['field_value_checked']); ?>"<?php checked($account['checked'], true); ?> />
					<p class="readonly"><?php echo esc_textarea($account['content']); ?></p>
					<a href="#" class="edit"><?php _e('Edit', 'social'); ?></a>
					<textarea name="<?php echo esc_attr($account['field_name_content']); ?>" cols="40" rows="2"><?php echo esc_textarea($account['content']); ?></textarea>
				</div>
			</div>
		</li>
<?php
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
	<input type="hidden" name="social_action" value="<?php echo esc_attr($step); ?>" />
	<input type="submit" name="social_submit" value="<?php echo $step_text; // already localized in controller ?>" class="button" />
	<a href="<?php echo esc_url(get_edit_post_link($post->ID, 'url')); ?>"><?php _e('Cancel', 'social'); ?></a>
</p>
</form>
<script type="text/javascript" src="<?php echo esc_url(includes_url('/js/jquery/jquery.js')); ?>"></script>
<script type="text/javascript" src="<?php echo esc_url(SOCIAL_ADMIN_JS); ?>"></script>
</body>
</html>