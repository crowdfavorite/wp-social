<div class="social-identity">
	<?php
		if (current_user_can('manage_options')) {
			echo get_avatar($current_user->ID, 40);
	?>
	<p class="social-input-row">
		<?php if (count($accounts)) { ?>
		<select class="social-select" id="post_accounts" name="social_post_account">
			<option value=""><?php _e('WordPress Account', 'social'); ?></option>
			<?php
				foreach ($accounts as $key => $_accounts) {
					$service = $services[$key];
					if (count($_accounts)) {
						echo '<optgroup label="'.esc_attr(__(ucfirst($key), 'social')).'">';
						foreach ($_accounts as $account) {
							echo '<option value="'.esc_attr($account->id()).'" rel="'.esc_attr($account->avatar()).'" data-type="'.$key.'">'.esc_html($account->name()).'</option>';
						}
						echo '</optgroup>';
					}
				}
			?>
		</select>
		<?php
			}
			else {
				echo '<input type="hidden" name="social_post_account" value="" />';
				printf(__('Logged in as <a href="%1$s">%2$s</a>.', 'social'), esc_url(admin_url('profile.php')), esc_html($current_user->display_name));
			}
		?>
		<small class="social-psst">(<?php echo wp_loginout(null, false); ?>)</small>
	</p>
	<?php
		}
		else {
			echo get_avatar($current_user->ID, 40);
			
			foreach ($services as $key => $service) {
				if (count($service->accounts())) {
					foreach ($service->accounts() as $account) {
						if ($account->personal()) {
	?>
	<p class="social-input-row">
		<span class="social-<?php echo esc_attr($key); ?>-icon">
			<?php echo esc_html($account->name()); ?>
			<small class="social-psst"><?php echo $service->disconnect_link($account); ?></small>
		</span>
	</p>
	<input type="hidden" name="social_post_account" value="<?php echo esc_attr($account->id()); ?>" />
	<?php
							break;
						}
					}
				}
			}
		}
	?>
</div>
