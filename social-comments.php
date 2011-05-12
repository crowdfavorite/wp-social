<?php
/*
Plugin Name: Social Comments
Plugin URI:
Description: Social integration for comments.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR', 'wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__))) {
	define('SOCIAL_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__));
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(dirname(__FILE__)).'/'.basename(__FILE__))) {
	define('SOCIAL_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(dirname(__FILE__)).'/'.basename(__FILE__));
}

// Activation Hook
register_activation_hook(SOCIAL_FILE, array('Social', 'install'));

// Actions
add_action('init', array('Social', 'init'));
add_action('init', array('Social', 'request_handler'));
add_action('do_meta_boxes', array('Social', 'do_meta_boxes'));
add_action('publish_post', array('Social', 'set_broadcast'));
add_action('save_post', array('Social', 'set_broadcast'));
add_action('comment_post', array('Social', 'comment_post'));

// Admin Actions
add_action('admin_menu', array('Social', 'admin_menu'));

// Filters
add_filter('redirect_post_location', array('Social', 'redirect_post_location'));
add_filter('comments_template', array('Social', 'comments_template'));
add_filter('get_avatar_comment_types', array('Social', 'get_avatar_comment_types'));
add_filter('get_avatar', array('Social', 'get_avatar'), 10, 5);
add_filter('register', array('Social', 'register'));
add_filter('loginout', array('Social', 'loginout'));

/**
 * Custom comment walker.
 *
 * @package Social
 */
class Social_Walker_Comment extends Walker_Comment {

	/**
	 * @see Walker::start_lvl()
	 * @since 2.7.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of comment.
	 * @param array $args Uses 'style' argument for type of HTML list.
	 */
	function start_lvl(&$output, $depth, $args) {
		$GLOBALS['comment_depth'] = $depth + 1;

		switch ($args['style']) {
			case 'div':
				break;
			case 'ol':
				echo "<ol class='social-children'>\n";
				break;
			default:
			case 'ul':
				echo "<ul class='social-children'>\n";
				break;
		}
	}

	/**
	 * @see Walker::end_lvl()
	 * @since 2.7.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of comment.
	 * @param array $args Will only append content if style argument value is 'ol' or 'ul'.
	 */
	function end_lvl(&$output, $depth, $args) {
		$GLOBALS['comment_depth'] = $depth + 1;

		switch ($args['style'] ) {
			case 'div':
				break;
			case 'ol':
				echo "</ol>\n";
				break;
			default:
			case 'ul':
				echo "</ul>\n";
				break;
		}

		echo "</li>\n";
	}

} // End Social_Walker_Comment

