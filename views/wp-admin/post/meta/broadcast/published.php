<?php
if (!$broadcasted) {
	echo '<p class="mar-top-none">'
	   . __('This post has not been broadcasted to any accounts yet. You may do so by clicking the "Broadcast" button below.', 'social')
	  . '</p>';

	if (!is_array($ids) or !count($ids)) {
		echo '<p>'.__('Would you like to broadcast this post?', 'social').'</p>';
	}
}
