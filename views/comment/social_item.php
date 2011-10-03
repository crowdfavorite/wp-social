<div class="social-items social-<?php echo $service->key(); ?>">
	<div class="social-items-icon"><?php echo $service->social_items_icon(); ?></div>
	<div class="social-items-comments">
		<?php
			$i = 0;
			foreach ($items as $item) {
                echo $service->social_item_output($item, $i);
                ++$i;
			}

			if ($i >= 10) {
				echo sprintf(__('<a href="%s" class="social-items-and-more">... and %s more</a>', Social::$i18n), '', ($i - 10));
			}
		?>
	</div>
</div>