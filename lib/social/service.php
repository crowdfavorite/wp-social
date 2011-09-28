<?php
/**
 * @package Social
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
	 * Builds the authorize URL for the service.
	 *
	 * @return string
	 */
	public function authorize_url() {
		global $post;

		$proxy = Social::$api_url.$this->_key.'/authorize';
		$url = apply_filters('social_authorize_url', $proxy, $this->_key);

		$url = '?social_controller=auth&social_action=authorize&target='.urlencode($url);
		if (is_admin()) {
			$url = admin_url($url);
		}
		else {
			$url = site_url($url.'&post_id='.$post->ID);
		}

		return $url;
	}

	/**
	 * Returns the disconnect URL.
	 *
	 * @static
	 * @param  object  $account
	 * @param  bool    $is_admin
	 * @param  string  $before
	 * @param  string  $after
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
			$personal = false;
			if (defined('IS_PROFILE_PAGE')) {
				$personal = true;
			}
			$url = Social::settings_url($params, $personal);
			$text = '<span title="'.__('Disconnect', Social::$i18n).'" class="social-disconnect social-ir">'.__('Disconnect', Social::$i18n).'</span>';
		}
		else {
			$path = array();
			foreach ($params as $key => $value) {
				$path[] = $key.'='.urlencode($value);
			}

			$redirect_to = $_SERVER['REQUEST_URI'];
			if (isset($_GET['redirect_to'])) {
				$redirect_to = $_GET['redirect_to'];
			}

			$url = site_url('?'.implode('&', $path).'&redirect_to='.$redirect_to);
			$text = __('Disconnect', Social::$i18n);
		}

		return sprintf('%s<a href="%s">%s</a>%s', $before, esc_url($url), $text, $after);
	}

	/**
	 * Creates a WordPress user with the passed in account.
	 *
	 * @param  Social_Service_Account  $account
	 * @param  string  $nonce
	 * @return int|bool
	 */
	public function create_user($account, $nonce = null) {
		$username = $account->username();
		$username = str_replace(' ', '_', $username);
		if (!empty($username)) {
			$user = get_userdatabylogin($this->_key.'_'.$username);
			if ($user === false) {
				$id = wp_create_user($this->_key.'_'.$username, wp_generate_password(20, false), $this->_key.'.'.$username.'@example.com');

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
				update_user_meta($id, 'social_commenter', 'true');
				update_user_meta($id, 'social_auth_nonce_'.$nonce, 'true');
			}

			return $id;
		}

		return false;
	}

	/**
	 * Saves the accounts on the service.
	 *
	 * @return void
	 */
	public function save() {
		$accounts = array();
		if (!is_admin() or defined('IS_PROFILE_PAGE')) {
			foreach ($this->_accounts AS $account) {
				if ($account->personal()) {
					$accounts[$account->id()] = $account->as_array();
				}

				$account->universal(false);
			}

			if (count($accounts)) {
				$current = get_user_meta(get_current_user_id(), 'social_accounts', true);
				$current[$this->_key] = $accounts;
				update_user_meta(get_current_user_id(), 'social_accounts', $current);
			}
			else {
				delete_user_meta(get_current_user_id(), 'social_accounts');
			}
		}
		else {
			foreach ($this->_accounts AS $account) {
				if ($account->universal()) {
					$accounts[$account->id()] = $account->as_array();
				}

				$account->personal(false);
			}

			if (count($accounts)) {
				$current = get_option('social_accounts', array());
				$current[$this->_key] = $accounts;
				update_option('social_accounts', $current);
			}
			else {
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
		if (is_int($account)) {
			$account = $this->account($account);
		}

		if ($account !== false) {
			unset($this->_accounts[$account->id()]);
		}

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
		$available = $available - strlen($_format);

		$_format = explode(' ', $format);
		foreach (Social::broadcast_tokens() as $token => $description) {
			$content = '';
			switch ($token) {
				case '{url}':
					$url = wp_get_shortlink($post->ID);
					if (empty($url)) {
						$url = site_url('?p='.$post->ID);
					}
					$url = apply_filters('social_broadcast_permalink', $url, $post, $this);
					$content = esc_url($url);
				break;
				case '{title}':
					$content = $post->post_title;
				break;
				case '{content}':
					$content = strip_tags($post->post_content);
                    $content = preg_replace('/\s+/', ' ', $content);
					$content = str_replace(array("\n", "\r", PHP_EOL), ' ', $content);
					$content = str_replace('&nbsp;', ' ', $content);
				break;
				case '{author}':
					$user = get_userdata($post->post_author);
					$content = $user->display_name;
				break;
				case '{date}':
					$content = get_date_from_gmt($post->post_date_gmt);
				break;
			}

			if (strlen($content) > $available) {
				if (in_array($token, array('{date}', '{author}'))) {
					$content = '';
				}
				else {
					$content = substr($content, 0, ($available-3)).'...';
				}
			}

			// Filter the content
			$content = apply_filters('social_format_content', $content, $post, $format, $this);

			foreach ($_format as $haystack) {
				if (strpos($haystack, $token) !== false and $available > 0) {
					$haystack = str_replace($token, $content, $haystack);
					$available = $available - strlen($haystack);
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
		foreach (Social::comment_broadcast_tokens() as $token => $description) {
			$_format = str_replace($token, '', $_format);
		}
		$available = $available - strlen($_format);

		$_format = explode(' ', $format);
		foreach (Social::comment_broadcast_tokens() as $token => $description) {
			$content = '';
			switch ($token) {
				case '{url}':
					$url = wp_get_shortlink($comment->comment_post_ID);
					if (empty($url)) {
						$url = home_url('?p='.$comment->comment_post_ID);
					}
					$url .= '#comment-'.$comment->comment_ID;
					$url = apply_filters('social_comment_broadcast_permalink', $url, $comment, $this);
					$content = esc_url($url);
				break;
				case '{content}':
					$content = strip_tags($comment->comment_content);
					$content = str_replace(array("\n", "\r", PHP_EOL), '', $content);
					$content = str_replace('&nbsp;', '', $content);
				break;
			}

			if (strlen($content) > $available) {
				$content = substr($content, 0, ($available-3)).'...';
			}

			$content = apply_filters('social_format_comment_content', $content, $comment, $format, $this);

			foreach ($_format as $haystack) {
				if (strpos($haystack, $token) !== false and $available > 0) {
					$haystack = str_replace($token, $content, $haystack);
					$available = $available - strlen($haystack);
					$format = str_replace($token, $content, $format);
					break;
				}
			}
		}

		$format = apply_filters('social_comment_broadcast_content_formatted', $format, $comment, $this);
		return $format;
	}

	/**
	 * Handles the requests to the proxy.
	 *
	 * @param  Social_Service_Account|int  $account
	 * @param  string  $api
	 * @param  array   $args
	 * @param  string  $method
	 * @return Social_Response|bool
	 */
	public function request($account, $api, array $args = array(), $method = 'GET') {
		if (!is_object($account)) {
			$account = $this->account($account);
		}

		if ($account !== false) {
			$request = wp_remote_post(Social::$api_url.$this->_key, array(
				'sslverify' => false,
				'body' => array(
					'api' => $api,
					'method' => $method,
					'public_key' => $account->public_key(),
					'hash' => sha1($account->public_key().$account->private_key()),
					'params' => json_encode(stripslashes_deep($args))
				)
			));

			if (!is_wp_error($request)) {
				$request['body'] = apply_filters('social_response_body', $request['body'], $this->_key);
				if (is_string($request['body'])) {
					$request['body'] = json_decode($request['body']);
				}
				return Social_Response::factory($this, $request, $account);
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
		return true;
	}

	/**
	 * Disconnects an account from the user's account.
	 *
	 * @param  int  $id
	 * @return void
	 */
	public function disconnect($id) {
		if (!is_admin() or defined('IS_PROFILE_PAGE')) {
			$accounts = get_user_meta(get_current_user_id(), 'social_accounts', true);;
			if (isset($accounts[$this->_key][$id])) {
				unset($accounts[$this->_key][$id]);
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
	}

	/**
	 * Loads all of the accounts to use for aggregation.
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
	 * @param  WP_Post  $post
	 * @param  int      $result_id
	 * @return bool
	 */
	protected function is_original_broadcast($post, $result_id) {
        if (isset($post->broadcasted_ids[$this->_key])) {
            foreach ($post->broadcasted_ids[$this->_key] as $account_id => $broadcasted_ids) {
                if (in_array($result_id, $broadcasted_ids)) {
                    return true;
                }
            }
        }
		return false;
	}

} // End Social_Service
