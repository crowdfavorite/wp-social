<?php
echo '<p>'.sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', 'social'), esc_html($post->post_title)).'</p>';
foreach ($accounts as $key => $items) {
	echo '<ul class="social-posting-errors">';
	foreach ($items as $item) {
		echo '<li>'.esc_html($social->service($key)->title()).': '.esc_html($item->account->name()).' ('.esc_html($item->reason).')</li>';
	}
	echo '</ul>';
}
