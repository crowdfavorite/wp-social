<div id="social">
	<div class="social-heading">
		<h2 class="social-title social-tab-active"><span>Profile</span></h2>
	</div>

	<div class="social-sign-in" id="respond">
		<form action="<?php echo site_url('/wp-comments-post.php'); ?>" method="post" id="<?php echo esc_attr($args['id_form']); ?>">
		<?php comment_id_fields(); ?>
		<?php if (!is_user_logged_in()): ?>
		<div class="social-sign-in-links social-clearfix">
			<?php foreach (Social::$services as $key => $service): ?>
			<a class="social-<?php echo $key; ?> social-imr social-login" href="<?php echo Social_Helper::authorize_url($key); ?>" id="<?php echo $key; ?>_signin"><?php echo __('Sign in with '.$service->title(), Social::$i10n); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="social-divider">
			<span><?php echo __('or', Social::$i10n); ?></span>
		</div>
		<?php endif; ?>
		<div class="social-sign-in-form">
			<?php if (!is_user_logged_in()): ?>
			<div class="social-input-row">
				<label for="social-sign-in-name"><?php echo __('Name', Social::$i10n); ?></label>
				<input class="social-input-text" type="text" id="social-sign-in-name" name="author" />
			</div>
			<div class="social-input-row">
				<label for="social-sign-in-email"><?php echo __('Email', Social::$i10n); ?></label>
				<input class="social-input-text" type="text" id="social-sign-in-email" name="email" />
			</div>
			<div class="social-input-row">
				<label for="social-sign-in-website"><?php echo __('Website', Social::$i10n); ?></label>
				<input class="social-input-text" type="text" id="social-sign-in-website" name="url" />
			</div>
			<?php endif; ?>
			<div class="social-input-row">
				<label for="social-sign-in-comment"><?php echo __('Comment', Social::$i10n); ?></label>
				<textarea id="social-sign-in-comment" name="comment"></textarea>
			</div>
			<div class="social-input-row">
				<button type="submit" class="social-input-submit" style="float:left;"><span><?php echo __('Post It', Social::$i10n); ?></span></button>
				<?php if (is_user_logged_in()): ?>
					<?php if (current_user_can('manage_options')): ?>
						<span style="float:left;margin:4px 10px;">via</span>
						<select name="<?php echo Social::$prefix; ?>post_account">
							<option value=""><?php echo __('WordPress Account', Social::$i10n); ?></option>
							<?php foreach (Social::$services as $key => $service): ?>
							<optgroup label="<?php echo __(ucfirst($key), Social::$i10n); ?>">
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
							<span style="float:left;margin:4px 10px;"><?php echo __('via', Social::$i10n); ?></span>
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
			<li class="social-twitter"><a href="#social-comments-tab-twitter"><span><?php printf(_n('1 Twitter Comment', '%1$s Twitter Comments', count($groups['twitter']), Social::$i10n), count($groups['twitter'])); ?></span></a></li>
			<li class="social-facebook"><a href="#social-comments-tab-facebook"><span><?php printf(_n('1 Facebook Comment', '%1$s Facebook Comments', count($groups['facebook']), Social::$i10n), count($groups['facebook'])); ?></span></a></li>
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
				<p><?php echo __('There are no comments.', Social::$i10n); ?></p>
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
				<p><?php echo __('There are no comments.', Social::$i10n); ?></p>
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
				<p><?php echo __('There are no comments.', Social::$i10n); ?></p>
				<?php endif; ?>
			</div><!-- #comments -->
		</div>
		<?php else: ?>
		<?php endif; ?>
	</div>
	<!-- #Comments Tabs -->
</div>
