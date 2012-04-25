<?php
// backward compat check
if (strpos($comment_type, 'social-') === false) {
	$comment_type = 'social-'.$comment_type;
}

// set up the comment meta class (used for icon indicator)
switch ($comment_type) {
	case 'social-twitter':
	case 'social-facebook':
	case 'social-pingback':
	case 'social-wordpress':
		$parts = explode('-', $comment_type);
		$comment_meta_class = $parts[0].'-comment-meta-'.$parts[1];
	break;
	default:
		$comment_meta_class = 'social-comment-meta-wordpress';
}

?>
<li <?php comment_class('social-comment social-clearfix '.esc_attr($comment_type)); ?> id="li-comment-<?php comment_ID(); ?>">
<div class="social-comment-inner social-clearfix" id="comment-<?php comment_ID(); ?>">
	<div class="social-comment-header">
		<div class="social-comment-author vcard">
			<?php
				switch ($comment_type) {
					case 'pingback':
						echo '<span class="social-comment-label">'.__('Pingback', 'social').'</span>';
					break;
					default:
						echo get_avatar($comment, 40);
					break;
				}

				if (!$service instanceof Social_Service or $service->show_full_comment($comment->comment_type)) {
					printf('<cite class="social-fn fn">%s</cite>', get_comment_author_link());
				}

				if ($depth > 1) {
					echo '<span class="social-replied social-imr">'.__('replied:', 'social').'</span>';
				}
			?>
		</div>
		<!-- .comment-author .vcard -->
		<div class="social-comment-meta <?php echo esc_attr($comment_meta_class); ?>">
			<span class="social-posted-from">
				<?php if ($status_url !== null): ?>
				<a href="<?php echo esc_url($status_url); ?>" title="<?php _e(sprintf('View on %s', $service->title()), 'social'); ?>" target="_blank">
				<?php endif; ?>
				<span><?php _e('View', 'social'); ?></span>
				<?php if ($status_url !== null): ?>
				</a>
				<?php endif; ?>
			</span>
			<a href="<?php echo esc_url(get_comment_link(get_comment_ID())); ?>" class="social-posted-when" target="_blank"><?php echo esc_html(Social_Date::span_comment(strtotime($comment->comment_date_gmt))); ?></a>
		</div>
	</div>
	<div class="social-comment-body">
		<?php if ($comment->comment_approved == '0'): ?>
		<em class="comment-awaiting-moderation"><?php _e('Your comment is awaiting moderation.', 'social'); ?></em><br />
		<?php endif; ?>
		<?php comment_text(); ?>
	</div>
	<?php if (!$service instanceof Social_Service or $service->show_full_comment($comment->comment_type)): ?>
	<?php
		if (!empty($social_items)) {
	        echo '<div class="social-items-comment">'.$social_items.'</div>';
	    }
	?>
	<div class="social-actions entry-meta">
		<?php
            comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth'])));
            edit_comment_link(__('Edit', 'social'), '<span class="comment-edit-link"> &middot; ', '</span>');
        ?>
	</div>
	<?php endif; ?>
	<!-- .reply -->
</div><!-- #comment-<?php echo comment_ID(); ?> -->