/**
 * Social Comments Core
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
	 * @var  array  the user's accounts
	 */
	public static $accounts = array();

	/**
	 * @var  array  default options
	 */
	protected static $options = array(
		'debug' => 'false',
		'install_date' => '',
		'installed_version' => ''
	);

	/**
	 * @var  bool  update plugin flag
	 */
	protected static $update = true;

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
			$fh = fopen(plugins_url('/log.txt', SOCIAL_FILE), 'a');
			fwrite($fh, $content."\n");
			fclose($fh);
		}
    }

	/**
	 * Builds the settings URL for the plugin.
	 *
	 * @static
	 * @param  array  $params
	 * @return void
	 */
	public static function settings_url(array $params = null) {
		$path = 'options-general.php?page='.basename(__FILE__);

		if ($params !== null) {
			foreach ($params as $key => $value) {
				$path .= '&'.$key.'='.urlencode($value);
			}
		}

		return admin_url($path);
	}

	/**
	 * Sets and returns the option(s).
	 *
	 * @static
	 * @param  string  $key    option key
	 * @param  string  $value  option value
	 * return array
	 */
	private static function option($key = null, $value = null) {
		if ($key === null) {
			return Social::$options;
		}
		else if ($key !== null and $value === null) {
			return Social::$options[$key];
		}

		Social::$options[$key] = $value;
	}

	/**
	 * Registers the plugin to WordPress.
	 *
	 * @static
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		// require PHP 5
		if (version_compare(PHP_VERSION, '5.0.0', '<')) {
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social Comments requires PHP 5 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i10n));
		}

		// Set the options
		foreach (Social::option() as $option => $default) {
			add_option(Social::$prefix.$option, $default);
		}
		add_option(Social::$prefix.'update_hash', '');
	}

	/**
	 * Initializes the plugin.
	 *
	 * @static
	 * @return void
	 */
	public static function init() {
		// Load the settings
		foreach (Social::option() as $key => $default) {
			$value = get_option(Social::$prefix.$key, $default);
			Social::option($key, $value);
		}

		// Load the accounts
		Social::$accounts = get_user_meta(get_current_user_id(), Social::$prefix.'accounts', true);
		if (is_admin()) {
			foreach (Social::$accounts as $service => $accounts) {
				foreach ($accounts as $id => $account) {
					Social::$update = false;
					Social::$accounts[$service][$id] = Social_Service::instance($service, $account);
				}
			}

			// Update actions.
			if (Social::$update) {
				add_action('admin_notices', array('Social', 'display_upgrade'));
			}

			// Add the CSS
			wp_register_style('social_css', plugins_url('/assets/admin.css', SOCIAL_FILE));
		}
		else {
			// Add the CSS
			wp_register_style('social_css', plugins_url('/assets/comments.css', SOCIAL_FILE));

			// Add the JS
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-tabs');
		}

		// Defaults
		wp_register_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array(), false, true);
		wp_enqueue_script('social_js');
		wp_enqueue_style('social_css');
	}

	/**
	 * Return the service's accounts.
	 *
	 * @static
	 * @param  string  $service  twitter|facebook
	 * @return array
	 */
	public static function accounts($service) {
		if (!isset(Social::$accounts[$service])) {
			return array();
		}

		return Social::$accounts[$service];
	}

	/**
	 * Handles the request.
	 *
	 * @static
	 * @return void
	 */
	public static function request_handler() {
		if (!empty($_POST[Social::$prefix.'action'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'])) {
				wp_die('Oops, please try again.');
			}

			switch ($_POST[Social::$prefix.'action']) {
                case 'broadcast_options':
                    Social::broadcast_options($_POST['post_ID'], $_POST['location']);
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

			$service = Social_Service::instance($data->service, $account);

			// Add the service
			$service->add($data->user, $data->keys->public, $data->keys->secret);

			// Do we need to create a user?
			if (!$service->loaded()) {
				$service->create_user();
			}

			// Save the services
			$service->save();
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
		else if (isset($_GET['social_disconnect'])) {
			$service = Social_Service::instance($_GET['service']);
			$service->disconnect($_GET['id']);

			if (is_admin()) {
				wp_redirect(Social::settings_url());
			}
			else {
				wp_logout();
				wp_redirect(site_url());
			}
			exit;
		}
	}

	/**
     * Add Meta Boxes
     *
     * @static
     * @return void
     */
    public static function do_meta_boxes() {
		global $post;

		// Already broadcasted?
		$broadcasted = get_post_meta($post->ID, Social::$prefix.'broadcasted', true);
		if (!self::$update and $broadcasted != 'yes') {
			add_meta_box(Social::$prefix.'meta_broadcast', __('Social Comments', Social::$i10n), array('Social', 'add_meta_box'), 'post');
		}
    }

	/**
	 * Adds the broadcasting meta box.
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function add_meta_box() {
		global $post;

		if (!self::$update) {
			$broadcast_accounts = get_post_meta($post->ID, Social::$prefix.'broadcast_accounts', true);

			// Have Twitter account(s)?
			if (isset(Social::$accounts['twitter']) and count(Social::$accounts['twitter'])) {
				$twitter_content = get_post_meta($post->ID, Social::$prefix.'twitter_content', true);

				// Notify?
				$notify_twitter = get_post_meta($post->ID, Social::$prefix.'notify_twitter', true);
				if (!$notify_twitter) {
					$notify_twitter = 'no';
				}

				$counter = 140;
				if (!empty($twitter_content)) {
					$counter = $counter - strlen($twitter_content);
				}
?>
<div style="padding:10px 0">
	<span class="service-label"><?php echo __('Send post to Twitter?', Social::$i10n); ?></span>
	<input type="radio" name="<?php echo Social::$prefix; ?>notify_twitter" id="social_notify_twitter_yes" class="social-toggle" value="yes" <?php echo checked('yes', $notify_twitter, false); ?> /> <label for="social_notify_twitter_yes" class="social-toggle-label"><?php echo __('Yes', Social::$i10n); ?></label>
	<input type="radio" name="<?php echo Social::$prefix; ?>notify_twitter" id="social_notify_twitter_no" class="social-toggle" value="no" <?php echo checked('no', $notify_twitter, false); ?> /> <label for="social_notify_twitter_no" class="social-toggle-label"><?php echo __('No', Social::$i10n); ?></label>
	<div id="twitter_options" class="form-wrap"<?php echo ($notify_twitter != 'yes' ? ' style="display:none"' : ''); ?>>
		<div class="form-field">
			<span id="tweet_counter"><?php echo $counter; ?></span>
			<label for="tweet_preview"><?php echo __('Tweet', Social::$i10n); ?></label>
			<textarea rows="3" cols="20" id="tweet_preview" name="<?php echo Social::$prefix; ?>twitter_content"><?php echo $twitter_content; ?></textarea>
		</div>
		<div class="form-field">
			<label><?php echo __('Broadcast to These Accounts:', Social::$i10n); ?></label>
			<?php foreach (Social::$accounts['twitter'] as $account): ?>
			<div class="social-broadcastable">
				<input type="checkbox" name="<?php echo Social::$prefix; ?>broadcast_twitter_accounts[]" id="social_twitter_<?php echo $account->user->id; ?>" value="<?php echo $account->user->id; ?>"<?php echo ((empty($broadcast_accounts) or array_search($account->user->id, $broadcast_accounts['twitter']) !== false) ? ' checked="checked"' : ''); ?> />
				<span class="social-twitter-icon"><i></i><label for="social_twitter_<?php echo $account->user->id; ?>"><?php echo $account->user->screen_name; ?></label></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php
			}

			// Have Facebook accounts?
			if (isset(Social::$accounts['facebook']) and count(Social::$accounts['facebook'])) {
				$facebook_content = get_post_meta($post->ID, Social::$prefix.'facebook_content', true);

				$notify_facebook = get_post_meta($post->ID, Social::$prefix.'notify_facebook', true);
				if (!$notify_facebook) {
					$notify_facebook = 'no';
				}

				$counter = 420;
				if (!empty($facebook_content)) {
					$counter = $counter - strlen($facebook_content);
				}
?>
<div style="padding: 10px 0">
	<span class="service-label"><?php echo __('Send post to Facebook?', Social::$i10n); ?></span>
	<input type="radio" name="<?php echo Social::$prefix; ?>notify_facebook" id="social_notify_facebook_yes" class="social-toggle" value="yes" <?php echo checked('yes', $notify_facebook, false); ?> /> <label for="social_notify_facebook_yes" class="social-toggle-label"><?php echo __('Yes', Social::$i10n); ?></label>
	<input type="radio" name="<?php echo Social::$prefix; ?>notify_facebook" id="social_notify_facebook_no" class="social-toggle" value="no" <?php echo checked('no', $notify_facebook, false); ?> /> <label for="social_notify_facebook_no" class="social-toggle-label"><?php echo __('No', Social::$i10n); ?></label>
	<div id="facebook_options" class="form-wrap"<?php echo ($notify_facebook != 'yes' ? ' style="display:none"' : ''); ?>>
		<div class="form-field">
			<span id="facebook_counter"><?php echo $counter; ?></span>
			<label for="facebook_preview"><?php echo __('Status Update', Social::$i10n); ?></label>
			<textarea rows="3" cols="20" id="facebook_preview" name="<?php echo Social::$prefix; ?>facebook_content"><?php echo $facebook_content; ?></textarea>
		</div>
		<div class="form-field">
			<label><?php echo __('Broadcast to These Accounts:', Social::$i10n); ?></label>
			<?php foreach (Social::$accounts['facebook'] as $account): ?>
			<div class="social-broadcastable">
				<input type="checkbox" name="<?php echo Social::$prefix; ?>broadcast_facebook_accounts[]" id="social_facebook_<?php echo $account->user->id; ?>" value="<?php echo $account->user->id; ?>"<?php echo ((empty($broadcast_accounts) or array_search($account->user->id, $broadcast_accounts['facebook']) !== false) ? ' checked="checked"' : ''); ?> />
				<span class="social-facebook-icon"><i></i><label for="social_facebook_<?php echo $account->user->id; ?>"><?php echo $account->user->name; ?></label></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php
			}
		}
	}

	/**
     * Show the broadcast options if publishing.
     *
     * @static
     * @param  string  $location  default post-publish location
     * @param  int     $post_id   post ID
     * @return string|void
     */
    public static function redirect_post_location($location, $post_id) {
        if (isset($_POST['publish'])) {
            Social::broadcast_options($post_id, $location);
        }
        return $location;
    }

	/**
	 * Displays the upgrade message.
	 *
	 * @static
	 * @return void
	 */
	public static function display_upgrade() {
		$message = sprintf(__('To broadcast to Twitter or Facebook, please update your <a href="%s">Social Comment settings</a>', Social::$i10n), Social::settings_url());
		echo '<div class="error"><p>'.$message.'</p></div>';
	}

	/**
	 * Adds a link to the "Settings" menu in WP-Admin.
	 *
	 * @static
	 * @return void
	 */
	public static function admin_menu() {
		if (current_user_can('manage_options')) {
			add_options_page(
				__('Social Comment Options', Social::$i10n),
				__('Social Comments', Social::$i10n),
				10,
				basename(__FILE__),
				array('Social', 'admin_options_form')
			);
		}
	}

	/**
	 * Displays the option form for the WP-Admin user.
	 *
	 * @static
	 * @return void
	 */
	public static function admin_options_form() {
?>
<div class="wrap" id="social_options_page">
	<h2><?php echo __('Social Comment Options', Social::$i10n); ?></h2>

	<h3><?php echo __('Connect to Twitter/Facebook', Social::$i10n); ?></h3>
	<p><?php echo __('Before you can broadcast to Twitter or Facebook, you will need to connect your account(s).', Social::$i10n); ?></p>
	<div class="social-settings-connect">
		<?php foreach (Social::accounts('twitter') as $account): ?>
		<?php
			$url = '<a href="http://twitter.com/'.$account->user->screen_name.'">'.$account->user->screen_name.'</a>';
			$disconnect = '<a href="'.Social::settings_url(array('social_disconnect' => 'true', 'id' => $account->user->id, 'service' => 'twitter')).'">'
						. '<img src="'.plugins_url('/assets/delete.png', SOCIAL_FILE).'" alt="'.__('Disconnect', Social::$i10n).'" />'
						. '</a>';

			$output = sprintf(__('Connected to %s. %s', Social::$i10n), $url, $disconnect);
		?>
		<span class="social-twitter-icon big"><i></i><?php echo $output; ?></span>
		<?php endforeach; ?>

		<a href="<?php echo Social_Service_Helper::authorize_url('twitter', true); ?>" id="twitter_signin"><span><?php echo __('Sign In With Twitter', Social::$i10n); ?></span></a>
	</div>
	<div class="social-settings-connect">
		<?php foreach (Social::accounts('facebook') as $account): ?>
		<?php
			$url = '<a href="'.$account->user->link.'">'.$account->user->name.'</a>';
			$disconnect = '<a href="'.Social::settings_url(array('social_disconnect' => 'true', 'id' => $account->user->id, 'service' => 'facebook')).'">'
						. '<img src="'.plugins_url('/assets/delete.png', SOCIAL_FILE).'" alt="'.__('Disconnect', Social::$i10n).'" />'
						. '</a>';

			$output = sprintf(__('Connected to %s. %s', Social::$i10n), $url, $disconnect);
		?>
		<span class="social-facebook-icon big"><i></i><?php echo $output; ?></span>
		<?php endforeach; ?>

		<a href="<?php echo Social_Service_Helper::authorize_url('facebook', true); ?>" id="facebook_signin" style="float:left"><span><?php echo __('Sign In With Facebook', Social::$i10n); ?></span></a>
	</div>
</div>
<?php
	}

	/**
	 * Sets the broadcasting options for a post.
	 *
	 * @static
	 * @param  int     $post_id   post ID
     * @param  string  $location  location to send the form to
	 * @return void
	 */
	public static function broadcast_options($post_id, $location) {
        $post = get_post($post_id);
		$notify_twitter = get_post_meta($post->ID, Social::$prefix.'notify_twitter', true) == 'yes' ? true : false;
		$notify_facebook = get_post_meta($post->ID, Social::$prefix.'notify_facebook', true) == 'yes' ? true : false;

		$errors = array();
		if ($notify_twitter or $notify_facebook) {
			if (isset($_POST[Social::$prefix.'action'])) {
				$services = array(
					'twitter',
					'facebook'
				);
				foreach ($services as $service) {
					if (${'notify_'.$service} and empty($_POST[Social::$prefix.$service.'_content'])) {
						$errors[$service] = 'Please enter some content for '.ucwords($service).'.';
					}
				}

				if (!count($errors)) {
					foreach ($services as $service) {
						update_post_meta($post->ID, Social::$prefix.$service.'_content', $_POST[Social::$prefix.$service.'_content']);
					}

                    Social::broadcast($post_id);
                    wp_redirect($location);
					return;
				}
			}

            $twitter_content = get_post_meta($post->ID, Social::$prefix.'twitter_content', true);
            $facebook_content = get_post_meta($post->ID, Social::$prefix.'facebook_content', true);

            $opening = '';
            if ($notify_twitter and $notify_facebook) {
                $opening = 'Twitter and Facebook';
            } else if ($notify_twitter) {
                $opening = 'Twitter';
            } else if ($notify_facebook) {
                $opening = 'Facebook';
            }
			$opening = __($opening, Social::$i10n);
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
<h1 id="logo"><?php echo __('Social Broadcasting Options', Social::$i10n); ?></h1>
<?php
	if (count($errors)) {
?>
<div id="social_error">
	<?php
		foreach ($errors as $error) {
			echo $error.'<br />';
		}
	?>
</div>
<?php
	}
?>
<p><?php printf(__('You have chosen to broadcast your blog post to %s. Use the form below to edit your broadcasted messages.', Social::$i10n), $opening); ?></p>
<form id="setup" method="post" action="<?php echo admin_url(); ?>">
<?php wp_nonce_field(); ?>
<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
<input type="hidden" name="location" value="<?php echo $location; ?>" />
<input type="hidden" name="<?php echo Social::$prefix; ?>action" value="broadcast_options" />
<table class="form-table">
    <?php
        if ($notify_twitter)
        {
            $counter = 140;
            if (!empty($twitter_content)) {
                $counter = $counter - strlen($twitter_content);
            }
    ?>
    <tr>
        <th scope="row">
            <label for="tweet_preview"><?php __('Twitter', Social::$i10n); ?></label><br />
            <span id="tweet_counter"><?php echo $counter; ?></span>
        </th>
        <td><textarea id="tweet_preview" name="<?php echo Social::$prefix; ?>twitter_content" cols="40" rows="5"><?php echo ((isset($_POST[Social::$prefix.'twitter_content']) and !empty($_POST[Social::$prefix.'twitter_content'])) ? $_POST[Social::$prefix.'twitter_content'] : $twitter_content); ?></textarea></td>
    </tr>
    <?php
        }
        if ($notify_facebook)
        {
            $counter = 420;
            if (!empty($facebook_content)) {
                $counter = $counter - strlen($facebook_content);
            }
    ?>
    <tr>
        <th scope="row">
            <label for="facebook_preview"><?php __('Facebook', Social::$i10n); ?></label><br />
            <span id="facebook_counter"><?php echo $counter; ?></span>
        </th>
        <td><textarea id="facebook_preview" name="<?php echo Social::$prefix; ?>facebook_content" cols="40" rows="5"><?php echo ((isset($_POST[Social::$prefix.'facebook_content']) and !empty($_POST[Social::$prefix.'facebook_content'])) ? $_POST[Social::$prefix.'facebook_content'] : $facebook_content); ?></textarea></td>
    </tr>
    <?php
        }
    ?>
</table>
<p class="step"><input type="submit" value="<?php echo __('Publish', Social::$i10n); ?>" class="button" /></p>
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
	 * @static
	 * @access public
	 * @return void
	 */
	public static function set_broadcast($post_id) {
		$broadcast = false;
		$broadcast_accounts = array();
		foreach (Social::$accounts as $service => $accounts) {
			$post_key = Social::$prefix.'notify_'.$service;
			if (isset($_POST[$post_key])) {
				update_post_meta($post_id, $post_key, $_POST[$post_key]);

				$content_key = Social::$prefix.$service.'_content';
				if ($_POST[$post_key] == 'yes') {
					$broadcast = true;
					if (isset($_POST[$content_key]) and !empty($_POST[$content_key])) {
						update_post_meta($post_id, $content_key, $_POST[$content_key]);
					}
					else {
						$content = get_post_meta($post_id, $content_key, true);
						if (empty($content)) {
							$content = substr($_POST['post_content'], 0, Social_Service::instance($service)->max_broadcast_length());
						}
						update_post_meta($post_id, $content_key, $content);
					}
				}
				else {
					delete_post_meta($post_id, $content_key);
				}

				// Ignored accounts
				$broadcast_accounts[$service] = $_POST[Social::$prefix.'broadcast_'.$service.'_accounts'];
			}
		}
		update_post_meta($post_id, Social::$prefix.'broadcast_accounts', $broadcast_accounts);

		if ($broadcast) {
			$broadcasted = get_post_meta($post_id, Social::$prefix.'broadcasted', true);
			if (empty($broadcasted) or $broadcasted != 'yes') {
				update_post_meta($post_id, Social::$prefix.'broadcasted', 'no');
			}
		}
		else {
			delete_post_meta($post_id, Social::$prefix.'broadcasted');
		}
	}

	/**
	 * Broadcast the post to Twitter and/or Facebook.
	 *
	 * @static
	 * @param  int  post ID
	 * @return void
	 */
	public static function broadcast($post_id) {
        $broadcasted = get_post_meta($post_id, Social::$prefix.'broadcasted', true);
        if ($broadcasted == 'no' or empty($broadcasted)) {
            $twitter = get_post_meta($post_id, Social::$prefix.'notify_twitter', true);
            $facebook = get_post_meta($post_id, Social::$prefix.'notify_facebook', true);

            // Notify the service(s)?
            if ($twitter == 'yes' or $facebook == 'yes') {
	            $ids = array();
				$broadcast_accounts = get_post_meta($post_id, Social::$prefix.'broadcast_accounts', true);
	            foreach (Social::$accounts as $service => $accounts) {
		            $content = get_post_meta($post_id, Social::$prefix.$service.'_content', true);
		            if (!empty($content)) {

						foreach ($accounts as $account) {
							if (in_array($account->account()->user->id, $broadcast_accounts[$service])) {
								$ids[$service][] = Social_Service::instance($service, $account->account())->status_update($content)->id;
							}
						}
		            }
	            }
	            update_post_meta($post_id, Social::$prefix.'broadcasted_ids', $ids);
                update_post_meta($post_id, Social::$prefix.'broadcasted', 'yes');

	            delete_post_meta($post_id, Social::$prefix.'notify_facebook');
	            delete_post_meta($post_id, Social::$prefix.'notify_twitter');
            }
        }
	}

	/**
	 * Auth Cookie expiration for API users.
	 *
	 * @static
	 * @return int
	 */
	public static function auth_cookie_expiration() {
		return 31536000; // 1 Year
	}

	/**
	 * Overrides the default WordPress comments_template function.
	 *
	 * @static
	 * @param  string  $file  default comments.php path
	 * @return string
	 */
	public static function comments_template() {
		global $post;

		if (!(is_singular() and (have_comments() or $post->comment_status == 'open'))) {
			return;
		}

		$file = trailingslashit(dirname(SOCIAL_FILE)).'comments.php';
		return $file;
	}

	/**
	 * Returns an array of comment types that display avatars.
	 *
	 * @static
	 * @param  array  $types  default WordPress types
	 * @return array
	 */
	public static function get_avatar_comment_types($types) {
		return array_merge($types, array('facebook', 'twitter'));
	}

	/**
	 * Gets the avatar based on the comment type.
	 *
	 * @static
	 * @param  string  $avatar
	 * @param  object  $comment
	 * @param  int     $size
	 * @param  string  $default
	 * @param  string  $alt
	 * @return string
	 */
	public static function get_avatar($avatar, $comment, $size, $default, $alt) {
		if ($comment->comment_type == 'twitter' or $comment->comment_type == 'facebook') {
			$accounts = get_user_meta($comment->user_id, Social::$prefix.'accounts', true);
			$account_id = get_comment_meta($comment->comment_ID, Social::$prefix.'account_id', true);

			$image = null;
			if (isset($accounts[$comment->comment_type][$account_id])) {
				$service = Social_Service::instance($comment->comment_type, $accounts[$comment->comment_type][$account_id]);
				$image = $service->get_avatar();
			}

			if ($image !== null) {
				return "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo {$comment->comment_type}' height='{$size}' width='{$size}' />";
			}
		}

		return $avatar;
	}

	/**
	 * Displays a comment.
	 *
	 * @static
	 * @param  object  $comment  comment object
	 * @param  array  $args
	 * @param  int  $depth
	 * @return void
	 */
	public static function comment($comment, $args, $depth) {
		$GLOBALS['comment'] = $comment;
?>
<li class="social-comment social-<?php echo (empty($comment->comment_type) ? 'wordpress' : $comment->comment_type); ?>" id="li-comment-<?php comment_ID(); ?>">
	<div class="social-comment-inner" id="comment-<?php comment_ID(); ?>">
		<div class="social-comment-header">
			<div class="social-comment-author vcard">
				<?php echo get_avatar($comment, 40); ?>
				<?php printf('<cite class="social-fn fn">%s</cite>', get_comment_author_link()); ?>
				<?php if ($depth > 1): ?>
					<span class="social-replied social-imr"><?php echo __('replied:', Social::$i10n); ?></span>
				<?php endif; ?>
			</div><!-- .comment-author .vcard -->
			<div class="social-comment-meta"><span class="social-posted-from"><?php echo __('via Twitter', Social::$i10n); ?></span> <a href="#" class="social-posted-when"><?php printf(__('%s ago', Social::$i10n), human_time_diff(strtotime($comment->comment_date))); ?></a></div>
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
	 * @static
	 * @param  int  $comment_ID
	 * @return void
	 */
	public static function comment_post($comment_ID) {
		global $wpdb;
		$type = false;
		if (!empty(Social::$accounts)) {
			$account_id = $_POST[Social::$prefix.'post_account'];

			foreach (Social::$accounts as $service => $_accounts) {
				foreach ($_accounts as $account) {
					if ($account_id == $account->user->id) {
						$service = Social_Service::instance($service, $account);
						$service->status_update('Check out this comment I posted!');
						update_comment_meta($comment_ID, Social::$prefix.'account_id', $account_id);
						$wpdb->query("UPDATE $wpdb->comments SET comment_type='$service' WHERE comment_ID='$comment_ID'");
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
	 * @static
	 * @param  string  $link
	 * @return string
	 */
	public static function register($link) {
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
	 * @static
	 * @param  string  $link
	 * @return string
	 */
	public static function loginout($link) {
		if (is_user_logged_in()) {
			$commenter = get_user_meta(get_current_user_id(), Social::$prefix.'commenter', true);
			if ($commenter === '1') {
				foreach (Social::$accounts as $service => $accounts) {
					$account = reset($accounts);
					return '<a href="'.Social::commenter_disconnect_url(array('social_disconnect' => 'true', 'id' => $account->user->id, 'service' => $service)).'">Disconnect</a>';
				}
			}
		}

		return $link;
	}

	public static function commenter_disconnect_url($args) {
		$url = site_url().'?';
		$params = array();
		foreach ($args as $k => $v) {
			$params[] = $k.'='.$v;
		}
		$url .= implode('&', $params);

		return $url;
	}

} // End Social

/**
 * The Social_Service Class
 *
 * @package Social
 */
final class Social_Service {

	/**
	 * @var  array  Social_Service instances
	 */
	protected static $instances = array();

	/**
	 * Initializes and returns an instance of the service.
	 *
	 * @static
	 * @param  string  $service  twitter|facebook
	 * @param  object  $account  account
	 * @return Social_Service
	 */
	public static function instance($service, $account = null) {
		if ($account !== null) {
			if (!isset(Social_Service::$instances[$service.'_'.$account->user->id])) {
				Social_Service::$instances[$service.'_'.$account->user->id] = new Social_Service($service, $account);
			}

			return Social_Service::$instances[$service.'_'.$account->user->id];
		}
		else {
			if (!isset(Social_Service::$instances[$service])) {
				Social_Service::$instances[$service] = new Social_Service($service);
			}

			return Social_Service::$instances[$service];
		}
	}

	/**
	 * @var  string  service
	 */
	private $service = '';

	/**
	 * @var  WP_User  current user
	 */
	private $user = false;

	/**
	 * @var  array  associated accounts
	 */
	private $account = array();

	/**
	 * @var  Social_Service_Twitter|Social_Service_Facebook  the service interface
	 */
	private $interface = null;

	/**
	 * Initializes the service, and loads a user by ID.
	 *
	 * [!!] Always instantiate a service by calling Social_Service::instance('service');
	 *
	 * @param  string  $service  twitter|facebook
	 * @param  object  $account  the user's account
	 */
	public function __construct($service, $account = null) {
		$this->service = $service;

		$interface = 'Social_Service_'.ucfirst($service);
		$this->interface = new $interface;

		$this->user = wp_get_current_user();
		if ($account !== null) {
			$this->account($account);
		}
	}

	/**
	 * Returns the property of an object.
	 *
	 * @param  string  $name  property name
	 * @return mixed
	 */
	function __get($name) {
		if (!isset($this->account->{$name})) {
			throw new Exception('The '.$name.' property does not exist in '.get_class($this).'.');
		}

		return $this->account->{$name};
	}


	/**
	 * Checks to see if the WP_User object is loaded.
	 *
	 * @return bool
	 */
	public function loaded() {
		return ($this->user !== false and $this->user->ID) ? true : false;
	}

	/**
	 * Sets or returns the service's accounts.
	 *
	 * @param  object  $account  the service's account
	 * @return object
	 */
	public function account($account = null) {
		if ($account === null) {
			return $this->account;
		}

		$this->account = $account;
		$this->interface->account($account);
		return $this;
	}

	/**
	 * Adds an account to the user's user_meta, if it exists update the user_meta with
	 * any updated information from the service.
	 *
	 * @param  object  $account  account data
	 * @param  string  $public   public key
	 * @param  string  $private  private key
	 * @return Social_Service
	 */
	public function add($account, $public, $private) {
		if (empty($this->account)) {
			$this->account = (object) array_merge((array) $account, array(
				'keys' => (object) array(
					'public' => $public,
					'private' => $private
				)
			));
		}
		else {
			$this->account->user = (object) array_merge((array) $this->account->user, (array) $account);
		}

		return $this;
	}

	/**
	 * Disconnects an account from the user's account.
	 *
	 * @param  int  $id
	 * @return void
	 */
	public function disconnect($id) {
		$accounts = get_user_meta($this->user->ID, Social::$prefix.'accounts', true);;
		if (isset($accounts[$this->service][$id])) {
			unset($accounts[$this->service][$id]);
			update_user_meta($this->user->ID, Social::$prefix.'accounts', $accounts);
		}
	}

	/**
	 * Creates the user.
	 *
	 * @return void
	 */
	public function create_user() {
		$id = $this->interface->account($this->account)->create_user();
		wp_set_current_user($id);

		add_filter('auth_cookie_expiration', array('Social', 'auth_cookie_expiration'));
		wp_set_auth_cookie($id, true);
		remove_filter('auth_cookie_expiration', array('Social', 'auth_cookie_expiration'));
	}

	/**
	 * Saves a WP_User object.
	 *
	 * @return void
	 */
	public function save() {
		$accounts = get_user_meta($this->user->ID, Social::$prefix.'accounts', true);
		$accounts[$this->service][$this->account->user->id] = $this->account;
		update_user_meta(get_current_user_id(), Social::$prefix.'accounts', $accounts);
	}

	/**
	 * Returns the user's avatar.
	 *
	 * @return string
	 */
	public function get_avatar() {
		return $this->interface->get_avatar();
	}

	/**
	 * Update's the user's status.
	 *
	 * @param  string  $status
	 * @return void
	 */
	public function status_update($status) {
		$this->interface->status_update($status);
	}

	/**
	 * Gets the max broadcast length for the service.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return $this->interface->max_broadcast_length();
	}

} // End Social_Service

/**
 * Helper class for Social_Service_* classes.
 *
 * @package Social
 */
abstract class Social_Service_Helper {

	/**
	 * @var  string  service for the account
	 */
	protected $service = null;

	/**
	 * @var  object  service's account
	 */
	protected $account = array();

	/**
	 * Checks to make sure the service defined their $service variable.
	 *
	 * @throws Exception
	 * @return void
	 */
	public function __construct() {
		if ($this->service === null) {
			throw new Exception('You must set the $service variable for '.get_class($this));
		}
	}

	/**
	 * The account to us for this service.
	 *
	 * @param  object  $account  user's account
	 * @return mixed
	 */
	public function account($account = null) {
		if ($account === null) {
			return $this->account;
		}

		$this->account = $account;
		return $this;
	}

	/**
	 * Executes the request for the service.
	 *
	 * @abstract
	 * @param  string  $api     API endpoint to request
	 * @param  array   $params  parameters to pass to the API
	 * @param  string  $method  GET|POST, default: GET
	 * @param  string  $service the service to use
	 * @return array
	 */
	public function request($api, array $params = array(), $method = 'GET', $service = null) {
		$request = wp_remote_post(Social::$api_url.$this->service, array(
			'sslverify' => false,
			'body' => array(
				'api' => $api,
				'method' => $method,
				'public_key' => $this->account->keys->public,
				'hash' => sha1($this->account->keys->public.$this->account->keys->public),
				'params' => json_encode($params)
			)
		));

		if (!is_wp_error($request)) {
			$body = json_decode($request['body']);
			if ($body->result != 'error') {
				return $this->object_to_array($body->response);
			}
		}

		return array();
	}

	/**
	 * Creates an account using the account information.
	 *
	 * @param  array  $account  social network account
	 * @return int
	 */
	public function create_user() {
		// Make sure the user doesn't exist
		$username = $this->service.'_'.$this->account->user->id;
		$user = get_userdatabylogin($username);
		if ($user === false) {
			$id = wp_create_user($username, wp_generate_password(20, false));
			update_user_meta($id, Social::$prefix.'commenter', '1');
		}
		else {
			$id = $user->ID;
		}

		return $id;
	}

	/**
	 * Converts an stdClass to an array.
	 *
	 * @param  array|object  $object  object to convert to an array
	 * @return array
	 */
	protected function object_to_array($object) {
		$array = array();
        foreach ($object as $k => $v) {
            if (is_object($v)) {
                $array[$k] = $this->object_to_array($v);
            }
            else if (is_array($v)) {
                $array[$k] = $this->object_to_array($v);
            }
            else {
                $array[$k] = $v;
            }
        }

        return $array;
	}

	/**
	 * Builds the authorize URL for the provided service.
	 *
	 * @static
	 * @param  string  $service
	 * @param  bool    $admin
	 * @return string
	 */
	public static function authorize_url($service, $admin = false) {
		$url = ($admin ? admin_url() : site_url()).'?t='.time();
		return Social::$api_url.$service.'/authorize?redirect_to='.urlencode($url);
	}

} // End Social_Service_Helper

/**
 * An interface that must be used by services that want to hook onto the plugin.
 *
 * @package Social
 */
interface Social_IService {

	/**
	 * The account to us for this service.
	 *
	 * @abstract
	 * @param  object  $account  user's account
	 * @return void
	 */
	function account($account);

	/**
	 * Updates a user's status on the service.
	 *
	 * @abstract
	 * @param  string  $status       status message
	 * @return void
	 */
	function status_update($status);

	/**
	 * Executes the request for the service.
	 *
	 * @abstract
	 * @param  string  $api          API endpoint to request
	 * @param  array   $params       parameters to pass to the API
	 * @param  string  $method       GET|POST, default: GET
	 * @param  string  $service      service to use
	 * @return array
	 */
	function request($api, array $params = array(), $method = 'GET', $service = null);

	/**
	 * Creates a WordPress User
	 *
	 * @abstract
	 * @param  array  $account  social network account
	 * @return int
	 */
	function create_user();

	/**
	 * Builds the user's avatar.
	 *
	 * @abstract
	 * @return string
	 */
	function get_avatar();

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @abstract
	 * @return void
	 */
	function max_broadcast_length();

} // End Social_Service_Interface

/**
 * Twitter integration.
 *
 * @package Social
 */
final class Social_Service_Twitter extends Social_Service_Helper implements Social_IService {

	/**
	 * @var  string  the service
	 */
	protected $service = 'twitter';

	/**
	 * Updates the user's status.
	 *
	 * @param  string  $status  status message
	 * @return array
	 */
	public function status_update($status) {
		return $this->request('statuses/update', array('status' => $status), 'POST');
	}

	/**
	 * Builds the user's avatar.
	 *
	 * @return string
	 */
	public function get_avatar() {
		return $this->account['profile_image_url'];
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140;
	}

} // End Social_Service_Twitter

/**
 * Facebook integration.
 *
 * @package Social
 */
final class Social_Service_Facebook extends Social_Service_Helper implements Social_IService {

	/**
	 * @var  string  the service
	 */
	protected $service = 'facebook';

	/**
	 * Updates the user's status.
	 *
	 * @param  string  $status  status message
	 * @return array
	 */
	public function status_update($status) {
		return $this->request('feed', array('message' => $status), 'POST');
	}

	/**
	 * Builds the user's avatar.
	 *
	 * @return string
	 */
	function get_avatar() {
		return 'http://graph.facebook.com/'.$this->account['username'].'/picture';
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 400;
	}

} // End Social_Service_Facebook
