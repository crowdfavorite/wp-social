<div id="social">
	<?php if (post_password_required()): ?>
	<p class="nopassword"><?php _e('This post is password protected. Enter the password to view any comments.', Social::$i10n); ?></p>
	<?php else: ?>
	<div class="social-heading">
		<h2 class="social-title social-tab-active"><span>Profile</span></h2>
	</div>

	<div class="social-sign-in" id="respond">
		<div id="loading" style="display:none">
			<input type="hidden" id="reload_url" value="<?php echo site_url('?'.Social::$prefix.'action=reload_form&redirect_to='.$_SERVER['REQUEST_URI']); ?>" />
			<img src="<?php echo admin_url('images/loading.gif'); ?>" style="position:relative;top:2px" /> Logging In...
		</div>
		<?php if (comments_open()): ?>
			<?php if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) : ?>
				<?php echo $args['must_log_in']; ?>
				<?php do_action( 'comment_form_must_log_in_after' ); ?>
			<?php else : ?>
			<div id="responsd">
				<?php echo Social::comment_form(); ?>
			</div>
			<?php endif; ?>
		<?php else: ?>
		<?php do_action('comment_form_comments_closed'); ?>
		<p class="nocomments"><?php _e('Comments are closed.', Social::$i10n); ?></p>
		<?php endif; ?>
	</div>

	<div id="social-tabs-comments">
		<?php if (have_comments()): ?>
		<?php
			$groups = array(
				'wordpress' => array(),
				'twitter' => array(),
				'facebook' => array(),
			);
			foreach ($comments as $comment) {
				$type = (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type);
				$groups[$type][] = $comment;
			}

			foreach ($groups as $type => $data) {
				$groups[$type] = array_reverse($data);
			}
		?>
		<ul class="social-nav social-clearfix">
			<li class="social-all social-tab-main"><a href="#social-comments-tab-all"><span><?php printf(_n('1 Reply', '%1$s Replies', get_comments_number(), Social::$i10n), get_comments_number()); ?></span></a></li>
			<li class="social-wordpress"><a href="#social-comments-tab-wordpress"><span><?php printf(_n('1 Comment', '%1$s Comments', count($groups['wordpress']), Social::$i10n), count($groups['wordpress'])); ?></span></a></li>
			<li class="social-twitter"><a href="#social-comments-tab-twitter"><span><?php printf(_n('1 Tweet', '%1$s Tweets', count($groups['twitter']), Social::$i10n), count($groups['twitter'])); ?></span></a></li>
			<li class="social-facebook"><a href="#social-comments-tab-facebook"><span><?php printf(_n('1 Facebook', '%1$s Facebook', count($groups['facebook']), Social::$i10n), count($groups['facebook'])); ?></span></a></li>
			<li class="social-pingback"><a href="#social-comments-tab-pingback"><span><?php printf(_n('1 Pingback', '%1$s Pingbacks', count($groups['pingback']), Social::$i10n), count($groups['pingback'])); ?></span></a></li>
		</ul>

		<!-- panel items -->
		<div id="social-comments-tab-all" class="social-tabs-panel social-tabs-first-panel">
			<div class="social-comments">
				<div class="social-last-reply-when"><?php printf(__('Last reply was %s ago', Social::$i10n), human_time_diff(strtotime($comments[(count($comments)-1)]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
			</div><!-- #comments -->
		</div>
		<div id="social-comments-tab-wordpress" class="social-tabs-panel">
			<div class="social-comments">
				<?php if (count($groups['wordpress'])): ?>
				<div class="social-last-reply-when"><?php printf(__('Last reply was %s ago', Social::$i10n), human_time_diff(strtotime($groups['wordpress'][0]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('type' => 'comment', 'callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
				<?php else: ?>
				<p class="social-comments-no-comments wordpress"><?php _e('There are no comments.', Social::$i10n); ?></p>
				<?php endif; ?>
			</div><!-- #comments -->
		</div>
		<div id="social-comments-tab-twitter" class="social-tabs-panel">
			<div class="social-comments">
				<?php if (count($groups['twitter'])): ?>
				<div class="social-last-reply-when"><?php printf(__('Last reply was %s ago', Social::$i10n), human_time_diff(strtotime($groups['twitter'][0]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('type' => 'twitter', 'callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
				<?php else: ?>
				<p class="social-comments-no-comments twitter"><?php _e('There are no Twitter comments.', Social::$i10n); ?></p>
				<?php endif; ?>
			</div><!-- #comments -->
		</div>
		<div id="social-comments-tab-facebook" class="social-tabs-panel">
			<div class="social-comments">
				<?php if (count($groups['facebook'])): ?>
				<div class="social-last-reply-when"><?php printf(__('Last reply was %s ago', Social::$i10n), human_time_diff(strtotime($groups['facebook'][0]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('type' => 'facebook', 'callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
				<?php else: ?>
				<p class="social-comments-no-comments facebook"><?php _e('There are no Facebook comments.', Social::$i10n); ?></p>
				<?php endif; ?>
			</div><!-- #comments -->
		</div>
		<div id="social-comments-tab-pingback" class="social-tabs-panel">
			<div class="social-comments">
				<?php if (count($groups['pingback'])): ?>
				<div class="social-last-reply-when"><?php printf(__('Last pingback was %s ago', Social::$i10n), human_time_diff(strtotime($groups['pingback'][0]->comment_date))); ?></div>
				<ol class="social-commentlist">
					<?php wp_list_comments(array('type' => 'pingback', 'callback' => array('Social', 'comment'), 'walker' => new Social_Walker_Comment)); ?>
				</ol>
				<?php else: ?>
				<p class="social-comments-no-comments pingbacks"><?php _e('There are no pingbacks.', Social::$i10n); ?></p>
				<?php endif; ?>
			</div><!-- #comments -->
		</div>
		<?php else: ?>
		<?php endif; ?>
	</div>
	<!-- #Comments Tabs -->
	<?php endif; ?>
</div>
