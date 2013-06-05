<?php
/**
 * @package    Social
 * @subpackage services
 */
abstract class Social_Service {

	/**
	 * @var  string  service key
	 */
	protected $_key = '';

	/**
	 * @var  array  collection of account objects
	 */
	protected $_accounts = array();

	/**
	 * Instantiates the
	 *
	 * @param  array  $accounts
	 */
	public function __construct(array $accounts = array()) {
		$this->accounts($accounts);
	}

	/**
	 * Returns the service key.
	 *
	 * @return string
	 */
	public function key() {
		return $this->_key;
	}

	/**
	 * Gets the title for the service.
	 *
	 * @return string
	 */
	public function title() {
		return ucwords(str_replace('_', ' ', $this->_key));
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140; // default to Twitter length
	}

	/**
	 * Builds the authorize URL for the service.
	 *
	 * @return string
	 */
	public function authorize_url() {
		global $post;

		$params = '?social_controller=auth&social_action=authorize&key='.$this->_key;
		if (is_admin()) {
			$url = (defined('IS_PROFILE_PAGE') ? 'profile.php' : 'options-general.php');
			$url = admin_url($url.$params);
		}
		else {
			$url = home_url('index.php'.$params.'&post_id='.$post->ID);
		}

		return $url;
	}

	/**
	 * Returns the disconnect URL.
	 *
	 * @static
	 *
	 * @param  object  $account
	 * @param  bool    $is_admin
	 * @param  string  $before
	 * @param  string  $after
	 *
	 * @return string
	 */
	public function disconnect_url($account, $is_admin = false, $before = '', $after = '') {
		$params = array(
			'social_controller' => 'auth',
			'social_action' => 'disconnect',
			'id' => $account->id(),
			'service' => $this->_key
		);

		if ($is_admin) {
			$personal = defined('IS_PROFILE_PAGE');
			$url = Social::settings_url($params, $personal);
		}
		else {
			$params['redirect_to'] = (isset($_GET['redirect_to']) ? $_GET['redirect_to'] : $_SERVER['REQUEST_URI']);
			foreach ($params as $key => $value) {
				$params[$key] = urlencode($value);
			}
			$url = add_query_arg($params, home_url());
		}
		return $url;
	}

	/**
	 * Returns the disconnect link.
	 *
	 * @static
	 *
	 * @param  object  $account
	 * @param  bool    $is_admin
	 * @param  string  $before
	 * @param  string  $after
	 *
	 * @return string
	 */
	public function disconnect_link($account, $is_admin = false, $before = '', $after = '') {
		$url = $this->disconnect_url($account, $is_admin, $before, $after);
		if ($is_admin) {
			$text = '<span title="'.__('Disconnect', 'social').'" class="social-disconnect">'.__('Disconnect', 'social').'</span>';
		}
		else {
			$text = __('Disconnect', 'social');
		}
		return sprintf('%s<a href="%s">%s</a>%s', $before, esc_url($url), $text, $after);
	}

	/**
	 * Creates a WordPress user with the passed in account.
	 *
	 * @param  Social_Service_Account  $account
	 * @param  string                  $nonce
	 * @return int|bool
	 */
	public function create_user($account, $nonce = null) {
		$username = $account->username();
		$username = sanitize_user($username, true);
		if (!empty($username)) {
			$user = get_user_by('login', $this->_key.'_'.$username);
			if ($user === false) {
				$id = wp_create_user($this->_key.'_'.$username, wp_generate_password(20, false), $this->_key.'.'.$username.'@example.com');
				if (is_wp_error($id)) {
					Social::log('Failed to create/find user with username of :username.', array(
						'username' => $username,
					));
					return false;
				}

				$role = '';
				if (get_option('users_can_register') == '1') {
					$role = get_option('default_role');
				}
				else {
					// Set commenter flag
					update_user_meta($id, 'social_commenter', 'true');
				}

				$user = new WP_User($id);
				$user->set_role($role);
				$user->show_admin_bar_front = 'false';
				wp_update_user(get_object_vars($user));
			}
			else {
				$id = $user->ID;
			}

			// Set the nonce
			if ($nonce !== null) {
				wp_set_current_user($id);
				update_user_meta($id, 'social_commenter', 'true');
				update_user_meta($id, 'social_auth_nonce_'.$nonce, 'true');
			}

			Social::log('Created/found user :username. (#:id)', array(
				'username' => $username,
				'id' => $id,
			));
			return $id;
		}

		Social::log('Failed to create/find user with username of :username.', array(
			'username' => $username,
		));
		return false;
	}

