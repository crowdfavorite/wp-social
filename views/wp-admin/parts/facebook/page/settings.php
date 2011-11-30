<?php
	if (count($pages)) {
		echo '<h6>Account Pages</h6>'
		   . '<ul>';
		foreach ($pages as $page) {
			$checked = '';
			if ($account->page($page['id'], $is_profile) !== false) {
				$checked = ' checked="checked"';
			}

			echo '<li>'
			   . '    <input type="checkbox" name="social_facebook_pages_'.esc_attr($account->id()).'[]" value="'.esc_attr($page['id']).'"'.$checked.' />'
			   . '    <img src="'.esc_url($service->page_image_url($page)).'" width="24" height="24" />'
			   . '    <a href="http://facebook.com/'.esc_attr($page['id']).'" target="_blank">'.esc_html($page['name']).'</a>'
			   . '</li>';
		}

		echo '</ul>';
	}
?>
