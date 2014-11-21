<div class="social-meta-box-block">
	<h4><?php _e('Add Tweet by URL', 'social'); ?></h4>
	<p><?php _e('Enter the URL of the tweet to add it as a comment.', 'social'); ?></p>
	
	<p>
		<input type="text" id="social-source-url" name="source_url" style="width:350px" />
		<span class="submit" style="float:none">
			<a href="<?php echo esc_url(Social::wp39_nonce_url(admin_url('options-general.php?social_controller=import&social_action=from_url&social_service=twitter&post_id='.$post->ID), 'from_url')); ?>" id="import_from_url" class="button"><?php _e('Import Tweet', 'social'); ?></a>
		</span>
		<img src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" style="position:relative;top:4px;left:0;display:none" id="import_from_url_loader" />
		<span id="social-import-error"></span>
	</p>
</div><!-- .social-meta-box-block -->

<?php
if (Social::option('aggregate_comments')) {
?>
<div class="social-meta-box-block cf-clearfix">
	<h4>
		<?php _e('Manual Refresh', 'social'); ?>
		<span id="social-next-run">(<?php echo sprintf(__('Next automatic run <span>%s</span>', 'social'), $next_run); ?>)</span>
	</h4>

	<p class="submit" style="clear:both;float:none;padding:0;">
		<a href="<?php echo esc_url(Social::wp39_nonce_url(admin_url('options-general.php?social_controller=aggregation&social_action=run&post_id='.$post->ID), 'run')); ?>" id="run_aggregation" class="button" style="float:left;margin-bottom:10px;"><?php _e('Find Social Comments', 'social'); ?></a>
		<img src="<?php echo esc_url(admin_url('images/wpspin_light.gif')); ?>" style="float:left;position:relative;top:4px;left:5px;display:none;" id="run_aggregation_loader" />
	</p>
</div><!-- .social-meta-box-block -->
<?php
}
?>

<div class="social-meta-box-block">
	<h4><?php _e('Log', 'social'); ?></h4>

	<div id="aggregation_log">
		<?php echo Social_Aggregation_Log::instance($post->ID); ?>
	</div>
</div><!-- .social-meta-box-block -->
