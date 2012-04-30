<?php
/**
 * Just a singleton for filter methods to live under.
 *
 * @uses Social
 * @package Social
 * @subpackage comment
 */
final class Social_Comment_Form {

	/**
	 * @var  array  comment form instances
	 */
	protected static $instances = array();

	/**
	 * Loads an instance of the comment form.
	 *
	 * @static
	 * @param  int    $post_id
	 * @param  array  $args
	 * @return Social_Comment_Form
	 */
	public static function instance($post_id, array $args = array()) {
		if (!isset(self::$instances[$post_id])) {
			self::$instances[$post_id] = new self($post_id, $args);
		}
		return self::$instances[$post_id];
	}

	/**
	 * @var  object  post
	 */
	protected $post = null;

	/**
	 * @var  int  post ID
	 */
	protected $post_id = 0;

	/**
	 * @var  array  arguments to pass into the comment form
	 */
	protected $args = array();

	/**
	 * @var  bool  is logged in flag
	 */
	protected $is_logged_in = false;

	/**
	 * @var  WP_User  current user object
	 */
	protected $current_user = null;

	/**
	 * Initializes the comment form.
	 *
	 * @param  int    $post_id
	 * @param  array  $args
	 */
	public function __construct($post_id, array $args = array()) {
		global $post;

		$this->post_id = $post_id;
		$this->args = $args;
		$this->is_logged_in = is_user_logged_in();
		$this->current_user = wp_get_current_user();

		if ($post === null) {
			$post = get_post($this->post_id);
		}

		$this->post = $post;
	}

