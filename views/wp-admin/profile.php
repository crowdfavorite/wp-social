<h3 id="social-accounts"><?php _e('Social Accounts', 'social'); ?></h3>
<table class="form-table">
	<tr id="social-accounts">
		<th>
			<?php _e('Accounts', 'social'); ?>
			<p class="description" style="padding-top: 40px;"><?php _e('Only I can broadcast to these accounts.', 'social'); ?></p>
		</th>
		<td>
<?php
echo Social_View::factory(
	'wp-admin/parts/accounts',
	compact('services', 'accounts', 'defaults')
);
?>
		</td>
	</tr>
</table>
<input type="hidden" name="social_profile" value="true" />