	/**
	 * Saves the accounts on the service.
	 *
	 * @param  bool  $personal  personal account?
	 * @return void
	 */
	public function save($personal = false) {
		// Flush the cache
		wp_cache_delete('services', 'social');

		$accounts = array();
		if ($personal) {
			foreach ($this->_accounts AS $account) {
				if ($account->personal()) {
					$accounts[$account->id()] = $account->as_object();
				}

				$account->universal(false);
			}

			$current = get_user_meta(get_current_user_id(), 'social_accounts', true);
			Social::log('Current accounts: :accounts', array(
				'accounts' => print_r($current, true)
			));
			if (count($accounts)) {
				$current[$this->_key] = $accounts;

			}
			else if (isset($current[$this->_key])) {
				unset($current[$this->_key]);
			}

			if (count($current)) {
				Social::log('New accounts: :accounts', array(
					'accounts' => print_r($current, true)
				));
				update_user_meta(get_current_user_id(), 'social_accounts', $current);
			}
			else {
				Social::log('No accounts, deleting user meta for user #:user_id social_accounts', array(
					'user_id' => get_current_user_id(),
				));
				delete_user_meta(get_current_user_id(), 'social_accounts');
			}
		}
		else {
			foreach ($this->_accounts AS $account) {
				if ($account->universal()) {
					$accounts[$account->id()] = $account->as_object();
				}

				$account->personal(false);
			}

			$current = Social::option('accounts');
			if ($current == null) {
				$current = array();
			}
			Social::log('Current accounts: :accounts', array(
				'accounts' => print_r($current, true)
			));

			if (count($accounts)) {
				$current[$this->_key] = $accounts;
			}
			else if (isset($current[$this->_key])) {
				unset($current[$this->_key]);
			}

			if (count($current)) {
				Social::log('New accounts: :accounts', array(
					'accounts' => print_r($current, true)
				));
				Social::option('accounts', $current);
			}
			else {
				Social::log('No accounts, deleting option social_accounts');
				delete_option('social_accounts');
			}
		}
	}

	/**
	 * Checks to see if the account exists on the object.
	 *
	 * @param  int  $id  account id
	 * @return bool
	 */
	public function account_exists($id) {
		return isset($this->_accounts[$id]);
	}

	/**
	 * Gets the requested account.
	 *
	 * @param  int|Social_Service_Account  $account  account id/object
	 * @return Social_Service_Account|Social_Service|bool
	 */
	public function account($account) {
		if ($account instanceof Social_Service_Account) {
			$this->_accounts[$account->id()] = $account;
			return $this;
		}

		if ($this->account_exists($account)) {
			return $this->_accounts[$account];
		}

		return false;
	}

	/**
	 * Gets the specified "api" account.
	 *
	 * @return Social_Service_Account|Social_Service|bool
	 */
	public function api_account() {
		if ($social_api_accounts = Social::option('social_api_accounts')) {
			if (isset($social_api_accounts[$this->key()])) {
				return $this->account($social_api_accounts[$this->key()]);
			}
		}

		return null;
	}

	/**
	 * Acts as a getter and setter for service accounts.
	 *
	 * @param  array  $accounts  accounts to add to the service
	 * @return array|Social_Service
	 */
	public function accounts(array $accounts = null) {
		if ($accounts === null) {
			return $this->_accounts;
		}

		$class = 'Social_Service_'.$this->_key.'_Account';
		foreach ($accounts as $account) {
			$account = new $class($account);
			if (!$this->account_exists($account->id())) {
				$this->_accounts[$account->id()] = $account;
			}
		}
		return $this;
	}

	/**
	 * Removes an account from the service.
	 *
	 * @abstract
	 * @param  int|Social_Service_Account  $account
	 * @return Social_Service
	 */
	public function remove_account($account) {
		Social::log('Starting account removal...');
		if (is_int($account)) {
			$account = $this->account($account);
		}

		Social::log('Accounts: :accounts', array(
			'accounts' => print_r($this->_accounts, true),
		));
		if ($account !== false) {
			Social::log('Removing...');
			unset($this->_accounts[$account->id()]);
		}
		Social::log('Accounts: :accounts', array(
			'accounts' => print_r($this->_accounts, true)
		));

		return $this;
	}

