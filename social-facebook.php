<?php
/**
 * Twitter implementation for Social.
 *
 * @package    Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Facebook')) {

final class Social_Facebook {

	/**
	 * Registers Facebook to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 *
	 * @param  array  $services
	 *
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'facebook';
		return $services;
	}

	/**
	 * Adds the permissions stuff in for Facebook.
	 *
	 * @static
	 * @wp-filter  social_authorize_url
	 * @param  string  $url  authorization url
	 * @param  string  $key  service key
	 * @return string
	 */
	public static function social_authorize_url($url, $key) {
		if ($key == 'facebook') {
			$perms = 'publish_stream';
			if (is_admin()) {
				$perms .= ',read_stream,offline_access';
			}

			$url = $url.'?req_perms='.$perms;
		}

		return $url;
	}

	/**
	 * Adds to the avatar comment types array.
	 *
	 * @static
	 * @param  array  $types
	 * @return array
	 */
	public static function get_avatar_comment_types(array $types) {
		return array_merge($types, Social_Service_Facebook::comment_types());
	}

	/**
	 * Gets the avatar based on the comment type.
	 *
	 * @static
	 * @wp-filter  get_avatar
	 * @param  string  $avatar
	 * @param  object  $comment
	 * @param  int     $size
	 * @param  string  $default
	 * @param  string  $alt
	 * @return string
	 */
	public static function get_avatar($avatar, $comment, $size, $default, $alt) {
		if (is_object($comment) and in_array($comment->comment_type, Social_Service_Facebook::comment_types())) {
			$image = esc_url(get_comment_meta($comment->comment_ID, 'social_profile_image_url', true));
			if ($image !== null) {
				$size = esc_attr($size);
				$type = esc_attr($comment->comment_type);
				return '<img alt="'.$alt.'" src="'.$image.'" class="avatar avatar-'.$size.' photo '.$type.'" height="'.$size.'" width="'.$size.'" />';
			}
		}
		return $avatar;
	}

	/**
	 * Pre-processor to the comments.
	 *
	 * @wp-filter social_comments_array
	 * @static
	 * @param  array  $comments
	 * @param  int    $post_id
	 * @return array
	 */
	public static function comments_array(array $comments, $post_id) {
		// pre-load the hashes for broadcasted tweets
		$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids) or empty($broadcasted_ids['facebook'])) {
			$broadcasted_ids = array();
		}
		global $wpdb;

		// we need comments to be keyed by ID, check for Facebook comments
		$facebook_comments = $facebook_likes = $_comments = $comment_ids = array();
		foreach ($comments as $key => $comment) {
			if (is_object($comment)) {
				$_comments['id_'.$comment->comment_ID] = $comment;
				if (in_array($comment->comment_type, Social_Service_Facebook::comment_types())) {
					$comment_ids[] = $comment->comment_ID;
					$facebook_comments['id_'.$comment->comment_ID] = $comment;
				}
			}
			else { // social items
				$_comments[$key] = $comment;
			}
		}

		// if no Facebook comments, get out now
		if (!count($facebook_comments)) {
			return $comments;
		}

		// use our keyed array
		$comments = $_comments;
		unset($_comments);

		// Load the comment meta
		$results = $wpdb->get_results("
			SELECT meta_key, meta_value, comment_id
			  FROM $wpdb->commentmeta
			 WHERE comment_id IN (".implode(',', $comment_ids).")
			   AND (
			       meta_key = 'social_status_id'
			    OR meta_key = 'social_profile_image_url'
			    OR meta_key = 'social_comment_type'
			)
		");

		// Set up social data for facebook comments
		foreach ($facebook_comments as $key => &$comment) {
			$comment->social_items = array();

			// Attach meta
			foreach ($results as $result) {
				if ($comment->comment_ID == $result->comment_id) {
					$comment->{$result->meta_key} = $result->meta_value;
				}
			}
		}

		// merge data so that $comments has the data we've set up
		$comments = array_merge($comments, $facebook_comments);

		// set-up the likes
		foreach ($facebook_comments as $key => &$comment) {
			if (is_object($comment) and isset($broadcasted_ids['facebook'])) {
				foreach ($broadcasted_ids['facebook'] as $account_id => $broadcasted) {
					if (isset($comment->social_status_id) and isset($broadcasted[$comment->social_status_id]) and $comment->comment_type == 'social-facebook-like') {
						$facebook_likes[] = $comment;
						unset($comments['id_'.$comment->comment_ID]);
					}
				}
			}
		}

		// Add the likes
		if (!isset($comments['social_items'])) {
			$comments['social_items'] = array();
		}

		if (count($facebook_likes)) {
			$comments['social_items']['facebook'] = $facebook_likes;
		}

		return $comments;
	}

	/**
	 * Filters the groups.
	 *
	 * @static
	 * @param  array  $groups
	 * @param  array  $comments
	 * @return array
	 */
	public static function comments_array_groups(array $groups, array $comments) {
		if (isset($groups['social-facebook-like'])) {
			if (!isset($groups['social-facebook'])) {
				$groups['social-facebook'] = 0;
			}

			$groups['social-facebook'] = $groups['social-facebook'] + $groups['social-facebook-like'];
			unset($groups['social-facebook-like']);
		}

		return $groups;
	}

	/**
	 * Adds the Facebook Pages checkbox to the button.
	 *
	 * @static
	 * @param  string                   $button
	 * @param  Social_Service_Facebook  $service
	 * @param  bool                     $profile_page
	 * @return string
	 */
	public static function social_service_button($button, $service, $profile_page = false) {
		if ($service->key() == 'facebook') {
			$label = '<input type="checkbox" id="social-facebook-pages" value="true" />'
			       . '<label for="social-facebook-pages">'.__('Connect with Pages support', 'social').'</label>';

			if (!$profile_page) {
				$button = explode('</div>', $button);
				$button = $button[0].$label.'</div>';
			}
		}
		return $button;
	}

	/**
	 * Adds the manage pages permission onto the URL.
	 *
	 * @static
	 * @param  string  $url
	 * @return array|string
	 */
	public static function social_proxy_url($url) {
		if (isset($_GET['use_pages']) and strpos($url, 'req_perms') !== false) {
			$url = explode('req_perms=', $url);
			$url = $url[0].'req_perms=manage_pages,'.$url[1];

			// Now add the query param to the response URL
			$url = explode('response_url=', $url);
			$response_url = add_query_arg(array(
				'use_pages' => 'true'
			), urldecode($url[1]));
			$url = $url[0].'response_url='.urlencode($response_url);
		}
		return $url;
	}

	/**
	 * Saves the Facebook pages.
	 *
	 * @wp-action social_settings_save
	 * @static
	 * @param  bool $is_personal
	 */
	public static function social_settings_save($is_personal = false) {
		$service = Social::instance()->service('facebook');
		if ($service !== false) {
			$accounts = $service->accounts();
			if (count($accounts)) {
				foreach ($accounts as $account_id => $account) {
					if (isset($_POST['social_facebook_pages_'.$account->id()])) {
						$pages = $service->get_pages($account);

						$account->pages(array());
						if (count($pages)) {
							foreach ($_POST['social_facebook_pages_'.$account->id()] as $page_id) {
								if (isset($pages[$page_id])) {
									$accounts[$account_id] = $account->page($pages[$page_id]);
								}
							}
						}
					}

					if (defined('IS_PROFILE_PAGE')) {
						$accounts[$account_id]->universal(false);
						$accounts[$account_id]->use_pages(false, false);
						$accounts[$account_id]->pages(array(), false);
					}
					else {
						$accounts[$account_id]->personal(false);
						$accounts[$account_id]->use_pages(true, false);
						$accounts[$account_id]->pages(array(), true);
					}

					$accounts[$account_id] = $accounts[$account_id]->as_object();
				}

				$service->accounts($accounts)->save($is_personal);
			}
		}
	}

	/**
	 * @static
	 * @param  object                   $account
	 * @param  WP_Post                  $post
	 * @param  Social_Service_Facebook  $service
	 *
	 * @return object|bool
	 */
	public static function social_get_broadcast_account($account, $post, $service) {
		if ($service->key() == 'facebook') {
			// Load accounts
			$found = false;
			$accounts = $service->accounts();
			foreach ($accounts as $_account) {
				$pages = $_account->pages(null, 'combined');
				if (isset($pages[$account->id])) {
					$found = true;
					$account = $_account->broadcast_page($pages[$account->id]);
					break;
				}
			}

			if (!$found) {
				$personal_accounts = get_user_meta($post->post_author, 'social_accounts', true);
				if (isset($personal_accounts['facebook'])) {
					foreach ($personal_accounts['facebook'] as $account_id => $_account) {
						$_account = new Social_Service_Facebook_Account($_account);
						$pages = $_account->pages(null, 'combined');
						if (isset($pages[$account->id])) {
							$found = true;
							$account = $_account->broadcast_page($pages[$account->id]);
							break;
						}
					}
				}
			}

			if ($found) {
				return $account;
			}
		}

		return false;
	}

	/**
	 * Sets the raw data for the broadcasted post.
	 *
	 * @wp-filter social_broadcast_response
	 * @static
	 * @param  array                   $data
	 * @param  Social_Service_Account  $account
	 * @param  string                  $service_key
	 * @param  int                     $post_id
	 * @param  Social_Response         $response
	 * @return array
	 */
	public static function social_save_broadcasted_ids_data(array $data, Social_Service_Account $account, $service_key, $post_id, Social_Response $response = null) {
		if ($service_key == 'facebook') {
			$broadcast_page = $account->broadcast_page();
			if ($broadcast_page !== null) {
				$data['page'] = (object) array(
					'id' => $broadcast_page->id,
					'name' => $broadcast_page->name
				);
			}

			$data['account'] = (object) array(
				'user' => $account->as_object()->user
			);
		}

		return $data;
	}

	/**
	 * Filter to change the view for Facebook Pages
	 *
	 * @static
	 * @param  string  $file
	 * @param  array   $data
	 * @return string
	 */
	public static function social_view_set_file($file, $data) {
		if (isset($data['service']) and
			$data['service'] != false and
			$data['service']->key() == 'facebook' and
			(isset($data['data']) and isset($data['data']['page'])) or
			(isset($data['account']) and !$data['account'] instanceof Social_Service_Account))
		{
			$file = 'wp-admin/post/meta/broadcast/parts/facebook/page';
		}

		return $file;
	}

	/**
	 * Sets the Social view data.
	 *
	 * @static
	 * @param  array   $data
	 * @param  string  $file
	 * @return array
	 */
	public static function social_view_data($data, $file) {
		if ($file == 'wp-admin/post/meta/broadcast/parts/facebook/page') {
			if (isset($data['data']) and isset($data['data']['page'])) {
				$data['account'] = $data['data']['page'];
			}
			else if ($data['account'] instanceof Social_Service_Account) {
				$data['account'] = (object) array(
					'id' => $data['account']->id(),
					'name' => $data['account']->username()
				);
			}
		}

		return $data;
	}

	/**
	 * Merges the personal pages into the universal account.
	 *
	 * @static
	 * @param  object  $universal
	 * @param  object  $personal
	 * @param  string  $service_key
	 * @return object
	 */
	public static function social_merge_accounts($universal, $personal, $service_key) {
		// Merge pages
		if ($service_key == 'facebook') {
			$universal->pages->personal = $personal->pages->personal;
			$universal->use_personal_pages = $personal->use_personal_pages;
		}
		return $universal;
	}

	/**
	 * Adds messaging to the title.
	 *
	 * @static
	 * @param  string  $title
	 * @param  string  $key
	 * @return string
	 */
	public static function social_item_output_title($title, $key) {
		if ($key == 'facebook') {
			$title = sprintf(__('%s liked this', 'social'), $title);
		}

		return $title;
	}
	
	/**
	 * Output the link to be sent to Facebook.
	 *
	 * @static
	 * @param  object  $post
	 * @param  object  $service
	 * @param  object  $account
	 * @return void
	 */
	public static function social_broadcast_form_item_content($post, $service, $account) {
		if ($service->key() != 'facebook' || get_post_format($post) == 'status') {
			return;
		}
		remove_filter('social_view_set_file', array('Social_Facebook', 'social_view_set_file'), 10, 2);
		echo Social_View::factory(
			'wp-admin/post/broadcast/facebook-link-preview',
			compact('post', 'service', 'account')
		)->render();
		add_filter('social_view_set_file', array('Social_Facebook', 'social_view_set_file'), 10, 2);
	}
	
	/**
	 * Don't output URL in format since we're sending a link as well.
	 *
	 * @static
	 * @param  string  $format
	 * @param  object  $post
	 * @param  object  $service
	 * @return string
	 */
	public static function social_broadcast_format($format, $post, $service) {
		if ($service->key() == 'facebook' && get_post_format($post) != 'status') {
			$format = trim(str_replace('{url}', '', $format));
		}
		return $format;
	}
	

} // End Social_Facebook

