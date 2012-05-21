<div id="social">
<?php
ob_start();
?>
	<?php if (post_password_required()): ?>
	<p class="nopassword"><?php _e('This post is password protected. Enter the password to view any comments.', 'social'); ?></p>
	<?php else: ?>
	<div class="social-post">
		<div id="loading" style="display:none">
			<input type="hidden" id="reload_url" value="<?php echo esc_url(home_url('index.php?social_controller=auth&social_action=reload_form&redirect_to='.get_permalink(get_the_ID()).'&post_id='.get_the_ID())); ?>" />
			<?php _e('Logging In...', 'social'); ?>
		</div>
		<?php
			if (comments_open()) {
				if (get_option('comment_registration') and !is_user_logged_in()) {
		?>
		<p class="must-log-in"><?php printf(__('You must be <a href="%s">logged in</a> to post a comment.', 'social'), wp_login_url(apply_filters('the_permalink', get_permalink(get_the_ID())))); ?></p>
		<?php
					do_action('comment_form_must_log_in_after');
				}

				echo Social_Comment_Form::instance(get_the_ID());
			}
			else {
				do_action('comment_form_comments_closed');
		?>
		<p class="nocomments"><?php _e('Comments are closed.', 'social'); ?></p>
		<?php
			}
		?>
	</div>
<?php
$form = ob_get_clean();

ob_start();
?>
	<div id="social-tabs-comments">
		<?php 
		if (have_comments()): 
			$groups = array();
			$social_items = array();
			if (get_comments_number()) {
				$comments = apply_filters('social_comments_array', $comments, $post->ID);

				if (isset($comments['social_items'])) {
					$social_items = $comments['social_items'];
					unset($comments['social_items']);
				}

				if (isset($comments['social_groups'])) {
					$groups = $comments['social_groups'];
					unset($comments['social_groups']);
				}
			}

			$last_reply_time = 0;
			if (count($comments)) {
				foreach ($comments as $key => $comment) {
					$time = strtotime($comment->comment_date_gmt);
					if ($time > $last_reply_time) {
						$last_reply_time = $time;
					}
				}
			}

			if (count($social_items)) {
				$latest_item = 0;
				foreach ($social_items as $service => $items) {
					foreach ($items as $comment) {
						if ($latest_item === 0) {
							$latest_item = strtotime($comment->comment_date_gmt);
						}
						else {
							$time = strtotime($comment->comment_date_gmt);
							if ($time > $latest_item) {
								$latest_item = $time;
							}
						}
					}
				}

				if ($latest_item > $last_reply_time) {
					$last_reply_time = $latest_item;
				}
			}

			Social::add_social_items_count($social_items, $groups);
		?>
		<ul class="social-nav social-clearfix">
			<li class="social-all social-tab-main<?php echo (!isset($_GET['social_tab']) ? ' social-current-tab' : ''); ?>"><a href="#" rel="social-all"><span><?php comments_number(__('0 Replies', 'social'), __('1 Reply', 'social'), __('% Replies', 'social')); ?></span></a></li>
			<li class="social-wordpress<?php echo ((isset($_GET['social_tab']) and $_GET['social_tab'] == 'wordpress') ? ' social-current-tab' : ''); ?>"><a href="#" rel="wordpress"><span><?php printf(_n('1 Comment', '%1$s Comments', (isset($groups['wordpress']) ? $groups['wordpress'] : 0), 'social'), (isset($groups['wordpress']) ? $groups['wordpress'] : 0)); ?></span></a></li>
			<li class="social-twitter<?php echo ((isset($_GET['social_tab']) and $_GET['social_tab'] == 'social-twitter') ? ' social-current-tab' : ''); ?>"><a href="#" rel="social-twitter"><span><?php printf(_n('1 Tweet', '%1$s Tweets', (isset($groups['social-twitter']) ? $groups['social-twitter'] : 0), 'social'), (isset($groups['social-twitter']) ? $groups['social-twitter'] : 0)); ?></span></a></li>
			<li class="social-facebook<?php echo ((isset($_GET['social_tab']) and $_GET['social_tab'] == 'social-facebook') ? ' social-current-tab' : ''); ?>"><a href="#" rel="social-facebook"><span><?php printf(_n('1 Facebook', '%1$s Facebook', (isset($groups['social-facebook']) ? $groups['social-facebook'] : 0), 'social'), (isset($groups['social-facebook']) ? $groups['social-facebook'] : 0)); ?></span></a></li>
			<li class="social-pingback<?php echo ((isset($_GET['social_tab']) and $_GET['social_tab'] == 'social-pingback') ? ' social-current-tab' : ''); ?>"><a href="#" rel="social-pingback"><span><?php printf(_n('1 Pingback', '%1$s Pingbacks', (isset($groups['pingback']) ? $groups['pingback'] : 0), 'social'), (isset($groups['pingback']) ? $groups['pingback'] : 0)); ?></span></a></li>
		</ul>

		<!-- panel items -->
		<div id="social-comments-tab-all" class="social-tabs-panel social-tabs-first-panel">
			<div id="comments" class="social-comments">
				<?php
				if ($last_reply_time) {
					echo '<div class="social-last-reply-when">'.sprintf(__('Last reply was %s', 'social'), Social_Date::span_comment($last_reply_time)).'</div>';
				}
				if (count($social_items)) {
					echo '<div id="social-items-wrapper">';
					foreach ($social_items as $group => $items) {
						$service = Social::instance()->service($group);
						if ($service !== false and count($items)) {
							$avatar_size = apply_filters('social_items_avatar_size', array(
								'width' => 24,
								'height' => 24,
							));
							echo Social_View::factory('comment/social_item', compact('items', 'service', 'avatar_size'));
						}
					}
					echo '</div>';
				}

				if ($last_reply_time or count($social_items)) {
					echo '<div class="cf-clearfix"></div>';
				}
				if (count($comments)) {
				?>
				<ol class="social-commentlist">
				<?php
						wp_list_comments(array(
							'callback' => array(Social::instance(), 'comment'),
							'walker' => new Social_Walker_Comment,
						), $comments);
				?>
				</ol>
				<?php
				}
				if (get_comment_pages_count() > 1 and get_option('page_comments')): 
				?>
				<nav id="comment-nav-below">
					<h1 class="assistive-text"><?php _e('Comment navigation', 'social'); ?></h1>
					<div class="nav-previous"><?php previous_comments_link(__('&larr; Older Comments', 'social')); ?></div>
					<div class="nav-next"><?php next_comments_link(__('Newer Comments &rarr;', 'social')); ?></div>
				</nav>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<!-- #Comments Tabs -->
	<?php endif; ?>
<?php
$comments = ob_get_clean();

$order = apply_filters('social_comment_block_order', array('form', 'comments'));

foreach ($order as $block) {
	if (isset($$block)) {
		echo $$block;
	}
}
?>
</div>
