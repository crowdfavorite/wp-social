<?php
/*
Plugin Name: Social
Plugin URI: http://mailchimp.com/
Description: Broadcast newly published posts and pull in dicussions using integrations with Twitter and Facebook. Brought to you by MailChimp.
Version: 1.0
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
	 * @var  string  $plugins_url  Path to the Social Plugin's directory.  Set in constructor
	 */
	public static $plugins_url = '';

	/**
	 * @var  string  $version  plugin version
	 */
	public static $version = '1.0';

	/**
	 * @var  string  $prefix  prefix used to identify the plugin
	 */
	public static $prefix = 'social_';

	/**
	 * @var  string  domain to use for i10n
	 */
	public static $i18n = 'social';

	/**
	 * @var  array  services registered to Social
	 */
	public static $services = array();

	/**
	 * @var  array  global services registered to Social
	 */
	public static $global_services = array();

	/**
	 * @var  array  combined services
	 */
	public static $combined_services = array();

	/**
	 * @var  array  commenter user accounts
	 */
	public static $commenters = array();

	/**
	 * @var  array  default options
	 */
	protected static $options = array(
		'debug' => 'false',
		'install_date' => false,
		'installed_version' => false,
		'broadcast_format' => '{title}: {content} {url}',
		'twitter_anywhere_api_key' => '',
		'system_cron_api_key' => '',
		'system_crons' => '0'
	);

	/**
	 * @var  bool  update plugin settings
	 */
	protected static $update = true;

	/**
	 * @var  bool  upgrade the plugin
	 */
	protected static $upgrade = false;

	/**
	 * @var  string  directory for the cron lock files
	 */
	private $cron_lock_dir = false;

	/**
	 * @var  bool  broadcast meta set
	 */
	private $broadcast_meta_set = false;

	/**
	 * Used to log debug data.
	 *
	 * @static
	 * @param  string  $content
	 * @return void
	 */
	public static function debug($content) {
		$debug = get_option(Social::$prefix . 'debug', false);
		if ($debug) {
			$fh = fopen(Social::$plugins_url . 'log.txt', 'a');
			fwrite($fh, $content . "\n");
			fclose($fh);
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
		return apply_filters(Social::$prefix . 'broadcast_tokens', $defaults);
	}

	/**
	 * Returns the service object.
	 *
	 * @static
	 * @param  string  $service  name of the service
	 * @param  int     $user_id  custom user to load
	 * @param  bool    $global   global service?
	 * @return Social_Facebook|Social_Twitter|bool
	 */
	public function service($service, $user_id = null, $global = false) {
		if ($user_id !== null) {
			if (!isset(Social::$services[$service])) {
				return false;
			}

			if (!isset(Social::$commenters[$user_id])) {
				$class = get_class(Social::$services[$service]);
				Social::$commenters[$user_id] = new $class($user_id);
			}

			return Social::$commenters[$user_id];
		}

		if (!$global and isset(Social::$services[$service])) {
			return Social::$services[$service];
		}
		else if ($global and isset(Social::$global_services[$service])) {
			return Social::$global_services[$service];
		}

		return false;
	}

	/**
	 * Sets and returns the option.
	 *
	 * @static
	 * @param  string  $key     option key
	 * @param  string  $value   option value
	 * @param  bool    $update  update system option?
	 * @return array|void
	 */
	public function option($key, $value = null, $update = false) {
		if ($value === null) {
			return (isset(Social::$options[$key]) ? Social::$options[$key] : null);
		}

		Social::$options[$key] = $value;

		if ($update) {
			update_option(Social::$prefix . $key, $value);
		}
	}

	/**
	 * Sets the group of options
	 *
	 * @static
	 * @param  array  $options
	 * @return array|void
	 */
	public function options($options = null) {
		if ($options === null) {
			return Social::$options;
		}

		Social::$options = $options;
	}

	/**
	 * @return void
	 */
	public function install() {
		if (version_compare(PHP_VERSION, '5.2.1', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social requires PHP 5.2.1 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i18n));
		}
	}

	/**
	 * Remove the CRON unpon plugin deactivation.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook(Social::$prefix . 'aggregate_comments');
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
	 * Initializes the plugin.
	 */
	public function init() {
		$url = plugins_url('', SOCIAL_FILE);
		Social::$plugins_url = trailingslashit(apply_filters('social_plugins_url', $url));

		$global_services = get_option(Social::$prefix . 'accounts');
		$services = get_user_meta(get_current_user_id(), Social::$prefix . 'accounts', true);

		if (!empty($global_services)) {
			foreach ($global_services as $service => $accounts) {
				if (!empty($accounts)) {
					Social::$update = false;
					break;
				}
			}
		}

		if (Social::$update and !empty($services)) {
			foreach ($services as $service => $accounts) {
				if (!empty($accounts)) {
					Social::$update = false;
					break;
				}
			}
		}

		if (!defined('SOCIAL_COMMENTS_JS')) {
			define('SOCIAL_COMMENTS_JS', Social::$plugins_url . 'assets/social.js');
		}

		if (!defined('SOCIAL_ADMIN_JS')) {
			define('SOCIAL_ADMIN_JS', Social::$plugins_url . 'assets/admin.js');
		}

		if (!defined('SOCIAL_ADMIN_CSS')) {
			define('SOCIAL_ADMIN_CSS', Social::$plugins_url . 'assets/admin.css');
		}

		if (!defined('SOCIAL_COMMENTS_CSS')) {
			define('SOCIAL_COMMENTS_CSS', Social::$plugins_url . 'assets/comments.css');
		}

		if (is_admin()) {
			if (Social::$update) {
				add_action('admin_notices', array($this, 'display_upgrade'));
			}

			// Deauthed accounts?
			$deauthed = get_option(Social::$prefix . 'deauthed', array());
			if (count($deauthed)) {
				add_action('admin_notices', array($this, 'display_deauthed'));
			}

			if (SOCIAL_ADMIN_CSS !== false) {
				wp_enqueue_style('social_admin', SOCIAL_ADMIN_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_ADMIN_JS !== false) {
				wp_enqueue_script('social_admin', SOCIAL_ADMIN_JS, array(), Social::$version, true);
			}

			// CRON Lock Location
			if (is_writable(SOCIAL_PATH)) {
				$this->cron_lock_dir = SOCIAL_PATH;
			}
			else {
				$upload_dir = wp_upload_dir();
				if (is_writable($upload_dir['basedir'])) {
					$this->cron_lock_dir = $upload_dir['basedir'];
				}
				else if ($_GET['page'] == 'social.php') {
					add_action('admin_notices', array($this, 'display_cron_lock_write_error'));
				}
			}

			// Schedule the CRON?
			if (isset($_GET['page']) and $_GET['page'] == 'social.php') {
				if (Social::option('system_crons') != '1') {
					if (wp_next_scheduled(Social::$prefix . 'cron_15_core') === false) {
						wp_schedule_event(time() + 900, 'every15min', Social::$prefix . 'cron_15_core');
						$this->log(basename(__FILE__).'.'.__LINE__.'.'.$_SERVER['REQUEST_URI'].'.scheduling CRON 15');
					}
					if (wp_next_scheduled(Social::$prefix . 'cron_60_core') === false) {
						wp_schedule_event(time() + 3600, 'hourly', Social::$prefix . 'cron_60_core');
						$this->log(basename(__FILE__).'.'.__LINE__.'.'.$_SERVER['REQUEST_URI'].'.scheduling CRON 60');
					}
				}

				$url = str_replace('&amp;', '&', wp_nonce_url(site_url('?' . Social::$prefix . 'action=check_crons')));
				wp_remote_get($url, array(
					'timeout' => 0.01,
					'blocking' => false,
					'sslverify' => apply_filters('https_local_ssl_verify', true)
				));
			}

			// Fix for the helper
			if (!defined('IS_PROFILE_PAGE')) {
				define('IS_PROFILE_PAGE', false);
			}
		}
		else {
			if (SOCIAL_COMMENTS_CSS !== false) {
				wp_enqueue_style('social_comments', SOCIAL_COMMENTS_CSS, array(), Social::$version, 'screen');
			}

			if (SOCIAL_COMMENTS_JS !== false) {
				wp_enqueue_script('jquery');
			}
		}

		if (SOCIAL_COMMENTS_JS !== false) {
			wp_enqueue_script('social_js', SOCIAL_COMMENTS_JS, array(), Social::$version, true);
		}

		if (version_compare(PHP_VERSION, '5.2.1', '<')) {
			wp_die(__("Sorry, Social requires PHP 5.2.1 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i18n));
		}

		// Load Twitter/Facebook
		require SOCIAL_PATH . 'lib/social/interface/service.php';
		require SOCIAL_PATH . 'lib/social/helper.php';
		require SOCIAL_PATH . 'lib/social/service.php';
		require SOCIAL_PATH . 'social-facebook.php';
		require SOCIAL_PATH . 'social-twitter.php';

		// Load the settings
		$options = apply_filters(Social::$prefix . 'options', $this->options());
		foreach ($options as $key => $default) {
			$value = get_option(Social::$prefix . $key, $default);
			if (empty($value)) {
				switch ($key) {
					case 'install_date':
						$value = time();
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

				update_option(Social::$prefix . $key, $value);
			}

			if ($key == 'installed_version' and (int)$value < (int)Social::$version) {
				// Need to run an upgrade
				Social::$upgrade = true;
			}

			$this->option($key, $value);
		}

		// Register the Social services
		Social::$services = apply_filters(Social::$prefix . 'register_service', Social::$services);
		Social::$global_services = apply_filters(Social::$prefix . 'register_service', Social::$global_services);

		// Load the user's accounts
		if (!empty($services)) {
			foreach ($services as $service => $accounts) {
				if (!isset(Social::$services[$service]) or empty($accounts)) {
					continue;
				}
				$this->service($service)->accounts($accounts);
			}
		}

		// Load the global accounts
		if (!empty($global_services)) {
			foreach ($global_services as $service => $accounts) {
				if (!isset(Social::$global_services[$service]) or empty($accounts)) {
					continue;
				}
				$this->service($service, null, true)->accounts($accounts);
			}
		}

		// Cache the global and user accounts.
		$this->services();
	}

	/**
	 * Displays the upgrade message.
	 */
	public function display_upgrade() {
		if (current_user_can('manage_options') || current_user_can('publish_posts')) {
			if (current_user_can('manage_options')) {
				$url = Social_Helper::settings_url(null, true);
			}
			else {
				$url = admin_url('profile.php#social-networks');
			}
			$message = sprintf(__('To broadcast to Twitter or Facebook, please update your <a href="%s">Social settings</a>.', Social::$i18n), $url);
			echo '<div class="error"><p>' . $message . '</p></div>';
		}
	}

	/**
	 * Displays warnings about deauthed accounts.
	 */
	public function display_deauthed() {
		$deauthed = get_option(Social::$prefix . 'deauthed', array());
		foreach ($deauthed as $service => $data) {
			foreach ($data as $id => $message) {
				echo '<div class="error"><p>' . $message . ' <a href="' . Social_Helper::settings_url(array('clear_deauth' => $id, 'service' => $service)) . '" class="' . Social::$prefix . 'deauth">[Dismiss]</a></p></div>';
			}
		}
	}

	/**
	 * Displays the CRON lock directory error.
	 */
	public function display_cron_lock_write_error() {
		$upload_dir = wp_upload_dir();
		if (isset($upload_dir['basedir'])) {
			$message = sprintf(__('Social requires that either %s or %s be writable for CRON jobs.', Social::$i18n), SOCIAL_PATH, $upload_dir['basedir']);
		}
		else {
			$message = sprintf(__('Social requires that %s is writable for CRON jobs.', Social::$i18n), SOCIAL_PATH);
		}
		echo '<div class="error"><p>' . $message . '</p></div>';
	}

	/**
	 * Handles the request.
	 */
	public function request_handler() {
		if (isset($_GET[Social::$prefix . 'cron'])) {
			$api_key_verified = false;
			if (isset($_GET['api_key'])) {
				if ($_GET['api_key'] == Social::option('system_cron_api_key')) {
					$api_key_verified = true;
				}
			}

			if (!$api_key_verified and !wp_verify_nonce($_GET['_wpnonce'])) {
				wp_die('Oops, please try again.');
			}

			if (get_option(Social::$prefix . 'system_crons', '0') == '0') {
				$schedule = wp_get_schedule($_GET[Social::$prefix . 'cron']);
				$timestamp = wp_next_scheduled($_GET[Social::$prefix . 'cron']);
				if (!$schedule !== false and $timestamp !== false) {
					$this->log(basename(__FILE__).'.'.__LINE__.'.'.$_SERVER['REQUEST_URI'].'.rescheduling CRON '.$_GET[Social::$prefix . 'cron']);
					wp_reschedule_event(time(), $schedule, $_GET[Social::$prefix . 'cron']);
					spawn_cron();
				}
			}
			else {
				$method = $_GET[Social::$prefix . 'cron'] . '_core';
				$this->$method();
			}
		}
		else if (!empty($_POST[Social::$prefix . 'action'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'])) {
				wp_die('Oops, please try again.');
			}

			switch ($_POST[Social::$prefix . 'action']) {
				case 'broadcast_options':
					$this->broadcast_options($_POST['post_ID'], $_POST['location']);
					break;
				case 'settings':
					Social::option('broadcast_format', $_POST[Social::$prefix . 'broadcast_format'], true);
					Social::option('debug', $_POST[Social::$prefix . 'debug'], true);

					// Store the XML-RPC accounts
					if (isset($_POST[Social::$prefix . 'xmlrpc_accounts'])) {
						$accounts = array();
						foreach ($_POST[Social::$prefix . 'xmlrpc_accounts'] as $account) {
							$account = explode('|', $account);
							$accounts[$account[0]][] = $account[1];
						}
						Social::option('xmlrpc_accounts', $accounts, true);
					}
					else {
						delete_option(Social::$prefix . 'xmlrpc_accounts');
					}

					// Anywhere key
					if (isset($_POST[Social::$prefix . 'twitter_anywhere_api_key'])) {
						Social::option('twitter_anywhere_api_key', $_POST[Social::$prefix . 'twitter_anywhere_api_key'], true);
					}

					// System CRON
					if (isset($_POST[Social::$prefix . 'system_crons'])) {
						Social::option('system_crons', $_POST[Social::$prefix . 'system_crons'], true);

						// Unschedule the CRONs
						if (($timestamp = wp_next_scheduled(Social::$prefix . 'cron_15_core')) !== false) {
							wp_unschedule_event($timestamp, Social::$prefix . 'cron_15_core');
						}
						if (($timestamp = wp_next_scheduled(Social::$prefix . 'cron_60_core')) !== false) {
							wp_unschedule_event($timestamp, Social::$prefix . 'cron_60_core');
						}
					}

					wp_redirect(Social_Helper::settings_url(array('saved' => 'true')));
					exit;
					break;
			}
		}
		else if (!empty($_GET[Social::$prefix . 'action'])) {
			$api_key_verified = false;
			if (isset($_GET['api_key'])) {
				if ($_GET['api_key'] == Social::option('system_cron_api_key')) {
					$api_key_verified = true;
				}
			}
			switch ($_GET[Social::$prefix . 'action']) {
				case 'regenerate_api_key':
					if (!wp_verify_nonce($_GET['_wpnonce'], 'regenerate_api_key')) {
						wp_die('Oops, please try again.');
					}

					$key = wp_generate_password(16, false);
					Social::option('system_cron_api_key', $key, true);
					echo $key;
					exit;
				break;
				case 'reload_form':
					if (!is_user_logged_in()) {
						echo json_encode(array(
							'result' => 'error',
							'html' => 'not logged in',
						));
					}
					else {
						$form = Social_Comment_Form::as_html(array(), $_GET['post_id'], false);
						echo json_encode(array(
							'result' => 'success',
							'html' => $form,
							'disconnect_url' => wp_loginout('', false)
						));
					}
					exit;
				break;
				case 'cron_15':
					if (!$api_key_verified and !wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}

					if ($this->cron_lock('cron_15')) {
						do_action(Social::$prefix . 'cron_15');
					}
					$this->cron_unlock('cron_15');
				break;
				case 'cron_60':
					if (!$api_key_verified and !wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}

					if ($this->cron_lock('cron_60')) {
						do_action(Social::$prefix . 'cron_60');
					}
					$this->cron_unlock('cron_15');
				break;
				case 'aggregate_comments':
					if (!$api_key_verified) {
						wp_die('Oops, please try again.');
					}

					do_action(Social::$prefix . 'aggregate_comments');
				break;
				case 'retry_broadcast':
					if (!$api_key_verified and !wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}
					$this->retry_broadcast();
				break;
				case 'run_aggregation':
					if (!wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}

					if (Social::option('debug') == '1') {
						$this->log('run_aggregation called manually.');
					}

					$this->run_aggregation($_GET['post_id']);

					echo Social_Aggregate_Log::logs($_GET['post_id']);
					exit;
				break;
				case 'import_from_url':
					if (!wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}

					if (Social::option('debug') == '1') {
						$this->log('import_from_url called manually.');
					}

					$services = $this->services();
					if (isset($services['twitter']) and !empty($_GET['url'])) {
						$services['twitter']->import_tweet($_GET['post_id'], $_GET['url']);
					}

					echo Social_Aggregate_Log::logs($_GET['post_id']);
					exit;
				break;
				case 'check_crons':
					if (!wp_verify_nonce($_GET['_wpnonce'])) {
						wp_die('Oops, please try again.');
					}

					$crons = _get_cron_array();
					$cron_60 = false;
					$cron_15 = false;
					foreach ($crons as $timestamp => $_crons) {
						foreach ($_crons as $key => $cron) {
							if ($key == Social::$prefix . 'cron_15_core') {
								if ($cron_15) {
									wp_unschedule_event($timestamp, Social::$prefix . 'cron_15_core');
								}
								else {
									$cron_15 = true;
								}
							}
							else if ($key == Social::$prefix . 'cron_60_core') {
								if ($cron_60) {
									wp_unschedule_event($timestamp, Social::$prefix . 'cron_60_core');
								}
								else {
									$cron_60 = true;
								}
							}
						}
					}
				break;
			}
		}
		// Authorization complete?
		else if (isset($_POST['data'])) {
			$data = stripslashes($_POST['data']);
			if (strpos($data, "\r") !== false) {
				$data = str_replace(array("\r\n", "\r"), "\n", $data);
			}
			$data = json_decode($data);
			$account = (object)array(
				'keys' => $data->keys,
				'user' => $data->user
			);

			// Add the account to the service.
			if (IS_PROFILE_PAGE) {
				$service = $this->service($data->service)->account($account);
			}
			else {
				$service = $this->service($data->service, null, true)->account($account);
			}

			// Do we need to create a user?
			if (!$service->loaded()) {
				$service->create_user($account);
			}

			// Save the services
			$service->save($account);

			// Remove the service from the errors?
			$deauthed = get_option(Social::$prefix . 'deauthed');
			if (isset($deauthed[$service->service][$account->user->id])) {
				unset($deauthed[$service->service][$account->user->id]);
				update_option(Social::$prefix . 'deauthed', $deauthed);

				// Remove from the global broadcast content as well.
				$this->remove_from_xmlrpc($service->service, $account->user->id);
			}
?>
<html>
<head>
	<title>Authorized</title>
	<?php wp_enqueue_script('jquery'); ?>
	<?php wp_head(); ?>
</head>
<script type="text/javascript">
	jQuery(function() {
		window.close();
	});
</script>
</html>
<?php
	exit;
		}
		else if (isset($_GET[Social::$prefix . 'disconnect'])) {
			if (IS_PROFILE_PAGE) {
				$service = $this->service($_GET['service']);
			}
			else {
				$service = Social::$global_services[$_GET['service']];
				$this->remove_from_xmlrpc($_GET['service'], $_GET['id']);
			}
			$service->disconnect($_GET['id']);

			if (is_admin()) {
				wp_redirect(Social_Helper::settings_url());
			}
			else {
				wp_logout();
				wp_redirect($_GET['redirect_to']);
			}
			exit;
		}
		else if (isset($_GET['clear_deauth'])) {
			$id = $_GET['clear_deauth'];
			$service = $_GET['service'];
			$deauthed = get_option(Social::$prefix . 'deauthed', array());
			if (isset($deauthed[$service][$id])) {
				unset($deauthed[$service][$id]);
				update_option(Social::$prefix . 'deauthed', $deauthed);

				$this->remove_from_xmlrpc($service, $id);
			}
		}
		else if (isset($_GET['post']) and isset($_GET['action']) and $_GET['action'] == 'edit') {
			$error = get_post_meta($_GET['post'], Social::$prefix . 'broadcast_error', true);
			if ($error === 'true') {
				add_action('admin_notices', array($this, 'display_failed_broadcast'));

				delete_post_meta($_GET['post'], Social::$prefix . 'broadcast_error');
			}
		}
	}

	/**
	 * Removes an account from the XML-RPC stack.
	 *
	 * @param  string  $service
	 * @param  int     $service_id
	 * @return void
	 */
	private function remove_from_xmlrpc($service, $service_id) {
		// Remove from the XML-RPC
		$xmlrpc = get_option(Social::$prefix . 'xmlrpc_accounts', array());
		if (isset($xmlrpc[$service])) {
			$ids = array_values($xmlrpc[$service]);
			if (in_array($service_id, $ids)) {
				$_ids = array();
				foreach ($ids as $id) {
					if ($id != $_GET['id']) {
						$_ids[] = $id;
					}
				}
				$xmlrpc[$_GET['service']] = $_ids;
				update_option(Social::$prefix . 'xmlrpc_accounts', $xmlrpc);
			}
		}
	}

	/**
	 * Handles the future to publish transition.
	 *
	 * @param  object  $post
	 * @return void
	 */
	public function publish_post($post) {
		$this->broadcast($post);
	}

	/**
	 * Add Meta Boxes
	 */
	public function do_meta_boxes() {
		global $post;

		if (!Social::$update and $post !== null) {
			foreach ($this->services() as $service) {
				if (count($service->accounts())) {
					add_meta_box(Social::$prefix . 'meta_broadcast', __('Social Broadcasting', Social::$i18n), array($this, 'add_meta_box'), 'post', 'side', 'core');
					break;
				}
			}

			if ($post->post_status == 'publish') {
				add_meta_box(Social::$prefix . 'meta_aggregation_log', __('Social Comments', Social::$i18n), array($this, 'add_meta_log_box'), 'post', 'normal', 'core');
			}
		}
	}

	/**
	 * Adds the broadcasting meta box.
	 */
	public function add_meta_box() {
		global $post;

		$services = $this->services();
		$broadcasted_ids = get_post_meta($post->ID, Social::$prefix . 'broadcasted_ids', true);
?>
<div class="social-meta-box" style="padding-top:6px;">
<?php
		if ($post->post_status !== 'publish' or empty($broadcasted_ids))
		{
?>
	<h4 class="mar-top-none"><?php _e('Broadcast Post', Social::$i18n); ?></h4>
	<p>Would you like to broadcast this post?</p>
	<p>
		<input type="radio" name="<?php echo Social::$prefix . 'notify'; ?>" id="<?php echo Social::$prefix . 'notify_yes'; ?>" class="social-toggle" value="1"/>
		<label for="<?php echo Social::$prefix . 'notify_yes'; ?>" class="social-toggle-label"><?php _e('Yes', Social::$i18n); ?></label>

		<input type="radio" name="<?php echo Social::$prefix . 'notify'; ?>" id="<?php echo Social::$prefix . 'notify_no'; ?>" class="social-toggle" value="0" checked="checked"/>
		<label for="<?php echo Social::$prefix . 'notify_no'; ?>" class="social-toggle-label"><?php _e('No', Social::$i18n); ?></label>
	</p>
<?php
		}
		else {
			$header_shown = false;
			foreach ($services as $key => $service) {
				if (isset($broadcasted_ids[$key]) and count($broadcasted_ids[$key])) {
					$accounts = $service->accounts();
					if (!$header_shown) {
						$header_shown = true;
?>
	<p class="mar-top-none">This post has been broadcasted to the following accounts. You may broadcast to more accounts by clicking on the "Broadcast" button below.</p>
	<input type="hidden" name="<?php echo Social::$prefix . 'notify'; ?>" value="1"/>
<?php
					}

					$accounts_output = '';
					foreach ($broadcasted_ids[$key] as $user_id => $broadcast_id) {
						if (isset($accounts[$user_id])) {
							if (empty($accounts_output)) {
								$accounts_output = '<h4>' . $service->title . '</h4><ul style="margin:0 0 25px 0;">';
							}

							$username = '';
							if (isset($accounts[$user_id]->user->screen_name)) {
								$username = $accounts[$user_id]->user->screen_name;
							}

							$accounts_output .= '<li style="clear:both;">';
							$accounts_output .= '<img src="' . $service->profile_avatar($accounts[$user_id]) . '" width="24" height="24" style="float:left;" />';
							$accounts_output .= '<span style="position:relative;top:5px;left:5px;">';
							$accounts_output .= $service->profile_name($accounts[$user_id]);
							$accounts_output .= ' <a href="' . $service->status_url($username, $broadcast_id) . '" target="_blank">View</a>';
							$accounts_output .= '</span></li>';
						}
					}

					if (!empty($accounts_output)) {
						$accounts_output .= '</ul>';
						echo $accounts_output;
					}
				}
			}
		}

		if ($post->post_status == 'publish') {
?>
	<p class="submit" style="clear:both;padding:0;margin:20px 0 0;">
		<input type="submit" name="<?php echo Social::$prefix . 'broadcast'; ?>" value="<?php _e('Broadcast', Social::$i18n); ?>"/>
		<a href="<?php echo admin_url('profile.php#social-networks'); ?>" style="float: right; padding-top: 8px;"><?php _e('My Accounts', Social::$i18n); ?></a>
	</p>
<?php
		}
?>
</div>
<?php
	}

	/**
	 * Adds the aggregation log meta box.
	 *
	 * @return void
	 */
	public function add_meta_log_box() {
		global $post;
?>
<div class="social-meta-box-block">
	<h4><?php _e('Add Tweet by URL', Social::$i18n); ?></h4>

	<p><?php _e('Want to add a tweet? Enter the URL of the tweet here and Social will add it as a comment.', Social::$i18n); ?></p>

	<p>
		<input type="text" name="source_url" style="width:350px"/>
		<span class="submit" style="float:none">
			<a href="<?php echo wp_nonce_url(admin_url('?social_action=import_from_url&post_id=' . $post->ID)); ?>" id="import_from_url" class="button"><?php _e('Import Tweet', Social::$i18n); ?></a>
		</span>
		<img src="<?php echo admin_url('images/loading.gif'); ?>" style="position:relative;top:4px;left:0;display:none" id="import_from_url_loader"/>
	</p>
</div><!-- .social-meta-box-block -->

<div class="social-meta-box-block cf-clearfix">
	<h4><?php _e('Manual Refresh', Social::$i18n); ?></h4>

	<p><?php _e('Manually run the comment aggregation and Social will look for mentions of this post on Facebook and Twitter.', Social::$i18n); ?></p>

	<p class="submit" style="clear:both;float:none;padding:0;">
		<a href="<?php echo wp_nonce_url(admin_url('?social_action=run_aggregation&post_id=' . $post->ID)); ?>" id="run_aggregation" class="button" style="float:left;margin-bottom:10px;"><?php _e('Find Social Comments', Social::$i18n); ?></a>
		<img src="<?php echo admin_url('images/loading.gif'); ?>" style="float:left;position:relative;top:4px;left:5px;display:none;" id="run_aggregation_loader"/>
	</p>
</div><!-- .social-meta-box-block -->

<div class="social-meta-box-block">
	<h4><?php _e('Log', Social::$i18n); ?></h4>

	<div id="aggregation_log">
		<?php echo Social_Aggregate_Log::logs($post->ID); ?>
	</div>
</div><!-- .social-meta-box-block -->
<?php
	}

	/**
	 * Show the broadcast options if publishing.
	 *
	 * @param  string  $location  default post-publish location
	 * @param  int     $post_id   post ID
	 * @return string|void
	 */
	public function redirect_post_location($location, $post_id) {
		if (isset($_POST[Social::$prefix . 'notify']) or isset($_POST[Social::$prefix . 'broadcast'])) {
			$this->broadcast_options($post_id, $location);
		}
		return $location;
	}

	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
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
	 * Displays the option form for the WP-Admin user.
	 */
	public function admin_options_form() {
?>
<form id="setup" method="post" action="<?php echo admin_url(); ?>">
	<?php wp_nonce_field(); ?>
	<input type="hidden" name="<?php echo Social::$prefix; ?>action" value="settings"/>
	<?php if (isset($_GET['saved'])): ?>
	<div id="message" class="updated">
		<p><strong><?php _e('Social settings have been updated.', Social::$i18n); ?></strong></p>
	</div>
	<?php endif; ?>
	<div class="wrap" id="social_options_page">
		<h2><?php _e('Social Options', Social::$i18n); ?></h2>

		<h3 id="social-networks"><?php _e('Connect to Social Networks', Social::$i18n); ?></h3>
		<p><?php _e('Before blog authors can broadcast to social networks you need to connect some accounts. <strong>These accounts will be accessible by every blog author.</strong>', Social::$i18n); ?></p>
		<?php
			$have_accounts = false;
			foreach (Social::$global_services as $key => $service):
		?>
		<div class="social-settings-connect">
			<?php
				foreach ($service->accounts() as $account):
					$have_accounts = true;
					$profile_url = $service->profile_url($account);
					$profile_name = $service->profile_name($account);
					$url = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);
					$disconnect = $service->disconnect_url($account, true);
					$output = sprintf(__('Connected to %s. %s', Social::$i18n), $url, $disconnect);
			?>
			<span class="social-<?php echo $key; ?>-icon big"><i></i><?php echo $output; ?></span>
			<?php endforeach; ?>

			<a href="<?php echo Social_Helper::authorize_url($key, true); ?>" id="<?php echo $key; ?>_signin" class="social-login"><span><?php _e('Sign In With ' . $service->title, Social::$i18n); ?></span></a>
		</div>
		<?php endforeach; ?>

		<h3 style="clear:both;"><?php _e('Broadcasting Format', Social::$i18n); ?></h3>
		<p><?php _e('Define how you would like your posts to be formatted when being broadcasted.'); ?></p>
		<table class="form-table">
			<tr>
				<th style="width:100px" colspan="2">
					<strong><?php _e('Tokens:', Social::$i18n); ?></strong>
					<ul style="margin:10px 0 0 15px;">
						<?php foreach (Social::broadcast_tokens() as $token => $description): ?>
						<li><span style="float:left;width:60px"><?php echo $token; ?></span> - <?php echo $description; ?></li>
						<?php endforeach; ?>
					</ul>
				</th>
			</tr>
			<tr>
				<th style="width:100px"><label
					for="<?php echo Social::$prefix . 'broadcast_format'; ?>"><?php _e('Format', Social::$i18n); ?></label>
				</th>
				<td><input type="text" class="text" name="<?php echo Social::$prefix . 'broadcast_format'; ?>" id="<?php echo Social::$prefix . 'broadcast_format'; ?>" style="width:400px" value="<?php echo Social::option('broadcast_format'); ?>"/></td>
			</tr>
		</table>

		<?php if ($have_accounts): ?>
		<div id="social_xmlrpc">
			<h3><?php _e('XML-RPC/Email Broadcasting Accounts', Social::$i18n); ?></h3>
			<p><?php _e('These accounts will be the accounts that are automatically broadcasted to when you publish a blog post via XML-RPC or email.', Social::$i18n); ?></p>
			<?php
				$accounts = get_option(Social::$prefix . 'xmlrpc_accounts', array());
				foreach (Social::$global_services as $key => $service):
					foreach ($service->accounts() as $account):
			?>
			<label class="social-broadcastable" for="<?php echo $service->service . $account->user->id; ?>" style="cursor:pointer">
				<input type="checkbox" name="<?php echo Social::$prefix . 'xmlrpc_accounts[]'; ?>" id="<?php echo $service->service . $account->user->id; ?>" value="<?php echo $key . '|' . $account->user->id; ?>"<?php echo ((isset($accounts[$key]) and in_array($account->user->id, array_values($accounts[$key]))) ? ' checked="checked"' : ''); ?> />
				<img src="<?php echo $service->profile_avatar($account); ?>" width="24" height="24"/>
				<span><?php echo $service->profile_name($account); ?></span>
			</label>
			<?php
					endforeach;
				endforeach;
			?>
			<div style="clear:both"></div>
		</div>
		<?php endif; ?>

		<h3><?php _e('Twitter @Anywhere Settings', Social::$i18n); ?></h3>
		<p><?php _e('To enable Twitter\'s @Anywhere hovercards for linked Twitter usernames, enter your application\'s Consumer API key here. (<a href="http://dev.twitter.com/anywhere" target="_blank">Click here to get an API key</a>)', Social::$i18n); ?></p>
		<p><input type="text" class="text" name="<?php echo Social::$prefix . 'twitter_anywhere_api_key'; ?>" id="<?php echo Social::$prefix . 'twitter_anywhere_api_key'; ?>" style="width:400px" value="<?php echo Social::option('twitter_anywhere_api_key'); ?>"/></p>

		<h3><?php _e('Disable Internal CRON Mechanism', Social::$i18n); ?></h3>
		<p><?php _e('If you disable this feature, Social\'s scheduled CRON jobs to fetch new tweets and Facebook posts will not run until you setup the correct system CRON jobs or fetch new items manually from each individual post screen.', Social::$i18n); ?></p>
		<p>
			<label for="system_crons_yes">
				<input type="radio" name="<?php echo Social::$prefix . 'system_crons'; ?>" value="1" id="system_crons_yes" style="position:relative;top:-1px"<?php echo Social::option('system_crons') == '1' ? ' checked="checked"' : ''; ?> />
				<?php _e('Yes', Social::$i18n); ?>
			</label>
		</p>

		<p>
			<label for="system_crons_no">
				<input type="radio" name="<?php echo Social::$prefix . 'system_crons'; ?>" value="0" id="system_crons_no" style="position:relative;top:-1px"<?php echo Social::option('system_crons') != '1' ? ' checked="checked"' : ''; ?> />
				<?php _e('No', Social::$i18n); ?>
			</label>
		</p>

		<?php if (Social::option('system_crons') == '1'): ?>
		<h4 style="margin-bottom:0"><?php _e('API Key', Social::$i18n); ?></h4>
		<p style="margin-top:0"><?php _e('This is the API key that your system CRON jobs will need to use:', Social::$i18n); ?></p>
		<p>
			<strong class="<?php echo Social::$prefix . 'api_key'; ?>"><?php echo Social::option('system_cron_api_key'); ?></strong><br/>
			<a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=social.php&' . Social::$prefix . 'action=regenerate_api_key'), 'regenerate_api_key'); ?>" rel="<?php echo Social::$prefix . 'api_key'; ?>" id="<?php echo Social::$prefix . 'regenerate_api_key'; ?>"><?php _e('Regenerate Key', Social::$i18n); ?></a>
		</p>
		<h4 style="margin-bottom:0"><?php _e('Running System CRON', Social::$i18n); ?></h4>
		<p style="margin-top:0"><?php _e('For your system CRON to run correctly, make sure it is pointing towards a URL that looks something like the following:', Social::$i18n); ?></p>
		<p><?php echo site_url('?' . Social::$prefix . 'cron=cron_15&api_key=<span class="' . Social::$prefix . 'api_key">' . Social::option('system_cron_api_key') . '</span>'); ?></p>
		<?php endif; ?>

		<h4 style="margin-bottom:0"><?php _e('Debug Mode', Social::$i18n); ?></h4>
		<p style="margin-top:0"><?php _e('If you enable this option Social may run slower, but you\'ll be able to see various informational items in your PHP error log.', Social::$i18n); ?></p>
		<p>
			<label for="debug_mode_yes">
				<input type="radio" name="<?php echo Social::$prefix . 'debug'; ?>" id="debug_mode_yes" value="1"<?php echo Social::option('debug') == '1' ? ' checked="checked"' : ''; ?> />
				<?php _e('Yes', Social::$i18n); ?>
			</label>
		</p>

		<p>
			<label for="debug_mode_no">
				<input type="radio" name="<?php echo Social::$prefix . 'debug'; ?>" id="debug_mode_no" value="0"<?php echo Social::option('debug') != '1' ? ' checked="checked"' : ''; ?> />
				<?php _e('No', Social::$i18n); ?>
			</label>
		</p>

		<p class="submit" style="clear:both">
			<input type="submit" name="submit" value="Save Settings" class="button-primary"/>
		</p>
	</div>
</form>
<?php
	}

	/**
	 * Shows the user's social network accounts.
	 *
	 * @param  object  $profileuser
	 * @return void
	 */
	public function show_user_profile($profileuser) {
?>
	<h3 id="social-networks"><?php _e('Connect to Social Networks', Social::$i18n); ?></h3>
	<p><?php _e('Before you can broadcast to your social networks, you will need to connect your account(s).', Social::$i18n); ?></p>
	<?php foreach (Social::$services as $key => $service): ?>
	<div class="social-settings-connect">
		<?php
			foreach ($service->accounts() as $account):
				$profile_url = $service->profile_url($account);
				$profile_name = $service->profile_name($account);
				$url = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);
				$disconnect = $service->disconnect_url($account, true);
				$output = sprintf(__('Connected to %s. %s', Social::$i18n), $url, $disconnect);
		?>
		<span class="social-<?php echo $key; ?>-icon big"><i></i><?php echo $output; ?></span>
		<?php endforeach; ?>

		<a href="<?php echo Social_Helper::authorize_url($key, true); ?>" id="<?php echo $key; ?>_signin"
		   class="social-login"><span><?php _e('Sign In With ' . $service->title, Social::$i18n); ?></span></a>
	</div>
	<?php endforeach; ?>
	<div style="clear:both"></div>
<?php
	}

	/**
	 * Sets the broadcasting options for a post.
	 *
	 * @param  int     $post_id   post ID
	 * @param  string  $location  location to send the form to
	 * @return void
	 */
	public function broadcast_options($post_id, $location) {
		$notify = get_post_meta($post_id, Social::$prefix . 'notify', true);
		$post = get_post($post_id);

		$errors = array();
		if ($notify == '1') {
			$services = $this->services();
			$broadcasted_ids = get_post_meta($post_id, Social::$prefix . 'broadcasted_ids', true);
			if (empty($broadcasted_ids)) {
				$broadcasted_ids = array();
			}
			if (isset($_POST[Social::$prefix . 'action'])) {
				foreach ($services as $key => $service) {
					if (count($service->accounts()) and empty($_POST[Social::$prefix . $key . '_content'])) {
						$errors[$key] = 'Please enter some content for ' . $service->title() . '.';
					}
				}

				if (!count($errors)) {
					$broadcast_accounts = array();
					foreach ($services as $key => $service) {
						if (isset($_POST[Social::$prefix . $key . '_accounts'])) {
							$accounts = $_POST[Social::$prefix . $key . '_accounts'];
							$_accounts = array();
							foreach ($accounts as $account) {
								$account = explode('|', $account);

								$_accounts[] = array(
									'id' => $account[0],
									'global' => (isset($account[1]) ? true : false)
								);
							}

							if (!empty($_accounts)) {
								$broadcast_accounts[$key] = $_accounts;
							}
							update_post_meta($post_id, Social::$prefix . $key . '_content', $_POST[Social::$prefix . $key . '_content']);
						}
					}
					update_post_meta($post_id, Social::$prefix . 'broadcast_accounts', $broadcast_accounts);
					$this->broadcast($post_id);
					wp_redirect($location);
					return;
				}
			}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php _e('Social Broadcasting Options', 'social'); ?></title>
	<?php
		wp_admin_css('install', true);
		do_action('admin_print_styles');
	?>
</head>
<body>
<h1 id="logo"><?php _e('Social Broadcasting Options', Social::$i18n); ?></h1>
<?php if (count($errors)): ?>
<div id="social_error">
	<?php
		foreach ($errors as $error) {
			echo $error . '<br />';
		}
	?>
</div>
<?php endif; ?>
<p><?php __('You have chosen to broadcast this blog post to your social accounts. Use the form below to edit your broadcasted messages.', Social::$i18n); ?></p>

<form id="setup" method="post" action="<?php echo admin_url(); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="post_ID" value="<?php echo $post_id; ?>"/>
<input type="hidden" name="location" value="<?php echo $location; ?>"/>
<input type="hidden" name="<?php echo Social::$prefix; ?>action" value="broadcast_options"/>
<table class="form-table">
<?php
	foreach ($services as $key => $service) {
		// Custom content?
		$content = get_post_meta($post_id, Social::$prefix . $key . '_content', true);
		if (!$content) {
			$content = $service->format_content(get_post($post_id), $this->option('broadcast_format'));
		}
		$counter = $service->max_broadcast_length();
		if (!empty($content)) {
			$length = strlen($content);
			if ($length > $counter) {
				$content = substr($content, 0, $counter);
				$counter = 0;
			}
			else {
				$counter = $counter - strlen($content);
			}
		}

		$accounts = $service->accounts();
		if (isset(Social::$global_services[$service->service])) {
			foreach (Social::$global_services[$service->service]->accounts() as $id => $account) {
				$accounts[$id] = (object)array_merge((array)$account, array('global' => true));
			}
		}

		$total_accounts = count($accounts);
		$heading = sprintf(__('Publish to %s:', Social::$i18n), ($total_accounts == '1' ? 'this account' : 'these accounts'));

		if ($total_accounts) {
?>
	<tr>
		<th scope="row">
			<label for="<?php echo $service->service . '_preview'; ?>"><?php _e($service->title(), Social::$i18n); ?></label><br/>
			<span id="<?php echo $service->service . '_counter'; ?>" class="social-preview-counter"><?php echo $counter; ?></span>
		</th>
		<td>
			<textarea id="<?php echo $service->service . '_preview'; ?>" name="<?php echo Social::$prefix . $service->service . '_content'; ?>" class="social-preview-content" cols="40" rows="5"><?php echo ((isset($_POST[Social::$prefix . $service->service . '_content']) and !empty($_POST[Social::$prefix . $service->service . '_content'])) ? $_POST[Social::$prefix . $service->service . '_content'] : $content); ?></textarea><br/>
			<strong><?php echo $heading; ?></strong><br/>
			<?php
				foreach ($accounts as $account):
					$checked = false;
					if (!count($broadcasted_ids)) {
						$checked = true;
					}
					else if (!isset($broadcasted_ids[$service->service])) {
						$checked = true;
					}
					else if (!isset($broadcasted_ids[$service->service][$account->user->id])) {
						$checked = true;
					}
			?>
			<label class="social-broadcastable" for="<?php echo $service->service . $account->user->id; ?>" style="cursor:pointer">
				<input type="checkbox" name="<?php echo Social::$prefix . $service->service . '_accounts[]'; ?>" id="<?php echo $service->service . $account->user->id; ?>" value="<?php echo $account->user->id . (isset($account->global) ? '|true' : ''); ?>"<?php echo ($checked ? ' checked="checked"' : ''); ?> />
				<img src="<?php echo $service->profile_avatar($account); ?>" width="24" height="24"/>
				<span><?php echo $service->profile_name($account); ?></span>
			</label>
			<?php endforeach; ?>
		</td>
	</tr>
<?php
		}
	}
?>
</table>
<p class="step">
	<input type="submit" value="<?php _e(($post->post_status == 'future' ? 'Schedule' : (isset($_POST[Social::$prefix.'broadcast']) ? 'Broadcast' : 'Publish')), Social::$i18n); ?>" class="button"/>
	<a href="<?php echo get_edit_post_link($post_id, 'url'); ?>" class="button">Cancel</a>
</p>
</form>
<script type="text/javascript" src="<?php echo includes_url('/js/jquery/jquery.js'); ?>"></script>
<script type="text/javascript" src="<?php echo Social::$plugins_url . '/assets/js/admin.js'; ?>"></script>
</body>
</html>
<?php
			exit;
		}
	}

	/**
	 * This will flag a post to be broadcasted.
	 *
	 *   [!] Called during the publish_post action.
	 *
	 * @param  int     $post_id
	 * @param  object  $post
	 */
	public function set_broadcast_meta_data($post_id, $post) {
		if (!$this->broadcast_meta_set) {
			$post_key = Social::$prefix . 'notify';
			if (isset($_POST[$post_key])) {
				update_post_meta($post_id, $post_key, $_POST[$post_key]);

				$this->broadcast_meta_set = true;
				$broadcasted = get_post_meta($post_id, Social::$prefix . 'broadcasted', true);
				if (empty($broadcasted) or !count($broadcasted)) {
					update_post_meta($post_id, Social::$prefix . 'broadcasted', array());

					if ($_POST[$post_key] == '1') {
						// Post needs to stay a draft for now.
						$post->post_status = 'draft';
						wp_update_post($post);
					}
				}
			}
		}
	}

	/**
	 * Attempts to rebroadcast posts.
	 *
	 * @return void
	 */
	public function retry_broadcast_core() {
		$url = str_replace('&amp;', '&', wp_nonce_url(site_url('?' . Social::$prefix . 'action=retry_broadcast')));
		wp_remote_get($url, array(
			'timeout' => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters('https_local_ssl_verify', true)
		));
	}

	/**
	 * Attempts to rebroadcast posts.
	 *
	 * @return void
	 */
	public function retry_broadcast() {
		// Find posts that require a rebroadcast
		$retry_ids = get_option(Social::$prefix . 'retry_broadcast');
		if ($retry_ids !== false) {
			foreach ($retry_ids as $id => $attempts) {
				$post = get_post($id);
				if ($attempts < 3) {
					$this->broadcast($post);
					++$retry_ids[$id];
				}
				else if ($attempts === 3) {
					$author = get_userdata($post->post_author);

					$message = 'Hello,' . "\n\n";
					$message .= wordwrap('Social has tried to broadcast the blog post "' . $post->post_title . '" three times and has failed all three times.', 60) . "\n\n";

					wp_mail($author->user_email, get_bloginfo('name') . ': Failed to broadcast post with Social.', $message);

					unset($retry_ids[$id]);
				}
			}

			update_option(Social::$prefix . 'retry_broadcast', $retry_ids);
		}
	}

	/**
	 * Broadcast the post to Twitter and/or Facebook.
	 *
	 * @param  object  $post
	 */
	public function broadcast($post) {
		if (!is_object($post)) {
			$post = get_post($post);
		}
		$broadcasted = get_post_meta($post->ID, Social::$prefix . 'broadcasted', true);
		if (empty($broacasted)) {
			$broadcasted = array();
		}

		$_broadcast_accounts = false;
		$broadcast_accounts = get_post_meta($post->ID, Social::$prefix . 'broadcast_accounts', true);
		if (!empty($broadcast_accounts)) {
			$ids = array();
			$errored_accounts = false;
			$services = $this->services();
			foreach ($services as $service_key => $service) {
				$content = get_post_meta($post->ID, Social::$prefix . $service_key . '_content', true);
				if (!empty($content)) {
					foreach ($broadcast_accounts as $key => $accounts) {
						if ($key == $service_key) {
							foreach ($accounts as $account) {
								$_account = $account;
								$id = $account['id'];
								if (isset($account['global']) and !empty($account['global'])) {
									$global_accounts = Social::$global_services[$key]->accounts();
									if (isset($global_accounts[$id])) {
										$account = $global_accounts[$id];
									}
									else {
										$account = false;
									}
								}
								else {
									$user_accounts = Social::$services[$key]->accounts();
									if (!count($user_accounts)) {
										$accounts = get_user_meta($post->post_author, Social::$prefix . 'accounts', true);
										if (isset($accounts[$key])) {
											$user_accounts = $accounts[$key];
										}
									}
									if (isset($user_accounts[$id])) {
										$account = $user_accounts[$id];
									}
									else {
										$account = false;
									}
								}

								if ($account !== false) {
									if (Social::option('debug') == '1') {
										$this->log('Broadcasting to ' . $service->profile_name($account) . ' (' . $service->title . ')...START.');
									}
									$response = $service->status_update($account, $content);
									if (!$service->deauthed($response, $account)) {
										$ids[$key]["{$account->user->id}"] = $response->response->id;
										// pass response to anyone else who wants it
										do_action(Social::$prefix . 'broadcast_response', $response);
										do_action(Social::$prefix . $key . '_broadcast_response', $response);

										$broadcasted[$service_key] = '1';

										if (Social::option('debug') == '1') {
											$this->log('Broadcasting to ' . $service->profile_name($account) . ' (' . $service->title . ')...COMPLETE.');
										}
									}
									else {
										if ($response === false or ($response == 'deauthed')) {
											$_broadcast_accounts[$key][] = $_account;

											if (Social::option('debug') == '1') {
												$this->log('Broadcasting to ' . $service->profile_name($account) . ' (' . $service->title . ')...ERROR - DEAUTH PROBABLE.');
											}
										}
										else {
											$errored_accounts[$service->service][] = $account;
											if (Social::option('debug') == '1') {
												$this->log('Broadcasting to ' . $service->profile_name($account) . ' (' . $service->title . ')...ERROR.');
											}
										}
									}
								}
								else {
									$errored_accounts[$service->service][] = $account;
								}
							}
						}
					}
				}

				if (!isset($_broadcast_accounts[$service_key])) {
					delete_post_meta($post->ID, Social::$prefix . 'notify_' . $service_key);
					delete_post_meta($post->ID, Social::$prefix . $service_key . '_content');
				}
			}

			$broadcasted_ids = get_post_meta($post->ID, Social::$prefix . 'broadcasted_ids', true);
			if (!is_array($broadcasted_ids)) {
				$broadcasted_ids = array();
			}
			foreach ($ids as $key => $_broadcasted) {
				if (!isset($broadcasted_ids[$key])) {
					$broadcasted_ids[$key] = array();
				}

				foreach ($_broadcasted as $user_id => $status_id) {
					if ($status_id !== null) {
						$broadcasted_ids[$key][$user_id] = $status_id;
					}
				}
			}
			update_post_meta($post->ID, Social::$prefix . 'broadcasted_ids', $broadcasted_ids);

			// Accounts errored?
			if ($errored_accounts !== false) {
				$this->send_publish_error_notification($post, $errored_accounts);
			}
			if ($_broadcast_accounts !== false) {
				update_post_meta($post->ID, Social::$prefix . 'broadcast_accounts', $_broadcast_accounts);
				update_post_meta($post->ID, Social::$prefix . 'broadcast_error', 'true');

				$retry_broadcast = get_option(Social::$prefix . 'retry_broadcast');
				if (!isset($retry_broadcast[$post->ID])) {
					$retry_broadcast[$post->ID] = 0;
				}
				update_option(Social::$prefix . 'retry_broadcast', $retry_broadcast);
			}
			else {
				update_post_meta($post->ID, Social::$prefix . 'broadcasted', $broadcasted);
				delete_post_meta($post->ID, Social::$prefix . 'broadcast_accounts');
			}
		}
	}

	/**
	 * Displays the upgrade message.
	 */
	public function display_failed_broadcast() {
		$accounts = get_post_meta($_GET['post'], Social::$prefix . 'broadcast_accounts', true);
		if (!empty($accounts)) {
			$services = $this->services();
			$message = __('Failed to broadcast to the following accounts:', Social::$i18n);
?>
<div class="error">
	<p><?php echo $message; ?></p>
	<?php foreach ($accounts as $key => $_accounts): ?>
	<p>
		<?php echo $services[$key]->title(); ?>
		<ul>
			<?php foreach ($_accounts as $account): ?>
			<li>- <?php echo $services[$key]->profile_name($account); ?></li>
			<?php endforeach; ?>
		</ul>
	</p>
	<?php endforeach; ?>
</div>
<?php
		}
	}

	/**
	 * Sends the error notification to the blog post author.
	 *
	 * @param  object  $post
	 * @param  array   $errored_accounts
	 * @return void
	 */
	private function send_publish_error_notification($post, $errored_accounts) {
		$author = get_userdata($post->post_author);

		$services = $this->services();

		$message = 'Hello,' . "\n\n";
		$message .= wordwrap('Social failed to broadcast the blog post "' . $post->post_title . '" to one or more of your Social accounts.', 60) . "\n\n";
		foreach ($errored_accounts as $service => $accounts) {
			$message .= $services[$service]->title() . ':' . "\n";
			foreach ($accounts as $account) {
				$message .= '- ' . $services[$service]->profile_name($account) . "\n";
			}
			$message .= "\n";
		}
		$message .= 'Please login and reauthenticate the above accounts if you' . "\n";
		$message .= 'wish to continue using them.' . "\n\n";
		$message .= 'Global accounts: ' . "\n";
		$message .= Social_Helper::settings_url() . "\n\n";
		$message .= 'Personal accounts: ' . "\n";
		$message .= admin_url('profile.php#social-networks') . "\n\n";

		wp_mail($author->user_email, get_bloginfo('name') . ': Failed to broadcast post with Social.', $message);
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
	 * Overrides the default WordPress comments_template function.
	 *
	 * @return string
	 */
	public static function comments_template() {
		global $post;

		if (!(is_singular() and (have_comments() or $post->comment_status == 'open'))) {
			return;
		}

		require SOCIAL_PATH . 'lib/social/walker/comment.php';

		if (!defined('SOCIAL_COMMENTS_FILE')) {
			define('SOCIAL_COMMENTS_FILE', trailingslashit(dirname(SOCIAL_FILE)) . 'comments.php');
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
		return array_merge($types, array('facebook', 'twitter', 'wordpress'));
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
				$image = get_comment_meta($comment->comment_ID, Social::$prefix . 'profile_image_url', true);
			}
		}
		else if (is_string($comment) or is_int($comment)) {
			$services = Social::$services;
			foreach ($services as $key => $service) {
				if (count($service->accounts())) {
					$image = $service->profile_avatar(reset($service->accounts()));
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
	 * Displays a comment.
	 *
	 * @param  object  $comment  comment object
	 * @param  array   $args
	 * @param  int     $depth
	 */
	public function comment($comment, $args, $depth) {
		$comment_type = get_comment_meta($comment->comment_ID, Social::$prefix . 'comment_type', true);
		if (empty($comment_type)) {
			$comment_type = (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type);
		}
		$comment->comment_type = $comment_type;
		$GLOBALS['comment'] = $comment;

		$status_url = null;
		if (!in_array($comment_type, array('wordpress', 'pingback'))) {
			$service = Social::service($comment->comment_type);
			if ($service !== false) {
				$status_id = get_comment_meta($comment->comment_ID, Social::$prefix . 'status_id', true);
				if (!empty($status_id)) {
					$status_url = $service->status_url(get_comment_author(), $status_id);
				}
			}

			if ($status_url === null) {
				$comment_type = 'wordpress';
			}
		}
?>
<li class="social-comment social-clearfix social-<?php echo $comment_type; ?>" id="li-comment-<?php comment_ID(); ?>">
<div class="social-comment-inner social-clearfix" id="comment-<?php comment_ID(); ?>">
	<div class="social-comment-header">
		<div class="social-comment-author vcard">
			<?php
				switch ($comment_type) {
					case 'pingback':
						echo '<span class="social-comment-label">Pingback</span>';
					break;
					default:
						echo get_avatar($comment, 40);
					break;
				}

				printf('<cite class="social-fn fn">%s</cite>', get_comment_author_link());
				if ($depth > 1) {
					echo '<span class="social-replied social-imr">'.__('replied:', Social::$i18n).'</span>';
				}
			?>
		</div>
		<!-- .comment-author .vcard -->
		<div class="social-comment-meta">
			<span class="social-posted-from">
				<?php if ($status_url !== null): ?>
				<a href="<?php echo $status_url; ?>" title="<?php _e(sprintf('View on %s', $service->title()), Social::$i18n); ?>">
				<?php endif; ?>
				<span><?php _e('View', Social::$i18n); ?></span>
				<?php if ($status_url !== null): ?>
				</a>
				<?php endif; ?>
			</span>
			<a href="<?php echo get_comment_link(get_comment_ID()); ?>" class="social-posted-when"><?php printf(__('%s ago', Social::$i18n), human_time_diff(strtotime($comment->comment_date_gmt))); ?></a>
		</div>
	</div>
	<div class="social-comment-body">
		<?php if ($comment->comment_approved == '0'): ?>
		<em class="comment-awaiting-moderation"><?php _e('Your comment is awaiting moderation.', 'social'); ?></em><br />
		<?php endif; ?>
		<?php comment_text(); ?>
	</div>
	<div class="social-actions entry-meta">
		<?php comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth']))); ?>
		<?php edit_comment_link(__('Edit', Social::$i18n), '<span class="comment-edit-link"> &middot; ', '</span>'); ?>
	</div>
	<!-- .reply -->
</div><!-- #comment-##  -->
<?php
	}

	/**
	 * Sets the comment type upon being saved.
	 *
	 * @param  int  $comment_ID
	 */
	public function comment_post($comment_ID) {
		global $wpdb, $comment_content, $commentdata;
		$type = false;
		$services = $this->services();
		if (!empty($services)) {
			$account_id = $_POST[Social::$prefix . 'post_account'];

			$url = get_comment_link($comment_ID);
			$url_length = strlen($url) + 1;
			$comment_length = strlen($comment_content);
			$combined_length = $url_length + $comment_length;
			foreach ($services as $key => $service) {
				$max_length = $service->max_broadcast_length();
				if ($combined_length > $max_length) {
					$output = substr($comment_content, 0, ($max_length - $url_length - 3)) . '...';
				} else {
					$output = $comment_content;
				}
				$output .= ' ' . $url;

				foreach ($service->accounts() as $account) {
					if ($account_id == $account->user->id) {
						if (isset($_POST['post_to_service'])) {
							$id = $service->status_update($account, $output)->response->id;
							if ($id === false) {
								// An error occurred...
								$sql = "
									DELETE
									  FROM $wpdb->comments
									 WHERE comment_ID='$comment_ID'
								";
								$wpdb->query($sql);
								$commentdata = null;
								$comment_content = null;

								wp_die(sprintf(__('Error: Failed to post your comment to %s, please go back and try again.', Social::$i18n), $service->title()));
							}
							update_comment_meta($comment_ID, Social::$prefix . 'status_id', $id);
						}

						update_comment_meta($comment_ID, Social::$prefix . 'account_id', $account_id);
						update_comment_meta($comment_ID, Social::$prefix . 'profile_image_url', $service->profile_avatar($account));
						update_comment_meta($comment_ID, Social::$prefix . 'comment_type', $service->service);

						if ($commentdata['user_ID'] != '0') {
							$sql = "
								UPDATE $wpdb->comments
								   SET comment_author='{$service->profile_name($account)}',
								       comment_author_url='{$service->profile_url($account)}'
								 WHERE comment_ID='$comment_ID'
							";
							$wpdb->query($sql);
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
	 * Hides the Site Admin link for commenters.
	 *
	 * @param  string  $link
	 * @return string
	 */
	public function register($link) {
		if (is_user_logged_in()) {
			$commenter = get_user_meta(get_current_user_id(), Social::$prefix . 'commenter', true);
			if ($commenter === '1') {
				return '';
			}
		}

		return $link;
	}

	/**
	 * Show the disconnect link instead.
	 *
	 * @param  string  $link
	 * @return string
	 */
	public function loginout($link) {
		if (is_user_logged_in()) {
			$commenter = get_user_meta(get_current_user_id(), Social::$prefix . 'commenter', true);
			if ($commenter === '1') {
				foreach (Social::$services as $key => $service) {
					$account = reset($service->accounts());
					if ($account) {
						return $service->disconnect_url($account);
					}
				}
			}
		}
		else {
			$link = explode('>' . __('Log in'), $link);
			$link = $link[0] . ' id="' . Social::$prefix . 'login">' . __('Log in') . $link[1];
		}

		return $link;
	}

	/**
	 * Creates the disconnect URL for a user.
	 *
	 * @param  array  $args
	 * @return string
	 */
	public function commenter_disconnect_url($args) {
		$url = site_url() . '?';
		$params = array();
		foreach ($args as $k => $v) {
			$params[] = $k . '=' . $v;
		}
		$url .= implode('&', $params);

		return $url;
	}

	/**
	 * Creates the file lock.
	 *
	 * @param  string  $cron
	 * @return bool
	 */
	private function cron_lock($cron) {
		$locked = false;
		$file = trailingslashit($this->cron_lock_dir) . $cron . '.txt';

		$timestamp = 0;
		if (is_file($file)) {
			$timestamp = file_get_contents($file);
		}

		$fp = fopen($file, 'w+');
		if (flock($fp, LOCK_EX)) {
			$locked = true;
			fwrite($fp, time());
		}
		else if (!empty($timestamp) and time() - $timestamp >= 3600) {
			$locked = true;
			$this->cron_unlock($cron);
		}

		fclose($fp);

		if (Social::option('debug') == '1') {
			$this->log('CRON ' . $cron . ' LOCK COMPLETE.');
		}

		return $locked;
	}

	/**
	 * Unlocks the file.
	 *
	 * @param  string  $cron
	 * @return bool
	 */
	private function cron_unlock($cron) {
		$file = trailingslashit($this->cron_lock_dir) . $cron . '.txt';
		$fp = fopen($file, 'r+');
		ftruncate($fp, 0);
		flock($fp, LOCK_UN);
		fclose($fp);

		if (Social::option('debug') == '1') {
			$this->log('CRON ' . $cron . ' UNLOCK COMPLETE.');
		}
	}

	/**
	 * Handles the file locking for aggregate_comments.
	 */
	public function aggregate_comments_core() {
		do_action(Social::$prefix . 'aggregate_comments');
	}

	/**
	 * Handles the file locking for cron_15.
	 */
	public function cron_15_core() {
		if (Social::option('debug') == '1') {
			$this->log('cron_15_core initiated.');
		}

		$url = str_replace('&amp;', '&', wp_nonce_url(site_url('?' . Social::$prefix . 'action=cron_15')));
		wp_remote_get($url, array(
			'timeout' => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters('https_local_ssl_verify', true)
		));
	}

	/**
	 * Handles the file locking for cron_60.
	 */
	public function cron_60_core() {
		if (Social::option('debug') == '1') {
			$this->log('cron_60_core initiated.');
		}

		$url = str_replace('&amp;', '&', wp_nonce_url(site_url('?' . Social::$prefix . 'action=cron_60')));
		wp_remote_get($url, array(
			'timeout' => 0.01,
			'blocking' => false,
			'sslverify' => apply_filters('https_local_ssl_verify', true)
		));
	}

	/**
	 * Runs the aggregation of comments for all of the services.
	 */
	public function aggregate_comments() {
		global $wpdb;

		if (Social::option('debug') == '1') {
			$this->log('aggregate_comments initiated.');
		}

		// Load the ignored posts
		$queued = get_option(Social::$prefix . 'queued_for_aggregation', array());
		$ignored = get_option(Social::$prefix . 'ignored_posts_for_aggregation', array());

		// Load all the posts
		$sql = "
			SELECT p.ID, p.post_author, p.post_date, p.guid
			  FROM $wpdb->posts AS p
			 WHERE p.post_status = 'publish'
			   AND p.comment_status = 'open'
			   AND p.post_type = 'post'
			   AND p.post_parent = '0'
		";
		if (is_array($ignored) and count($ignored)) {
			$sql .= "AND p.ID NOT IN (" . implode(',', $ignored) . ")";
		}
		$posts = $wpdb->get_results($sql, OBJECT);

		foreach ($posts as $post) {
			$timestamp = time() - strtotime($post->post_date);

			$hours = 0;
			if ($timestamp >= 172800) {
				$hours = 48;
			}
			else if ($timestamp >= 86400) {
				$hours = 24;
			}
			else if ($timestamp >= 43200) {
				$hours = 12;
			}
			else if ($timestamp >= 28800) {
				$hours = 8;
			}
			else if ($timestamp >= 14400) {
				$hours = 4;
			}
			else if ($timestamp >= 7200) {
				$hours = 2;
			}

			if (!isset($queued[$post->ID]) or $queued[$post->ID] < $hours) {
				$queued[$post->ID] = $hours;

				if (Social::option('debug') == '1') {
					$this->log('Aggregation for POST #' . $post->ID . '...START. (' . $hours . ' hours)');
				}

				$this->run_aggregation($post);

				if (Social::option('debug') == '1') {
					$this->log('Aggregation for POST #' . $post->ID . '...COMPLETE. (' . $hours . ' hours)');
				}

				// Remove the post from the CRON.
				if ($hours === 48) {
					unset($queued[$post->ID]);
					$ignored[] = $post->ID;
				}

				update_option(Social::$prefix . 'ignored_posts_for_aggregation', $ignored);
				update_option(Social::$prefix . 'queued_for_aggregation', $queued);
			}
		}

		if (Social::option('debug') == '1') {
			$this->log('aggregate_comments complete.');
		}
	}

	/**
	 * Runs aggregation for a comment.
	 *
	 * @param  int|object  $post
	 * @return void
	 */
	public function run_aggregation($post) {
		if (!is_object($post)) {
			$post = get_post($post);
		}

		$urls = array(
			urlencode(site_url('?p=' . $post->ID)),
		);

		$permalink = urlencode(get_permalink($post->ID));
		if ($urls[0] != $permalink) {
			$urls[] = $permalink;
		}

		$broadcasted = get_post_meta($post->ID, Social::$prefix . 'broadcasted_ids', true);
		if (empty($broadcasted)) {
			$broadcasted = array();
		}
		$post->broadcasted_ids = $broadcasted;

		$aggregated = get_post_meta($post->ID, Social::$prefix . 'aggregated_ids', true);
		if (empty($aggregated)) {
			$aggregated = array();
		}
		$post->aggregated_ids = $aggregated;

		$broadcasted_ids = array();
		foreach ($broadcasted as $service => $ids) {
			if (!isset($broadcasted_ids[$service])) {
				$broadcasted_ids[$service] = $ids;
			}
			else {
				$broadcasted_ids[$service] = array_merge($broadcasted_ids[$service], $ids);
			}
		}

		// Run search!
		$services = $this->services();
		foreach ($services as $key => $service) {
			$results = $service->search_for_replies($post, $urls, (isset($broadcasted_ids[$key])
					? $broadcasted_ids[$key] : null));

			// Results?
			if (is_array($results)) {
				$service->save_replies($post->ID, $results);
			}
		}
		Social_Aggregate_Log::instance($post->ID)->save();
	}

	/**
	 * Merges the user and global accounts together.
	 *
	 * @return array
	 */
	public function services() {
		if (!count(Social::$combined_services)) {
			$user = Social::$services;
			$global = Social::$global_services;
			$services = array();

			foreach ($user as $key => $service) {
				$services[$key] = clone $service;
			}

			foreach ($global as $key => $service) {
				if (!isset($services[$key])) {
					$services[$key] = clone $service;
				}
				else {
					$accounts = $service->accounts();
					if (count($accounts)) {
						$_accounts = $services[$key]->accounts();
						foreach ($accounts as $account) {
							if (!isset($_accounts[$account->user->id])) {
								$_accounts[$account->user->id] = $account;
							}
						}
						$services[$key]->accounts($_accounts);
					}
				}
			}

			Social::$combined_services = $services;
		}

		return Social::$combined_services;
	}

	/**
	 * Helper: Turn an array or two into HTML attribute string
	 * 
	 * @param  array  $arr1
	 * @param  array  $arr2
	 * @return string
	 */
	public static function to_attr($arr1 = array(), $arr2 = array()) {
		$attrs = array();
		$arr = array_merge($arr1, $arr2);
		foreach ($arr as $key => $value) {
			if (function_exists('esc_attr')) {
				$key = esc_attr($key);
				$value = esc_attr($value);
			}
			$attrs[] = $key . '="' . $value . '"';
		}
		return implode(' ', $attrs);
	}

	/**
	 * Helper for creating HTML tag from strings and arrays of attributes.
	 *
	 * @param  string  $tag
	 * @param  string  $text
	 * @param  array   $attr1
	 * @param  array   $attr2
	 * @return string
	 */
	public static function to_tag($tag, $text = '', $attr1 = array(), $attr2 = array()) {
		if (function_exists('esc_attr')) {
			$tag = esc_attr($tag);
		}
		$attrs = self::to_attr($attr1, $attr2);
		if ($text !== false) {
			return '<' . $tag . ' ' . $attrs . '>' . $text . '</' . $tag . '>';
		}
			// No text == self closing tag
		else {
			return '<' . $tag . ' ' . $attrs . ' />';
		}
	}

	/**
	 * Adds a message to the error log.
	 *
	 * @param  string  $message
	 * @return void
	 */
	private function log($message) {
		$file = fopen(SOCIAL_PATH . 'log.txt', 'a+');
		fwrite($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
		fclose($file);
	}

} // End Social

/**
 * Just a singleton for filter methods to live under.
 * @uses Social
 */
class Social_Comment_Form {
	protected static $instances = array();

	protected function __construct($post_id, $args = array()) {
		global $post;

		$this->post_id = $post_id;
		$this->args = $args;
		$this->is_logged_in = is_user_logged_in();
		$this->current_user = wp_get_current_user();

		if ($post === null) {
			$post = get_post($this->post_id);
		}
	}

	/**
	 * Factory method
	 *
	 * @static
	 * @param  int    $post_id
	 * @param  array  $args
	 * @return object instance of Social_Comment_Form
	 */
	public static function get_instance($post_id, $args = array()) {
		$_class = 'Social_Comment_Form';
		if (!isset(self::$instances[$post_id]) or !(self::$instances[$post_id] instanceof $_class)) {
			self::$instances[$post_id] = new $_class($post_id, $args);
		}
		return self::$instances[$post_id];
	}

	/**
	 * Factory method with immediate render to HTML.
	 *
	 * @static
	 * @param  array  $args
	 * @param  int    $post_id
	 * @param  bool   $echo
	 * @return string
	 */
	public static function as_html($args = array(), $post_id = null, $echo = true) {
		if (!$post_id) {
			$post_id = get_the_ID();
		}
		$ins = self::get_instance($post_id, $args);
		$comment_form = $ins->get_comment_form($post_id);
		if (!$echo) {
			return $comment_form;
		}
		echo $comment_form;
	}

	/**
	 * Calls comment_form() with filters attached. Also does some regex replacement for
	 * areas of comment form that cannot be filtered.
	 *
	 * @uses comment_form()
	 * @return string
	 */
	public function get_comment_form() {
		ob_start();
		try {
			$this->attach_hooks();
			comment_form($this->args, $this->post_id);
			$this->remove_hooks();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}

		$comment_form = ob_get_clean();

		return preg_replace('/<h3 id="reply-title">(.+)<\/h3>/', '<h3 id="reply-title"><span>$1</span></h3>', $comment_form);
	}

	public function attach_hooks() {
		add_action('comment_form_top', array($this, 'top'));
		add_action('comment_form_defaults', array($this, 'configure_args'));
		add_filter('comment_form_logged_in', array($this, 'logged_in_as'));
		add_filter('comment_id_fields', array($this, 'comment_id_fields'), 10, 3);
	}

	public function remove_hooks() {
		remove_action('comment_form_top', array($this, 'top'));
		remove_action('comment_form_defaults', array($this, 'configure_args'));
		remove_filter('comment_form_logged_in', array($this, 'logged_in_as'));
		remove_filter('comment_id_fields', array($this, 'comment_id_fields'), 10, 3);
	}

	public function to_field_group($label, $id, $tag, $text, $attr1 = array(), $attr2 = array(), $help_text = '') {
		$attr = array_merge($attr1, $attr2);

		$label = Social::to_tag('label', $label, array(
			'for' => $id,
			'class' => 'social-label'
		));

		$input_defaults = array(
			'id' => $id,
			'name' => $id,
			'class' => 'social-input'
		);
		$input = Social::to_tag($tag, $text, $input_defaults, $attr);

		$help = '';
		if ($help_text) {
			$help = Social::to_tag('small', $help_text, array('class' => 'social-help'));
		}

		return Social::to_tag('p', $label . $input . $help, array(
			'class' => 'social-input-row social-input-row-' . $id
		));
	}

	/**
	 * Helper for generating input row HTML
	 *
	 * @param  string  $label
	 * @param  int     $id
	 * @param  string  $value
	 * @param  bool    $req
	 * @param  string  $help_text
	 * @return string
	 * @uses Social::to_tag()
	 */
	public function to_input_group($label, $id, $value, $req = false, $help_text = '') {
		$maybe_req = ($req ? array('required' => 'required') : array());

		return $this->to_field_group($label, $id, 'input', false, $maybe_req, array(
			'type' => 'text',
			'value' => $value
		), $help_text);
	}

	public function to_textarea_group($label, $id, $value, $req = true) {
		$maybe_req = ($req ? array('required' => 'required') : array());
		return $this->to_field_group($label, $id, 'textarea', $value, $maybe_req);
	}

	public function comment_id_fields($result, $id, $replytoid) {
		$html = $this->get_also_post_to_controls();

		$html .= $result;

		$hidden = array('type' => 'hidden');
		$html .= Social::to_tag('input', false, $hidden, array(
			'id' => 'use_twitter_reply',
			'name' => 'use_twitter_reply',
			'value' => 0
		));
		$html .= Social::to_tag('input', false, $hidden, array(
			'id' => 'in_reply_to_status_id',
			'name' => 'in_reply_to_status_id',
			'value' => ''
		));

		return $html;
	}

	public function configure_args($default_args) {
		$commenter = wp_get_current_commenter();
		$req = get_option('require_name_email');

		$fields = array(
			'author' => $this->to_input_group(__('Name', Social::$i18n), 'author', $commenter['comment_author'], $req),
			'email' => $this->to_input_group(__('Email', Social::$i18n), 'email', $commenter['comment_author_email'], $req, __('Not published', Social::$i18n)),
			'url' => $this->to_input_group(__('Website', Social::$i18n), 'url', $commenter['comment_author_url'])
		);

		$args = array(
			'label_submit' => __('Post It', Social::$i18n),
			'title_reply' => __('Profile', Social::$i18n),
			'title_reply_to' => __('Post a Reply to %s', Social::$i18n),
			'cancel_reply_link' => __('cancel', Social::$i18n),
			'comment_notes_after' => '',
			'comment_notes_before' => '',
			'fields' => $fields,
			'comment_field' => $this->to_textarea_group(__('Comment', Social::$i18n), 'comment', '', true, 'textarea')
		);

		if ($this->is_logged_in) {
			$override = array(
				'title_reply' => __('Post a Comment', Social::$i18n)
			);
			$args = array_merge($args, $override);
		}

		return array_merge($default_args, $args);
	}

	/**
	 * Outputs checkboxes for cross-posting
	 * @uses Social::to_tag()
	 */
	public function get_also_post_to_controls() {
		$id = 'post_to_service';
		$label_base = array(
			'for' => $id,
			'id' => 'post_to'
		);

		$checkbox = Social::to_tag('input', false, array(
			'type' => 'checkbox',
			'name' => $id,
			'id' => $id,
			'value' => 1
		));

		if ($this->is_logged_in) {
			if (current_user_can('manage_options')) {
				$text = sprintf(__('Also post to %s', Social::$i18n), '<span></span>');
				$post_to = Social::to_tag('label', $checkbox . ' ' . $text, $label_base, array('style' => 'display:none;'));
			}
			else {
				$post_to = '';
				foreach (Social::$services as $key => $service) {
					if (count($service->accounts())) {
						$text = sprintf(__('Also post to %s'), $service->title());
						$post_to .= Social::to_tag('label', $checkbox . ' ' . $text, $label_base);
					}
				}
			}

			return $post_to;
		}
	}

	/**
	 * Hook for 'comment_form_before' action.
	 */
	public function before() {
?>
<div class="social-heading">
<?php
	if ($this->is_logged_in) {
		$tab = __('Post a Comment', Social::$i18n);
	}
	else {
		$tab = __('Profile', Social::$i18n);
	}
?>
	<h2 class="social-title social-tab-active"><span><?php echo $tab; ?></span></h2>
</div>
<?php
	}

	public function top() {
		if (!$this->is_logged_in) {
?>
<div class="social-sign-in-links social-clearfix">
	<?php foreach (Social::$services as $key => $service): ?>
	<a class="social-<?php echo $key; ?> social-imr social-login comments" href="<?php echo Social_Helper::authorize_url($key); ?>" id="<?php echo $key; ?>_signin"><?php printf(__('Sign in with %s', Social::$i18n), $service->title()); ?></a>
	<?php endforeach; ?>
</div>
<div class="social-divider">
<span><?php _e('or', Social::$i18n); ?></span>
</div>
<?php
		}
	}

	public function logged_in_as() {
		$html = '<div class="social-identity">';
		$html .= get_avatar($this->current_user->ID, 40);
		if (current_user_can('manage_options')) {
			$html .= '<p class="social-input-row">' . $this->get_logged_in_management_controls() . ' <small class="social-psst">(' . wp_loginout(null, false) . ')</small></p>';
		}
		else {
			foreach (Social::$services as $key => $service) {
				if (count($service->accounts())) {
					$account = reset($service->accounts());
					$html .= '<p class="social-input-row">
						          <span class="social-' . $key . '-icon">' . $service->profile_name($account) . '.<small class="social-psst">(' . $service->disconnect_url($account) . ')</small></span>
					          </p>
					          <input type="hidden" name="' . Social::$prefix . 'post_account" value="' . $account->user->id . '" />';
				}
			}
		}
		$html .= '</div>';
		return $html;
	}

	public function get_logged_in_management_controls() {
		$html = '';
		$accounts = array();
		if (count(Social::$combined_services)) {
			foreach (Social::$combined_services as $key => $service) {
				$_accounts = Social::$services[$key]->accounts();
				if (isset(Social::$global_services[$key])) {
					foreach (Social::$global_services[$key]->accounts() as $id => $account) {
						$_accounts[$id] = $account;
					}
				}

				if (count($_accounts)) {
					$accounts[$key] = $_accounts;
				}
			}
		}
		if (count($accounts)) {
			$html .= '<select class="social-select" id="post_accounts" name="' . Social::$prefix . 'post_account">';
			$html .= '<option value="">' . __('WordPress Account', Social::$i18n) . '</option>';
			foreach ($accounts as $key => $_accounts) {
				$service = Social::$combined_services[$key];
				if (count($_accounts)) {
					$html .= '<optgroup label="' . __(ucfirst($key), Social::$i18n) . '">';
					foreach ($_accounts as $account) {
						$html .= '<option value="' . $account->user->id . '" rel="' . $service->profile_avatar($account) . '">' . $service->profile_name($account) . '</option>';
					}
					$html .= '</optgroup>';
				}
			}
			$html .= '</select>';
		}
		else {
			$html .= '<input type="hidden" name="' . Social::$prefix . 'post_account" value="" />';
			$user = $this->current_user;
			$html .= sprintf(__('Logged in as <a href="%1$s">%2$s</a>.', Social::$i18n), admin_url('profile.php'), $user->display_name);
		}
		return $html;
	}
}

/**
 * Social Comment Aggregation Logger
 */
class Social_Aggregate_Log {

	/**
	 * @var  array  logger instances
	 */
	public static $instances = array();

	/**
	 * Loads a post's instance.
	 *
	 * @static
	 * @param  int  $post_id
	 * @return Social_Aggregate_Log
	 */
	public static function instance($post_id) {
		if (!isset(self::$instances[$post_id])) {
			self::$instances[$post_id] = new self($post_id);
		}

		return self::$instances[$post_id];
	}

	/**
	 * @var  int  timestamp
	 */
	private $timestamp = 0;

	/**
	 * @var  int  post ID
	 */
	private $post_id = 0;

	/**
	 * @var  array  post logs
	 */
	private $logs = array();

	/**
	 * Sets the post ID and loads the existing logs.
	 *
	 * @param  int  $post_id
	 */
	public function __construct($post_id) {
		$this->timestamp = current_time('timestamp', 1);
		$this->post_id = $post_id;

		$this->logs = Social_Aggregate_Log::logs($post_id, false);
		$this->logs[$this->timestamp] = array();
	}

	/**
	 * Adds an item to the log.
	 *
	 * @param  string  $service  service key (twitter, facebook, etc.)
	 * @param  string  $id       object id
	 * @param  string  $type     type of response (reply, retweet, url)
	 * @param  bool    $ignored  comment ignored?
	 * @param  array   $data     extra data for output
	 * @return void
	 */
	public function add($service, $id, $type, $ignored = false, array $data = array()) {
		if (!isset($this->logs[$this->timestamp][$service])) {
			$this->logs[$this->timestamp][$service] = array();
		}

		foreach ($this->logs[$this->timestamp][$service] as $item) {
			if ($item['id'] === $id) {
				// No need to add the same item multiple times.
				return;
			}
		}

		$this->logs[$this->timestamp][$service][] = array(
			'id' => $id,
			'type' => $type,
			'ignored' => $ignored,
			'data' => $data,
		);
	}

	/**
	 * Saves the log.
	 *
	 * @return void
	 */
	public function save() {
		update_post_meta($this->post_id, Social::$prefix . 'aggregation_log', $this->logs);
	}

	/**
	 * Returns the logs for the post.
	 *
	 * @param  int   $post_id
	 * @param  bool  $echo
	 * @return array|string
	 */
	public static function logs($post_id, $echo = true) {
		$logs = get_post_meta($post_id, Social::$prefix . 'aggregation_log', true);
		if (empty($logs)) {
			$logs = array();
		}

		if (!$echo) {
			return $logs;
		}

		$output = '';
		if (empty($logs)) {
			$output = '<p>There have been no comments aggregated yet.</p>';
		}
		else {
			$i = 0;
			$logs = array_reverse($logs, true);
			foreach ($logs as $timestamp => $services) {
				++$i;
				$output .= '<h5 id="log-' . $i . '">' . date('j F Y, g:i a', $timestamp + (get_option('gmt_offset') * 3600)) . '</h5><ul id="log-' . $i . '-output" class="parent">';
				if (count($services)) {
					foreach ($services as $service => $items) {
						$service = Social::$combined_services[$service];
						$output .= '<li>' . $service->title . ':<ul>';

						if (count($items)) {
							$_items = array();
							foreach ($items as $item) {
								if (!isset($_items[$item['type']])) {
									$_items[$item['type']] = array();
								}
								$_items[$item['type']][] = $item;
							}
							foreach ($_items as $type => $items) {
								foreach ($items as $item) {
									$username = '';
									if (isset($item['data']['username'])) {
										$username = $item['data']['username'];
									}

									$id = $item['id'];
									if (isset($item['data']['parent_id'])) {
										$ids = explode('_', $item['id']);
										$id = $item['data']['parent_id'] . '_' . $ids[0];
										$item['id'] = $id . '#' . $ids[1];
									}

									$link = $service->status_url($username, $id);
									$output .= '<li>';
									$output .= '<a href="' . $link . '" target="_blank">#' . $item['id'] . '</a>';
									switch ($type) {
										case 'reply':
											$output .= ' (Reply Search)';
											break;
										case 'url':
											$output .= ' (URL Search)';
											break;
										case 'retweet':
											$output .= ' (Retweet Search)';
											break;
										default:
											$output .= ' (' . $type . ')';
											break;
									}

									if ($item['ignored'] == true) {
										$output .= ' (Existing Comment)';
									}
									$output .= '</li>';
								}
							}
						}
						else {
							$output .= '<li style="list-style: none">No results found.</li>';
						}
						$output .= '</ul></li>';
					}
				}
				else {
					$output .= '<li style="list-style: none">No results found.</li>';
				}
				$output .= '</ul>';
			}
		}

		return $output;
	}

} // End Social_Aggregate_Log

if (!defined('IS_PROFILE_PAGE')) {
	define('IS_PROFILE_PAGE', false);
}

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
define('SOCIAL_PATH', dirname($social_file) . '/');

$social = new Social;

// Activation Hook
register_activation_hook(SOCIAL_FILE, array($social, 'install'));
register_deactivation_hook(SOCIAL_FILE, array($social, 'deactivate'));

// Actions
add_action('init', array($social, 'init'), 1);
add_action('init', array($social, 'request_handler'), 2);
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

} // End class_exists check
