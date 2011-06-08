<div id="social">
	<?php if (post_password_required()): ?>
	<p class="nopassword"><?php _e('This post is password protected. Enter the password to view any comments.', Social::$i18n); ?></p>
	<?php else: ?>
	<div class="social-heading">
		<?php
		if (is_user_logged_in()) {
			$tab = __('Post a Comment', Social::$i18n);
		}
		else {
			$tab = __('Profile', Social::$i18n);
		}
		?>
		<h2 class="social-title social-tab-active"><span><?php echo $tab; ?></span></h2>
	</div>

	<div class="social-post">
		<div id="loading" style="display:none">
			<input type="hidden" id="reload_url" value="<?php echo site_url('?'.Social::$prefix.'action=reload_form&redirect_to='.$_SERVER['REQUEST_URI']); ?>" />
			<img src="<?php echo admin_url('images/loading.gif'); ?>" style="position:relative;top:2px" /> Logging In...
		</div>
		<?php if (comments_open()): ?>
			<?php if (get_option( 'comment_registration' ) && !is_user_logged_in() ): ?>
				<p class="must-log-in"><?php printf(__('You must be <a href="%s">logged in</a> to post a comment.'), wp_login_url(apply_filters('the_permalink', get_permalink(get_the_ID())))); ?></p>
				<?php do_action('comment_form_must_log_in_after'); ?>
			<?php else: ?>
			<?php echo Social::comment_form(); ?>
			<?php endif; ?>
		<?php else: ?>
		<?php do_action('comment_form_comments_closed'); ?>
		<p class="nocomments"><?php _e('Comments are closed.', Social::$i18n); ?></p>
		<?php endif; ?>
	</div>

	<div id="social-tabs-comments">
		<?php if (have_comments()): ?>
		<?php
			$groups = array();
			foreach ($comments as $comment) {
				if (empty($comment->comment_type)) {
					$comment_type = get_comment_meta($comment->comment_ID, Social::$prefix.'comment_type', true);
					if (empty($comment_type)) {
						$comment_type = 'wordpress';
					}
					$comment->comment_type = $comment_type;
				}

				if (!isset($groups[$comment->comment_type])) {
					$groups[$comment->comment_type] = 1;
				}
				else {
					++$groups[$comment->comment_type];
				}
			}
		?>
		<ul class="social-nav social-clearfix">
			<li class="social-all social-tab-main social-current-tab"><a href="#" rel="social-all"><span><?php comments_number(__('0 Replies', Social::$i18n), __('1 Reply', Social::$i18n), __('% Replies', Social::$i18n)); ?></span></a></li>
			<li class="social-wordpress"><a href="#" rel="social-wordpress"><span><?php printf(_n('1 Comment', '%1$s Comments', $groups['wordpress'], Social::$i18n), $groups['wordpress']); ?></span></a></li>
			<li class="social-twitter"><a href="#" rel="social-twitter"><span><?php printf(_n('1 Tweet', '%1$s Tweets', $groups['twitter'], Social::$i18n), (isset($groups['twitter']) ? $groups['twitter'] : 0)); ?></span></a></li>
			<li class="social-facebook"><a href="#" rel="social-facebook"><span><?php printf(_n('1 Facebook', '%1$s Facebook', $groups['facebook'], Social::$i18n), (isset($groups['facebook']) ? $groups['facebook'] : 0)); ?></span></a></li>
			<li class="social-pingback"><a href="#" rel="social-pingback"><span><?php printf(_n('1 Pingback', '%1$s Pingbacks', $groups['pingback'], Social::$i18n), (isset($groups['pingback']) ? $groups['pingback'] : 0)); ?></span></a></li>
		</ul>

		<!-- panel items -->
		<div id="social-comments-tab-all" class="social-tabs-panel social-tabs-first-panel">
			<div class="social-comments">
				<div class="social-last-reply-when"><?php printf(__('Last reply was %s ago', Social::$i18n), human_time_diff(strtotime($comments[(count($comments)-1)]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
			</div><!-- #comments -->
		</div>
		<?php else: ?>
		<?php endif; ?>
	</div>
	<!-- #Comments Tabs -->
	<?php endif; ?>
</div>
