<div class="social-items social-<?php echo $service->key(); ?>">
	<div class="social-items-icon"></div>
	<div class="social-items-comments">
		<?php
			$i = 0;
			foreach ($items as $item) {
				echo $service->social_item_output($item, $i, (isset($avatar_size) ? $avatar_size : array()));
				++$i;
			}

			if ($i > 10) {
				printf(__('<a href="%s" class="social-items-and-more">... and %s more</a>', 'social'), '#', ($i - 10));
			}
		?>
	</div>
</div>
