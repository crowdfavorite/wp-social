<?php
echo '<p>'.sprintf(__('Social failed to broadcast the blog post "%s" to one or more of your Social accounts.', 'social'), esc_html($post->post_title)).'</p>';
foreach ($accounts as $key => $items) {
	echo '<h4>'.esc_html($social->service($key)->title()).':</h4><ul>';
	foreach ($items as $item) {
		echo '<li>'.esc_html($item->account->name()).' ('.esc_html($item->reason).')</li>';
	}
	echo '</ul>';
}