	/**
	 * Removes all accounts from the service.
	 *
	 * @abstract
	 * @return Social_Service
	 */
	public function clear_accounts() {
		$this->_accounts = array();
		return $this;
	}

	/**
	 * Formats the broadcast content.
	 *
	 * @param  object  $post
	 * @param  string  $format
	 * @return string
	 */
	public function format_content($post, $format) {
		// Filter the format
		$format = apply_filters('social_broadcast_format', $format, $post, $this);

		$_format = $format;
		$available = $this->max_broadcast_length();
		foreach (Social::broadcast_tokens() as $token => $description) {
			$_format = str_replace($token, '', $_format);
		}
		$available = $available - social_strlen($_format);

		$_format = explode(' ', $format);
		foreach (Social::broadcast_tokens() as $token => $description) {
			$content = '';
			switch ($token) {
				case '{url}':
					$url = social_get_shortlink($post->ID);
					if (empty($url)) {
						$url = home_url('?p='.$post->ID);
					}
					$url = apply_filters('social_broadcast_permalink', $url, $post, $this);
					$content = esc_url($url);
					break;
				case '{title}':
					$content = htmlspecialchars_decode($post->post_title);
					break;
				case '{content}':
					$content = do_shortcode($post->post_content);
					$content = htmlspecialchars_decode(strip_tags($content));
					$content = preg_replace('/\s+/', ' ', $content);
					break;
				case '{author}':
					$user = get_userdata($post->post_author);
					$content = htmlspecialchars_decode($user->display_name);
					break;
				case '{date}':
					$content = get_date_from_gmt($post->post_date_gmt);
					break;
			}

			if (social_strlen($content) > $available) {
				if (in_array($token, array('{date}', '{author}'))
				) {
					$content = '';
				}
				else {
					$content = social_substr($content, 0, ($available - 3)).'...';
				}
			}

			// Filter the content
			$content = apply_filters('social_format_content', $content, $post, $format, $this);

			foreach ($_format as $haystack) {
				if (strpos($haystack, $token) !== false and $available > 0) {
					$haystack = str_replace($token, $content, $haystack);
					$available = $available - social_strlen($haystack);
					$format = str_replace($token, $content, $format);
					break;
				}
			}
		}

		// Filter the content
		$format = apply_filters('social_broadcast_content_formatted', $format, $post, $this);

		return $format;
	}

	/**
	 * Formats a comment before it's broadcasted.
	 *
	 * @param  WP_Comment  $comment
	 * @param  array       $format
	 * @return string
	 */
	public function format_comment_content($comment, $format) {
		// Filter the format
		$format = apply_filters('social_comment_broadcast_format', $format, $comment, $this);

		$_format = $format;
		$available = $this->max_broadcast_length();
		$used_tokens = array();

		// Gather used tokens and subtract remaining characters from available length
		foreach (Social::comment_broadcast_tokens() as $token => $description) {
			$replaced = 0;
			$_format = str_replace($token, '', $_format, $replaced);
			if ($replaced) {
				$used_tokens[$token] = '';
			}
		}
		$available = $available - social_strlen($_format);

		// Prep token replacement content
		foreach ($used_tokens as $token => $content) {
			switch ($token) {
				case '{url}':
					$url = social_get_shortlink($comment->comment_post_ID);
					if (empty($url)) {
						$url = home_url('?p='.$comment->comment_post_ID);
					}
					$url .= '#comment-'.$comment->comment_ID;
					$url = apply_filters('social_comment_broadcast_permalink', $url, $comment, $this);
					$used_tokens[$token] = esc_url($url);
					break;
				case '{content}':
					$used_tokens[$token] = strip_tags($comment->comment_content);
					$used_tokens[$token] = str_replace('&nbsp;', '', $used_tokens[$token]);
					break;
			}
		}

		// if {url} is used, pre-allocate its length
		if (isset($used_tokens['{url}'])) {
			$available = $available - social_strlen($used_tokens['{url}']);
		}

		$used_tokens['{content}'] = apply_filters('social_format_comment_content', $used_tokens['{content}'], $comment, $format, $this);

		// Truncate content to size limit
		if (social_strlen($used_tokens['{content}']) > $available) {
			$used_tokens['{content}'] = social_substr($used_tokens['{content}'], 0, ($available - 3)).'...';
		}

		foreach ($used_tokens as $token => $replacement) {
			if (strpos($format, $token) !== false) {
				$format = str_replace($token, $replacement, $format);
			}
		}

		$format = apply_filters('social_comment_broadcast_content_formatted', $format, $comment, $this);
		return $format;
	}

