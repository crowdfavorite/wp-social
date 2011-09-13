<?php
/*
Plugin Name: Social
Plugin URI: http://mailchimp.com/social-plugin-for-wordpress/
Description: Broadcast newly published posts and pull in discussions using integrations with Twitter and Facebook. Brought to you by <a href="http://mailchimp.com">MailChimp</a>.
Version: 1.5
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
	public static $api_url = 'https://sopresto.mailchimp.com/';

	/**
	 * @var  string  version number
	 */
	public static $version = '1.5';

	/**
	 * @var  string  internationalization key
	 */
	public static $i18n = 'social';

	/**
	 * @var  Social_Log  logger
	 */
	public static $log = null;

	/**
	 * @var  string  CRON lock directory.
	 */
	public static $cron_lock_dir = null;

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
		'system_crons' => '0'
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
	 * Returns the broadcast format tokens.
	 *
	 * @static
	 * @return array
	 */
	public static function broadcast_tokens() {
		$defaults = array(
			'{url}' => __('Blog post\'s permalink', Social::$i18n),
			'{title}' => __('Blog post\'s title', Social::$i18n),
			'{content}' => __('Blog post\'s content', Social::$i18n),
			'{date}' => __('Blog post\'s date', Social::$i18n),
			'{author}' => __('Blog post\'s author', Social::$i18n),
		);
		return apply_filters('social_broadcast_tokens', $defaults);
	}

	/**
	 * Returns the comment broadcast format tokens.
	 *
	 * @static
	 * @return mixed
	 */
	public static function comment_broadcast_tokens() {
		$defaults = array(
			'{content}' => __('Comment\'s content', Social::$i18n),
			'{url}' => __('Comment\'s permalink', Social::$i18n),
		);
		return apply_filters('social_comment_broadcast_tokens', $defaults);
	}

	/**
	 * Sets or gets an option based on the key defined.
	 *
	 * @static
	 * @param  string  $key     option key
	 * @param  mixed   $value   option value
	 * @return bool|mixed
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
	 * @return array
	 */
	public function services() {
		return $this->load_services();
	}

	/**
	 * Returns a service by access key.
	 * 
	 * @param  string  $key    service key
	 * @return Social_Service|Social_Service_Twitter|Social_Service_Facebook
	 */
	public function service($key) {
		$services = $this->load_services();

		if (!isset($services[$key])) {
			return false;
		}

		return $services[$key];
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

		// Just activated?
		if (!Social::option('install_date')) {
			Social::option('install_date', current_time('timestamp', 1));
			Social::option('system_cron_api_key', wp_generate_password(16, false));
		}
		
		// Trigger upgrade?
		$this->upgrade(Social::option('installed_version'));
	}

	/**
	 * Enqueues the assets for Social.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// JS/CSS
		if (!defined('SOCIAL_COMMENTS_JS')) {
			define('SOCIAL_COMMENTS_JS', plugins_url('assets/social.js', SOCIAL_FILE));
		}

		if (!is_admin()) {
			if (!defined('SOCIAL_COMMENTS_CSS')) {
				define('SOCIAL_COMMENTS_CSS', plugins_url('assets/comments.css', SOCIAL_FILE));
			}

			// JS/CSS
			if (SOCIAL_COMMENTS_CSS !== false) {
				wp_enqueue_style('social_comments', SOCIAL_COMMENTS_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_COMMENTS_JS !== false) {
				wp_enqueue_script('jquery');
			}
		}
		else {
			if (!defined('SOCIAL_ADMIN_JS')) {
				define('SOCIAL_ADMIN_JS', plugins_url('assets/admin.js', SOCIAL_FILE));
			}

			if (!defined('SOCIAL_ADMIN_CSS')) {
				define('SOCIAL_ADMIN_CSS', plugins_url('assets/admin.css', SOCIAL_FILE));
			}

			if (SOCIAL_ADMIN_CSS !== false) {
				wp_enqueue_style('social_admin', SOCIAL_ADMIN_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_ADMIN_JS !== false) {
				wp_enqueue_script('social_admin', SOCIAL_ADMIN_JS, array(), Social::$version, true);
			}
		}

		// JS/CSS
		if (SOCIAL_COMMENTS_JS !== false) {
			wp_enqueue_script('social_js', SOCIAL_COMMENTS_JS, array('jquery'), Social::$version, true);
		}
	}

	/**
	 * Loads the services on every page if the user is an admin.
	 *
	 * @return void
	 */
	public function admin_init() {
		if (current_user_can('manage_options') or current_user_can('publish_posts')) {
			$this->load_services();
		}
	}

	/**
	 * Checks to see if system crons are disabled.
	 *
	 * @return void
	 */
	public function check_system_cron() {
		// Schedule CRONs
		if (Social::option('system_crons') != '1') {
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
	 * Handles the display of different messages for admin notices.
	 *
	 * @action admin_notices
	 */
	public function admin_notices() {
		if (current_user_can('manage_options') or current_user_can('publish_posts')) {
			if (!$this->_enabled) {
				$message = sprintf(__('Social will not run until you update your <a href="%s">settings</a>.', Social::$i18n), esc_url(Social_Helper::settings_url()));
				echo '<div class="error"><p>'.$message.'</p></div>';
			}

			if (isset($_GET['page']) and $_GET['page'] == basename(SOCIAL_FILE)) {
				// CRON Lock
				if (Social::option('cron_lock_error') !== null) {
					$upload_dir = wp_upload_dir();
					if (isset($upload_dir['basedir'])) {
						$message = sprintf(__('Social requires that either %s or %s be writable for CRON jobs.', Social::$i18n), SOCIAL_PATH, $upload_dir['basedir']);
					}
					else {
						$message = sprintf(__('Social requires that %s is writable for CRON jobs.', Social::$i18n), SOCIAL_PATH);
					}

					echo '<div class="error"><p>'.esc_html($message).'</p></div>';
				}
			}

			// Log write error
			$error = Social::option('log_write_error');
			if ($error == '1') {
				echo '<div class="error"><p>'.
					 sprintf(__('%s needs to be writable for Social\'s logging. <a href="%" class="social_deauth">[Dismiss]</a>', Social::$i18n), SOCIAL_PATH, esc_url(admin_url('?social_controller=settings&social_action=clear_log_write_error'))).
					 '</p></div>';
			}
		}

		// Deauthed accounts
		$deauthed = Social::option('deauthed');
		if (!empty($deauthed)) {
			foreach ($deauthed as $service => $data) {
				foreach ($data as $id => $message) {
					echo '<div class="error"><p>'.esc_html($message).' <a href="'.esc_url(admin_url('?social_controller=settings&social_action=clear_deauth&id='.$id.'&service='.$service)).'" class="social_deauth">[Dismiss]</a></p></div>';
				}
			}
		}

		// 1.5 Upgrade?
		$upgrade_1_5 = get_user_meta(get_current_user_id(), 'social_1.5_upgrade', true);
		if (!empty($upgrade_1_5)) {
			if (current_user_can('manage_options')) {
				$output = 'Social needs to re-authorize in order to post to Facebook on your behalf. Please reconnect your <a href="%s">global</a> and <a href="%s">personal</a> accounts.';
				$output = sprintf($output, esc_url(Social_Helper::settings_url()), esc_url(admin_url('profile.php#social-networks')));
			}
			else {
				$output = 'Social needs to re-authorize in order to post to Facebook on your behalf. Please reconnect your <a href="%s">personal</a> accounts.';
				$output = sprintf($output, esc_url(admin_url('profile.php#social-networks')));
			}

			$dismiss = sprintf(__('<a href="%s" class="%s">[Dismiss]</a>', Social::$i18n), esc_url(admin_url('?social_controller=settings&social_action=clear_1_5_upgrade')), 'social_deauth');
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
	 * @action do_meta_boxes
	 * @return void
	 */
	public function do_meta_boxes() {
		global $post;

		if ($post !== null) {
			foreach ($this->services() as $service) {
				if (count($service->accounts())) {
					add_meta_box('social_meta_broadcast', __('Social Broadcasting', Social::$i18n), array($this, 'add_meta_box_broadcast'), 'post', 'side', 'high');
					break;
				}
			}

			if ($this->_enabled) {
				if ($post->post_status == 'publish') {
					add_meta_box('social_meta_aggregation_log', __('Social Comments', Social::$i18n), array($this, 'add_meta_box_log'), 'post', 'normal', 'core');
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

		$content = '';
		if (!in_array($post->post_status, array('publish', 'future', 'pending'))) {
			$notify = false;
			if (get_post_meta($post->ID, '_social_notify', true) == '1') {
				$notify = true;
			}

			$content = Social_View::factory('wp-admin/post/meta/broadcast/default', array(
				'post' => $post,
				'notify' => $notify,
			));
		}
		else if (in_array($post->post_status, array('future', 'pending'))) {
			$accounts = get_post_meta($post->ID, '_social_broadcast_accounts', true);
			$content = Social_View::factory('wp-admin/post/meta/broadcast/scheduled', array(
				'post' => $post,
				'services' => $this->services(),
				'accounts' => $accounts
			));
		}
		else if ($post->post_status == 'publish') {
			$ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
			$content = Social_View::factory('wp-admin/post/meta/broadcast/published', array(
				'ids' => $ids,
				'services' => $this->services()
			));
		}

		echo Social_View::factory('wp-admin/post/meta/broadcast/shell', array(
			'post' => $post,
			'content' => $content
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
			$next_run = __('Not Scheduled', Social::$i18n);
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
	 * @param  string  $location  default post-publish location
	 * @param  int     $post_id   post ID
	 * @return string|void
	 */
	public function redirect_post_location($location, $post_id) {
		if ((isset($_POST['social_notify']) and $_POST['social_notify'] == '1') and
		    (isset($_POST['visibility']) and $_POST['visibility'] !== 'private')) {
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
		else if ($new == 'publish') {
			Social_Aggregation_Queue::factory()->add($post->ID)->save();

			// Sends previously saved broadcast information
			if ($old == 'future') {
				Social_Request::factory('broadcast/run')->query(array(
					'post_ID' => $post->ID
				))->execute();
			}
		}
	}

	/**
	 * Sets the broadcasted IDs for the post.
	 *
	 * @param  int     $post_id         post id
	 * @param  string  $service         service key
	 * @param  string  $account         account id
	 * @param  string  $broadcasted_id  broadcasted id
	 * @return void
	 */
	public function add_broadcasted_id($post_id, $service, $account, $broadcasted_id) {
		$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}

		if (!isset($broadcasted_ids[$service])) {
			$broadcasted_ids[$service] = array();
		}

		if (!isset($broadcasted_ids[$service][$account])) {
			$broadcasted_ids[$service][$account] = array();
		}

		if (!in_array($broadcasted_id, $broadcasted_ids[$service][$account])) {
			$broadcasted_ids[$service][$account][] = $broadcasted_id;
			update_post_meta($post_id, '_social_broadcasted_ids', $broadcasted_ids);
		}
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
	 * Sends a request to initialize CRON 15.
	 *
	 * @return void
	 */
	public function cron_15_init() {
		$this->request(site_url('?social_controller=cron&social_action=cron_15'), 'cron_15');
	}

	/**
	 * Sends a request to initialize CRON 60.
	 *
	 * @return void
	 */
	public function cron_60_init() {
		$this->request(site_url('?social_controller=cron&social_action=cron_60'), 'cron_60');
	}

	/**
	 * Runs the aggregation loop.
	 * 
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
	 * @param  int  $post_id
	 * @return void
	 */
	public function delete_post($post_id) {
		Social_Aggregation_Queue::factory()->remove($post_id);
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
			$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
			if ($commenter === '1') {
				return '';
			}
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
			$commenter = get_user_meta(get_current_user_id(), 'social_commenter', true);
			if ($commenter === '1') {
				foreach ($this->services() as $key => $service) {
					$account = reset($service->accounts());
					if ($account) {
						return $service->disconnect_url($account);
					}
				}
			}
		}
		else {
			$link = explode('>' . __('Log in'), $link);
			$link = $link[0] . ' id="social_login">' . __('Log in') . $link[1];
		}

		return $link;
	}

	/**
	 * Overrides the default WordPress comments_template function.
	 *
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
	 * @param  array  $types  default WordPress types
	 * @return array
	 */
	public function get_avatar_comment_types($types) {
		$types = array_merge($types, array(
			'wordpress',
			'twitter',
			'facebook',
		));
		return $types;
	}

	/**
	 * Gets the avatar based on the comment type.
	 *
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
			$service = $this->service($comment->comment_type);
			if ($service !== false) {
				$image = get_comment_meta($comment->comment_ID, 'social_profile_image_url', true);
			}
		}
		else if ((is_string($comment) or is_int($comment)) and $default != 'force-wordpress') {
			foreach ($this->services() as $key => $service) {
				if (count($service->accounts())) {
					$account = reset($service->accounts());
					$image = $account->avatar();
				}
			}
		}

		if ($image !== null) {
			$type = '';
			if (is_object($comment)) {
				$type = $comment->comment_type;
			}
			return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$type}' height='{$size}' width='{$size}' />";
		}

		return $avatar;
	}

	/**
	 * Sets the comment type upon being saved.
	 *
	 * @param  int  $comment_ID
	 */
	public function comment_post($comment_ID) {
		global $wpdb;

		$comment = get_comment($comment_ID);
		
		$type = false;
		$services = $this->services();
		if (!empty($services)) {
			$account_id = $_POST['social_post_account'];
			foreach ($services as $key => $service) {
				$output = $service->format_comment_content($comment, Social::option('comment_broadcast_format'));
				foreach ($service->accounts() as $account) {
					if ($account_id == $account->id()) {
						if (isset($_POST['post_to_service'])) {
							$id = $service->broadcast($account, $output)->id();
							if ($id === false) {
								wp_delete_comment($comment_ID);
								wp_die(sprintf(__('Error: Failed to post your comment to %s, please go back and try again.', Social::$i18n), $service->title()));
							}
							update_comment_meta($comment_ID, 'social_status_id', $id);
						}

						update_comment_meta($comment_ID, 'social_account_id', $account_id);
						update_comment_meta($comment_ID, 'social_profile_image_url', $account->avatar());
						update_comment_meta($comment_ID, 'social_comment_type', $service->key());

						if ($comment->user_id != '0') {
							$comment->comment_author = $account->name();
							$comment->comment_author_url = $account->url();
							wp_update_comment(get_object_vars($comment));
						}
						break;
					}
				}

				if ($type !== false) {
					break;
				}
			}
		}
	}

	/**
	 * Displays a comment.
	 *
	 * @param  object  $comment  comment object
	 * @param  array   $args
	 * @param  int     $depth
	 */
	public function comment($comment, $args, $depth) {
		$comment_type = get_comment_meta($comment->comment_ID, 'social_comment_type', true);
		if (empty($comment_type)) {
			$comment_type = (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type);
		}
		$comment->comment_type = $comment_type;
		$GLOBALS['comment'] = $comment;

		$status_url = null;
		$service = null;
		if (!in_array($comment_type, apply_filters('social_ignored_comment_types', array('wordpress', 'pingback')))) {
			$service = $this->service($comment->comment_type);
			if ($service !== false and $service->show_full_comment($comment->comment_type)) {
				$status_id = get_comment_meta($comment->comment_ID, 'social_status_id', true);
				if (!empty($status_id)) {
					$status_url = $service->status_url(get_comment_author(), $status_id);
				}

				if ($status_url === null) {
					$comment_type = 'wordpress';
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
		));
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
			global $wpdb;

			$upgrades = array(
				SOCIAL_PATH.'upgrades/1.5.php',
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
	 * Removes an account from the XMLRPC accounts.
	 *
	 * @param  string  $service
	 * @param  int     $id
	 * @return void
	 */
	public function remove_from_xmlrpc($service, $id) {
		// Remove from the XML-RPC
		$xmlrpc = Social::option('xmlrpc_accounts');
		if (!empty($xmlrpc) and isset($xmlrpc[$service])) {
			$ids = array_values($xmlrpc[$service]);
			if (in_array($id, $ids)) {
				$_ids = array();
				foreach ($ids as $id) {
					if ($id != $_GET['id']) {
						$_ids[] = $id;
					}
				}
				$xmlrpc[$_GET['service']] = $_ids;
				update_option('social_xmlrpc_accounts', $xmlrpc);
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
			if (is_object($val)) {
				$_object->$key = $this->kses($val);
			}
			else if (is_array($val)) {
				$_object[$key] = $this->kses($val);
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
				$accounts = Social::option('accounts');
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
							foreach ($_accounts as $account) {
								$account = new $class($account);
								if (!$services[$key]->account_exists($account->id())) {
									$services[$key]->account($account);
								}

								$services[$key]->account($account->id())->personal(true);
							}
						}
					}
				}
			}

			wp_cache_set('services', $services, 'social');
		}

		return $services;
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

$social = Social::instance();

// General Actions
add_action('init', array($social, 'init'), 1);
add_action('init', array($social, 'request_handler'), 2);
add_action('admin_init', array($social, 'admin_init'), 1);
add_action('load-settings_page_social', array($social, 'check_system_cron'));
add_action('load-'.basename(SOCIAL_FILE), array($social, 'check_system_cron'));
add_action('comment_post', array($social, 'comment_post'));
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

// CRON Actions
add_action('social_cron_15_init', array($social, 'cron_15_init'));
add_action('social_cron_60_init', array($social, 'cron_60_init'));
add_action('social_cron_15', array($social, 'run_aggregation'));

// Admin Actions
add_action('admin_menu', array($social, 'admin_menu'));

// Filters
add_filter('cron_schedules', array($social, 'cron_schedules'));
add_filter('plugin_action_links', array($social, 'add_settings_link'), 10, 2);
add_filter('redirect_post_location', array($social, 'redirect_post_location'), 10, 2);
add_filter('comments_template', array($social, 'comments_template'));
add_filter('get_avatar_comment_types', array($social, 'get_avatar_comment_types'));
add_filter('get_avatar', array($social, 'get_avatar'), 10, 5);
add_filter('register', array($social, 'register'));

// Service filters
add_filter('social_auto_load_class', array($social, 'auto_load_class'));

// Require Facebook and Twitter by default.
require SOCIAL_PATH.'social-twitter.php';
require SOCIAL_PATH.'social-facebook.php';

} // End class_exists check
