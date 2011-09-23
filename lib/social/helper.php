<?php
/**
 * Helpers for Social
 *
 * @package Social
 */
final class Social_Helper {

	/**
	 * Builds the settings URL for the plugin.
	 *
	 * @param  array  $params
	 * @param  bool   $personal
	 * @return string
	 */
	public static function settings_url(array $params = null, $personal = false) {
		if (!current_user_can('manage_options') or $personal) {
			$path = 'profile.php?';
		}
		else {
			$path = 'options-general.php?page='.basename(SOCIAL_FILE).'&';
		}

		if ($params !== null) {
			foreach ($params as $key => $value) {
				$path .= $key.'='.urlencode($value).'&';
			}
		}

		$path = rtrim($path, '&');
		if (!current_user_can('manage_options')) {
			$path .= '#social-networks';
		}

		return admin_url($path);
	}

} // End Social_Helper