define('SOCIAL_FACEBOOK_FILE', __FILE__);

// Actions
add_action('social_settings_save', array('Social_Facebook', 'social_settings_save'));
add_action('social_broadcast_form_item_content', array('Social_Facebook', 'social_broadcast_form_item_content'), 10, 3);

// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));
add_filter('social_authorize_url', array('Social_Facebook', 'social_authorize_url'), 10, 2);
add_filter('get_avatar', array('Social_Facebook', 'get_avatar'), 10, 5);
add_filter('get_avatar_comment_types', array('Social_Facebook', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Facebook', 'comments_array'), 10, 2);
add_filter('social_comments_array_groups', array('Social_Facebook', 'comments_array_groups'), 10, 2);
add_filter('social_service_button', array('Social_Facebook', 'social_service_button'), 10, 3);
add_filter('social_proxy_url', array('Social_Facebook', 'social_proxy_url'));
add_filter('social_get_broadcast_account', array('Social_Facebook', 'social_get_broadcast_account'), 10, 3);
add_filter('social_save_broadcasted_ids_data', array('Social_Facebook', 'social_save_broadcasted_ids_data'), 10, 5);
add_filter('social_view_set_file', array('Social_Facebook', 'social_view_set_file'), 10, 2);
add_filter('social_view_data', array('Social_Facebook', 'social_view_data'), 10, 2);
add_filter('social_merge_accounts', array('Social_Facebook', 'social_merge_accounts'), 10, 3);
add_filter('social_item_output_title', array('Social_Facebook', 'social_item_output_title'), 10, 2);
add_filter('social_broadcast_format', array('Social_Facebook', 'social_broadcast_format'), 11, 3);

}