	/**
	 * Magic method to render the form on echo.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}

	/**
	 * Renders the comment form HTML.
	 *
	 * @static
	 * @return string
	 */
	public function render() {
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

	/**
	 * Attaches hooks before rending the comment form.
	 *
	 * @return void
	 */
	private function attach_hooks() {
		add_action('comment_form_top', array($this, 'top'));
		add_action('comment_form_defaults', array($this, 'configure_args'));
		add_action('comment_form', array($this, 'comment_form_wrapper_open'), 0);
		add_action('comment_form', array($this, 'comment_form_wrapper_close'), 99999);
		add_filter('comment_form_logged_in', array($this, 'logged_in_as'));
		add_filter('comment_id_fields', array($this, 'comment_id_fields'), 10, 3);
	}

	/**
	 * Removes the hooks after rending the comment form.
	 *
	 * @return void
	 */
	private function remove_hooks() {
		remove_action('comment_form_top', array($this, 'top'));
		remove_action('comment_form_defaults', array($this, 'configure_args'));
		remove_action('comment_form', array($this, 'comment_form_wrapper_open'), 0);
		remove_action('comment_form', array($this, 'comment_form_wrapper_close'), 99999);
		remove_filter('comment_form_logged_in', array($this, 'logged_in_as'));
		remove_filter('comment_id_fields', array($this, 'comment_id_fields'), 10, 3);
	}

	/**
	 * Echo opening wrapper around comment_form action.
	 *
	 * @return void
	 */
	public function comment_form_wrapper_open() {
		echo '<div id="commentform-extras">';
	}

	/**
	 * Echo closing wrapper around comment_form action.
	 *
	 * @return void
	 */
	public function comment_form_wrapper_close() {
		echo '</div>';
	}

	/**
	 * Creates a fieldgroup.
	 *
	 * @param  string  $label
	 * @param  string  $id
	 * @param  string  $tag
	 * @param  string  $text
	 * @param  array   $attr1
	 * @param  array   $attr2
	 * @param  string  $help_text
	 * @return string
	 */
	public function to_field_group($label, $id, $tag, $text, $attr1 = array(), $attr2 = array(), $help_text = '') {
		$attr = array_merge($attr1, $attr2);

		$label = $this->to_tag('label', $label, array(
			'for' => $id,
			'class' => 'social-label'
		));

		$input_defaults = array(
			'id' => $id,
			'name' => $id,
			'class' => 'social-input'
		);
		$input = $this->to_tag($tag, $text, $input_defaults, $attr);

		$help = '';
		if ($help_text) {
			$help = $this->to_tag('small', $help_text, array('class' => 'social-help'));
		}

		return $this->to_tag('p', $label.$input.$help, array(
			'class' => 'social-input-row social-input-row-'.$id
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

	/**
	 * Creates a textarea.
	 *
	 * @param  string  $label
	 * @param  string  $id
	 * @param  string  $value
	 * @param  bool    $req
	 * @return string
	 */
	public function to_textarea_group($label, $id, $value, $req = true) {
		$maybe_req = ($req ? array('required' => 'required') : array());
		return $this->to_field_group($label, $id, 'textarea', $value, $maybe_req);
	}

	/**
	 * @param  string  $result
	 * @param  string  $id
	 * @param  string  $replytoid
	 * @return string
	 */
	public function comment_id_fields($result, $id, $replytoid) {
		$html = $this->get_also_post_to_controls();

		$html .= $result;

		$hidden = array('type' => 'hidden');
		$html .= $this->to_tag('input', false, $hidden, array(
			'id' => 'use_twitter_reply',
			'name' => 'use_twitter_reply',
			'value' => 0
		));
		$html .= $this->to_tag('input', false, $hidden, array(
			'id' => 'in_reply_to_status_id',
			'name' => 'in_reply_to_status_id',
			'value' => ''
		));

		return $html;
	}

	/**
	 * @param  array  $default_args
	 * @return array
	 */
	public function configure_args(array $default_args) {
		$commenter = wp_get_current_commenter();
		$req = get_option('require_name_email');

		$fields = array(
			'author' => $this->to_input_group(__('Name', 'social'), 'author', $commenter['comment_author'], $req),
			'email' => $this->to_input_group(__('Email', 'social'), 'email', $commenter['comment_author_email'], $req, __('Not published', 'social')),
			'url' => $this->to_input_group(__('Website', 'social'), 'url', $commenter['comment_author_url'])
		);

		$args = array(
			'label_submit' => __('Post It', 'social'),
			'title_reply' => __('Profile', 'social'),
			'title_reply_to' => __('Post a Reply to %s', 'social'),
			'cancel_reply_link' => __('cancel', 'social'),
			'comment_notes_after' => '',
			'comment_notes_before' => '',
			'fields' => $fields,
			'comment_field' => $this->to_textarea_group(__('Comment', 'social'), 'comment', '', true, 'textarea')
		);

		if ($this->is_logged_in) {
			$override = array(
				'title_reply' => __('Post a Comment', 'social')
			);
			$args = array_merge($args, $override);
		}

		return array_merge($default_args, $args);
	}

	/**
	 * Outputs checkboxes for cross-posting
	 * 
	 * @uses Social::to_tag()
	 * @return string
	 */
	public function get_also_post_to_controls() {
		if ($this->is_logged_in and $this->post->post_status != 'private') {
			$id = 'post_to_service';
			$label_base = array(
				'for' => $id,
				'id' => 'post_to'
			);

			$checkbox = $this->to_tag('input', false, array(
				'type' => 'checkbox',
				'name' => $id,
				'id' => $id,
				'value' => 1
			));

			if (current_user_can('manage_options')) {
				$text = sprintf(__('Also post to %s', 'social'), '<span></span>');
				$post_to = $this->to_tag('label', $checkbox.' '.$text, $label_base, array('style' => 'display:none;'));
			}
			else {
				$post_to = '';
				foreach (Social::instance()->services() as $key => $service) {
					if (count($service->accounts())) {
						Social::log(print_r($service->accounts(), true));
						foreach ($service->accounts() as $account) {
							if ($account->personal()) {
								$text = sprintf(__('Also post to %s', 'social'), $service->title());
								$post_to .= $this->to_tag('label', $checkbox . ' ' . $text, $label_base);
								break;
							}
						}
					}
				}
			}

			return $post_to;
		}

		return '';
	}

	/**
	 * Hook for 'comment_form_top' action.
	 *
	 * @return void
	 */
	public function top() {
		if (!$this->is_logged_in) {
			echo Social_View::factory('comment/top', array(
				'services' => Social::instance()->services()
			));
		}
	}

	/**
	 * Hook for 'comment_form_logged_in' action.
	 *
	 * @return string
	 */
	public function logged_in_as() {
		$services = Social::instance()->services();
		$accounts = array();
		foreach ($services as $key => $service) {
			if (count($service->accounts())) {
				$accounts[$key] = $service->accounts();
			}
		}
		return Social_View::factory('comment/logged_in_as', array(
			'services' => $services,
			'accounts' => $accounts,
			'current_user' => $this->current_user,
		))->render();
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
	private function to_tag($tag, $text = '', $attr1 = array(), $attr2 = array()) {
		if (function_exists('esc_attr')) {
			$tag = esc_attr($tag);
		}

		$attrs = $this->to_attr($attr1, $attr2);
		if ($text !== false) {
			return '<'.$tag.' '.$attrs.'>'.$text.'</'.$tag.'>';
		}
			// No text == self closing tag
		else {
			return '<'.$tag.' '.$attrs.' />';
		}
	}

	/**
	 * Helper: Turn an array or two into HTML attribute string
	 *
	 * @param  array  $arr1
	 * @param  array  $arr2
	 * @return string
	 */
	private function to_attr($arr1 = array(), $arr2 = array()) {
		$attrs = array();
		$arr = array_merge($arr1, $arr2);
		foreach ($arr as $key => $value) {
			if (function_exists('esc_attr')) {
				$key = esc_attr($key);
				$value = esc_attr($value);
			}
			$attrs[] = $key.'="'.$value.'"';
		}
		return implode(' ', $attrs);
	}

} // End Social_Comment_Form
