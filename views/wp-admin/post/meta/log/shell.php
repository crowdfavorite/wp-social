<div class="social-meta-box-block">
	<h4><?php _e('Add Tweet by URL', Social::$i18n); ?></h4>
	<p><?php _e('Want to add a tweet? Enter the URL of the tweet here and Social will add it as a comment.', Social::$i18n); ?></p>
	
	<p>
		<input type="text" name="source_url" style="width:350px" />
		<span class="submit" style="float:none">
			<a href="<?php echo esc_url(wp_nonce_url(admin_url('?social_controller=import&social_action=from_url&social_service=twitter&post_id='.$post->ID))); ?>" id="import_from_url" class="button"><?php _e('Import Tweet', Social::$i18n); ?></a>
		</span>
		<img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" style="position:relative;top:4px;left:0;display:none" id="import_from_url_loader" />
	</p>
</div><!-- .social-meta-box-block -->

<div class="social-meta-box-block cf-clearfix">
	<h4>
		<?php _e('Manual Refresh', Social::$i18n); ?>
		<span>(<?php echo sprintf(__('Automatic aggregation scheduled for: %s', Social::$i18n), $next_run); ?>)</span>
	</h4>
	<p><?php _e('Manually run the comment aggregation and Social will look for mentions of this post on Facebook and Twitter.', Social::$i18n); ?></p>

	<p class="submit" style="clear:both;float:none;padding:0;">
		<?php // TODO Fix manual aggregation ?>
		<a href="<?php echo esc_url(wp_nonce_url(admin_url('?social_controller=aggregation&social_action=run&post_id='.$post->ID))); ?>" id="run_aggregation" class="button" style="float:left;margin-bottom:10px;"><?php _e('Find Social Comments', Social::$i18n); ?></a>
		<img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" style="float:left;position:relative;top:4px;left:5px;display:none;" id="run_aggregation_loader" />
	</p>
</div><!-- .social-meta-box-block -->

<div class="social-meta-box-block">
	<h4><?php _e('Log', Social::$i18n); ?></h4>

	<div id="aggregation_log">
		<?php echo Social_Aggregation_Log::instance($post->ID); ?>
	</div>
</div><!-- .social-meta-box-block -->