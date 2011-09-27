<?php
/*
Plugin Name: Social
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Broadcast newly published posts and pull in discussions using integrations with Twitter and Facebook. Brought to you by <a href="http://mailchimp.com">MailChimp</a>.
Version: 2.0
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
	// TODO uncomment this when 1.1 goes live.
	//public static $api_url = 'https://sopresto.mailchimp.com/';
	public static $api_url = 'https://soprestodev.mailchimp.com/dev.php/';

	/**
	 * @var  string  version number
	 */
	public static $version = '2.0';

	/**
	 * @var  string  CRON lock directory.
	 */
	public static $cron_lock_dir = null;

	/**
	 * @var  string  plugins URL
	 */
	public static $plugins_url = '';

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
		'twitter_anywhere_api_key' => null,
		'system_cron_api_key' => null,
		'fetch_comments' => '1',
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
		if (substr($class, 0, 7) == 'Social_') {
			try {
				$file = SOCIAL_PATH.'lib/'.str_replace('_', '/', strtolower($class)).'.php';
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
		$defaults = array(
			'{url}' => __('Blog post\'s permalink', 'social'),
			'{title}' => __('Blog post\'s title', 'social'),
			'{content}' => __('Blog post\'s content', 'social'),
			'{date}' => __('Blog post\'s date', 'social'),
			'{author}' => __('Blog post\'s author', 'social'),
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
			'{content}' => __('Comment\'s content', 'social'),
			'{url}' => __('Comment\'s permalink', 'social'),
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
	 * @param  string  $message  message to add to the log
	 * @param  array   $args     arguments to pass to the writer
	 * @return void
	 */
	public static function log($message, array $args = null) {
		Social::$log->write($message, $args);
	}

	/**
	 * @var  bool  is Social enabled?
	 */
	private $_enabled = false;

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
	 * @return Social_Service|Social_Service_Twitter|Social_Service_Facebook
	 */
	public function service($key) {
		$services = $this->load_services();

		$key = str_replace('social-', '', $key);
		$key = apply_filters('social_comment_type_to_service', $key);
		if (!isset($services[$key])) {
			return false;
		}

		return $services[$key];
	}

	/**
	 * Initializes Social.
	 *
	 * @wp-action  init
	 * @return void
	 */
	public function init() {
		if (version_compare(PHP_VERSION, '5.2.4', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social requires PHP 5.2.4 or higher. Ask your host how to enable PHP 5 as the default on your servers.", 'social'));
		}

		// Set the logger
		Social::$log = Social_Log::factory();

		// Just activated?
		if (!Social::option('install_date')) {
			Social::option('install_date', current_time('timestamp', 1));
			Social::option('system_cron_api_key', wp_generate_password(16, false));
		}

		// Trigger upgrade?
		$this->upgrade(Social::option('installed_version'));

		// Plugins URL
		$url = plugins_url('', SOCIAL_FILE);
		Social::$plugins_url = trailingslashit(apply_filters('social_plugins_url', $url));
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
		// JS/CSS
		if (!defined('SOCIAL_COMMENTS_JS')) {
			define('SOCIAL_COMMENTS_JS', Social::$plugins_url.'assets/social.js');
		}

		if (!is_admin()) {
			if (!defined('SOCIAL_COMMENTS_CSS')) {
				define('SOCIAL_COMMENTS_CSS', Social::$plugins_url.'assets/comments.css');
			}

			// JS/CSS
			if (SOCIAL_COMMENTS_CSS !== false) {
				wp_enqueue_style('social_comments', SOCIAL_COMMENTS_CSS, array(), Social::$version, 'screen');
			}
		}

		// JS/CSS
		if (SOCIAL_COMMENTS_JS !== false) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('social_js', SOCIAL_COMMENTS_JS, array('jquery'), Social::$version, true);
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
			$this->load_services();
		}

		$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
		if (!empty($commenter) and $commenter == 'true') {
			wp_redirect(site_url());
		}
	}

	/**
	 * Checks to see if system crons are disabled.
	 *
	 * @wp-action  load-settings_page_social
	 * @return void
	 */
	public function check_system_cron() {
		// Schedule CRONs
		if (Social::option('fetch_comments') == '1') {
			if (wp_next_scheduled('social_cron_15_init') === false) {
				wp_schedule_event(time() + 900, 'every15min', 'social_cron_15_init');
			}

			if (wp_next_scheduled('social_cron_60_init') === false) {
				wp_schedule_event(time() + 3600, 'hourly', 'social_cron_60_init');
			}

			$this->request(admin_url('?social_controller=cron&social_action=check_crons'), 'check_crons');
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
			$settings_link = '<a href="'.esc_url(admin_url('options-general.php?page=social.php')).'">'.__("Settings", "photosmash-galleries").'</a>';
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
			if (!$this->_enabled) {
				$message = sprintf(__('Social will not run until you update your <a href="%s">settings</a>.', 'social'), esc_url(Social::settings_url()));
				echo '<div class="error"><p>'.$message.'</p></div>';
			}

			if (isset($_GET['page']) and $_GET['page'] == basename(SOCIAL_FILE)) {
				// CRON Lock
				if (Social::option('cron_lock_error') !== null) {
					$upload_dir = wp_upload_dir();
					if (isset($upload_dir['basedir'])) {
						$message = sprintf(__('Social requires that either %s or %s be writable for CRON jobs.', 'social'), SOCIAL_PATH, $upload_dir['basedir']);
					}
					else {
						$message = sprintf(__('Social requires that %s is writable for CRON jobs.', 'social'), SOCIAL_PATH);
					}

					echo '<div class="error"><p>'.esc_html($message).'</p></div>';
				}
			}

			// Log write error
			$error = Social::option('log_write_error');
			if ($error == '1') {
				echo '<div class="error"><p>'.
					sprintf(__('%s needs to be writable for Social\'s logging. <a href="%" class="social_dismiss">[Dismiss]</a>', 'social'), SOCIAL_PATH, esc_url(admin_url('?social_controller=settings&social_action=clear_log_write_error'))).
					'</p></div>';
			}

			// Enable notice?
			$suppress_enable_notice = get_user_meta(get_current_user_id(), 'social_suppress_enable_notice', true);
			if (empty($suppress_enable_notice)) {
				$message = __('When you enable Social, users will be created in your system and given the "%s" as specified in your <a href="%s">Settings</a>. Users that are created by Social and only have Subscriber permissions will be prevented from accessing the admin side of WordPress.', 'social');
				$dismiss = sprintf(__('<a href="%s" class="social_dismiss">[Dismiss]</a>', 'social'), esc_url(admin_url('?social_controller=settings&social_action=suppress_enable_notice')));
				$message = sprintf($message, get_option('default_role'), esc_url(admin_url('options-general.php')));
				echo '<div class="updated"><p>'.$message.' '.$dismiss.'</p></div>';
			}
		}

		// Deauthed accounts
		$deauthed = Social::option('deauthed');
		if (!empty($deauthed)) {
			foreach ($deauthed as $service => $data) {
				foreach ($data as $id => $message) {
					$dismiss = sprintf(__('<a href="%s" class="%s">[Dismiss]</a>', 'social'), esc_url(admin_url('?social_controller=settings&social_action=clear_deauth&id='.$id.'&service='.$service)), 'social_dismiss');
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
				echo '<div class="error">'.$message.'</div>';

				delete_post_meta($post->ID, '_social_broadcast_error');
			}
		}

		// 2.0 Upgrade?
		$upgrade_2_0 = get_user_meta(get_current_user_id(), 'social_2.0_upgrade', true);
		if (!empty($upgrade_2_0)) {
			if (current_user_can('manage_options')) {
				$output = 'Social needs to re-authorize in order to post to Facebook on your behalf. Please reconnect your <a href="%s">global</a> and <a href="%s">personal</a> accounts.';
				$output = sprintf($output, esc_url(Social::settings_url()), esc_url(admin_url('profile.php#social-networks')));
			}
			else {
				$output = 'Social needs to re-authorize in order to post to Facebook on your behalf. Please reconnect your <a href="%s">personal</a> accounts.';
				$output = sprintf($output, esc_url(admin_url('profile.php#social-networks')));
			}

			$dismiss = sprintf(__('<a href="%s" class="%s">[Dismiss]</a>', 'social'), esc_url(admin_url('?social_controller=settings&social_action=clear_2_0_upgrade')), 'social_dismiss');
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
		echo Social_View::factory('wp-admin/profile', array(
			'services' => $this->services()
		));
	}

	/**
	 * Add Meta Boxes
	 *
	 * @wp-action  do_meta_boxes
	 * @return void
	 */
	public function do_meta_boxes() {
		global $post;

		if ($post !== null) {
			foreach ($this->services() as $service) {
				if (count($service->accounts())) {
					add_meta_box('social_meta_broadcast', __('Social Broadcasting', 'social'), array(
						$this,
						'add_meta_box_broadcast'
					), 'post', 'side', 'high');
					break;
				}
			}

			$fetch = Social::option('fetch_comments');
			if ($this->_enabled and !empty($fetch)) {
				if ($post->post_status == 'publish') {
					add_meta_box('social_meta_aggregation_log', __('Social Comments', 'social'), array(
						$this,
						'add_meta_box_log'
					), 'post', 'normal', 'core');
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
				'ids' => $broadcasted_ids
			));
		}

		// Content
		$button = '';
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
				$content = Social_View::factory('wp-admin/post/meta/broadcast/published', array(
					'ids' => $broadcasted_ids,
					'broadcasted' => !empty($broadcasted_ids),
				));
				break;
			default:
				$notify = false;
				if (get_post_meta($post->ID, '_social_notify', true) == '1') {
					$notify = true;
				}

				$content = Social_View::factory('wp-admin/post/meta/broadcast/default', array(
					'post' => $post,
					'notify' => $notify,
				));
				break;
		}

		// Button
		if (!empty($button)) {
			$button = Social_View::factory('wp-admin/post/meta/broadcast/parts/button', array(
				'broadcasted' => $broadcasted,
				'button_text' => $button,
			));
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
			$next_run = date(get_option('date_format').' '.get_option('time_format'), ((int) $next_run + (get_option('gmt_offset') * 3600)));
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
			if ($new == 'publish') {
				Social_Aggregation_Queue::factory()->add($post->ID)->save();
			}

			// Sends previously saved broadcast information
			if (in_array($old, array('pending', 'future'))) {
				Social_Request::factory('broadcast/run')->query(array(
					'post_ID' => $post->ID
				))->execute();
			}
		}
	}

	/**
	 * Sets the broadcasted IDs for the post.
	 *
	 * @param  int     $post_id           post id
	 * @param  string  $service           service key
	 * @param  string  $broadcasted_id    broadcasted id
	 * @param  string  $message           broadcasted message
	 * @param  Social_Service_Facebook_Account|Social_Service_Twitter_Account  $account        account
	 * @param  string  $account_username  broadcasted username
	 * @return void
	 */
	public function add_broadcasted_id($post_id, $service, $broadcasted_id, $message, $account, $account_username) {
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
			$data = array(
				'message' => $message,
			);
			$data = apply_filters('social_save_broadcasted_ids_data', $data, $account, $service, $post_id);
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
	 * @wp-action  social_cron_15_init
	 * @return void
	 */
	public function cron_15_init() {
		$this->request(site_url('?social_controller=cron&social_action=cron_15'), 'cron_15');
	}

	/**
	 * Sends a request to initialize CRON 60.
	 *
	 * @wp-action  social_cron_60_init
	 * @return void
	 */
	public function cron_60_init() {
		$this->request(site_url('?social_controller=cron&social_action=cron_60'), 'cron_60');
	}

	/**
	 * Runs the aggregation loop.
	 *
	 * @wp-action  social_cron_15
	 * @return void
	 */
	public function run_aggregation() {
		$queue = Social_Aggregation_Queue::factory();

		foreach ($queue->runnable() as $timestamp => $posts) {
			foreach ($posts as $id => $interval) {
				$post = get_post($id);
				if ($post !== null) {
					$queue->add($id, $interval)->save();
					$this->request(site_url('?social_controller=aggregation&social_action=run&post_id='.$id), 'run');
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
						return $service->disconnect_url($account);
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
	public function comments_template() {
		global $post;

		if (!(is_singular() and (have_comments() or $post->comment_status == 'open'))) {
			return;
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
	public function get_avatar($avatar, $comment, $size, $default, $alt) {
		$image = null;
		if (is_object($comment)) {
			$image = get_comment_meta($comment->comment_ID, 'social_profile_image_url', true);
			if (empty($image)) {
				$image = null;
		    }
		}

		if ($image !== null) {
			$type = '';
			if (is_object($comment)) {
				$type = $comment->comment_type;
			}

			$image = esc_url($image);
			return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$type}' height='{$size}' width='{$size}' />";
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
		$comment = get_comment($comment_ID);
		$services = $this->services();
		if (!empty($services)) {
			$account_id = $_POST['social_post_account'];
			foreach ($services as $key => $service) {
				$output = $service->format_comment_content($comment, Social::option('comment_broadcast_format'));
				foreach ($service->accounts() as $account) {
					if ($account_id == $account->id()) {
						if (isset($_POST['post_to_service'])) {
							if ($comment->comment_approved == '0') {
								update_comment_meta($comment_ID, 'social_to_broadcast', $_POST['social_post_account']);
							}
							else {
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s.', 'social'), $comment_ID, $service->title(), $account->id()));
								$id = $service->broadcast($account, $output)->id();
								if ($id === false) {
									wp_delete_comment($comment_ID);
									$message = sprintf(__('Error: Broadcast comment #%s to %s using account #%s, please go back and try again.', 'social'), $comment_ID, $service->title(), $account->id());

									Social::log($message);
									wp_die($message);
								}
								$this->set_comment_aggregated_id($comment_ID, $service->key(), $id);
								update_comment_meta($comment_ID, 'social_status_id', $id);
								Social::log(sprintf(__('Broadcasting comment #%s to %s using account #%s COMPLETE.', 'social'), $comment_ID, $service->title(), $account->id()));
							}
						}

						update_comment_meta($comment_ID, 'social_account_id', $account_id);
						update_comment_meta($comment_ID, 'social_profile_image_url', $account->avatar());
						update_comment_meta($comment_ID, 'social_comment_type', 'social-'.$service->key());

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
								$output = $service->format_comment_content($comment, Social::option('comment_broadcast_format'));
								$id = $service->broadcast($account, $output)->id();
								if ($id === false) {
									wp_delete_comment($comment_id);
									Social::log(sprintf(__('Error: Broadcast comment #%s to %s using account #%s, please go back and try again.', 'social'), $comment_id, $service->title(), $account->id()));
								}
								$this->set_comment_aggregated_id($comment_id, $service->key(), $id);
								update_comment_meta($comment_id, 'social_status_id', $id);
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

		foreach ($comments as $comment) {
			if (is_object($comment)) {
				if (isset($comment->social_comment_type)) {
					$comment->comment_type = $comment->social_comment_type;
				}
				
				if (empty($comment->comment_type)) {
					$comment->comment_type = 'wordpress';
				}

				if (!isset($groups[$comment->comment_type])) {
					$groups[$comment->comment_type] = 1;
				}
				else {
					++$groups[$comment->comment_type];
				}
			}
		}

		if (isset($groups['social-facebook-like'])) {
			if (!isset($groups['social-facebook'])) {
				$groups['social-facebook'] = 0;
			}

			$groups['social-facebook'] = $groups['social-facebook'] + $groups['social-facebook-like'];
		    unset($groups['social-facebook-like']);
		}

		if (count($groups)) {
			$comments['social_groups'] = $groups;
		}

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
		$comment_type = get_comment_meta($comment->comment_ID, 'social_comment_type', true);
		if (empty($comment_type)) {
			$comment_type = (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type);
		}
		$comment->comment_type = $comment_type;
		$GLOBALS['comment'] = $comment;

		$status_url = null;
		$service = null;
		if (!in_array($comment_type, apply_filters('social_ignored_comment_types', array(
			'wordpress',
			'pingback'
		)))
		) {
			$service = $this->service($comment->comment_type);
			if ($service !== false and $service->show_full_comment($comment->comment_type)) {
				if ($status_url === null) {
					$comment_type = 'wordpress';
				}
			}

			$status_id = get_comment_meta($comment->comment_ID, 'social_status_id', true);
			if (!empty($status_id)) {
				$status_url = $service->status_url(get_comment_author(), $status_id);
			}
		}

		// Social items?
		$social_items = '';
		if (!empty($comment->social_items)) {
			$social_items = Social_View::factory('comment/social_item', array(
				'items' => $comment->social_items,
				'service' => $service,
				'avatar_size' => array(
					'width' => 18,
					'height' => 18,
				)
			));
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
		if ($post->post_status == 'publish') {
			$actions['social_aggregation'] = sprintf(__('<a href="%s" rel="%s">Social Comments</a>', 'social'), esc_url(wp_nonce_url(admin_url('?social_controller=aggregation&social_action=run&post_id='.$post->ID), 'run')), $post->ID).
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
			and ($post_type_object = get_post_type_object($current_object->post_type))
				and current_user_can($post_type_object->cap->edit_post, $current_object->ID)
					and ($post_type_object->show_ui or 'attachment' == $current_object->post_type)
		) {
			$wp_admin_bar->add_menu(array(
				'parent' => 'comments',
				'id' => 'social_find_comments',
				'title' => __('Find Social Comments', 'social').'<img src="'.esc_url(admin_url('images/wpspin_dark.gif')).'" class="social-aggregation-spinner" style="display: none;" />',
				'href' => esc_url(wp_nonce_url(admin_url('?social_controller=aggregation&social_action=run&post_id='.$current_object->ID), 'run')),
			));
		}
	}

	/**
	 * Runs the upgrade only if the installed version is older than the current version.
	 *
	 * @param  string  $installed_version
	 * @return void
	 */
	private function upgrade($installed_version) {
		if (version_compare($installed_version, Social::$version, '<')) {
			define('SOCIAL_UPGRADE', true);
			global $wpdb; // Don't delete, this is used in upgrade files.

			$upgrades = array(
				SOCIAL_PATH.'upgrades/2.0.php',
			);
			$upgrades = apply_filters('social_upgrade_files', $upgrades);
			foreach ($upgrades as $file) {
				if (file_exists($file)) {
					include_once $file;
				}
			}

			Social::option('installed_version', Social::$version);
		}
	}

	/**
	 * Removes an account from the default broadcast accounts.
	 *
	 * @param  string  $service
	 * @param  int     $id
	 * @return void
	 */
	public function remove_from_default_accounts($service, $id) {
		$defaults = Social::option('default_accounts');
		if (!empty($defaults) and isset($defaults[$service])) {
			$ids = array_values($defaults[$service]);
			if (in_array($id, $ids)) {
				$_ids = array();
				foreach ($ids as $id) {
					if ($id != $_GET['id']) {
						$_ids[] = $id;
					}
				}
				$defaults[$_GET['service']] = $_ids;
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
	private function request($url, $nonce_key, $post = false) {
		$url = str_replace('&amp;', '&', wp_nonce_url($url, $nonce_key));
		$data = array(
			'timeout' => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters('https_local_ssl_verify', true),
		);

		if ($post) {
			wp_remote_post($url, $data);
		}
		else {
			wp_remote_get($url, $data);
		}
	}

	/**
	 * Loads the services.
	 *
	 * @return array
	 */
	private function load_services() {
		$services = wp_cache_get('services', 'social');
		if ($services === false) {
			// Register services
			$registered_services = apply_filters('social_register_service', array());
			if (is_array($registered_services) and count($registered_services)) {
				$accounts = array();
				$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);

				if ($commenter != 'true') {
					$accounts = Social::option('accounts');
				}
				foreach ($registered_services as $service) {
					if (!isset($services[$service])) {
						$service_accounts = array();

						if (isset($accounts[$service]) and count($accounts[$service])) {
							$this->_enabled = true; // Flag social as enabled, we have at least one account.
							$service_accounts = $accounts[$service];
						}

						$class = 'Social_Service_'.$service;
						$services[$service] = new $class($service_accounts);
					}
				}

				$personal_accounts = get_user_meta(get_current_user_id(), 'social_accounts', true);
				if (is_array($personal_accounts)) {
					foreach ($personal_accounts as $key => $_accounts) {
						if (count($_accounts) and isset($services[$key])) {
							$this->_enabled = true;
							$class = 'Social_Service_'.$key.'_Account';
							foreach ($_accounts as $account_id => $account) {
								if ($services[$key]->account_exists($account_id)) {
									$account = $this->merge_accounts($services[$key]->account($account_id)->as_object(), $account);
								}
								$this->_enabled = true;
								$account = new $class((object) $account);
								$services[$key]->account($account);
							}
						}
					}
				}
			}

			wp_cache_set('services', $services, 'social');
		}

		return $services;
	}

	/**
	 * Merges universal with personal account.
	 *
	 * @param  array  $arr1
	 * @param  array  $arr2
	 * @return object
	 */
	private function merge_accounts($arr1, $arr2) {
		$arr1->personal = true;
		return apply_filters('social_merge_accounts', $arr1, $arr2);
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

} // End Social

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
define('SOCIAL_PATH', dirname(__FILE__).'/');

// Register Social's autoloading
spl_autoload_register(array('Social', 'auto_load'));

$social = Social::instance();

// General Actions
add_action('init', array($social, 'init'), 1);
add_action('init', array($social, 'request_handler'), 2);
add_action('admin_init', array($social, 'admin_init'), 1);
add_action('load-settings_page_social', array($social, 'check_system_cron'));
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
add_action('set_user_role', array($social, 'set_user_role'), 10, 2);

// CRON Actions
add_action('social_cron_15_init', array($social, 'cron_15_init'));
add_action('social_cron_60_init', array($social, 'cron_60_init'));
add_action('social_cron_15', array($social, 'run_aggregation'));

// Admin Actions
add_action('admin_menu', array($social, 'admin_menu'));

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

// Service filters
add_filter('social_auto_load_class', array($social, 'auto_load_class'));

// Require Facebook and Twitter by default.
require SOCIAL_PATH.'social-twitter.php';
require SOCIAL_PATH.'social-facebook.php';

} // End class_exists check
