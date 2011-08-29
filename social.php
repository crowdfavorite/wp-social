<?php
/*
Plugin Name: Social
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Broadcast newly published posts and pull in dicussions using integrations with Twitter and Facebook. Brought to you by <a href="http://mailchimp.com">MailChimp</a>.
Version: 1.0.1
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
	 * @var  string  $api_url  URL of the API
	 */
	public static $api_url = 'https://sopresto.mailchimp.com/';

	/**
	 * @var  string  $version  version number
	 */
	public static $version = '1.0.1';

	/**
	 * @var  string  $i18n  internationalization key
	 */
	public static $i18n = 'social';

	/**
	 * @var  Social_Log  $log  logger
	 */
	public static $log = null;

	/**
	 * @var  array  default options
	 */
	protected static $options = array(
		'debug' => false,
		'install_date' => false,
		'installed_version' => false,
		'broadcast_format' => '{title}: {content} {url}',
		'twitter_anywhere_api_key' => '',
		'system_cron_api_key' => '',
		'system_crons' => '0'
	);

	/**
	 * @var  Social  $instance  instance of Social
	 */
	public static $instance = null;

	/**
	 * @var  array  $services  connected services
	 */
	public static $services = array();

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
	 * @param  string  $class
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
				Social::log(sprintf(__('Failed to auto load class %s.', Social::$i18n), $class));
			}
		}

		return true;
	}

	/**
	 * Activates Facebook and Twitter
	 *
	 * @static
	 * @param  string  $plugin  plugin path
	 * @return void
	 */
	public static function activated_plugin($plugin) {
		if ($plugin == plugin_basename(SOCIAL_FILE)) {
			// Activate Twitter and Facebook
			$plugins = get_option('social_activated_plugins', array(
				'social/social-facebook.php',
				'social/social-twitter.php'
			));
			foreach ($plugins as $plugin) {
				$plugin = plugin_basename($plugin);

				$valid = validate_plugin($plugin);
				if (is_wp_error($valid)) {
					// Skip plugin, something is broken.
					error_log($valid->get_error_message());
					return;
				}

				$network_wide = false;
				if (isset($_GET['networkwide']) and !empty($_GET['networkwide'])) {
					$network_wide = true;
				}

				if (is_multisite() and ($network_wide or is_network_only_plugin($plugin))) {
					$network_wide = true;
					$current = get_site_option('active_sitewide_plugins', array());
				} else {
					$current = get_option('active_plugins', array());
				}

				if (!in_array($plugin, $current)) {
					do_action('activate_plugin', $plugin, $network_wide);
					do_action('activate_'.$plugin, $network_wide);

					if ($network_wide) {
						$current[$plugin] = time();
						update_site_option('active_sitewide_plugins', $current);
					} else {
						$current[] = $plugin;
						sort($current);
						update_option('active_plugins', $current);
					}

					do_action('activated_plugin', $plugin, $network_wide);
				}
			}
		}
	}

	/**
	 * Returns the broadcast format tokens.
	 *
	 * @static
	 * @return array
	 */
	public static function broadcast_tokens() {
		$defaults = array(
			'{url}' => __('Blog post\'s permalink'),
			'{title}' => __('Blog post\'s title'),
			'{content}' => __('Blog post\'s content'),
			'{date}' => __('Blog post\'s date'),
			'{author}' => __('Blog post\'s author'),
		);
		return apply_filters('social_broadcast_tokens', $defaults);
	}

	/**
	 * Sets or gets an option based on the key defined.
	 *
	 * @static
	 * @throws Exception
	 * @param  string  $key     option key
	 * @param  mixed   $value   option value
	 * @param  bool    $update  update option?
	 * @return bool
	 */
	public static function option($key, $value = null, $update = false) {
		if ($value === null) {
			$value = get_option('social_'.$key);
			Social::$options[$key] = $value;

			return $value;
		}

		Social::$options[$key] = $value;
		if ($update) {
			update_option('social_'.$key, $value);
		}
		return false;
	}

	/**
	 * Add a message to the log.
	 *
	 * @static
	 * @param  string  $message  message to add to the log
	 * @return void
	 */
	public static function log($message) {
		Social::$log->write($message);
	}

	/**
	 * Initializes Social.
	 *
	 * @return void
	 */
	public function init() {
		if (version_compare(PHP_VERSION, '5.2.4', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social requires PHP 5.2.4 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i18n));
		}

		// Set the logger
		Social::$log = Social_Log::factory();

		// Register services
		$services = apply_filters('social_register_service', array());
		if (is_array($services) and count($services)) {
			// TODO query for accounts here
			$accounts = array();

			foreach ($services as $service) {
				if (!isset(Social::$services[$service])) {
					$service_accounts = array();
					if (isset($accounts[$service])) {
						$service_accounts = $accounts[$service];
					}

					$class = 'Social_Service_'.$service;
					Social::$services[$service] = new $class($service_accounts);
				}
			}
		}
		
		// Load options
		foreach (Social::$options as $key => $default) {
			$value = Social::option($key);
			if (empty($value) or !$value) {
				switch ($key) {
					case 'install_date':
						$value = current_time('timestamp', 1);
					break;
					case 'installed_version':
						$value = Social::$version;
					break;
					case 'system_cron_api_key':
						$value = wp_generate_password(16, false);
					break;
					default:
						$value = $default;
					break;
				}

				Social::option($key, $value, true);
			}

			// Upgrades
			if ($key == 'installed_version') {
				$this->upgrade($value);
			}
		}

		// JS/CSS
		if (!defined('SOCIAL_COMMENTS_JS')) {
			define('SOCIAL_COMMENTS_JS', plugins_url('assets/social.js', SOCIAL_FILE));
		}

		if (!defined('SOCIAL_ADMIN_JS')) {
			define('SOCIAL_ADMIN_JS', plugins_url('assets/admin.js', SOCIAL_FILE));
		}
		$admin = SOCIAL_ADMIN_JS;

		if (!defined('SOCIAL_ADMIN_CSS')) {
			define('SOCIAL_ADMIN_CSS', plugins_url('assets/admin.css', SOCIAL_FILE));
		}

		if (!defined('SOCIAL_COMMENTS_CSS')) {
			define('SOCIAL_COMMENTS_CSS', plugins_url('assets/comments.css', SOCIAL_FILE));
		}

		if (is_admin()) {
			// JS/CSS
			if (SOCIAL_ADMIN_CSS !== false) {
				wp_enqueue_style('social_admin', SOCIAL_ADMIN_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_ADMIN_JS !== false) {
				wp_enqueue_script('social_admin', SOCIAL_ADMIN_JS, array(), Social::$version, true);
			}
		}
		else {
			// JS/CSS
			if (SOCIAL_COMMENTS_CSS !== false) {
				wp_enqueue_style('social_comments', SOCIAL_COMMENTS_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_COMMENTS_JS !== false) {
				wp_enqueue_script('jquery');
			}
		}

		// JS/CSS
		if (SOCIAL_COMMENTS_JS !== false) {
			wp_enqueue_script('social_js', SOCIAL_COMMENTS_JS, array(), Social::$version, true);
		}
	}

	/**
	 * Activates a plugin on Social.
	 *
	 * @param  string  $file  plugin file
	 * @return void
	 */
// TODO - why do we need this? Can we avoid storing data about these and instead let the presence of the PHP code for the plugin be the indicator?
	public function activate_plugin($file) {
		$file = plugin_basename($file);

		$key = 'social_activated_plugins';
		if (is_multisite()) {
			$key .= '_network';
		}

		$current = get_option($key, array());
		if (!in_array($file, $current)) {
			$current[] = $file;
			update_option($key, $current);
		}
	}

	/**
	 * Deactivates a plugin from Social.
	 *
	 * @param  string  $file  plugin file
	 * @return void
	 */
// TODO - why do we need this? Can we avoid storing data about these and instead let the presence of the PHP code for the plugin be the indicator?
	public function deactivate_plugin($file) {
		$file = plugin_basename($file);

		$key = 'social_activated_plugins';
		if (is_multisite()) {
			$key .= '_network';
		}

		$current = get_option($key, array());
		if (in_array($file, $current)) {
			$_current = array();
			foreach ($current as $plugin) {
				if ($plugin != $file) {
					$_current[] = $file;
				}
			}

			update_option($key, $_current);
		}
	}

	/**
	 * Handlers requests.
	 *
	 * @return void
	 */
	public function request_handler() {
		if (isset($_GET['social_controller']) or isset($_POST['social_controller'])) {
			Social_Request::instance()->execute();
		}
	}

	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__('Social Options', Social::$i18n),
			__('Social', Social::$i18n),
			'manage_options',
			basename(SOCIAL_FILE),
			array($this, 'admin_options_form')
		);
	}

	/**
	 * Adds the 15 minute interval.
	 *
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
	 * Displays the admin options form.
	 *
	 * @return void
	 */
	public function admin_options_form() {
		echo Social_View::factory('wp-admin/options');
	}

	/**
	 * Handles the logic to determine what meta boxes to display.
	 *
	 * @return void
	 */
	public function do_meta_boxes() {
		// TODO Social::do_meta_boxes()
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function save_post() {
		// TODO Social::save_post()
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function publish_post() {
		// TODO Social::publish_post()
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function comment_post() {
		// TODO Social::comment_post()
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function transition_post_status() {
		// TODO Social::transition_post_status()
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function broadcast() {
		// TODO Social::broadcast()
	}

	public function set_broadcasted_meta($post_id, $service, $broadcasted_id, array $broadcasted_accounts) {
		$post_id = (int) $post_id;

		if (is_string($service)) {
			$service = $this->service($service);
		}

		if ($service === false) {
			// Do nothing if an invaid service or account ID was passed in.
			Social::log(sprintf(__('Failed to set broadcasted meta; invalid service key %s.', Social::$i18n), $service));
			return;
		}

		//foreach ($broadcasted_accounts as $)

		// TODO Set post meta
		// - broadcasted_id
		// - broadcasted_accounts
		//
	}

	/**
	 *
	 * 
	 * @return void
	 */
	public function aggregate_comments() {
		// TODO Social::aggregate_comments()
	}

	/**
	 * Add Settings link to plugins - code from GD Star Ratings
	 *
	 * @param  array  $links
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
	 * Hides the Site Admin link for social-based users.
	 *
	 * @filter register
	 * @param  string  $link
	 * @return string
	 */
	public function register($link) {
		if (is_user_logged_in()) {
			// TODO Logic to hide the register link for social-based users.
		}

		return $link;
	}

	/**
	 * Show the disconnect link for social-based users.
	 *
	 * @filter loginout
	 * @param  string  $link
	 * @return string
	 */
	public function loginout($link) {
		if (is_user_logged_in()) {
			// TODO Logic to display the disconnect link for social-based users.
		}
		else {
			$link = explode('>'.__('Log in'), $link);
			$link = $link[0].' id="social_login">'.__('Log in').$link[1];
		}

		return $link;
	}

	/**
	 * Runs the upgrade only if the installed version is older than the current version.
	 *
	 * @param  string  $installed_version
	 * @return void
	 */
	private function upgrade($installed_version) {
		if (version_compare($installed_version, Social::$version, '<')) {
			global $wpdb;

			// 1.0.2
			// Find old social_notify and update to _social_notify.
			$meta_keys = array(
				'social_aggregated_replies',
				'social_broadcast_error',
				'social_broadcast_accounts',
				'social_broadcasted_ids',
				'social_aggregation_log',
				'social_twitter_content',
				'social_notify_twitter',
				'social_facebook_content',
				'social_notify_facebook',
				'social_broadcasted',
				'social_notify'
			);
			if (count($meta_keys)) {
				foreach ($meta_keys as $key) {
					$this->wpdb->query("
						UPDATE $wpdb->postmeta
						   SET meta_key = '_$key'
						 WHERE meta_key = '$key'
					");
				}
			}

			// De-auth Facebook accounts for new permissions.
			if (version_compare($installed_version, '1.0.2', '<')) {
				// Global accounts
				$accounts = get_option('social_accounts', array());
				if (isset($accounts['facebook'])) {
					$accounts['facebook'] = array();
					update_option('social_accounts', $accounts);
				}

				// Personal accounts
				$users = get_users(array('role' => 'subscriber'));
				$ids = array(0);
				if (is_array($users)) {
					foreach ($users as $user) {
						$ids[] = $user->ID;
					}
				}
				$ids = implode(',', $ids);

				$results = $wpdb->get_results("
					SELECT user_id, meta_value 
					  FROM $wpdb->usermeta
					 WHERE meta_key = 'social_accounts'
					   AND user_id NOT IN ($ids)
				");
				foreach ($results as $result) {
					$accounts = maybe_unserialize($result->meta_value);
					if (is_array($accounts) and isset($accounts['facebook'])) {
						$accounts['facebook'] = array();
						update_user_meta($result->user_id, 'social_accounts', $accounts);
						update_user_meta($result->user_id, 'social_1.0.2_upgrade', true);
					}
				}
			}

			Social::option('installed_version', Social::$version, true);
		}
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
$social_path = dirname($social_file);

define('SOCIAL_FILE', $social_file);
define('SOCIAL_PATH', $social_path.'/');

// Register Social's autoloading
spl_autoload_register(array('Social', 'auto_load'));

// Activation Hook
add_action('activated_plugin', array('Social', 'activated_plugin'));

$social = Social::instance();

// General Actions
add_action('init', array($social, 'init'), 1);
add_action('request_handler', array($social, 'request_handler'), 2);
add_action('do_meta_boxes', array($social, 'do_meta_boxes'));
add_action('save_post', array($social, 'set_broadcast_meta_data'), 10, 2);
add_action('comment_post', array($social, 'comment_post'));
add_action('social_cron_15_core', array($social, 'cron_15_core'));
add_action('social_cron_60_core', array($social, 'cron_60_core'));
add_action('social_cron_15', array($social, 'retry_broadcast_core'));
add_action('social_cron_60', array($social, 'aggregate_comments_core'));
add_action('social_aggregate_comments', array($social, 'aggregate_comments'));
add_action('publish_post', array($social, 'publish_post'));
add_action('show_user_profile', array($social, 'show_user_profile'));
add_action('transition_post_status', array($social, 'transition_post_status'), 10, 3);

// Admin Actions
add_action('admin_menu', array($social, 'admin_menu'));

// Filters
add_filter('redirect_post_location', array($social, 'redirect_post_location'), 10, 2);
add_filter('comments_template', array($social, 'comments_template'));
add_filter('get_avatar_comment_types', array($social, 'get_avatar_comment_types'));
add_filter('get_avatar', array($social, 'get_avatar'), 10, 5);
add_filter('register', array($social, 'register'));
add_filter('loginout', array($social, 'loginout'));
add_filter('cron_schedules', array($social, 'cron_schedules'));
add_filter('plugin_action_links', array($social, 'add_settings_link'), 10, 2);

// Service filters
add_filter('social_auto_load_class', array($social, 'auto_load_class'));

} // End class_exists check
