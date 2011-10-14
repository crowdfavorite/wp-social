<?php
	if (count($pages)) {
		echo '<h6>Account Pages</h6>'
		   . '<ul>';
		foreach ($pages as $page) {
			$checked = '';
			if ($account->page($page->id, $is_profile) !== false) {
				$checked = ' checked="checked"';
			}

			echo '<li>'
			   . '    <input type="checkbox" name="social_facebook_pages_'.$account->id().'[]" value="'.$page->id.'"'.$checked.' />'
			   . '    <img src="http://graph.facebook.com/'.$page->id.'/picture" width="16" height="16" />'
			   . '    <a href="http://facebook.com/'.$page->id.'" target="_blank">'.$page->name.'</a>'
			   . '</li>';
		}

		echo '</ul>';
	}
?>
