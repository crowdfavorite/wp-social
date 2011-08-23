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
	 * @var string $api_url URL of the API
	 */
	public static $api_url = 'https://sopresto.mailchimp.com/';

	/**
	 * @var string $version version number
	 */
	public static $version = '1.0.1';

	/**
	 * @var string $i18n internationalization key
	 */
	public static $i18n = 'social';

	/**
	 * @var Social_Log $log logger
	 */
	public static $log = null;

	/**
	 * @var array default options
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
	 * Runs basic installation checks to make sure Social can run.
	 *
	 * @static
	 * @return void
	 */
	public static function install() {
		if (version_compare(PHP_VERSION, '5.2.4', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social requires PHP 5.2.4 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i18n));
		}
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
			$value = apply_filters('social_get_option', $value, $key);
			Social::$options[$key] = $value;

			return $value;
		}

		$value = apply_filters('social_set_option', $value, $key);
		Social::$options[$key] = $value;
		if ($update) {
			update_option('social_'.$key, $value);
		}
		return false;
	}

	/**
	 * @var  wpdb  $wpdb  wpdb object
	 */
	public $wpdb = null;

	/**
	 * Sets the WordPress DB instance.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Initializes Social.
	 *
	 * @return void
	 */
	public function init() {
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

		require 'lib/social/log.php';
		Social::$log = Social_Log::instance();

		// Register services
		require 'lib/social/service/twitter.php';
		require 'lib/social/service/facebook.php';
		$services = $this->services_to_load();
		foreach ($services as $service) {
			if (!isset(Social::$services[$service])) {
				$class = 'Social_Service_'.$key;
				Social::$services[$service] = new $class;
			}
		}
	}

	/**
	 * Handlers requests.
	 *
	 * @return void
	 */
	public function request_handler() {
		require 'lib/social/request.php';
		Social_Request::instance()->execute();
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
			basename(__FILE__),
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

	/**
	 *
	 * 
	 * @return void
	 */
	public function aggregate_comments() {
		// TODO Social::aggregate_comments()
	}

	/**
	 * Displays the admin options form.
	 *
	 * @return void
	 */
	public function admin_options_form() {
		// TODO Social::admin_options_form()
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
		if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);

		if ($file == $this_plugin){
			$settings_link = '<a href="'.esc_url(admin_url('options-general.php?page=social.php')).'">'.__("Settings", "photosmash-galleries").'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}

	/**
	 * Hides the Site Admin link for social-based users.
	 *
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
	 * @param  string  $link
	 * @return string
	 */
	public function loginout($link) {
		if (is_user_logged_in()) {
			// TODO Logic to display the disconnect link for social-based users.
		}
		else {
			$link = explode('>'.__('Log in'), $link);
			$link = $link[0].' id="'.Social::$prefix.'login">'.__('Log in').$link[1];
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
			// 1.0.2
			// Find old social_notify and update to _social_notify.
			$meta_keys = array();
			$results = $this->wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key LIKE 'social_%' GROUP BY meta_key");
			foreach ($results as $result) {
				if (!isset($meta_keys[$result->meta_key])) {
					$meta_keys[$result->meta_key] = $result->meta_key;
				}

				if ($result->meta_key == 'social_aggregation_log') {
					$value = maybe_unserialize($result->meta_value);
					$new_value = array(
						'manual' => false,
						'items' => $value
					);
					update_post_meta($result->post_id, 'social_aggregation_log', $new_value);
				}
			}

			if (count($meta_keys)) {
				foreach ($meta_keys as $key) {
					$this->wpdb->query("UPDATE {$this->wpdb->postmeta} SET meta_key = '_$key' WHERE meta_key = '$key'");
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

				$results = $this->wpdb->get_results("SELECT user_id, meta_value FROM {$this->wpdb->usermeta} WHERE meta_key = 'social_accounts' AND user_id NOT IN ($ids)");
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

	/**
	 * Returns a list of services to load.
	 *
	 * @return array
	 */
	private function services_to_load() {
		return apply_filters('social_services_to_load', array());
	}

} // End Social

$social_file = __FILE__;
if (isset($mu_plugin)) {
	$social_file = $mu_plugin;
}
if (isset($network_plugin)) {
	$social_file = $network_plugin;
}
if (isset($plugin)) {
	$social_file = $plugin;
}

define('SOCIAL_FILE', $social_file);
define('SOCIAL_PATH', dirname($social_file).'/');

$social = Social::instance();

// Activation Hook
register_activation_hook(SOCIAL_FILE, array($social, 'install'));
register_deactivation_hook(SOCIAL_FILE, array($social, 'deactivate'));

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

} // End class_exists check
