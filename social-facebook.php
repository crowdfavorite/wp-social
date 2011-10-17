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
	 * Quick hook to fix the comment type to service.
	 *
	 * @static
	 * @wp-filter  social_comment_type_to_service
	 * @param  string  $type
	 * @return string
	 */
	public static function comment_type_to_service($type) {
		if ($type == 'facebook-like') {
			$type = 'facebook';
		}

		return $type;
	}

	/**
	 * Adds to the avatar comment types array.
	 *
	 * @static
	 * @param  array  $types
	 * @return array
	 */
	public static function get_avatar_comment_types(array $types) {
		return array_merge($types, array(
			'social-facebook',
			'social-facebook-like'
		));
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
		if (is_object($comment) and $comment->comment_type == 'social-facebook-like') {
			$image = get_comment_meta($comment->comment_ID, 'social_profile_image_url', true);
			if ($image !== null) {
				$type = '';
				if (is_object($comment)) {
					$type = $comment->comment_type;
				}
				return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$type}' height='25' width='25' />";
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
		global $wpdb;

		$comment_ids = array();
		foreach ($comments as $comment) {
			if (is_object($comment) and $comment->comment_type == 'social-facebook-like') {
				$comment_ids[] = $comment->comment_ID;
			}
		}

		if (count($comment_ids)) {
			$results = $wpdb->get_results("
				SELECT meta_key, meta_value, comment_id
				  FROM $wpdb->commentmeta
				 WHERE comment_id IN (".implode(',', $comment_ids).")
				   AND meta_key = 'social_status_id'
				    OR meta_key = 'social_profile_image_url'
				    OR meta_key = 'social_comment_type'
			");

			$social_items = array();
			if (isset($comments['social_items'])) {
				$social_items = $comments['social_items'];
			    unset($comments['social_items']);
			}

			foreach ($comments as $key => $comment) {
				if (is_object($comment)) {
					if ($comment->comment_type == 'social-facebook-like') {
						foreach ($results as $result) {
							if ($result->comment_id == $comment->comment_ID) {
								$comment->{$result->meta_key} = $result->meta_value;
							}
						}
						
						if (!isset($social_items['facebook'])) {
							$social_items['facebook'] = array();
						}

						$social_items['facebook'][] = $comment;
					    unset($comments[$key]);
					}
				    else {
					    $comments[$key] = $comment;
				    }
				}
			    else {
				    $comments[$key] = $comment;
			    }
			}

			if (count($social_items)) {
				sort($comments);
			    $comments['social_items'] = $social_items;
			}
		}

		return $comments;
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
			$label = '<label for="social-facebook-pages">'
			       . '    <input type="checkbox" id="social-facebook-pages" value="true" />'
			       . '    Connect with Pages support'
			       . '</label>';

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
	 * Stores the Facebook page data to the broadcasted ID.
	 *
	 * @static
	 *
	 * @param  array                            $data
	 * @param  Social_Service_Facebook_Account  $account
	 * @param  Social_Service_Facebook          $service
	 * @param  int                              $post_id
	 *
	 * @return array
	 */
	public static function social_save_broadcasted_ids_data($data, $account, $service, $post_id) {
		if ($service == 'facebook') {
			$broadcast_page = $account->broadcast_page();
			if ($broadcast_page !== null) {
				$data['page'] = (object) array(
					'id' => $broadcast_page->id,
					'name' => $broadcast_page->name
				);
			}
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

	public static function social_view_data($data, $file) {
		if ($file == 'wp-admin/post/meta/broadcast/parts/facebook/page') {
			if (isset($data['data']) and isset($data['data']['page'])) {
				$data['account'] = $data['data']['page'];
			}
			else {
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
	 * @return object
	 */
	public static function social_merge_accounts($universal, $personal) {
		// Merge pages
		$universal->pages->personal = $personal->pages->personal;
		$universal->use_personal_pages = $personal->use_personal_pages;
		return $universal;
	}

} // End Social_Facebook

define('SOCIAL_FACEBOOK_FILE', __FILE__);

// Actions
add_action('social_settings_save', array('Social_Facebook', 'social_settings_save'));

// Filters
add_filter('social_register_service', array('Social_Facebook', 'register_service'));
add_filter('social_authorize_url', array('Social_Facebook', 'social_authorize_url'), 10, 2);
add_filter('social_comment_type_to_service', array('Social_Facebook', 'comment_type_to_service'));
add_filter('get_avatar', array('Social_Facebook', 'get_avatar'), 10, 5);
add_filter('get_avatar_comment_types', array('Social_Facebook', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Facebook', 'comments_array'), 10, 2);
add_filter('social_service_button', array('Social_Facebook', 'social_service_button'), 10, 3);
add_filter('social_proxy_url', array('Social_Facebook', 'social_proxy_url'));
add_filter('social_get_broadcast_account', array('Social_Facebook', 'social_get_broadcast_account'), 10, 3);
add_filter('social_save_broadcasted_ids_data', array('Social_Facebook', 'social_save_broadcasted_ids_data'), 10, 4);
add_filter('social_view_set_file', array('Social_Facebook', 'social_view_set_file'), 10, 2);
add_filter('social_view_data', array('Social_Facebook', 'social_view_data'), 10, 2);
add_filter('social_merge_accounts', array('Social_Facebook', 'social_merge_accounts'), 10, 2);

}
