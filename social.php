<?php
/*
Plugin Name: Social
Plugin URI:
Description: Social (Includes Facebook and Twitter)
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
// TODO Add AJAX call to replace the comment form instead of refreshing the page upon authentication. (Comment form only)

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

$social = new Social;

// Activation Hook
register_activation_hook(SOCIAL_FILE, array($social, 'install'));
register_deactivation_hook(SOCIAL_FILE, array($social, 'deactivate'));

// Actions
add_action('init', array($social, 'init'), 1);
add_action('init', array($social, 'request_handler'), 2);
add_action('do_meta_boxes', array($social, 'do_meta_boxes'));
add_action('save_post', array($social, 'set_broadcast_meta_data'));
add_action('comment_post', array($social, 'comment_post'));
add_action('social_aggregate_comments', array($social, 'aggregate_comments'));

// Admin Actions
add_action('admin_menu', array($social, 'admin_menu'));

// Filters
add_filter('redirect_post_location', array($social, 'redirect_post_location'), 10, 2);
add_filter('comments_template', array($social, 'comments_template'));
add_filter('get_avatar_comment_types', array($social, 'get_avatar_comment_types'));
add_filter('get_avatar', array($social, 'get_avatar'), 10, 5);
add_filter('get_comment_author_url', array($social, 'get_comment_author_url'));
add_filter('get_comment_author', array($social, 'get_comment_author'));
add_filter('register', array($social, 'register'));
add_filter('loginout', array($social, 'loginout'));

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
	public static $i10n = 'social';

	/**
	 * @var  array  services registered to Social
	 */
	public static $services = array();

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
		'broadcast_format' => '{title}: {content} {url}'
	);

	/**
	 * @var  bool  update plugin settings
	 */
	protected static $update = true;

	/**
	 * @var bool  upgrade the plugin
	 */
	protected static $upgrade = false;

	/**
	 * Used to log debug data.
	 *
	 * @static
	 * @param  string  $content
	 * @return void
	 */
	public static function debug($content) {
		$debug = get_option(Social::$prefix.'debug', false);
		if ($debug) {
			$fh = fopen(plugins_url('log.txt', SOCIAL_FILE), 'a');
			fwrite($fh, $content."\n");
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
		return apply_filters(Social::$prefix.'broadcast_tokens', $defaults);
	}

	/**
	 * Returns the service object.
	 *
	 * @static
	 * @param  string  $service  name of the service
	 * @param  int     $user_id  custom user to load
	 * @return Social_Facebook|Social_Twitter|bool
	 */
	public function service($service, $user_id = null) {
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

		if (isset(Social::$services[$service])) {
			return Social::$services[$service];
		}

		return false;
	}

	/**
	 * Sets and returns the option.
	 *
	 * @static
	 * @param  string  $key    option key
	 * @param  string  $value  option value
	 * @return array|void
	 */
	public function option($key, $value = null) {
		if ($value === null) {
			return Social::$options[$key];
		}

		Social::$options[$key] = $value;
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
			wp_die(__("Sorry, Social requires PHP 5.2.1 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i10n));
		}
	}

	/**
	 * Remove the CRON unpon plugin deactivation.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook(Social::$prefix.'aggregate_comments');
	}

	/**
	 * Initializes the plugin.
	 */
	public function init() {
		$services = get_user_meta(get_current_user_id(), Social::$prefix.'accounts', true);
		if (!empty($services)) {
			foreach ($services as $service => $accounts) {
				if (!empty($accounts)) {
					Social::$update = false;
					break;
				}
			}
		}

		if (is_admin()) {
			if (Social::$update) {
				add_action('admin_notices', array($this, 'display_upgrade'));
			}

			wp_enqueue_style('social_css', plugins_url('/assets/admin.css', SOCIAL_FILE), array(), Social::$version, 'screen, tv, projection');
			wp_enqueue_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array('jquery'), Social::$version, true);
		}
		else {
			wp_enqueue_style('social_css', plugins_url('/assets/comments.css', SOCIAL_FILE), array(), Social::$version, 'screen, tv, projection');
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-tabs');
			wp_enqueue_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array('jquery', 'jquery-ui-tabs'), Social::$version, true);
		}

		if (version_compare(PHP_VERSION, '5.2.1', '<')) {
			wp_die(__("Sorry, Social requires PHP 5.2.1 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i10n));
		}

		// Load Twitter/Facebook
		require SOCIAL_PATH.'lib/social/interface/service.php';
		require SOCIAL_PATH.'lib/social/helper.php';
		require SOCIAL_PATH.'lib/social/service.php';
		require SOCIAL_PATH.'social-facebook.php';
		require SOCIAL_PATH.'social-twitter.php';

		// Load the settings
		$options = apply_filters(Social::$prefix.'options', $this->options());
		foreach ($options as $key => $default) {
			$value = get_option(Social::$prefix.$key, $default);
			if (empty($value)) {
				switch ($key) {
					case 'install_date':
						$value = time();
					break;
					case 'installed_version':
						$value = Social::$version;
					break;
					default:
						$value = $default;
					break;
				}

				update_option(Social::$prefix.$key, $value);
			}

			if ($key == 'installed_version' and (int) $value < (int) Social::$version) {
				// Need to run an upgrade
				Social::$upgrade = true;
			}

			$this->option($key, $value);
		}

		// Schedule the CRON?
		if (wp_next_scheduled(Social::$prefix.'aggregate_comments') === false) {
			wp_schedule_event(time() + 1200, 'hourly', Social::$prefix.'aggregate_comments');
		}

		// Register the Social services
		Social::$services = apply_filters(Social::$prefix.'register_service', Social::$services);

		// Load the user's accounts
		if (!empty($services)) {
			foreach ($services as $service => $accounts) {
				if (!isset(Social::$services[$service])) {
					continue;
				}
				$this->service($service)->accounts($accounts);
			}
		}
	}

	/**
	 * Displays the upgrade message.
	 */
	public function display_upgrade() {
		$message = sprintf(__('To broadcast to Twitter or Facebook, please update your <a href="%s">Social settings</a>', Social::$i10n), Social_Helper::settings_url());
		echo '<div class="error"><p>'.$message.'</p></div>';
	}

	/**
	 * Handles the request.
	 */
	public function request_handler() {
		if (!empty($_POST[Social::$prefix.'action'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'])) {
				wp_die('Oops, please try again.');
			}

			switch ($_POST[Social::$prefix.'action']) {
                case 'broadcast_options':
                    $this->broadcast_options($_POST['post_ID'], $_POST['location']);
				break;
				case 'settings':
					update_option(Social::$prefix.'broadcast_format', $_POST[Social::$prefix.'broadcast_format']);
					wp_redirect(Social_Helper::settings_url(array('saved' => 'true')));
					exit;
				break;
			}
		}
		else if (!empty($_GET[Social::$prefix.'action'])) {
			switch ($_GET[Social::$prefix.'action']) {
				case 'reload_form':
					$form = Social::comment_form();
					echo json_encode(array(
						'result' => 'success',
						'html' => $form,
						'disconnect_url' => wp_loginout('', false)
					));
					exit;
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
			$account = (object) array(
				'keys' => $data->keys,
				'user' => $data->user
			);

			// Add the account to the service.
			$service = $this->service($data->service)->account($account);

			// Do we need to create a user?
			if (!$service->loaded()) {
				$service->create_user($account);
			}

			// Save the services
			$service->save($account);
?>
<html>
<head>
	<title>Authorized</title>
	<?php wp_enqueue_script('jquery'); ?>
	<?php wp_head(); ?>
</head>
<script type="text/javascript">
	jQuery(function(){
		window.close();
	});
</script>
</html>
<?php
			exit;
		}
		else if (isset($_GET[Social::$prefix.'_disconnect'])) {
			$service = $this->service($_GET['service']);
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
	}

	/**
     * Add Meta Boxes
     */
    public function do_meta_boxes() {
		global $post;

		// Already broadcasted?
		$broadcasted = get_post_meta($post->ID, Social::$prefix.'broadcasted', true);
		if (!Social::$update and $broadcasted != '1' and $post->post_status != 'publish') {
			add_meta_box(Social::$prefix.'meta_broadcast', __('Social', Social::$i10n), array($this, 'add_meta_box'), 'post');
		}
    }

	/**
	 * Adds the broadcasting meta box.
	 */
	public function add_meta_box() {
		global $post;

		if (!Social::$update) {
			$broadcast_accounts = get_post_meta($post->ID, Social::$prefix.'broadcast_accounts', true);

			// Have Twitter account(s)?
			$services = Social::$services;
			foreach (Social::$services as $key => $service) {
				if (count($service->accounts())) {
					$content = get_post_meta($post->ID, Social::$prefix.$key.'_content', true);
					$notify = get_post_meta($post->ID, Social::$prefix.'notify_'.$key, true);
					$counter = $service->max_broadcast_length();
					if (!empty($content)) {
						$counter = $counter - strlen($content);
					}
?>
<input type="hidden" name="<?php echo Social::$prefix.'notify[]'; ?>" value="<?php echo $key; ?>" />
<div style="padding:10px 0">
	<span class="service-label"><?php _e('Send post to '.$service->title().'?', Social::$i10n); ?></span>
	<input type="radio" name="<?php echo Social::$prefix.'notify_'.$key; ?>" id="<?php echo Social::$prefix.'notify_'.$key.'_yes'; ?>" class="social-toggle" value="1" <?php echo checked('1', $notify, false); ?> /> <label for="<?php echo Social::$prefix.'notify_'.$key.'_yes'; ?>" class="social-toggle-label"><?php _e('Yes', Social::$i10n); ?></label>
	<input type="radio" name="<?php echo Social::$prefix.'notify_'.$key; ?>" id="<?php echo Social::$prefix.'notify_'.$key.'_no'; ?>" class="social-toggle" value="0" <?php echo checked('0', $notify, false); ?> /> <label for="<?php echo Social::$prefix.'notify_'.$key.'_no'; ?>" class="social-toggle-label"><?php _e('No', Social::$i10n); ?></label>
	<div id="<?php echo $key.'_options'; ?>" class="form-wrap"<?php echo ($notify != '1' ? ' style="display:none"' : ''); ?>>
		<div class="form-field">
			<label for="<?php echo $key.'_preview'; ?>" class="broadcast-label"><?php _e('Content (Optional)', Social::$i10n); ?></label>
			<span class="social-preview-counter" id="<?php echo $key.'_counter'; ?>"><?php echo $counter; ?></span>
			<textarea rows="3" cols="20" id="<?php echo $key.'_preview'; ?>" name="<?php echo Social::$prefix.$key.'_content'; ?>" class="social-preview-content"><?php echo $content; ?></textarea>
		</div>
		<div class="form-field">
			<label><?php _e('Broadcast to These Accounts:', Social::$i10n); ?></label>
			<?php foreach ($service->accounts() as $account): ?>
			<div class="social-broadcastable">
				<input type="checkbox" name="<?php echo Social::$prefix.'broadcast_'.$key.'_accounts[]'; ?>" id="<?php echo Social::$prefix.$key.'_'.$account->user->id; ?>" value="<?php echo $account->user->id; ?>"<?php echo ((empty($broadcast_accounts) or array_search($account->user->id, $broadcast_accounts[$key]) !== false) ? ' checked="checked"' : ''); ?> />
				<span class="<?php echo 'social-'.$key.'-icon'; ?>"><i></i><label for="<?php echo Social::$prefix.$key.'_'.$account->user->id; ?>"><?php echo $service->profile_name($account); ?></label></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php
				}
			}
		}
	}

	/**
     * Show the broadcast options if publishing.
     *
     * @param  string  $location  default post-publish location
     * @param  int     $post_id   post ID
     * @return string|void
     */
    public function redirect_post_location($location, $post_id) {
        if (isset($_POST['publish'])) {
            $this->broadcast_options($post_id, $location);
        }
        return $location;
    }

	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
	 */
	public function admin_menu() {
		add_options_page(
			__('Social Options', Social::$i10n),
			__('Social', Social::$i10n),
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
<input type="hidden" name="<?php echo Social::$prefix; ?>action" value="settings" />
<?php if (isset($_GET['saved'])): ?>
<div id="message" class="updated">
	<p><strong><?php _e('Social settings have been updated.', Social::$i10n); ?></strong></p>
</div>
<?php endif; ?>
<div class="wrap" id="social_options_page">
	<h2><?php _e('Social Options', Social::$i10n); ?></h2>

	<h3><?php _e('Broadcasting Format', Social::$i10n); ?></h3>
	<p><?php _e('Define how you would like your posts to be formatted when being broadcasted.'); ?></p>

	<table class="form-table">
		<tr>
			<th style="width:100px" colspan="2">
				<strong><?php _e('Tokens:', Social::$i10n); ?></strong>
				<ul style="margin:10px 0 0 15px;">
					<?php foreach (Social::broadcast_tokens() as $token => $description): ?>
					<li><span style="float:left;width:60px"><?php echo $token; ?></span> - <?php echo $description; ?></li>
					<?php endforeach; ?>
				</ul>
			</th>
		</tr>
		<tr>
			<th style="width:100px"><label for="<?php echo Social::$prefix.'broadcast_format'; ?>">Format</label></th>
			<td><input type="text" class="text" name="<?php echo Social::$prefix.'broadcast_format'; ?>" id="<?php echo Social::$prefix.'broadcast_format'; ?>" style="width:400px" value="<?php echo Social::option('broadcast_format'); ?>" /></td>
		</tr>
	</table>
	<p class="submit"><input type="submit" name="submit" value="Save Settings" class="button-primary" /></p>

	<h3><?php _e('Connect to Social Networks', Social::$i10n); ?></h3>
	<p><?php _e('Before you can broadcast to your social networks, you will need to connect your account(s).', Social::$i10n); ?></p>
	<?php foreach (Social::$services as $key => $service): ?>
	<div class="social-settings-connect">
		<?php foreach ($service->accounts() as $account): ?>
		<?php
			$profile_url = $service->profile_url($account);
			$profile_name = $service->profile_name($account);
			$url = sprintf('<a href="%s">%s</a>', $profile_url, $profile_name);
			$disconnect = $service->disconnect_url($account, true);
			$output = sprintf(__('Connected to %s. %s', Social::$i10n), $url, $disconnect);
		?>
		<span class="social-<?php echo $key; ?>-icon big"><i></i><?php echo $output; ?></span>
		<?php endforeach; ?>

		<a href="<?php echo Social_Helper::authorize_url($key, true); ?>" id="<?php echo $key; ?>_signin" class="social-login"><span><?php _e('Sign In With '.$service->title, Social::$i10n); ?></span></a>
	</div>
	<?php endforeach; ?>
</div>
</form>
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
		$notify = array();
		foreach (Social::$services as $key => $service) {
			$meta = get_post_meta($post_id, Social::$prefix.'notify_'.$key, true);
			if ($meta == '1') {
				$notify[] = $key;
			}
		}

		$errors = array();
		if (!empty($notify)) {
			// Post shouldn't be published yet
			$post = get_post($post_id);
			if ($post->post_status == 'publish') {
				$post->post_status = 'draft';
				wp_update_post($post);
			}

			if (isset($_POST[Social::$prefix.'action'])) {
				foreach (Social::$services as $key => $service) {
					if (isset($notify[$key]) and empty($_POST[Social::$prefix.$key.'_content'])) {
						$errors[$key] = 'Please enter some content for '.$service->title().'.';
					}
				}

				if (!count($errors)) {
					foreach (Social::$services as $key => $service) {
						update_post_meta($post_id, Social::$prefix.$key.'_content', $_POST[Social::$prefix.$key.'_content']);
					}

					Social::broadcast($post_id);
					$post->post_status = 'publish';
					wp_update_post($post);
					wp_redirect($location);
					return;
				}
			}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php __('Social Broadcasting Options', 'social'); ?></title>
	<?php
		wp_admin_css('install', true);
		do_action('admin_print_styles');
	?>
</head>
<body>
<h1 id="logo"><?php _e('Social Broadcasting Options', Social::$i10n); ?></h1>
<?php if (count($errors)): ?>
<div id="social_error">
	<?php foreach ($errors as $error): ?>
	<?php echo $error; ?><br />
	<?php endforeach; ?>
</div>
<?php endif; ?>
<p><?php __('You have chosen to broadcast this blog post to your social accounts. Use the form below to edit your broadcasted messages.', Social::$i10n); ?></p>
<form id="setup" method="post" action="<?php echo admin_url(); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="post_ID" value="<?php echo $post_id; ?>" />
<input type="hidden" name="location" value="<?php echo $location; ?>" />
<input type="hidden" name="<?php echo Social::$prefix; ?>action" value="broadcast_options" />
<table class="form-table">
<?php foreach ($notify as $service): ?>
<?php
	// Custom content?
	$content = get_post_meta($post_id, Social::$prefix.$service.'_content', true);
	$service = $this->service($service);
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
?>
<tr>
	<th scope="row">
		<label for="<?php echo $service->service.'_preview'; ?>"><?php _e($service->title(), Social::$i10n); ?></label><br />
		<span id="<?php echo $service->service.'_counter'; ?>" class="social-preview-counter"><?php echo $counter; ?></span>
	</th>
	<td><textarea id="<?php echo $service->service.'_preview'; ?>" name="<?php echo Social::$prefix.$service->service.'_content'; ?>" class="social-preview-content" cols="40" rows="5"><?php echo ((isset($_POST[Social::$prefix.$service->service.'_content']) and !empty($_POST[Social::$prefix.$service->service.'_content'])) ? $_POST[Social::$prefix.$service->service.'_content'] : $content); ?></textarea></td>
</tr>
<?php endforeach; ?>
</table>
<p class="step">
	<input type="submit" value="<?php _e('Publish', Social::$i10n); ?>" class="button" />
	<a href="<?php echo get_edit_post_link($post_id, 'url'); ?>" class="button">Cancel</a>
</p>
</form>
<script type="text/javascript" src="<?php echo includes_url('/js/jquery/jquery.js'); ?>"></script>
<script type="text/javascript" src="<?php echo plugins_url('/assets/js/admin.js', SOCIAL_FILE); ?>"></script>
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
	 * @param  int  $post_id
	 */
	public function set_broadcast_meta_data($post_id) {
		$broadcast = false;
		$broadcast_accounts = array();
		foreach (Social::$services as $key => $service) {
			$post_key = Social::$prefix.'notify_'.$key;
			if (isset($_POST[$post_key])) {
				update_post_meta($post_id, $post_key, $_POST[$post_key]);

				$content_key = Social::$prefix.$key.'_content';
				if ($_POST[$post_key] == '1') {
					$broadcast = true;
					if (isset($_POST[$content_key]) and !empty($_POST[$content_key])) {
						update_post_meta($post_id, $content_key, $_POST[$content_key]);
					}
					else {
						$content = get_post_meta($post_id, $content_key, true);
						update_post_meta($post_id, $content_key, $content);
					}
				}
				else {
					delete_post_meta($post_id, $content_key);
				}

				// Ignored accounts
				$broadcast_accounts[$key] = $_POST[Social::$prefix.'broadcast_'.$key.'_accounts'];
			}
		}
		update_post_meta($post_id, Social::$prefix.'broadcast_accounts', $broadcast_accounts);

		if ($broadcast) {
			$broadcasted = get_post_meta($post_id, Social::$prefix.'broadcasted', true);
			if (empty($broadcasted) or $broadcasted != '1') {
				update_post_meta($post_id, Social::$prefix.'broadcasted', '0');
			}
		}
		else {
			delete_post_meta($post_id, Social::$prefix.'broadcasted');
		}
	}

	/**
	 * Broadcast the post to Twitter and/or Facebook.
	 *
	 * @param  int  $post_id
	 */
	public function broadcast($post_id) {
        $broadcasted = get_post_meta($post_id, Social::$prefix.'broadcasted', true);
        if ($broadcasted == '0' or empty($broadcasted)) {
	        $broadcast_accounts = get_post_meta($post_id, Social::$prefix.'broadcast_accounts', true);
	        if (!empty($broadcast_accounts)) {
		        $ids = array();
				foreach (Social::$services as $key => $service) {
					$notify = get_post_meta($post_id, Social::$prefix.'notify_'.$key, true);

					if ($notify == '1') {
						$content = get_post_meta($post_id, Social::$prefix.$key.'_content', true);
						if (!empty($content)) {
							foreach ($service->accounts() as $account) {
								if (in_array($account->user->id, $broadcast_accounts[$key])) {
									$ids[$key]["{$account->user->id}"] = $service->status_update($account, $content)->id;
								}
							}
						}
					}

					delete_post_meta($post_id, Social::$prefix.'notify_'.$key);
				}

		        update_post_meta($post_id, Social::$prefix.'broadcasted_ids', $ids);
	        }
	        update_post_meta($post_id, Social::$prefix.'broadcasted', '1');
        }
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

		require SOCIAL_PATH.'lib/social/walker/comment.php';
		$file = trailingslashit(dirname(SOCIAL_FILE)).'comments.php';
		return $file;
	}

	/**
	 * Returns an array of comment types that display avatars.
	 *
	 * @param  array  $types  default WordPress types
	 * @return array
	 */
	public function get_avatar_comment_types($types) {
		return array_merge($types, array('facebook', 'twitter'));
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
		$service = $this->service($comment->comment_type, ($comment->user_id ? $comment->user_id : null));
		if ($service !== false) {
			$account_id = get_comment_meta($comment->comment_ID, Social::$prefix.'account_id', true);
			$image = $service->profile_avatar($service->account($account_id), $comment->comment_ID);

			return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$comment->comment_type}' height='{$size}' width='{$size}' />";
		}

		return $avatar;
	}

	/**
	 * Builds the URL to the author's Facebook/Twitter.
	 *
	 * @param  string  $url
	 * @return string
	 */
	public function get_comment_author_url($url) {
		global $comment;
		if ($comment->user_id) {
			$service = $this->service($comment->comment_type, $comment->user_id);
			if ($service !== false) {
				$account_id = get_comment_meta($comment->comment_ID, Social::$prefix.'account_id', true);
				return $service->profile_url($service->account($account_id));
			}
		}
		return $url;
	}

	/**
	 * Gets the comment author's username from Facebook/Twitter.
	 *
	 * @param  string  $author
	 * @return string
	 */
	public function get_comment_author($author) {
		global $comment;
		if ($comment->user_id) {
			$service = $this->service($comment->comment_type, $comment->user_id);
			if ($service !== false) {
				$account_id = get_comment_meta($comment->comment_ID, Social::$prefix.'account_id', true);
				return $service->profile_name($service->account($account_id));
			}
		}
		return $author;
	}

	/**
	 * Displays a comment.
	 *
	 * @param  object  $comment  comment object
	 * @param  array   $args
	 * @param  int     $depth
	 */
	public function comment($comment, $args, $depth) {
		$GLOBALS['comment'] = $comment;
?>
<li class="social-comment social-<?php echo (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type); ?>" id="li-comment-<?php comment_ID(); ?>">
	<div class="social-comment-inner" id="comment-<?php comment_ID(); ?>">
		<div class="social-comment-header">
			<div class="social-comment-author vcard">
				<?php echo get_avatar($comment, 40); ?>
				<?php printf('<cite class="social-fn fn">%s</cite>', get_comment_author_link()); ?>
				<?php if ($depth > 1): ?>
					<span class="social-replied social-imr"><?php _e('replied:', Social::$i10n); ?></span>
				<?php endif; ?>
			</div><!-- .comment-author .vcard -->
			<div class="social-comment-meta"><span class="social-posted-from"><?php _e('via Twitter', Social::$i10n); ?></span> <a href="#" class="social-posted-when"><?php printf(__('%s ago', Social::$i10n), human_time_diff(strtotime($comment->comment_date))); ?></a></div>
		</div>
		<div class="social-comment-body">
			<?php if ($comment->comment_approved == '0'): ?>
				<em class="comment-awaiting-moderation"><?php _e('Your comment is awaiting moderation.', 'social'); ?></em>
				<br />
			<?php endif; ?>
			<p>
				<?php comment_text(); ?>
			</p>
		</div>
		<div class="social-actions">
			<?php comment_reply_link(array_merge($args, array('depth' => $depth, 'max_depth' => $args['max_depth']))); ?>
		</div><!-- .reply -->
	</div><!-- #comment-##  -->
<?php
	}

	/**
	 * Sets the comment type upon being saved.
	 *
	 * @param  int  $comment_ID
	 */
	public function comment_post($comment_ID) {
		global $wpdb, $comment_content;
		$type = false;
		$services = Social::$services;
		if (!empty($services)) {
			$account_id = $_POST[Social::$prefix.'post_account'];

			$url = get_comment_link($comment_ID);
			$url_length = strlen($url) + 1;
			$comment_length = strlen($comment_content);
			$combined_length = $url_length + $comment_length;
			foreach ($services as $key => $service) {
				$max_length = $service->max_broadcast_length();
				if ($combined_length > $max_length) {
					$output = substr($comment_content, 0, ($max_length - $url_length - 3)).'...';
				} else {
					$output = $comment_content;
				}
				$output .= ' '.$url;

				foreach ($service->accounts() as $account) {
					if ($account_id == $account->user->id) {
						$service->status_update($account, $output);
						update_comment_meta($comment_ID, Social::$prefix.'account_id', $account_id);
						$wpdb->query("UPDATE $wpdb->comments SET comment_type='$key' WHERE comment_ID='$comment_ID'");
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
			$commenter = get_user_meta(get_current_user_id(), Social::$prefix.'commenter', true);
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
			$commenter = get_user_meta(get_current_user_id(), Social::$prefix.'commenter', true);
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
			$link = explode('>'.__('Log in'), $link);
			$link = $link[0].' id="'.Social::$prefix.'login">'.__('Log in').$link[1];
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
		$url = site_url().'?';
		$params = array();
		foreach ($args as $k => $v) {
			$params[] = $k.'='.$v;
		}
		$url .= implode('&', $params);

		return $url;
	}

	/**
	 * Runs the aggregation of comments for all of the services.
	 */
	public function aggregate_comments() {
		global $wpdb;
		// Load the ignored posts
		$ignored = get_option(Social::$prefix.'ignored_posts_for_aggregation', array());
		$queued = get_option(Social::$prefix.'queued_for_aggregation', array());

		// Load all the posts
		$sql = "
			SELECT p.ID, p.post_author, p.post_date, p.guid, (
			           SELECT b.meta_value
			             FROM $wpdb->postmeta AS b
			            WHERE b.meta_key = '".Social::$prefix."broadcasted_ids'
			              AND b.post_id = p.ID
			       ) AS broadcasted_ids
			  FROM $wpdb->posts AS p
			 WHERE p.post_status = 'publish'
			   AND p.comment_status = 'open'
			   AND p.post_type = 'post'
			   AND p.post_parent = '0'
	    ";
		if (is_array($ignored) and count($ignored)) {
			$sql .= "AND p.ID NOT IN (".implode(',', $ignored).")";
		}
		$posts = $wpdb->get_results($sql, OBJECT);

		// Compile the search parameters
		$broadcasted_ids = array();
		foreach ($posts as $post) {
			$timestamp = time() - strtotime($post->post_date);

			$hours = 0;
			if ($timestamp >=  172800) {
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

			if (!isset($queued[$post->ID]) or $queued[$post->ID] > $hours) {
				$queued[$post->ID] = (string) $hours;

				$urls = array(
					urlencode(home_url('?p='.$post->ID)),
					urlencode(get_permalink($post->ID)),
				);

				$broadcasted = maybe_unserialize($post->broadcasted_ids);
				if (is_array($broadcasted)) {
					foreach ($broadcasted as $service => $ids) {
						if (!isset($broadcasted_ids[$service])) {
							$broadcasted_ids[$service] = $ids;
						}
						else {
							$broadcasted_ids[$service] = array_merge($broadcasted_ids[$service], $ids);
						}
					}
				}

				// Run search!
				foreach (Social::$services as $key => $service) {
					$results = $service->search_for_replies($post, $urls, (isset($broadcasted_ids[$key]) ? $broadcasted_ids[$key] : null));

					// Results?
					if (is_array($results)) {
						$service->save_replies($post->ID, $results);
					}
				}

				// Remove the post from the CRON.
				if ($hours === 48) {
					unset($queued[$post->ID]);
					$ignored[] = $post->ID;
				}

				update_option(Social::$prefix.'ignored_posts_for_aggregation', $ignored);
				update_option(Social::$prefix.'queued_for_aggregation', $queued);
			}
		}
	}

	/**
	 * Loads the comment form.
	 *
	 * @static
	 * @return string
	 */
	public static function comment_form() {
		try {
			include SOCIAL_PATH.'comment-form.php';
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}

		return ob_get_clean();

	}

} // End Social
