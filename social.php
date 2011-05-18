<?php
/*
Plugin Name: Social Comments
Plugin URI:
Description: Social integration for comments.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// TODO Ask MC about double slashing on the Proxy outbound data
// TODO Check User ID on the wp_insert_comment() for current_user_id()
// TODO Add AJAX call to replace the comment form instead of refreshing the page upon authentication. (Comment form only)

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR', 'wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__))) {
	define('SOCIAL_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(__FILE__));
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).basename(dirname(__FILE__)).'/'.basename(__FILE__))) {
	define('SOCIAL_FILE', trailingslashit(ABSPATH.PLUGINDIR).basename(dirname(__FILE__)).'/'.basename(__FILE__));
}

// TODO Test this functionality
/*$monkeyman_Rewrite_Analyzer_file = __FILE__;
if ( isset( $mu_plugin ) ) {
    $monkeyman_Rewrite_Analyzer_file = $mu_plugin;
}

if ( isset( $network_plugin ) ) {
    $monkeyman_Rewrite_Analyzer_file = $network_plugin;
}

if ( isset( $plugin ) ) {
    $monkeyman_Rewrite_Analyzer_file = $plugin;
} */

// Activation Hook
register_activation_hook(SOCIAL_FILE, array('Social', 'install'));
register_deactivation_hook(SOCIAL_FILE, array('Social', 'deactivate'));

// Actions
add_action('init', array('Social', 'init'), 1);
add_action('init', array('Social', 'request_handler'), 2);
add_action('do_meta_boxes', array('Social', 'do_meta_boxes'));
add_action('save_post', array('Social', 'set_broadcast_meta_data'));
add_action('comment_post', array('Social', 'comment_post'));
add_action('social_aggregate_comments', array('Social', 'aggregate_comments'));

// Admin Actions
add_action('admin_menu', array('Social', 'admin_menu'));