	/**
	 * Handles the requests to the proxy.
	 *
	 * @param  Social_Service_Account|int  $account
	 * @param  string                      $api
	 * @param  array                       $args
	 * @param  string                      $method
	 * @return Social_Response|bool
	 */
	public function request($account, $api, array $args = array(), $method = 'GET') {
		if (!is_object($account)) {
			$account = $this->account($account);
		}
		if ($account !== false) {
			$proxy = apply_filters('social_api_proxy', Social::$api_url.$this->_key, $this->_key);
			$api = apply_filters('social_api_endpoint', $api, $this->_key);
			$method = apply_filters('social_api_endpoint_method', $method, $this->_key);
			$args = apply_filters('social_api_endpoint_args', $args, $this->_key);
			$request = wp_remote_post($proxy, array(
				'timeout' => 60, // default of 5 seconds if not set here
				'sslverify' => false,
				'body' => array(
					'api' => $api,
					'method' => $method,
					'public_key' => $account->public_key(),
					'hash' => sha1($account->public_key().$account->private_key()),
					'params' => json_encode($args)
				)
			));
			if (!is_wp_error($request)) {
				$request['body'] = apply_filters('social_response_body', $request['body'], $this->_key);
				if (is_string($request['body'])) {
					// slashes are normalized (always added) by WordPress
					$request['body'] = json_decode(stripslashes_deep($request['body']));
				}
				return Social_Response::factory($this, $request, $account);
			}
			else {
				Social::log('Service::request() error: '.$request->get_error_message());
			}
		}
		return false;
	}

	/**
	 * Show full comment?
	 *
	 * @param  string  $type
	 * @return bool
	 */
	public function show_full_comment($type) {
		return (!in_array($type, self::comment_types_meta()));
	}

	/**
	 * Disconnects an account from the user's account.
	 *
	 * @param  int  $id
	 * @return void
	 */
	public function disconnect($id) {
		if (!is_admin() or defined('IS_PROFILE_PAGE')) {
			$accounts = get_user_meta(get_current_user_id(), 'social_accounts', true);
			if (isset($accounts[$this->_key][$id])) {
				if (defined('IS_PROFILE_PAGE')) {
					unset($accounts[$this->_key][$id]);
				}
				else {
					unset($accounts[$this->_key][$id]->user);
				}

				if (!count($accounts[$this->_key])) {
					unset($accounts[$this->_key]);
				}

				update_user_meta(get_current_user_id(), 'social_accounts', $accounts);
			}
		}
		else {
			$accounts = Social::option('accounts');
			if (isset($accounts[$this->_key][$id])) {
				unset($accounts[$this->_key][$id]);

				if (!count($accounts[$this->_key])) {
					unset($accounts[$this->_key]);
				}

				Social::option('accounts', $accounts);
			}
		}
		do_action('social_account_disconnected', $this->_key, $id);
	}

	/**
	 * Loads all of the accounts to use for aggregation.
	 *
	 * Format of returned data:
	 *
	 *     $accounts = array(
	 *         'twitter' => array(
	 *             '1234567890' => Social_Service_Twitter_Account,
	 *             '0987654321' => Social_Service_Twitter_Account,
	 *             // ... Other connected accounts
	 *         ),
	 *         'facebook' => array(
	 *             '1234567890' => Social_Service_Facebook_Account,
	 *             '0987654321' => Social_Service_Facebook_Account,
	 *             // ... Other connected accounts
	 *         ),
	 *         // ... Other registered services
	 *     );
	 *
	 * @param  object  $post
	 * @return array
	 */
	protected function get_aggregation_accounts($post) {
		$accounts = array();
		foreach ($this->accounts() as $account) {
			if (!isset($accounts[$this->_key])) {
				$accounts[$this->_key] = array();
			}

			if (!isset($accounts[$this->_key][$account->id()])) {
				$accounts[$this->_key][$account->id()] = $account;
			}
		}

		return $accounts;
	}

