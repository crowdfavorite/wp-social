<li class="social-accounts-item">
	<div class="social-<?php echo $key; ?>-icon"><i></i></div>
	<span class="name"><?php echo $name; ?></span>
	<span class="disconnect"><?php echo $disconnect; ?></span>
	<?php
		if (count($pages)) {
			echo '<div class="social-facebook-pages">'
			   . '    <h6>Account Pages</h6>'
			   . '    <ul>';
			foreach ($pages as $page) {
				$checked = '';
				if ($account->page($page->id) !== false) {
					$checked = ' checked="checked"';
				}

				echo '<li>'
				   . '    <input type="checkbox" name="social_facebook_pages_'.$account->id().'[]" value="'.$page->id.'"'.$checked.' />'
				   . '    <img src="http://graph.facebook.com/'.$page->id.'/picture" width="16" height="16" />'
				   . '    <a href="http://facebook.com/'.$page->id.'" target="_blank">'.$page->name.'</a>'
				   . '</li>';
			}

			echo '    </ul>'
			   . '</div>';
		}
	?>
</li>