// Filters
add_filter('redirect_post_location', array('Social', 'redirect_post_location'));
add_filter('comments_template', array('Social', 'comments_template'));
// TODO multiple services
add_filter('get_avatar_comment_types', array('Social', 'get_avatar_comment_types'));
add_filter('get_avatar', array('Social', 'get_avatar'), 10, 5);
add_filter('get_comment_author_url', array('Social', 'get_comment_author_url'));
add_filter('get_comment_author', array('Social', 'get_comment_author'));
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
	 * @return string
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
	 * @return array|string
	 */
	private static function option($key = null, $value = null) {
		if ($key === null) {
			return Social::$options;
		}
		else if ($key !== null and $value === null) {
			return Social::$options[$key];
		}

		Social::$options[$key] = $value;
		return $value;
	}

	/**
	 * Registers the plugin to WordPress.
	 *
	 * @static
	 * @return void
	 */
	public static function install() {
		// require PHP 5
		if (version_compare(PHP_VERSION, '5.2.4', '<=')) {
			// TODO Move this to settings page and replace settings page with content instead of wp_die()
			deactivate_plugins(basename(__FILE__)); // Deactivate ourself
			wp_die(__("Sorry, Social Comments requires PHP 5.2.4 or higher. Ask your host how to enable PHP 5 as the default on your servers.", Social::$i10n));
		}

		// Set the options
		// TODO Check for defaults instead of relying on this install method.
		foreach (Social::option() as $option => $default) {
			add_option(Social::$prefix.$option, $default);
		}
		add_option(Social::$prefix.'update_hash', '');

		// Register our CRON
		// TODO check for event before scheduling
		wp_schedule_event(time() + 1200, 'hourly', Social::$prefix.'aggregate_comments');
	}

	/**
	 * Remove the CRON unpon plugin deactivation.
	 *
	 * @static
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook(Social::$prefix.'aggregate_comments');
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

		//self::aggregate_comments();

		// Load the accounts
		// TODO Move this to a lazy-load logic
		Social::accounts(get_user_meta(get_current_user_id(), Social::$prefix.'accounts', true));
		if (is_admin()) {
			// TODO Move this block to admin_init
			$_accounts = array();
			foreach (Social::accounts() as $service => $accounts) {
				foreach ($accounts as $id => $account) {
					Social::$update = false;
					$_accounts[$service][$id] = Social_Service::instance($service, $account);
				}
			}

			Social::accounts($_accounts);

			// Update actions.
			if (Social::$update) {
				add_action('admin_notices', array('Social', 'display_upgrade'));
			}

			// Add the CSS
			wp_register_style('social_css', plugins_url('/assets/admin.css', SOCIAL_FILE));
			wp_register_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array(), false, true);
		}
		else {
			// Add the CSS
			wp_register_style('social_css', plugins_url('/assets/comments.css', SOCIAL_FILE));

			// Add the JS
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-tabs');
			wp_register_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array(), false, true);
		}

		// Defaults
		// TODO Implement page_now = post.php/options.php (might be something else)
		wp_enqueue_script('social_js', plugins_url('/assets/social.js', SOCIAL_FILE), array('jquery'), Social::$version);
		wp_enqueue_style('social_css');
	}

	/**
	 * Return the service's accounts.
	 *
	 * @static
	 * @param  string  $service  twitter|facebook
	 * @return array
	 */
	public static function accounts($service = null) {
		// TODO implement lazy load check here
		if ($service === null) {
			return Social::$accounts;
		}
		else if (is_array($service)) {
			Social::$accounts = $service;
		}
		else if (!isset(Social::$accounts[$service])) {
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
		if (!self::$update and $broadcasted != '1') {
			add_meta_box(Social::$prefix.'meta_broadcast', __('Social Comments', Social::$i10n), array('Social', 'add_meta_box'), 'post');
		}
    }

	/**
	 * Adds the broadcasting meta box.
	 *
	 * @static
	 * @return void
	 */
	public static function add_meta_box() {
		global $post;

		if (!self::$update) {
			$broadcast_accounts = get_post_meta($post->ID, Social::$prefix.'broadcast_accounts', true);

			// Have Twitter account(s)?
			foreach (Social::accounts() as $service => $accounts) {
				if (count($accounts)) {
					$_service = reset($accounts);
					$content = get_post_meta($post->ID, Social::$prefix.$service.'_content', true);
					$notify = get_post_meta($post->ID, Social::$prefix.'notify_'.$service, true);
					$counter = $_service->max_broadcast_length();
					if (!empty($content)) {
						$counter = $counter - strlen($content);
					}
?>
<input type="hidden" name="<?php echo Social::$prefix.'notify[]'; ?>" value="<?php echo $service; ?>" />
<div style="padding:10px 0">
	<span class="service-label"><?php echo __('Send post to '.$_service->title().'?', Social::$i10n); ?></span>
	<input type="radio" name="<?php echo Social::$prefix.'notify_'.$service; ?>" id="<?php echo Social::$prefix.'notify_'.$service.'_yes'; ?>" class="social-toggle" value="1" <?php echo checked('1', $notify, false); ?> /> <label for="<?php echo Social::$prefix.'notify_'.$service.'_yes'; ?>" class="social-toggle-label"><?php echo __('Yes', Social::$i10n); ?></label>
	<input type="radio" name="<?php echo Social::$prefix.'notify_'.$service; ?>" id="<?php echo Social::$prefix.'notify_'.$service.'_no'; ?>" class="social-toggle" value="0" <?php echo checked('0', $notify, false); ?> /> <label for="<?php echo Social::$prefix.'notify_'.$service.'_no'; ?>" class="social-toggle-label"><?php echo __('No', Social::$i10n); ?></label>
	<div id="<?php echo $service.'_options'; ?>" class="form-wrap"<?php echo ($notify != '1' ? ' style="display:none"' : ''); ?>>
		<div class="form-field">
			<span id="<?php echo $service.'_counter'; ?>"><?php echo $counter; ?></span>
			<label for="<?php echo $service.'_preview'; ?>"><?php echo __('Content', Social::$i10n); ?></label>
			<textarea rows="3" cols="20" id="<?php echo $service.'_preview'; ?>" name="<?php echo Social::$prefix; ?>twitter_content"><?php echo $content; ?></textarea>
		</div>
		<div class="form-field">
			<label><?php echo __('Broadcast to These Accounts:', Social::$i10n); ?></label>
			<?php foreach ($accounts as $account): ?>
			<div class="social-broadcastable">
				<input type="checkbox" name="<?php echo Social::$prefix.'broadcast_'.$service.'_accounts[]'; ?>" id="<?php echo Social::$prefix.$service.'_'.$account->user->id; ?>" value="<?php echo $account->user->id; ?>"<?php echo ((empty($broadcast_accounts) or array_search($account->user->id, $broadcast_accounts[$service]) !== false) ? ' checked="checked"' : ''); ?> />
				<span class="<?php echo 'social-'.$service.'-icon'; ?>"><i></i><label for="<?php echo Social::$prefix.$service.'_'.$account->user->id; ?>"><?php echo $account->name(); ?></label></span>
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
		add_options_page(
			__('Social Comment Options', Social::$i10n),
			__('Social Comments', Social::$i10n),
			'manage_options',
			basename(__FILE__),
			array('Social', 'admin_options_form')
		);
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

	<h3><?php echo __('Connect to Social Networks', Social::$i10n); ?></h3>
	<p><?php echo __('Before you can broadcast to your social networks, you will need to connect your account(s).', Social::$i10n); ?></p>
	<?php foreach (Social::accounts() as $service => $accounts): ?>
	<div class="social-settings-connect">
		<?php foreach ($accounts as $account): ?>
		<?php
			$url = '<a href="http://twitter.com/'.$account->name().'">'.$account->name().'</a>';
			$disconnect = '<a href="'.Social::settings_url(array(Social::$prefix.'disconnect' => 'true', 'id' => $account->user->id, 'service' => $service)).'">'
						. '<img src="'.plugins_url('/assets/delete.png', SOCIAL_FILE).'" alt="'.__('Disconnect', Social::$i10n).'" />'
						. '</a>';

			$output = sprintf(__('Connected to %s. %s', Social::$i10n), $url, $disconnect);
		?>
		<span class="social-<?php echo $service; ?>-icon big"><i></i><?php echo $output; ?></span>
		<?php endforeach; ?>

		<a href="<?php echo Social_Service_Helper::authorize_url($service, true); ?>" id="<?php echo $service; ?>_signin"><span><?php echo __('Sign In With '.$account->title(), Social::$i10n); ?></span></a>
	</div>
	<?php endforeach; ?>
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
		$notify_twitter = get_post_meta($post->ID, Social::$prefix.'notify_twitter', true) == '1' ? true : false;
		$notify_facebook = get_post_meta($post->ID, Social::$prefix.'notify_facebook', true) == '1' ? true : false;

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
	 * @param  int  $post_id
	 * @return void
	 */
	public static function set_broadcast_meta_data($post_id) {
		$broadcast = false;
		$broadcast_accounts = array();
		foreach (Social::accounts() as $service => $accounts) {
			$post_key = Social::$prefix.'notify_'.$service;
			if (isset($_POST[$post_key])) {
				update_post_meta($post_id, $post_key, $_POST[$post_key]);

				$content_key = Social::$prefix.$service.'_content';
				if ($_POST[$post_key] == '1') {
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
	 * @static
	 * @param  int  $post_id
	 * @return void
	 */
	public static function broadcast($post_id) {
        $broadcasted = get_post_meta($post_id, Social::$prefix.'broadcasted', true);
        if ($broadcasted == '0' or empty($broadcasted)) {
            $twitter = get_post_meta($post_id, Social::$prefix.'notify_twitter', true);
            $facebook = get_post_meta($post_id, Social::$prefix.'notify_facebook', true);

            // Notify the service(s)?
            if ($twitter == '1' or $facebook == '1') {
	            $ids = array();
				$broadcast_accounts = get_post_meta($post_id, Social::$prefix.'broadcast_accounts', true);
	            foreach (Social::accounts() as $service => $accounts) {
		            $content = get_post_meta($post_id, Social::$prefix.$service.'_content', true);
		            if (!empty($content)) {

						foreach ($accounts as $account) {
							if (in_array($account->account()->user->id, $broadcast_accounts[$service])) {
								$ids[$service][] = Social_Service::instance($service, $account->account())->status_update($content)->id;
							}
						}

			            delete_post_meta($post_id, Social::$prefix.'notify_'.$service);
		            }
	            }
	            update_post_meta($post_id, Social::$prefix.'broadcasted_ids', $ids);
                update_post_meta($post_id, Social::$prefix.'broadcasted', '1');
	            update_post_meta($post_id, Social::$prefix.'cron', '1');
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
	 * Builds the URL to the author's Facebook/Twitter.
	 *
	 * @static
	 * @param  string  $url
	 * @return string
	 */
	public static function get_comment_author_url($url) {
		global $comment;
		if (Social::accounts($comment->comment_type) !== false) {
			return Social_Service::instance($comment->comment_type, reset(Social::accounts($comment->comment_type)))->url();
		}
		return $url;
	}

	/**
	 * Gets the comment author's username from Facebook/Twitter.
	 *
	 * @static
	 * @param  string  $author
	 * @return string
	 */
	public static function get_comment_author($author) {
		global $comment;
		if (Social::accounts($comment->comment_type) !== false) {
			return Social_Service::instance($comment->comment_type, reset(Social::accounts($comment->comment_type)))->display_name();
		}
		return $author;
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
		if (Social::accounts() !== null) {
			$account_id = $_POST[Social::$prefix.'post_account'];

			foreach (Social::accounts() as $service => $_accounts) {
				foreach ($_accounts as $account) {
					if ($account_id == $account->user->id) {
						$account->status_update('Check out this comment I posted!');
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
				foreach (Social::accounts() as $service => $accounts) {
					$account = reset($accounts);
					return '<a href="'.Social::commenter_disconnect_url(array('social_disconnect' => 'true', 'id' => $account->user->id, 'service' => $service)).'">Disconnect</a>';
				}
			}
		}

		return $link;
	}

	/**
	 * Creates the disconnect URL for a user.
	 *
	 * @static
	 * @param  array  $args
	 * @return string
	 */
	public static function commenter_disconnect_url($args) {
		$url = site_url().'?';
		$params = array();
		foreach ($args as $k => $v) {
			$params[] = $k.'='.$v;
		}
		$url .= implode('&', $params);

		return $url;
	}

	public static function aggregate_comments() {
		$posts = query_posts(array(
			'meta_key' => Social::$prefix.'broadcasted,'.Social::$prefix.'cron',
			'meta_value' => '1,1'
		));
		foreach ($posts as $post) {

		}


		/*if (time() - strtotime($post->post_date) > 172800 and !count($comments)) {
			wp_clear_scheduled_hook('social_aggregate_comments', array($post_id));
		}*/
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
	 * @var  mixed  the service interface
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
	 * Returns the title of the service.
	 *
	 * @return string
	 */
	function title() {
		return $this->interface->title();
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

	/**
	 * Returns the user's URL.
	 *
	 * @return string
	 */
	public function url() {
		return $this->interface->url();
	}

	/**
	 * Returns the user's display name.
	 *
	 * @return string
	 */
	public function display_name() {
		return $this->interface->display_name();
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
	 * @var string  the title of the service
	 */
	protected $title = null;

	/**
	 * @var  object  service's account
	 */
	protected $account = array();

	/**
	 * Checks to make sure the service defined their $service variable.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		if ($this->service === null) {
			throw new Exception('You must set the $service variable for '.get_class($this));
		}
	}

	/**
	 * Returns the UI display name of the service.
	 *
	 * @return string
	 */
	public function title() {
		return ($this->title === null) ? ucwords(str_replace('_', ' ', $this->service)) : $this->title;
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
				'hash' => sha1($this->account->keys->public.$this->account->keys->secret),
				'params' => json_encode($params)
			)
		));

		if (!is_wp_error($request)) {
			$body = json_decode($request['body']);
			if ($body->result != 'error') {
				return $body->response;
			}
		}

		return array();
	}

	/**
	 * Creates an account using the account information.
	 *
	 * @return int
	 */
	public function create_user() {
		// Make sure the user doesn't exist
		$username = $this->service.'_'.$this->account->user->id;
		$user = get_userdatabylogin($username);
		if ($user === false) {
			$id = wp_create_user($username, wp_generate_password(20, false), $this->create_email());
			update_user_meta($id, Social::$prefix.'commenter', '1');
			update_user_option($id, 'show_admin_bar_front', 'false');
		}
		else {
			$id = $user->ID;
		}

		return $id;
	}

	/**
	 * Builds the email for user creation.
	 *
	 * @param  string $alias
	 * @return string
	 */
	public function create_email($alias = null) {
		return $this->service.'.'.$alias.'@example.com';
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
		$url = ($admin ? admin_url() : site_url());
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

	/**
	 * Creates the email alias for the new account.
	 *
	 * @abstract
	 * @param  string  $alias
	 * @return string
	 */
	function create_email($alias = null);

	/**
	 * Returns the URL to the user's account.
	 *
	 * @abstract
	 * @return string
	 */
	function url();

	/**
	 * Returns the user's display name.
	 *
	 * @abstract
	 * @return string
	 */
	function display_name();

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
	 * @var string  the UI display value
	 */
	protected $title = 'Twitter';

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
		return $this->account->user->profile_image_url;
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140;
	}

	/**
	 * Creates the email alias for the new account.
	 *
	 * @param  string  $alias
	 * @return string
	 */
	function create_email($alias = null) {
		return parent::create_email($this->account->user->screen_name);
	}

	/**
	 * Returns the URL to the user's account.
	 *
	 * @return string
	 */
	function url() {
		return 'http://twitter.com/'.$this->account->user->screen_name;
	}

	/**
	 * Returns the user's display name.
	 *
	 * @return string
	 */
	function display_name() {
		return $this->account->user->screen_name;
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
	 * @var string  the UI display value
	 */
	protected $title = 'Facebook';

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
		return 'http://graph.facebook.com/'.$this->account->user->username.'/picture';
	}

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 400;
	}

	/**
	 * Creates the email alias for the new account.
	 *
	 * @param  string  $alias
	 * @return string
	 */
	function create_email($alias = null) {
		return parent::create_email($this->account->user->username);
	}

	/**
	 * Returns the URL to the user's account.
	 *
	 * @return string
	 */
	function url() {
		return $this->account->user->link;
	}

	/**
	 * Returns the user's display name.
	 *
	 * @return string
	 */
	function display_name() {
		return $this->account->user->name;
	}

} // End Social_Service_Facebook