	/**
	 * Checks to see if the result ID is the original broadcasted ID.
	 *
	 * @param  WP_Post|int  $post
	 * @param  int          $result_id
	 * @return bool
	 */
	public function is_original_broadcast($post, $result_id) {
		if (!is_object($post)) {
			$broadcasted_ids = get_post_meta($post, '_social_broadcasted_ids', true);
			if (empty($broadcasted_ids)) {
				$broadcasted_ids = array();
			}

			$post = (object) array(
				'broadcasted_ids' => $broadcasted_ids,
			);
		}

		if (isset($post->broadcasted_ids[$this->_key])) {
			foreach ($post->broadcasted_ids[$this->_key] as $account_id => $broadcasted) {
				if (isset($broadcasted[$result_id])) {
					Social::log('This is the original broadcast. (:result_id)', array('result_id' => $result_id));
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Checks to make sure the comment hasn't already been created.
	 *
	 * @param  object  $post
	 * @param  int     $result_id
	 * @return bool
	 */
	public function is_duplicate_comment($post, $result_id) {
		global $wpdb;

		$results = $wpdb->get_results($wpdb->prepare("
			SELECT meta_value
			  FROM $wpdb->commentmeta AS cm, $wpdb->comments AS c
			 WHERE cm.comment_id = c.comment_ID
			   AND c.comment_post_id = %s
			   AND cm.meta_key = 'social_status_id'
		", $post->ID));

		foreach ($results as $result) {
			if ($result->meta_value == $result_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the social item output.
	 *
	 * @param  object  $item         social item being rendered
	 * @param  int     $count        current display count
	 * @param  array   $avatar_size  array containing the width and height attributes
	 * @return string
	 */
	public function social_item_output($item, $count, array $avatar_size = array()) {
		$style = '';
		if ($count >= 10) {
			$style = ' style="display:none"';
		}

		$width = '24';
		$height = '24';
		if (isset($avatar_size['width'])) {
			$width = $avatar_size['width'];
		}
		if (isset($avatar_size['height'])) {
			$height = $avatar_size['height'];
		}

		$status_url = $this->status_url($item->comment_author, $item->social_status_id);
		$title = apply_filters('social_item_output_title', $item->comment_author, $this->key());
		$image_format = apply_filters('social_item_output_image_format', '<img src="%1$s" width="%2$s" height="%3$s" alt="%4$s" />');
		$image = sprintf($image_format, esc_url($item->social_profile_image_url), esc_attr($width), esc_attr($height), esc_attr($title));
		return sprintf('<a href="%s" title="%s"%s>%s</a>', esc_url($status_url), esc_attr($title), $style, $image);
	}

	/**
	 * Checks to see if the comment is allowed.
	 *
	 * [!!] Handles the exception for duplicate comments.
	 *
	 * @param  array   $commentdata
	 * @param  int     $result_id
	 * @param  object  $post
	 * @return array|bool
	 */
	public function allow_comment(array $commentdata, $result_id, &$post) {
		try {
			add_filter('wp_die_handler', array('Social', 'wp_die_handler'));
			$commentdata['comment_approved'] = wp_allow_comment($commentdata);
			remove_filter('wp_die_handler', array('Social', 'wp_die_handler'));
			return $commentdata;
		} catch (Exception $e) {
			remove_filter('wp_die_handler', array('Social', 'wp_die_handler'));
			if ($e->getMessage() == Social::$duplicate_comment_message) {
				// Remove the aggregation ID from the stack
				unset($post->results[$this->_key][$result_id]);
				$aggregated_ids = array();
				foreach ($post->aggregated_ids[$this->_key] as $id) {
					if ($id != $result_id) {
						$aggregated_ids[] = $id;
					}
				}
				$post->aggregated_ids[$this->_key] = $aggregated_ids;

				// Mark the result as ignored
				Social_Aggregation_Log::instance($post->ID)->ignore($result_id);
			}
		}

		return false;
	}

	/**
	 * Comment types for this service.
	 *
	 * @static
	 * @return array
	 */
	public static function comment_types() {
		return array();
	}

	/**
	 * Comment types that are "meta" (not displayed in full).
	 *
	 * @static
	 * @return array
	 */
	public static function comment_types_meta() {
		return array();
	}

	/**
	 * Any additional parameters that should be passed with a broadcast.
	 *
	 * @static
	 * @return array
	 */
	public function get_broadcast_extras($account_id, $post, $args = array()) {
		return apply_filters($this->key().'_broadcast_extras', $args, $this, $account_id, $post);
	}

} // End Social_Service
