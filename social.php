<?php
/*
Plugin Name: Social
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Broadcast newly published posts and pull in discussions using integrations with Twitter and Facebook. Brought to you by <a href="http://mailchimp.com">MailChimp</a>.
Version: 3.1.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com/
*/

if (!class_exists('Social')) { // try to avoid double-loading...

/**
 * Social Core
 *
 * @package Social
 */
final class Social {

	/**
	 * @var  string  URL of the API
	 */
	public static $api_url = 'https://sopresto.socialize-this.com/';

	/**
	 * @var  string  version number
	 */
	public static $version = '3.1.1';

	/**
	 * @var  string  CRON lock directory.
	 */
	public static $cron_lock_dir = null;

	/**
	 * @var  string  plugins URL
	 */
	public static $plugins_url = '';

	/**
	 * @var  string  plugins file path
	 */
	public static $plugins_path = '';

	/**
	 * @var  bool  loaded by theme?
	 */
	public static $loaded_by_theme = false;

	/**
	 * @var  string  duplicate comment message
	 */
	public static $duplicate_comment_message = 'duplicate comment';

	/**
	 * @var  Social_Log  logger
	 */
	private static $log = null;

	/**
	 * @var  array  default options
	 */
	protected static $options = array(
		'debug' => false,
		'install_date' => 0,
		'installed_version' => 0,
		'broadcast_format' => '{title}: {content} {url}',
		'comment_broadcast_format' => '{content} {url}',
		'system_cron_api_key' => null,
		'cron' => '1',
		'aggregate_comments' => '1',
		'broadcast_by_default' => '0',
		'use_standard_comments' => '0',
	);

	/**
	 * @var  Social  instance of Social
	 */
	public static $instance = null;

	/**
	 * Loads the instance of Social.
	 *
	 * @static
	 * @return Social
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Handles the auto loading of classes.
	 *
	 * @static
	 *
	 * @param  string  $class
	 *
	 * @return bool
	 */
	public static function auto_load($class) {
		if (substr($class, 0, 7) == 'Social_' or substr($class, 0, 7) == 'Kohana_') {
			try {
				$file = Social::$plugins_path.'lib/'.str_replace('_', '/', strtolower($class)).'.php';
				$file = apply_filters('social_auto_load_file', $file, $class);
				if (file_exists($file)) {
					require $file;

					return true;
				}

				return false;
			}
			catch (Exception $e) {
				Social::log(sprintf(__('Failed to auto load class %s.', 'social'), $class));
			}
		}

		return true;
	}

	/**
	 * Returns the broadcast format tokens.
	 *
	 * Format:
	 *
	 *     {key} => __('Description', 'social')
	 *
	 * @static
	 * @return array
	 */
	public static function broadcast_tokens() {
		$query = new WP_Query(array(
			'posts_per_page' => 1
		));
		if (count($query->posts) and $post = $query->posts[0]) {
			$url = social_get_shortlink($post->ID);
			$date = get_date_from_gmt($post->post_date_gmt);
		}
		else {
			$url = home_url('?p=123');
			$date = get_date_from_gmt(current_time('mysql', true));
		}

		$defaults = array(
			'{url}' => sprintf(__('Example: %s', 'social'), $url),
			'{title}' => '',
			'{content}' => '',
			'{date}' => sprintf(__('Example: %s', 'social'), $date),
			'{author}' => '',
		);

		return apply_filters('social_broadcast_tokens', $defaults);
	}

	/**
	 * Returns the comment broadcast format tokens.
	 *
	 * Format:
	 *
	 *     {key} => __('Description', 'social')
	 *
	 * @static
	 * @return mixed
	 */
	public static function comment_broadcast_tokens() {
		$defaults = array(
			'{content}' => '',
			'{url}' => '',
		);
		return apply_filters('social_comment_broadcast_tokens', $defaults);
	}

	/**
	 * Sets or gets an option based on the key defined.
	 *
	 * Get Format:
	 *
	 *     Running Social::option('option_name') will load "social_option_name" using get_option()
	 *
	 * Set Format:
	 *
	 *     Running Social::option('option_name', 'new_value') will update "social_option_name" to "new_value"
	 *     using update_option().
	 *
	 * @static
	 * @param  string  $key     option key
	 * @param  mixed   $value   option value
	 * @return bool|mixed
	 * @uses get_option()
	 * @uses update_option()
	 */
	public static function option($key, $value = null) {
		if ($value === null) {
			$default = null;
			if (isset(Social::$options[$key])) {
				$default = Social::$options[$key];
			}

			return get_option('social_'.$key, $default);
		}

		update_option('social_'.$key, $value);
		return false;
	}

	/**
	 * Add a message to the log.
	 *
	 * @static
	 * @param  string  $message    message to add to the log
	 * @param  array   $args       arguments to pass to the writer
	 * @param  string  $context    context of the log message
	 * @param  bool    $backtrace  show the backtrace
	 * @return void
	 */
	public static function log($message, array $args = null, $context = null, $backtrace = false) {
		Social::$log->write($message, $args, $context, $backtrace);
	}

	/**
	 * Sets the loaded by theme.
	 *
	 * @static
	 * @return void
	 */
	public static function social_loaded_by_theme() {
		self::$loaded_by_theme = true;
	}

	/**
	 * Sets the customer die handler.
	 *
	 * @static
	 * @param  string  $handler
	 * @return array
	 */
	public static function wp_die_handler($handler) {
		return array('Social', 'wp_comment_die_handler');
	}

	/**
	 * Don't actually die for aggregation runs.
	 *
	 * @static
	 * @param  string  $message
	 * @param  string  $title
	 * @param  array   $args
	 * @return mixed
	 */
	public static function wp_comment_die_handler($message, $title, $args) {
		if ($message == __('Duplicate comment detected; it looks as though you&#8217;ve already said that!')) {
			// Keep going
			throw new Exception(Social::$duplicate_comment_message);
		}
	}

	/**
	 * @var  bool  is Social enabled?
	 */
	private $_enabled = null;

	/**
	 * Returns an array of all of the services.
	 *
	 * Format of the data returned:
	 *
	 *     $services = array(
	 *         'twitter' => Social_Service_Twitter,
	 *         'facebook' => Social_Service_Facebook,
	 *         // ... any other services registered
	 *     )
	 *
	 * @return array
	 */
	public function services() {
		return $this->load_services();
	}

	/**
	 * Returns a service by access key.
	 *
	 * Loading a service:
	 *
	 *     $twitter = Social::instance()->service('twitter');
	 *
	 * @param  string  $key    service key
	 * @return mixed Social_Service|Social_Service_Twitter|Social_Service_Facebook|false
	 */
	public function service($key) {
		$services = $this->load_services();
		if (!isset($services[$key])) {
			return false;
		}
		return $services[$key];
	}

	/**
	 * Returns a service by comment type.
	 *
	 * Loading a service:
	 *
	 *     $twitter = Social::instance()->service_for_comment_type('social-twitter-rt');
	 *
	 * @param  string  $key    service key
	 * @return mixed  Social_Service|Social_Service_Twitter|Social_Service_Facebook|false
	 */
	public function service_for_comment_type($comment_type) {
		$services = $this->load_services();
		foreach ($services as $service) {
			if (in_array($comment_type, $service->comment_types())) {
				return $service;
			}
		}
		return false;
	}

	/**
	 * Initializes Social.
	 *
	 * @wp-action  init
	 * @return void
	 */
	public function init() {
		// Load the language translations
		if (Social::$loaded_by_theme) {
			$path = trailingslashit(Social::$plugins_path).'lang';
			load_theme_textdomain('social', $path);
		}
		else {
			$plugin_dir = basename(dirname(SOCIAL_FILE)).'/lang';
			load_plugin_textdomain('social', false, $plugin_dir);
		}

		if (version_compare(PHP_VERSION, '5.2.4', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social requires PHP 5.2.4 or higher. Ask your host how to enable PHP 5 as the default on your servers.", 'social'));
		}

		// Just activated?
		if (!Social::option('install_date')) {
			Social::option('install_date', current_time('timestamp', 1));
			Social::option('system_cron_api_key', wp_generate_password(16, false));
		}

		// Plugins URL
		$url = plugins_url('', SOCIAL_FILE);
		Social::$plugins_url = trailingslashit(apply_filters('social_plugins_url', $url));

		Social::$plugins_path = trailingslashit(apply_filters('social_plugins_path', SOCIAL_PATH));

		// Set the logger
		Social::$log = Social_Log::factory();

		// Require Facebook and Twitter by default.
		require Social::$plugins_path.'social-twitter.php';
		require Social::$plugins_path.'social-facebook.php';
	}

	/**
	 * Auth Cookie expiration for API users.
	 *
	 * @return int
	 */
	public function auth_cookie_expiration() {
		return 31536000; // 1 Year
	}

	/**
	 * Enqueues the assets for Social.
	 *
	 * @wp-action  wp_enqueue_scripts
	 * @wp-action  load-post-new.php
	 * @wp-action  load-post.php
	 * @wp-action  load-profile.php
	 * @wp-action  load-settings_page_social
	 * @return void
	 */
	public function enqueue_assets() {
		if (Social::option('use_standard_comments') == '1') {
			return;
		}
		// JS/CSS
		if (!defined('SOCIAL_COMMENTS_JS')) {
			define('SOCIAL_COMMENTS_JS', Social::$plugins_url.'assets/social.js');
		}
		if (SOCIAL_COMMENTS_JS !== false) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('social_js', SOCIAL_COMMENTS_JS, array('jquery'), Social::$version, true);
			wp_localize_script('social_js', 'Sociali18n', array(
				'commentReplyTitle' => __('Post a Reply', 'social'),
			));
		}

		if (!is_admin()) {
			if (!defined('SOCIAL_COMMENTS_CSS')) {
				define('SOCIAL_COMMENTS_CSS', Social::$plugins_url.'assets/comments.css');
			}
			if (SOCIAL_COMMENTS_CSS !== false) {
				wp_enqueue_style('social_comments', SOCIAL_COMMENTS_CSS, array(), Social::$version, 'screen');
			}
		}

	}

	/**
	 * Enqueues the assets for Social.
	 *
	 * @wp-action  admin_enqueue_scripts
	 * @return void
	 */
	public function admin_enqueue_assets() {
		if (!defined('SOCIAL_ADMIN_JS')) {
			define('SOCIAL_ADMIN_JS', Social::$plugins_url.'assets/admin.js');
		}

		if (!defined('SOCIAL_ADMIN_CSS')) {
			define('SOCIAL_ADMIN_CSS', Social::$plugins_url.'assets/admin.css');
		}

		if (SOCIAL_ADMIN_CSS !== false) {
			wp_enqueue_style('social_admin', SOCIAL_ADMIN_CSS, array(), Social::$version, 'screen');
		}

		if (SOCIAL_ADMIN_JS !== false) {
			wp_enqueue_script('social_admin', SOCIAL_ADMIN_JS, array(), Social::$version, true);
			$data = apply_filters('social_admin_js_strings', array(
				'protectedTweet' => __('Protected Tweet', 'social'),
				'invalidUrl' => __('Invalid URL', 'social'),
			));
			wp_localize_script('social_admin', 'socialAdminL10n', $data);
		}
	}

	/**
	 * Loads the services on every page if the user is an admin.
	 *
	 * @wp-action  admin_init
	 * @return void
	 */
	public function admin_init() {
		if (current_user_can('manage_options') or current_user_can('publish_posts')) {
			// Trigger upgrade?
			if (isset($_GET['page']) and $_GET['page'] == basename(SOCIAL_FILE)) {
				global $wpdb;

				// First check for the semaphore options, they need to be added before the upgrade starts.
				$results = $wpdb->get_results("
					SELECT option_id
					  FROM $wpdb->options
					 WHERE option_name IN ('social_locked', 'social_unlocked')
				");
				if (!count($results)) {
					update_option('social_unlocked', '1');
					update_option('social_last_lock_time', current_time('mysql', 1));
					update_option('social_semaphore', '0');
				}

				if (version_compare(Social::option('installed_version'), Social::$version, '<')) {
					$this->_enabled = false;
					$this->upgrade();
				}
			}

			if ($this->_enabled === null) {
				$this->load_services();
			}
		}

		// Redirect to the home_url() if the user is a commenter.
		if (!current_user_can('publish_posts')) {
			$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
			if (!empty($commenter) and $commenter == 'true') {
				wp_redirect(trailingslashit(home_url()));
			}
		}
	}

	/**
	 * Checks to see if system crons are disabled.
	 *
	 * @wp-action  load-settings_page_social
	 * @return void
	 */
	public function check_system_cron() {
		Social::log('Checking system CRON');
		// Schedule CRONs
		if (Social::option('cron') == '1') {
			if (wp_next_scheduled('socialcron15init') === false) {
				Social::log('Adding Social 15 CRON schedule');
				wp_schedule_event(time() + 900, 'every15min', 'socialcron15init');
			}
			wp_remote_get(
				admin_url('options_general.php?'.http_build_query(array(
					'social_controller' => 'cron',
					'social_action' => 'check_crons',
					'social_api_key' => Social::option('system_cron_api_key')
				), null, '&')),
				array(
					'timeout' => 0.01,
					'blocking' => false,
					'sslverify' => apply_filters('https_local_ssl_verify', true),
				)
			);
		}
	}

	/**
	 * Handlers requests.
	 *
	 * @wp-action  init
	 * @return void
	 */
	public function request_handler() {
		if (isset($_GET['social_controller'])) {
			Social_Request::factory()->execute();
		}
	}

	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
	 *
	 * @wp-action  admin_menu
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__('Social Options', 'social'),
			__('Social', 'social'),
			'manage_options',
			basename(SOCIAL_FILE),
			array(
				$this,
				'admin_options_form'
			)
		);
	}

	/**
	 * Add Settings link to plugins - code from GD Star Ratings
	 *
	 * @wp-filter  plugin_action_links
	 * @param  array   $links
	 * @param  string  $file
	 * @return array
	 */
	public function add_settings_link($links, $file) {
		static $this_plugin;
		if (!$this_plugin) {
			$this_plugin = plugin_basename(__FILE__);
		}

		if ($file == $this_plugin) {
			$settings_link = '<a href="'.esc_url(admin_url('options-general.php?page=social.php')).'">'.__('Settings', 'social').'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}

	/**
	 * Handles the display of different messages for admin notices.
	 *
	 * @wp-action  admin_notices
	 * @action     admin_notices
	 */
	public function admin_notices() {
		if (current_user_can('manage_options') or current_user_can('publish_posts')) {
			// Upgrade notice
			if (version_compare(Social::option('installed_version'), Social::$version, '<')) {
				$message = sprintf(__('Social is shiny and new! Please <a href="%s">verify and save your settings</a> to complete the upgrade.', 'social'), esc_url(Social::settings_url()));
				echo '<div class="error"><p>'.$message.'</p></div>';
			}

			$suppress_no_accounts_notice = get_user_meta(get_current_user_id(), 'social_suppress_no_accounts_notice', true);
			if (!$this->_enabled and (!isset($_GET['page']) or $_GET['page'] != basename(SOCIAL_FILE)) and empty($suppress_no_accounts_notice)) {
				$dismiss = sprintf(__('<a href="%s" class="social_dismiss">[Dismiss]</a>', 'social'), esc_url(admin_url('options-general.php?social_controller=settings&social_action=suppress_no_accounts_notice')));
				$message = sprintf(__('To start using Social, please <a href="%s">add an account</a>.', 'social'), esc_url(Social::settings_url()));
				echo '<div class="error"><p>'.$message.' '.$dismiss.'</p></div>';
			}

			if (isset($_GET['page']) and $_GET['page'] == basename(SOCIAL_FILE)) {
				// CRON Lock
				if (Social::option('cron_lock_error') !== null) {
					$upload_dir = wp_upload_dir();
					if (is_writeable(Social::$plugins_path) or (isset($upload_dir['basedir']) and is_writeable($upload_dir['basedir']))) {
						delete_option('social_cron_lock_error');
					}
					else {
						if (isset($upload_dir['basedir'])) {
							$message = sprintf(__('Social requires that either %s or %s be writable for CRON jobs.', 'social'), esc_html(Social::$plugins_path), esc_html($upload_dir['basedir']));
						}
						else {
							$message = sprintf(__('Social requires that %s is writable for CRON jobs.', 'social'), esc_html(Social::$plugins_path));
						}

						echo '<div class="error"><p>'.esc_html($message).'</p></div>';
					}
				}

				// Enable notice?
				$suppress_enable_notice = get_user_meta(get_current_user_id(), 'social_suppress_enable_notice', true);
				if (empty($suppress_enable_notice)) {
					$message = __('When you enable Social, users will be created when they log in with Facebook or Twitter to comment. These users are created without a role and will be prevented from accessing the admin side of WordPress until an administrator edits the user to give them a role.', 'social');
					$dismiss = sprintf(__('<a href="%s" class="social_dismiss">[Dismiss]</a>', 'social'), esc_url(admin_url('options-general.php?social_controller=settings&social_action=suppress_enable_notice')));
					echo '<div class="updated"><p>'.$message.' '.$dismiss.'</p></div>';
				}
			}

			// Log write error
			$error = Social::option('log_write_error');
			if ($error == '1') {
				echo '<div class="error"><p>'.
					sprintf(__('%s needs to be writable for Social\'s logging. <a href="%" class="social_dismiss">[Dismiss]</a>', 'social'), esc_html(Social::$plugins_path), esc_url(admin_url('options-general.php?social_controller=settings&social_action=clear_log_write_error'))).
					'</p></div>';
			}
		}

		// Deauthed accounts
		$deauthed = Social::option('deauthed');
		if (!empty($deauthed)) {
			foreach ($deauthed as $service => $data) {
				foreach ($data as $id => $message) {
					$dismiss = sprintf(__('<a href="%s" class="%s">[Dismiss]</a>', 'social'), esc_url(admin_url('options-general.php?social_controller=settings&social_action=clear_deauth&id='.$id.'&service='.$service)), 'social_dismiss');
					echo '<div class="error"><p>'.esc_html($message).' '.$dismiss.'</p></div>';
				}
			}
		}

		// Errored broadcasting?
		global $post;
		if (isset($post->ID)) {
			$error_accounts = get_post_meta($post->ID, '_social_broadcast_error', true);
			if (!empty($error_accounts)) {
				$message = Social_View::factory('wp-admin/post/broadcast/error/notice', array(
					'social' => $this,
					'accounts' => $error_accounts,
					'post' => $post,
				));
				echo '<div class="error" id="social-broadcast-error">'.$message.'</div>';

				delete_post_meta($post->ID, '_social_broadcast_error');
			}
		}

		// 2.0 Upgrade?
		$upgrade_2_0 = get_user_meta(get_current_user_id(), 'social_2.0_upgrade', true);
		if (!empty($upgrade_2_0)) {
			if (current_user_can('manage_options')) {
				$output = __('Social needs to re-authorize your Facebook account(s). Please re-connect your <a href="%s">global</a> and <a href="%s">personal</a> accounts.', 'social');
				$output = sprintf($output, esc_url(Social::settings_url()), esc_url(admin_url('profile.php#social-accounts')));
			}
			else {
				$output = __('Social needs to re-authorize your Facebook account(s).. Please re-connect your <a href="%s">personal</a> accounts.', 'social');
				$output = sprintf($output, esc_url(admin_url('profile.php#social-networks')));
			}

			$dismiss = sprintf(__('<a href="%s" class="%s">[Dismiss]</a>', 'social'), esc_url(admin_url('options-general.php?social_controller=settings&social_action=clear_2_0_upgrade')), 'social_dismiss');
			echo '<div class="error"><p>'.$output.' '.$dismiss.'</p></div>';
		}
	}

	/**
	 * Displays the admin options form.
	 *
	 * @return void
	 */
	public function admin_options_form() {
		Social_Request::factory('settings/index')->execute();
	}

	/**
	 * Shows the user's social network accounts.
	 *
	 * @wp-action  show_user_profile
	 * @param  object  $profileuser
	 * @return void
	 */
	public function show_user_profile($profileuser) {
		$default_accounts = get_user_meta($profileuser->ID, 'social_default_accounts', true);
		if (empty($default_accounts)) {
			$default_accounts = array();
		}
		$accounts = array();
		foreach ($this->services() as $key => $service) {
			if (!isset($accounts[$key])) {
				$accounts[$key] = array();
			}
			foreach ($service->accounts() as $account) {
				if ($account->personal()) {
					$accounts[$key][] = $account->id();
				}
			}
		}
		echo Social_View::factory('wp-admin/profile', array(
			'defaults' => $default_accounts,
			'services' => $this->services(),
			'accounts' => $accounts,
		));
	}

	/**
	 * Saves the default accounts for the user.
	 *
	 * @wp-action personal_options_update
	 * @param  int  $user_id
	 * @return void
	 */
	public function personal_options_update($user_id) {
		// Store the default accounts
		$accounts = array();
		if (isset($_POST['social_default_accounts']) and is_array($_POST['social_default_accounts'])) {
			foreach ($_POST['social_default_accounts'] as $account) {
				$account = explode('|', $account);
				$accounts[$account[0]][] = $account[1];
			}
		}

		// TODO abstract this to the facebook plugin
		if (isset($_POST['social_default_pages']) and is_array($_POST['social_default_pages'])) {
			if (!isset($accounts['facebook'])) {
				$accounts['facebook'] = array(
					'pages' => array()
				);
			}
			$accounts['facebook']['pages'] = $_POST['social_default_pages'];
		}

		if (count($accounts)) {
			update_user_meta($user_id, 'social_default_accounts', $accounts);
		}
		else {
			delete_user_meta($user_id, 'social_default_accounts');
		}

		// Save Enabled child accounts
		$is_profile = true;
		$enabled_child_accounts = is_array($_POST['social_enabled_child_accounts']) ? $_POST['social_enabled_child_accounts'] : array();
		foreach ($this->services() as $service_key => $service) {
			$updated_accounts = array();
			foreach ($service->accounts() as $account) {
				//default service to empty array in case it is not set
				$enabled_child_accounts[$service_key] = isset($enabled_child_accounts[$service_key]) ? $enabled_child_accounts[$service_key] : array();

				$account->update_enabled_child_accounts($enabled_child_accounts[$service_key]);
				$updated_accounts[$account->id()] = $account->as_object();
			}
			$service->accounts($updated_accounts)->save($is_profile);
		}
	}

	/**
	 * Return array of  social broadcasting post types
	 *
	 * @static
	 * @return array
	 */
	public static function broadcasting_available_post_types() {
		$types = get_post_types(array('public' => true));
		$blacklisted_types = apply_filters('social_broadcasting_blacklisted_post_types', array('attachment'));
		foreach ($blacklisted_types as $type) {
			unset($types[$type]);
		}

		return apply_filters('social_broadcasting_available_post_types', array_keys($types));
	}

	/**
	 * Return array of enabled social broadcasting post types
	 *
	 * @static
	 * @return array
	 */
	public static function broadcasting_enabled_post_types() {
		$available = Social::broadcasting_available_post_types();
		$enabled = Social::option('enabled_post_types');
		if (!$enabled) {
			$default = get_post_types(array(
				'hierarchical' => false,
			));
			$enabled = array_keys($default);
		}

		return apply_filters('social_broadcasting_enabled_post_types', array_intersect($available, $enabled));
	}

	/**
	 * Check if a post type has broadcasting enabled
	 *
	 * @static
	 * @param  string  $post_type  post type to check for
	 * @return bool
	 */
	public static function broadcasting_enabled_for_post_type($post_type = null) {
		return (bool) in_array($post_type, self::broadcasting_enabled_post_types());
	}

	/**
	 * Add Meta Boxes
	 *
	 * @wp-action  do_meta_boxes
	 * @return void
	 */
	public function do_meta_boxes() {
		global $post;

		if ($post !== null && Social::option('disable_broadcasting') != 1) {
			foreach (self::broadcasting_enabled_post_types() as $post_type) {
				add_meta_box('social_meta_broadcast', __('Social Broadcasting', 'social'), array(
					$this,
					'add_meta_box_broadcast'
				), $post_type, 'side', 'high');

				$fetch = Social::option('aggregate_comments');
				if ($this->_enabled
					&& !empty($fetch)
					&& $post->post_status == 'publish'
					&& post_type_supports($post->post_type, 'comments')
					&& Social::option('aggregate_comments'))
				{
					add_meta_box('social_meta_aggregation_log', __('Social Comments', 'social'), array(
						$this,
						'add_meta_box_log'
					), $post_type, 'normal', 'core');
				}
			}
		}
	}

	/**
	 * Adds the broadcasting meta box.
	 *
	 * @return void
	 */
	public function add_meta_box_broadcast() {
		global $post;

		$broadcasted = '';
		$broadcasted_ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
		if (!empty($broadcasted_ids)) {
			$broadcasted = Social_View::factory('wp-admin/post/meta/broadcast/parts/broadcasted', array(
				'services' => $this->services(),
				'ids' => $broadcasted_ids,
				'post' => $post,
			));
		}

		$show_broadcast = false;
		foreach ($this->services() as $service) {
			if (count($service->accounts())) {
				$show_broadcast = true;
				break;
			}
		}

		// Content
		$button = '';
		$content = '';
		if ($show_broadcast) {
			if ($post->post_status != 'private') {
				switch ($post->post_status) {
					case 'pending':
						$button = 'Edit';
						$accounts = get_post_meta($post->ID, '_social_broadcast_accounts', true);
						$content = Social_View::factory('wp-admin/post/meta/broadcast/pending', array(
							'accounts' => $accounts,
							'services' => $this->services(),
						));
						break;
					case 'future':
						$button = 'Edit';
						$accounts = get_post_meta($post->ID, '_social_broadcast_accounts', true);
						$content = Social_View::factory('wp-admin/post/meta/broadcast/scheduled', array(
							'services' => $this->services(),
							'accounts' => $accounts,
						));
						break;
					case 'publish':
						$button = 'Broadcast';
						break;
					default:
						if ($post->post_status == 'draft' and !empty($broadcasted_ids)) {
							$content = '';
						}
						else {
							$notify = false;
							if (get_post_meta($post->ID, '_social_notify', true) == '1') {
								$notify = true;
							}
							else if (Social::option('broadcast_by_default') == '1') {
								$notify = true;
							}

							$content = Social_View::factory('wp-admin/post/meta/broadcast/default', array(
								'post' => $post,
								'notify' => $notify,
							));
						}
						break;
				}
			}
			else {
				$content = Social_View::factory('wp-admin/post/meta/broadcast/private');
			}

			// Button
			if (!empty($button)) {
				$button = Social_View::factory('wp-admin/post/meta/broadcast/parts/button', array(
					'broadcasted' => $broadcasted,
					'button_text' => $button,
				));
			}
		}

		echo Social_View::factory('wp-admin/post/meta/broadcast/shell', array(
			'post' => $post,
			'content' => $content,
			'broadcasted' => $broadcasted,
			'button' => $button
		));
	}

	/**
	 * Adds the aggregation log meta box.
	 *
	 * @return void
	 */
	public function add_meta_box_log() {
		global $post;

		$next_run = get_post_meta($post->ID, '_social_aggregation_next_run', true);
		if (empty($next_run)) {
			$next_run = __('Not Scheduled', 'social');
		}
		else {
			$next_run = Social_Aggregation_Queue::next_run($next_run);
		}

		echo Social_View::factory('wp-admin/post/meta/log/shell', array(
			'post' => $post,
			'next_run' => $next_run,
		));
	}

	/**
	 * Show the broadcast options if publishing.
	 *
	 * @wp-filter  redirect_post_location
	 * @param  string  $location  default post-publish location
	 * @param  int     $post_id   post ID
	 * @return string|void
	 */
	public function redirect_post_location($location, $post_id) {
		if ((isset($_POST['social_notify']) and $_POST['social_notify'] == '1') and
			(isset($_POST['visibility']) and $_POST['visibility'] !== 'private')
		) {
			update_post_meta($post_id, '_social_notify', '1');
			if (isset($_POST['publish']) or isset($_POST['social_broadcast'])) {
				Social_Request::factory('broadcast/options')->post(array(
					'post_ID' => $post_id,
					'location' => $location,
				))->execute();
			}
		}
		else {
			delete_post_meta($post_id, '_social_notify');
		}
		return $location;
	}

	/**
	 * Removes post meta if the post is going to private.
	 *
	 * @wp-action  transition_post_status
	 * @param  string  $new
	 * @param  string  $old
	 * @param  object  $post
	 * @return void
	 */
	public function transition_post_status($new, $old, $post) {
		if ($new == 'private') {
			delete_post_meta($post->ID, '_social_notify');
			delete_post_meta($post->ID, '_social_broadcast_accounts');

			foreach ($this->services() as $key => $service) {
				delete_post_meta($post->ID, '_social_'.$key.'_content');
			}
		}
		else {
			$xmlrpc = false;
			if ($new == 'publish') {
				if ( ( defined('XMLRPC_REQUEST') or defined('SOCIAL_MAIL_PUBLISH') ) and $old != 'publish') {
					$xmlrpc = true;
					$this->xmlrpc_publish_post($post);
				}
				if (self::broadcasting_enabled_for_post_type($post->post_type)) {
					Social_Aggregation_Queue::factory()->add($post->ID)->save();
				}
			}

			// Sends previously saved broadcast information
			if ($xmlrpc or ($old == 'future' and !in_array($new, array('future', 'draft')))) {
				Social_Request::factory('broadcast/run')->query(array(
					'post_ID' => $post->ID
				))->execute();
			}
		}
	}

	/**
	 * Broadcasts the post on XML RPC requests.
	 *
	 * @param  object  $post
	 * @return void
	 */
	public function xmlrpc_publish_post($post) {
		if ($post and Social::option('broadcast_by_default') == '1') {
			Social::log('Broadcasting triggered by XML-RPC.');

			$broadcast_accounts = array();
			$broadcast_content = array();
			$broadcast_meta = array();

			foreach ($this->default_accounts($post) as $service_key => $accounts) {
				$service = $this->service($service_key);
				if ($service !== false) {
					$broadcast_content[$service_key] = array();
					$broadcast_meta[$service_key] = array();
					foreach ($accounts as $key => $id) {
						// TODO abstract this to the Facebook plugin
						if ($service_key == 'facebook' and $key === 'pages') {
							foreach ($id as $account_id => $pages) {
								$account = $service->account($account_id);

								// TODO This could use some DRY love
								$universal_pages = $account->pages();
								$personal_pages = $account->pages(null, true);

								foreach ($pages as $page_id) {
									if (!isset($broadcast_accounts[$service_key])) {
										$broadcast_accounts[$service_key] = array();
									}

									if (!isset($broadcast_accounts[$service_key][$page_id])) {
										if (isset($universal_pages[$page_id])) {
											$broadcast_accounts[$service_key][$page_id] = (object) array(
												'id' => $page_id,
												'name' => $universal_pages[$page_id]->name,
												'universal' => true,
												'page' => true,
											);
										}
										else if (isset($personal_pages[$page_id])) {
											$broadcast_accounts[$service_key][$page_id] = (object) array(
												'id' => $page_id,
												'name' => $personal_pages[$page_id]->name,
												'universal' => false,
												'page' => true,
											);
										}
									}
									$broadcast_content[$service_key][$page_id] = $service->format_content($post, Social::option('broadcast_format'));
									$broadcast_meta[$service_key][$page_id] = $service->get_broadcast_extras($page_id, $post);
								}
							}
						}
						else {
							$account = $service->account($id);
							if ($account !== false) {
								if (!isset($broadcast_accounts[$service_key])) {
									$broadcast_accounts[$service_key] = array();
								}

								$broadcast_accounts[$service_key][$account->id()] = (object) array(
									'id' => $account->id(),
									'universal' => $account->universal()
								);

								$broadcast_content[$service_key][$account->id()] = $service->format_content($post, Social::option('broadcast_format'));
								$broadcast_meta[$service_key][$account->id()] = $service->get_broadcast_extras($account->id(), $post);
							}
						}
					}
				}
			}

			update_post_meta($post->ID, '_social_broadcast_content', addslashes_deep($broadcast_content));
			update_post_meta($post->ID, '_social_broadcast_meta', addslashes_deep($broadcast_meta));

			if (count($broadcast_accounts)) {
				Social::log('There are default accounts, running broadcast');
				update_post_meta($post->ID, '_social_broadcast_accounts', addslashes_deep($broadcast_accounts));
			}
		}
	}

	/**
	 * Loads the default accounts for the post.
	 *
	 * @param  object  $post
	 * @return array
	 */
	public function default_accounts($post) {
		$default_accounts = Social::option('default_accounts');
		$author_default_accounts = get_user_meta($post->post_author, 'social_default_accounts', true);
		if (is_array($author_default_accounts)) {
			foreach ($author_default_accounts as $service_key => $accounts) {
				if (!isset($default_accounts[$service_key])) {
					$default_accounts[$service_key] = $accounts;
				}
				else {
					foreach ($accounts as $key => $account) {
						if ($key === 'pages') {
							if (!isset($default_accounts[$key]['pages'])) {
								$default_accounts[$key]['pages'] = $account;
							}
							else {
								foreach ($account as $page_id) {
									if (!in_array($page_id, $default_accounts[$key]['pages'])) {
										$default_accounts[$key]['pages'][] = $page_id;
									}
								}
							}
						}
						else {
							$default_accounts[$service_key][] = $account;
						}
					}
				}
			}
		}

		return apply_filters('social_default_accounts', $default_accounts, $post);
	}

	/**
	 * Sets the broadcasted IDs for the post.
	 *
	 * @param  int  $post_id  post id
	 * @param  string  $service  service key
	 * @param  string  $broadcasted_id  broadcasted id
	 * @param  string  $message  broadcasted message
	 * @param  Social_Service_Account  $account  account
	 * @param  Social_Response  $response  response object
	 * @return void
	 */
	public function add_broadcasted_id($post_id, $service, $broadcasted_id, $message, $account, Social_Response $response = null) {
		$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}

		if (!isset($broadcasted_ids[$service])) {
			$broadcasted_ids[$service] = array();
		}

		if (!isset($broadcasted_ids[$service][$account->id()])) {
			$broadcasted_ids[$service][$account->id()] = array();
		}

		if (!isset($broadcasted_ids[$service][$account->id()][$broadcasted_id])) {
			$urls = array(
				get_permalink($post_id)
			);

			$shortlink = social_get_shortlink($post_id);
			if (!in_array($shortlink, $urls)) {
				$urls[] = $shortlink;
			}

			$home_url = home_url('?p='.$post_id);
			if (!in_array($home_url, $urls)) {
				$urls[] = $home_url;
			}

			$data = array(
				'message' => $message,
				'urls' => $urls
			);
			$data = apply_filters('social_save_broadcasted_ids_data', $data, $account, $service, $post_id, $response);
			$broadcasted_ids[$service][$account->id()][$broadcasted_id] = $data;
			update_post_meta($post_id, '_social_broadcasted_ids', $broadcasted_ids);
		}
	}

	/**
	 * Adds the 15 minute interval.
	 *
	 * @wp-filter  cron_schedules
	 * @param  array  $schedules
	 * @return array
	 */
	public function cron_schedules($schedules) {
		$schedules['every15min'] = array(
			'interval' => 900,
			'display' => 'Every 15 minutes'
		);
		return $schedules;
	}

	/**
	 * Sends a request to initialize CRON 15.
	 *
	 * @wp-action  socialcron15init
	 * @return void
	 */
	public function cron_15_init() {
		Social::log('Running cron_15_init');
		Social_Request::factory('cron/cron_15')->query('api_key', Social::option('system_cron_api_key'))->execute();
	}

	/**
	 * Runs the aggregation loop.
	 *
	 * @wp-action  socialcron15
	 * @return void
	 */
	public function run_aggregation() {
		$semaphore = Social_Semaphore::factory();
		$queue = Social_Aggregation_Queue::factory();

		foreach ($queue->runnable() as $timestamp => $posts) {
			foreach ($posts as $id => $interval) {
				$post = get_post($id);
				if ($post !== null) {
					$queue->add($id, $interval)->save();
					$semaphore->increment();
					$this->request(home_url('index.php?social_controller=aggregation&social_action=run&post_id='.$id), 'run');
				}
				else {
					$queue->remove($id, $timestamp)->save();
				}
			}
		}
	}

	/**
	 * Removes the post from the aggregation queue.
	 *
	 * @wp-action  delete_post
	 * @param  int  $post_id
	 * @return void
	 */
	public function delete_post($post_id) {
		Social_Aggregation_Queue::factory()->remove($post_id);
	}

	/**
	 * Hides the Site Admin link for social-based users.
	 *
	 * @wp-filter register
	 * @param  string  $link
	 * @return string
	 */
	public function register($link) {
		if (is_user_logged_in()) {
			$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
			if (!empty($commenter)) {
				return '';
			}
		}

		return $link;
	}

	/**
	 * Sets the user role.
	 *
	 * @wp-action set_user_role
	 * @param  int     $user_id
	 * @param  string  $role
	 */
	public function set_user_role($user_id, $role) {
		if (!empty($role)) {
			delete_user_meta($user_id, 'social_commenter');
		}
	}

	/**
	 * Show the disconnect link for social-based users.
	 *
	 * @wp-filter loginout
	 * @param  string  $link
	 * @return string
	 */
	public function loginout($link) {
		if (is_user_logged_in()) {
			$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
			if (!empty($commenter)) {
				foreach ($this->services() as $key => $service) {
					$account = reset($service->accounts());
					if ($account) {
						return $service->disconnect_link($account);
					}
				}
			}
		}
		else {
			$link = explode('>'.__('Log in'), $link);
			$link = $link[0].' id="social_login">'.__('Log in').$link[1];
		}

		return $link;
	}

	/**
	 * Increments the service comment counter.
	 *
	 * @static
	 * @param  array  $items
	 * @param  array  $groups
	 */
	public static function add_social_items_count($items, &$groups) {
		foreach ($items as $group => $_items) {
			if ($group == 'parent') {
				self::add_social_items_count($_items, $groups);
			}
			else {
				if (!isset($groups['social-'.$group])) {
					$groups['social-'.$group] = 0;
				}

				$groups['social-'.$group] = $groups['social-'.$group] + count($_items);
			}
		}
	}

	/**
	 * Overrides the default WordPress comments_template function.
	 *
	 * @wp-filter  comments_template
	 * @return string
	 */
	public function comments_template($path) {
		global $post;

		if (!(
			is_singular() and
			(have_comments() or $post->comment_status == 'open') and
			Social::option('use_standard_comments') != '1'
		)) {
			return $path;
		}

		if (!defined('SOCIAL_COMMENTS_FILE')) {
			define('SOCIAL_COMMENTS_FILE', trailingslashit(dirname(SOCIAL_FILE)).'views/comments.php');
		}

		return SOCIAL_COMMENTS_FILE;
	}

	/**
	 * Returns an array of comment types that display avatars.
	 *
	 * @wp-filter  get_avatar_comment_types
	 * @param  array  $types  default WordPress types
	 * @return array
	 */
	public function get_avatar_comment_types($types) {
		$types[] = 'wordpress';
		return $types;
	}

	/**
	 * Gets the avatar based on the comment type.
	 *
	 * @wp-filter  get_avatar
	 * @param  string  $avatar
	 * @param  object  $comment
	 * @param  int     $size
	 * @param  string  $default
	 * @param  string  $alt
	 * @return string
	 */
	public function get_avatar($avatar, $comment, $size, $default, $alt = '') {
		$image = null;
		if (is_object($comment)) {
			$image = get_comment_meta($comment->comment_ID, 'social_profile_image_url', true);
			if (empty($image)) {
				$image = null;
			}
		}
		else {
			// Commenter?
			$social_avatar = get_user_meta($comment, 'social_avatar', true);
			if (!empty($social_avatar)) {
				$image = $social_avatar;
			}
		}

		if ($image !== null) {
			$image = esc_url($image);
			$size = esc_attr($size);
			$type = '';
			if (is_object($comment)) {
				$type = esc_attr($comment->comment_type);
			}

			$image = esc_url($image);
			$image_format = apply_filters('social_get_avatar_image_format', '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo %4$s" height="%3$s" width="%3$s" />');
			return sprintf($image_format, $alt, $image, $size, $type);
		}

		return $avatar;
	}

	/**
	 * Sets the comment type upon being saved.
	 *
	 * @wp-action  comment_post
	 * @param  int  $comment_ID
	 */
	public function comment_post($comment_ID) {
		global $wpdb;

		$comment = get_comment($comment_ID);
		$services = $this->services();
		if (!empty($services)) {
			$account_id = $_POST['social_post_account'];
			foreach ($services as $key => $service) {
				$output = $service->format_comment_content($comment, Social::option('comment_broadcast_format'));
				foreach ($service->accounts() as $account) {
					if ($account_id == $account->id()) {
						if (isset($_POST['post_to_service'])) {
							$in_reply_to_status_id = get_comment_meta($_POST['comment_parent'], 'social_status_id', true);
							if ($comment->comment_approved == '0') {
								update_comment_meta($comment_ID, 'social_to_broadcast', $_POST['social_post_account']);
								if (!empty($in_reply_to_status_id)) {
									update_comment_meta($comment_ID, 'social_in_reply_to_status_id', addslashes_deep($in_reply_to_status_id));
								}
							}
							else {
								$args = array();
								if (!empty($in_reply_to_status_id)) {
									$args['in_reply_to_status_id'] = $in_reply_to_status_id;
									delete_comment_meta($comment_ID, 'social_in_reply_to_status_id');
								}
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s.', 'social'), $comment_ID, $service->title(), $account->id()));
								$response = $service->broadcast($account, $output, $args, null, $comment_ID);
								if ($response === false || $response->body()->result !== 'success') {
									wp_delete_comment($comment_ID);
									Social::log(sprintf(__('Error: Broadcast comment #%s to %s using account #%s, please go back and try again.', 'social'), $comment_ID, esc_html($service->title()), esc_html($account->id())));
									wp_die(sprintf(__('Error: Your comment could not be sent to %s, please go back and try again.', 'social'), esc_html($service->title())));
								}

								$wpdb->query($wpdb->prepare("
									UPDATE $wpdb->comments
									   SET comment_type = %s
									 WHERE comment_ID = %s
								", 'social-'.$service->key(), $comment_ID));

								$this->set_comment_aggregated_id($comment_ID, $service->key(), $response->body()->response);

								// Feed posts return id with property, comment posts return raw id
								if (isset($response->body()->response->id)) {
									add_comment_meta($comment_ID, 'social_status_id', addslashes_deep($response->body()->response->id), true);
								}
								else {
									add_comment_meta($comment_ID, 'social_status_id', addslashes_deep($response->body()->response), true);
								}
								update_comment_meta($comment_ID, 'social_raw_data', addslashes_deep(base64_encode(json_encode($response->body()->response))));
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s COMPLETE.', 'social'), $comment_ID, $service->title(), $account->id()));
							}
						}

						update_comment_meta($comment_ID, 'social_account_id', addslashes_deep($account_id));
						update_comment_meta($comment_ID, 'social_profile_image_url', addslashes_deep($account->avatar()));
						update_comment_meta($comment_ID, 'social_comment_type', addslashes_deep('social-'.$service->key()));

						if ($comment->user_id != '0') {
							$comment->comment_author = $account->name();
							$comment->comment_author_url = $account->url();
							wp_update_comment(get_object_vars($comment));
						}
						Social::log(sprintf(__('Comment #%s saved.', 'social'), $comment_ID));
						break;
					}
				}
			}
		}
	}

	/**
	 * Sets the comment to be approved.
	 *
	 * @wp-action  wp_set_comment_status
	 * @param  int     $comment_id
	 * @param  string  $comment_status
	 * @return void
	 */
	public function wp_set_comment_status($comment_id, $comment_status) {
		if ($comment_status == 'approve') {
			global $wpdb;
			$results = $wpdb->get_results($wpdb->prepare("
				SELECT user_id, m.meta_value
				  FROM $wpdb->commentmeta AS m
				  JOIN $wpdb->comments AS c
				    ON m.comment_id = c.comment_ID
				 WHERE m.meta_key = %s
				   AND m.comment_id = %s
			", 'social_to_broadcast', $comment_id));
			if (!empty($results)) {
				$result = reset($results);
				$accounts = get_user_meta($result->user_id, 'social_accounts', true);
				if (!empty($accounts)) {
					foreach ($accounts as $service => $accounts) {
						$service = $this->service($service);
						if ($service !== false) {
							$account = null;
							if (!$service->account_exists($result->meta_value)) {
								foreach ($accounts as $id => $account) {
									if ($id == $result->meta_value) {
										$class = 'Social_Service_'.$service->key().'_Account';
										$account = new $class($account);
										break;
									}
								}
							}
							else {
								$account = $service->account($result->meta_value);
							}

							if ($account !== null) {
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s.', 'social'), $comment_id, $service->title(), $account->id()));
								$comment = get_comment($comment_id);

								$in_reply_to_status_id = get_comment_meta($comment_id, 'social_in_reply_to_status_id', true);
								$args = array();
								if (!empty($in_reply_to_status_id)) {
									$args['in_reply_to_status_id'] = $in_reply_to_status_id;
									delete_comment_meta($comment_id, 'social_in_reply_to_status_id');
								}

								$output = $service->format_comment_content($comment, Social::option('comment_broadcast_format'));
								$response = $service->broadcast($account, $output, $args, null, $comment_id);
								if ($response === false || $response->body()->result !== 'success') {
									wp_delete_comment($comment_id);
									Social::log(sprintf(__('Error: Broadcast comment #%s to %s using account #%s, please go back and try again.', 'social'), $comment_id, $service->title(), $account->id()));
								}

								$wpdb->query($wpdb->prepare("
									UPDATE $wpdb->comments
									   SET comment_type = %s
									 WHERE comment_ID = %s
								", 'social-'.$service->key(), $comment_id));

								$this->set_comment_aggregated_id($comment_id, $service->key(), $response->body()->response);

								// Feed posts return id with property, comment posts return raw id
								if (isset($response->body()->response->id)) {
									add_comment_meta($comment_ID, 'social_status_id', addslashes_deep($response->body()->response->id), true);
								}
								else {
									add_comment_meta($comment_ID, 'social_status_id', addslashes_deep($response->body()->response), true);
								}
								update_comment_meta($comment_id, 'social_raw_data', addslashes_deep(base64_encode(json_encode($response->body()->response))));
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s COMPLETE.', 'social'), $comment_id, $service->title(), $account->id()));
							}
						}
					}
				}

				delete_comment_meta($comment_id, 'social_to_broadcast');
			}
		}
	}

	/**
	 * Sets the comment aggregation ID.
	 *
	 * Format of the stored data (serialized):
	 *
	 *     array(
	 *         'twitter' => array(
	 *             1234567890,
	 *             0987654321,
	 *             // ... Other aggregated IDs
	 *         ),
	 *         'facebook' => array(
	 *             1234567890_1234567890,
	 *             0987654321_0987654321,
	 *             // ... Other aggregated IDs
	 *         )
	 *     )
	 *
	 * @param  int     $comment_id
	 * @param  string  $service
	 * @param  int     $broadcasted_id
	 * @return void
	 */
	private function set_comment_aggregated_id($comment_id, $service, $broadcasted_id) {
		$comment = get_comment($comment_id);
		if (is_object($comment)) {
			$aggregated_ids = get_post_meta($comment->comment_post_ID, '_social_aggregated_ids', true);
			if (empty($aggregated_ids)) {
				$aggregated_ids = array();
			}

			if (!isset($aggregated_ids[$service])) {
				$aggregated_ids[$service] = array();
			}

			if (!in_array($broadcasted_id, $aggregated_ids[$service])) {
				$aggregated_ids[$service][] = $broadcasted_id;
			}

			update_post_meta($comment->comment_post_ID, '_social_aggregated_ids', $aggregated_ids);
		}
	}

	/**
	 * Counts the different types of comments.
	 *
	 * @wp-filter social_comments_array
	 * @static
	 * @param  array  $comments
	 * @param  int    $post_id
	 * @return array
	 */
	public function comments_array(array $comments, $post_id) {
		$groups = array();
		if (isset($comments['social_groups'])) {
			$groups = $comments['social_groups'];
		}

		// count the comment types for output in tab headers
		foreach ($comments as $comment) {
			if (is_object($comment)) {
				if (empty($comment->comment_type)) {
					$comment->comment_type = 'wordpress';
				}

				if (!isset($groups[$comment->comment_type])) {
					$groups[$comment->comment_type] = 1;
				}
				else {
					++$groups[$comment->comment_type];
				}
				if (isset($comment->social_items) and is_array($comment->social_items)) {
					$groups[$comment->comment_type] += count($comment->social_items);
				}
			}
		}

		$comments['social_groups'] = apply_filters('social_comments_array_groups', $groups, $comments);

		return $comments;
	}

	/**
	 * Displays a comment.
	 *
	 * @param  object  $comment  comment object
	 * @param  array   $args
	 * @param  int     $depth
	 */
	public function comment($comment, array $args = array(), $depth = 0) {
		$GLOBALS['comment'] = $comment;

		$social_items = '';
		$status_url = null;
		$comment_type = $comment->comment_type;
		$ignored_types = apply_filters('social_ignored_comment_types', array(
			'wordpress',
			'pingback'
		));
		// set the comment type to WordPress if we can't load the Social service (perhaps it was deactivated)
		// and the type isn't an ignored type
		if (!($service = $this->service_for_comment_type($comment->comment_type)) && !in_array($comment->comment_type, $ignored_types)) {
			$comment_type = 'wordpress';
		}
		// set Social Items for Social comments
		if (!in_array($comment->comment_type, $ignored_types)) {
			$status_id = get_comment_meta($comment->comment_ID, 'social_status_id', true);
			if (is_string($status_id) && $status_id) {
				$status_url = $service->status_url(get_comment_author(), $status_id);
			}
			// Social items?
			if (!empty($comment->social_items) && isset($status_url)) {
				if (is_object($service) && method_exists($service, 'key')) {
					$avatar_size = apply_filters('social_items_comment_avatar_size', array(
						'width' => 18,
						'height' => 18,
					));
					$social_items = Social_View::factory('comment/social_item', array(
						'items' => $comment->social_items,
						'service' => $service,
						'avatar_size' => $avatar_size
					));
				}
				else {
					Social::log('service not set for: '.print_r($comment, true));
					ob_start();
					var_dump($service);
					Social::log('$service: '.ob_get_clean());
				}
			}
		}

		echo Social_View::factory('comment/comment', array(
			'comment_type' => $comment_type,
			'comment' => $comment,
			'service' => $service,
			'status_url' => $status_url,
			'depth' => $depth,
			'args' => $args,
			'social_items' => $social_items,
		));
	}

	/**
	 * Adds the Aggregate Comments link to the post row actions.
	 *
	 * @param  array    $actions
	 * @param  WP_Post  $post
	 * @return array
	 */
	public function post_row_actions(array $actions, $post) {
		if (post_type_supports( get_current_screen()->post_type, 'comments' )
			&& $post->post_status == 'publish'
			&& Social::option('aggregate_comments'))
		{
			$actions['social_aggregation'] = sprintf(__('<a href="%s" rel="%s">Social Comments</a>', 'social'), esc_url(Social::wp39_nonce_url(admin_url('options-general.php?social_controller=aggregation&social_action=run&post_id='.$post->ID), 'run')), $post->ID).
				'<img src="'.esc_url(admin_url('images/wpspin_light.gif')).'" class="social_run_aggregation_loader" />';
		}
		return $actions;
	}

	/**
	 * Adds the aggregation functionality to the admin bar.
	 *
	 * @return void
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar;

		$current_object = get_queried_object();

		if (empty($current_object)) {
			return;
		}

		if (!empty($current_object->post_type)
			&& ($post_type_object = get_post_type_object($current_object->post_type))
			&& current_user_can($post_type_object->cap->edit_post, $current_object->ID)
			&& ($post_type_object->show_ui || 'attachment' == $current_object->post_type)
			&& Social::option('aggregate_comments')
			&& post_type_supports($current_object->post_type, 'comments'))
		{
			$wp_admin_bar->add_menu(array(
				'parent' => 'comments',
				'id' => 'social-find-comments',
				'title' => __('Find Social Comments', 'social')
					.'<span class="social-aggregation-spinner" style="display: none;">&nbsp;(
						<span class="social-dot dot-active">.</span>
						<span class="social-dot">.</span>
						<span class="social-dot">.</span>
					)</span>',
				'href' => esc_url(Social::wp39_nonce_url(admin_url('options-general.php?social_controller=aggregation&social_action=run&post_id='.$current_object->ID), 'run')),
			));
			$wp_admin_bar->add_menu(array(
				'parent' => 'comments',
				'id' => 'social-add-tweet-by-url',
				'title' => __('Add Tweet by URL', 'social')
					.'<form class="social-add-tweet" style="display: none;" method="get" action="'.esc_url(Social::wp39_nonce_url(admin_url('options-general.php?social_controller=import&social_action=from_url&social_service=twitter&post_id='.$current_object->ID), 'from_url')).'">
						<input type="text" size="20" name="url" value="" autocomplete="off" />
						<input type="submit" name="social-add-tweet-button" value="'.__('Add Tweet by URL', 'social').'" />
					</form>',
				'href' => esc_url(get_edit_post_link($current_object->ID)),
			));
		}
	}

	function admin_bar_footer_css() {
?>
<style class="text/css">
#wpadminbar #wp-admin-bar-comments .social-aggregation-spinner {
	background: transparent;
	white-space: nowrap;
}
#wpadminbar .social-aggregation-spinner .dot-active {
	font-weight: bold;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url form {
	display: block;
	line-height: 100%;
	margin: 0;
	padding: 5px;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url input {
	color: #333;
	font-size: 11px;
	font-weight: normal;
	line-height: 1;
	margin-bottom: 3px;
	padding: 3px;
	text-shadow: none;
	width: 90%;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url input[type="submit"] {
	margin: 0;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url .loading {
	background: url(<?php echo admin_url('images/wpspin_light.gif'); ?>) center center no-repeat;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url p.msg {
	color: #333;
	font-size: 12px;
	font-weight: normal;
	margin: 0;
	padding: 0;
	text-align: center;
	text-shadow: none;
}
#wpadminbar #wp-admin-bar-social-add-tweet-by-url p.error {
	color: #900;
}
</style>
<?php
	}

	function admin_bar_footer_js() {
?>
<script type="text/javascript">
var socialAdminBarMsgs = {
	'protected': '<?php echo esc_js(__('Protected Tweet', 'social')); ?>',
	'invalid': '<?php echo esc_js(__('Invalid URL', 'social')); ?>',
	'success': '<?php echo esc_js(__('Tweet Imported!', 'social')); ?>'
};
</script>
<?php
	}

	/**
	 * Runs the upgrade only if the installed version is older than the current version.
	 *
	 * @return void
	 */
	private function upgrade() {
		define('SOCIAL_UPGRADE', true);
		global $wpdb; // Don't delete, this is used in upgrade files.

		$upgrades = array(
			Social::$plugins_path.'upgrades/2.0.php',
		);
		$upgrades = apply_filters('social_upgrade_files', $upgrades);
		foreach ($upgrades as $file) {
			if (file_exists($file)) {
				include_once $file;
			}
		}

		Social::option('installed_version', Social::$version);
	}

	/**
	 * Are there accounts connected?
	 *
	 * @return bool
	 */
	public function have_accounts() {
		foreach ($this->services() as $service) {
			if (count($service->accounts())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes an account from the default broadcast accounts.
	 *
	 * @param  string  $service
	 * @param  int     $id
	 * @return void
	 */
	public function remove_from_default_accounts($service, $id) {
		Social::log('Removing from default accounts #:id', array('id' => $id));
		if (defined('IS_PROFILE_PAGE')) {
			$defaults = get_user_meta(get_current_user_id(), 'social_default_accounts', true);
		}
		else {
			$defaults = Social::option('default_accounts');
		}

		if (!empty($defaults) and isset($defaults[$service])) {
			Social::log('Old default accounts: :accounts', array('accounts' => print_r($defaults, true)));

			$_ids = array();
			foreach ($defaults[$service] as $key => $_id) {
				if ($_id != $id) {
					$_ids[$key] = $_id;
				}
			}

			// TODO abstract this to the Facebook plugin
			if ($service == 'facebook' and isset($_ids['pages'])) {
				$pages = $_ids['pages'];
				unset($_ids['pages']);
				sort($_ids);
				foreach ($pages as $account_id => $account_pages) {
					if ($account_id != $id) {
						$_ids['pages'][$account_id] = $account_pages;
					}
				}
			}

			$defaults[$service] = $_ids;
			if (!count($defaults[$service])) {
				unset($defaults[$service]);
			}
			Social::log('New default accounts: :accounts', array('accounts' => print_r($defaults, true)));
			if (defined('IS_PROFILE_PAGE')) {
				update_user_meta(get_current_user_id(), 'social_default_accounts', $defaults);
			}
			else {
				Social::option('default_accounts', $defaults);
			}
		}
	}

	/**
	 * Recursively applies wp_kses() to an array/stdClass.
	 *
	 * @param  mixed  $object
	 * @return mixed
	 */
	public function kses($object) {
		if (is_object($object)) {
			$_object = new stdClass;
		}
		else {
			$_object = array();
		}

		foreach ($object as $key => $val) {
			if (is_object($val) or is_array($val)) {
				if (is_object($_object)) {
					$_object->$key = $this->kses($val);
				}
				else if (is_array($_object)) {
					$_object[$key] = $this->kses($val);
				}
			}
			else {
				if (is_object($_object)) {
					$_object->$key = wp_kses($val, array());
				}
				else {
					$_object[$key] = wp_kses($val, array());
				}
			}
		}

		return $_object;
	}

	/**
	 * Handles the remote timeout requests for Social.
	 *
	 * @param  string  $url        url to request
	 * @param  string  $nonce_key  key to use when generating the nonce
	 * @param  bool    $post       set to true to do a wp_remote_post
	 * @return void
	 */
	private function request($url, $nonce_key = null, $post = false) {
		if ($nonce_key !== null) {
			$url = str_replace('&amp;', '&', Social::wp39_nonce_url($url, $nonce_key));
		}


		$data = array(
			'timeout' => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters('https_local_ssl_verify', true),
		);

		if ($post) {
			Social::log('POST request to: :url', array(
				'url' => $url
			));
			wp_remote_post($url, $data);
		}
		else {
			Social::log('GET request to: :url', array(
				'url' => $url
			));
			wp_remote_get($url, $data);
		}
	}

	/**
	 * Loads the services.
	 *
	 * @return array
	 */
	private function load_services() {
		if ((isset($_GET['page']) and $_GET['page'] == basename(SOCIAL_FILE)) or defined('IS_PROFILE_PAGE')) {
			$services = false;
		}
		else {
			$services = wp_cache_get('services', 'social');
		}

		if ($services === false) {
			$services = array();
			// Register services
			$registered_services = apply_filters('social_register_service', array());
			if (is_array($registered_services) and count($registered_services)) {
				$accounts = Social::option('accounts');
				foreach ($registered_services as $service) {
					if (!isset($services[$service])) {
						$service_accounts = array();

						if (isset($accounts[$service]) && is_array($accounts[$service]) && !empty($accounts[$service])) {
							// Flag social as enabled, we have at least one account.
							if ($this->_enabled === null) {
								$this->_enabled = true;
							}

							foreach ($accounts[$service] as $account_id => $account) {
								// TODO Shouldn't have to do this. Fix later.
								$account->personal = '0';
								$service_accounts[$account_id] = $account;
							}
						}

						$class = 'Social_Service_'.$service;
						$services[$service] = new $class($service_accounts);
					}
				}
				wp_cache_set('services', $services, 'social');
			}
		}
		else if ($this->_enabled === null and is_array($services)) {
			foreach ($services as $service) {
				if (count($service->accounts())) {
					$this->_enabled = true;
					break;
				}
			}
		}

// don't return global services for commenters
		$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
		if ($commenter == 'true' && !current_user_can('publish_posts')) {
			foreach ($services as $key => $accounts) {
				$services[$key]->clear_accounts();
			}
		}

		$personal_accounts = get_user_meta(get_current_user_id(), 'social_accounts', true);
		if (is_array($personal_accounts)) {
			foreach ($personal_accounts as $key => $_accounts) {
				if (count($_accounts) and isset($services[$key])) {
					$class = 'Social_Service_'.$key.'_Account';
					foreach ($_accounts as $account_id => $account) {
						// TODO Shouldn't have to do this. Fix later.
						$account->universal = '0';
						if ($services[$key]->account_exists($account_id) and !defined('IS_PROFILE_PAGE')) {
							$account = $this->merge_accounts($services[$key]->account($account_id)->as_object(), $account, $key);
						}
						$account = new $class((object) $account);
						$services[$key]->account($account);
						// Flag social as enabled, we have at least one account.
						if ($this->_enabled === null) {
							$this->_enabled = true;
						}
					}
				}
			}
		}

		return $services;
	}

	/**
	 * Merges universal with personal account.
	 *
	 * @param  array   $arr1
	 * @param  array   $arr2
	 * @param  string  $service_key
	 * @return object
	 */
	private function merge_accounts($arr1, $arr2, $service_key) {
		$arr1->personal = true;
		return apply_filters('social_merge_accounts', $arr1, $arr2, $service_key);
	}

	/**
	 * Checks to see if the array is an associative array.
	 *
	 * @param  array  $arr
	 * @return bool
	 */
	private function is_assoc($arr) {
		$keys = array_keys($arr);
		return array_keys($keys) !== $keys;
	}

	/**
	 * Builds the settings URL for the plugin.
	 *
	 * @param  array  $params
	 * @param  bool   $personal
	 * @return string
	 */
	public static function settings_url(array $params = null, $personal = false) {
		if ($params !== null) {
			foreach ($params as $key => $value) {
				$params[$key] = urlencode($value);
			}
		}

		if (!current_user_can('manage_options') or $personal) {
			$file = 'profile.php';
		}
		else {
			$file = 'options-general.php';
			$params['page'] = basename(SOCIAL_FILE);
		}

		$url = add_query_arg($params, admin_url($file));

		if (!current_user_can('manage_options') or $personal) {
			$url .= '#social-networks';
		}

		return $url;
	}

	/**
	 * Filter the where clause for pulling comments for feeds (to exclude meta comments).
	 *
	 * @param  string  $where
	 * @return string
	 */
	public static function comments_feed_exclusions($where) {
		global $wpdb;
		$meta_types = array();
// get services
		$services = Social::instance()->services();
// ask each service for it's "meta" comment types
		foreach ($services as $service) {
			$meta_types = array_merge($meta_types, $service->comment_types_meta());
		}
		$meta_types = array_unique($meta_types);
		if (count($meta_types)) {
			$where .= " AND comment_type NOT IN ('".implode("', '", array_map('social_wpdb_escape', $meta_types))."') ";
		}
		return $where;
	}

	/**
	 * Filter the image tag to implement lazy loading support for meta comments.
	 *
	 * @param  string  $image_format
	 * @return string
	 */
	public static function social_item_output_image_format($image_format) {
		if (class_exists('LazyLoad_Images')) {
			// would be great if the plugin provided an API for this, until then we'll copy the code
			$placeholder_image = apply_filters( 'lazyload_images_placeholder_image', LazyLoad_Images::get_url( 'images/1x1.trans.gif' ) );
			$image_format = '<img src="'.esc_url($placeholder_image).'" data-lazy-src="%1$s" width="%2$s" height="%3$s" alt="%4$s" /><noscript>'.$image_format.'</noscript>';
		}
		return $image_format;
	}

	/**
	 * Filter the image tag to implement lazy loading support for avatars.
	 *
	 * @param  string  $image_format
	 * @return string
	 */
	public static function social_get_avatar_image_format($image_format) {
		if (class_exists('LazyLoad_Images')) {
			// would be great if the plugin provided an API for this, until then we'll copy the code
			$placeholder_image = apply_filters( 'lazyload_images_placeholder_image', LazyLoad_Images::get_url( 'images/1x1.trans.gif' ) );
			$image_format = '<img alt="%1$s" src="'.esc_url($placeholder_image).'" data-lazy-src="%2$s" class="avatar avatar-%3$s photo %4$s" height="%3$s" width="%3$s" /><noscript>'.$image_format.'</noscript>';
		}
		return $image_format;
	}

	/**
	 * Verify a nonce created by self::wp39_create_nonce().
	 *
	 * This re-implements the functionality of wp_verify_nonce() circa WP3.9,
	 * to verify nonces that are compatble with the Social authentication
	 * workflow.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/3.9-branch/wp-includes/pluggable.php
	 *
	 * @param string $nonce Nonce that was used in the form to verify
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return bool Whether the nonce check passed or failed.
	 */
	public static function wp39_verify_nonce($nonce, $action = -1) {
		$user = wp_get_current_user();
		$uid = (int) $user->ID;
		if (!$uid) {
			/**
			 * Filter whether the user who generated the nonce is logged out.
			 *
			 * @since 3.5.0
			 *
			 * @param int    $uid    ID of the nonce-owning user.
			 * @param string $action The nonce action.
			 */
			$uid = apply_filters('nonce_user_logged_out', $uid, $action);
		}

		$i = wp_nonce_tick();

		// Nonce generated 0-12 hours ago
		$expected = substr(wp_hash( $i.'|'.$action.'|'.$uid, 'nonce'), -12, 10);
		if (hash_equals($expected, $nonce)) {
			return 1;
		}

		// Nonce generated 12-24 hours ago
		$expected = substr(wp_hash(($i - 1).'|'.$action.'|'.$uid, 'nonce' ), -12, 10);
		if (hash_equals($expected, $nonce)) {
			return 2;
		}

		// Invalid nonce
		return false;
	}

	/**
	 * Create a nonce compatible with self::wp39_verify_nonce().
	 *
	 * This re-implements the functionality of wp_create_nonce() circa WP3.9,
	 * to provide nonces that are compatble with the Social authentication
	 * workflow.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/3.9-branch/wp-includes/pluggable.php
	 *
	 * @param string|int $action Scalar value to add context to the nonce.
	 * @return string The one use form token
	 */
	public static function wp39_create_nonce($action = -1) {
		$user = wp_get_current_user();
		$uid = (int) $user->ID;
		if (!$uid) {
			/** This filter is documented in wp-includes/pluggable.php */
			$uid = apply_filters('nonce_user_logged_out', $uid, $action);
		}

		$i = wp_nonce_tick();

		return substr(wp_hash($i.'|'.$action.'|'.$uid, 'nonce'), -12, 10);
	}


	/**
	 * Retrieve URL with nonce added to URL query using Social::wp39_create_nonce()
	 * instead of Social::wp_create_nonce()
	 *
	 * @param string $actionurl URL to add nonce action.
	 * @param string $action    Optional. Nonce action name. Default -1.
	 * @param string $name      Optional. Nonce name. Default '_wpnonce'.
	 * @return string Escaped URL with nonce action added.
	 */
	public static function wp39_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		$actionurl = str_replace( '&amp;', '&', $actionurl );
		return esc_html( add_query_arg( $name, Social::wp39_create_nonce( $action ), $actionurl ) );
	}


} // End Social

if (!function_exists('addslashes_deep')) {
/**
 * Navigates through an array and adds slashes to the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. Slashes will be added to this value.
 *
 * @since 3.4.0?
 *
 * @param array|string $value The array or string to be slashed.
 * @return array|string Slashed array (or string in the callback).
 */
function addslashes_deep($value) {
	if ( is_array($value) ) {
		$value = array_map('addslashes_deep', $value);
	} elseif ( is_object($value) ) {
		$vars = get_object_vars( $value );
		foreach ($vars as $key=>$data) {
			$value->{$key} = addslashes_deep( $data );
		}
	} else {
		$value = addslashes($value);
	}

	return $value;
}
}

function social_strlen($str) {
	if (function_exists('mb_strlen')) {
		return mb_strlen($str);
	}
	else {
		return strlen($str);
	}
}

function social_substr($str, $start = null, $end = null) {
	if (function_exists('mb_substr')) {
		switch (func_num_args()) {
			case 1:
				return mb_substr($str);
			break;
			case 2:
				return mb_substr($str, $start);
			break;
			case 3:
				return mb_substr($str, $start, $end);
			break;
		}
	}
	else {
		switch (func_num_args()) {
			case 1:
				return substr($str);
			break;
			case 2:
				return substr($str, $start);
			break;
			case 3:
				return substr($str, $start, $end);
			break;
		}
	}
}

function social_wpdb_escape($str) {
	global $wpdb;
	return $wpdb->escape($str);
}

function social_wp_mail_indicator() {
	define('SOCIAL_MAIL_PUBLISH', true);
}

/**
 * Social Get Shortlink
 *
 * This is required because wp_get_shortlink sometimes returns nothing.  If no shortlink is available we want to default to the permalink.
 *
 * @param  int Post ID
 * @return string
 */
function social_get_shortlink($post_id) {
        return (wp_get_shortlink($post_id)) ? wp_get_shortlink($post_id) : get_permalink($post_id);
}

$social_file = __FILE__;
if (isset($plugin)) {
	$social_file = $plugin;
}
else if (isset($mu_plugin)) {
	$social_file = $mu_plugin;
}
else if (isset($network_plugin)) {
	$social_file = $network_plugin;
}

define('SOCIAL_FILE', $social_file);
define('SOCIAL_PATH', WP_PLUGIN_DIR.'/'.basename(dirname($social_file)).'/');

// Register Social's autoloading
spl_autoload_register(array('Social', 'auto_load'));

$social = Social::instance();

// General Actions
add_action('init', array($social, 'init'), 1);
add_action('init', array($social, 'request_handler'), 2);
add_action('admin_init', array($social, 'admin_init'), 1);
add_action('load-settings_page_social', array($social, 'check_system_cron'));
add_action('load-social.php', array($social, 'check_system_cron'));
add_action('comment_post', array($social, 'comment_post'));
add_action('wp_set_comment_status', array($social, 'wp_set_comment_status'), 10, 3);
add_action('admin_notices', array($social, 'admin_notices'));
add_action('transition_post_status', array($social, 'transition_post_status'), 10, 3);
add_action('show_user_profile', array($social, 'show_user_profile'));
add_action('do_meta_boxes', array($social, 'do_meta_boxes'));
add_action('delete_post', array($social, 'delete_post'));
add_action('wp_enqueue_scripts', array($social, 'enqueue_assets'));
add_action('load-post-new.php', array($social, 'enqueue_assets'));
add_action('load-post.php', array($social, 'enqueue_assets'));
add_action('load-profile.php', array($social, 'enqueue_assets'));
add_action('load-settings_page_social', array($social, 'enqueue_assets'));
add_action('admin_enqueue_scripts', array($social, 'admin_enqueue_assets'));
add_action('admin_bar_menu', array($social, 'admin_bar_menu'), 95);
add_action('wp_after_admin_bar_render', array($social, 'admin_bar_footer_css'));
add_action('wp_after_admin_bar_render', array($social, 'admin_bar_footer_js'));
add_action('set_user_role', array($social, 'set_user_role'), 10, 2);
add_action('wp-mail.php', 'social_wp_mail_indicator');
add_action('social_settings_save', array('Social_Service_Facebook', 'social_settings_save'));

// CRON Actions
add_action('socialcron15init', array($social, 'cron_15_init'));
if (Social::option('aggregate_comments')) {
	add_action('socialcron15', array($social, 'run_aggregation'));
}

// Admin Actions
add_action('admin_menu', array($social, 'admin_menu'));
add_action('personal_options_update', array($social, 'personal_options_update'));

// Filters
add_filter('cron_schedules', array($social,'cron_schedules'));
add_filter('plugin_action_links', array($social, 'add_settings_link'), 10, 2);
add_filter('redirect_post_location', array($social, 'redirect_post_location'), 10, 2);
add_filter('comments_template', array($social, 'comments_template'));
add_filter('get_avatar_comment_types', array($social, 'get_avatar_comment_types'));
add_filter('get_avatar', array($social, 'get_avatar'), 10, 5);
add_filter('register', array($social, 'register'));
add_filter('loginout', array($social, 'loginout'));
add_filter('post_row_actions', array($social, 'post_row_actions'), 10, 2);
add_filter('social_comments_array', array($social, 'comments_array'), 100, 2);
add_filter('comment_feed_where', array($social, 'comments_feed_exclusions'));
add_filter('social_settings_default_accounts', array('Social_Service_Facebook', 'social_settings_default_accounts'), 10, 2);
add_filter('social_item_output_image_format', array($social, 'social_item_output_image_format'));
add_filter('social_get_avatar_image_format', array($social, 'social_get_avatar_image_format'));

// Service filters
add_filter('social_auto_load_class', array($social, 'auto_load_class'));

} // End class_exists check
