<?php
/**
 * View
 *
 * Handles all of the views.
 *
 * @package Social
 */
final class Social_View {

	/**
	 * Initializes a view.
	 *
	 * @static
	 * @param  string  $file
	 * @param  array   $data
	 * @return Social_View
	 */
	public static function factory($file = null, array $data = array()) {
		return new Social_View($file, $data);
	}

	/**
	 * @var  string  view file
	 */
	protected $_file;

	/**
	 * @var  array  view data
	 */
	protected $_data = array();

	/**
	 * Sets the view file and data. Should be called by Social_View::factory().
	 *
	 * @param  string  $file
	 * @param  array   $data
	 */
	public function __construct($file = null, array $data = array()) {
		if ($file !== null) {
			$this->set_file($file);
		}

		if (empty($this->_data)) {
			$this->_data = $data;
		}
	}

	/**
	 * Calls render() when the object is echoed.
	 *
	 * @return string
	 */
	public function __toString() {
		try {
			$output = $this->render();
			return $output;
		}
		catch (Exception $e) {
			// Log the exception
			Social::log($e->getMessage());
			return '';
		}
	}

	/**
	 * Sets view data.
	 *
	 * @param  mixed  $key
	 * @param  string  $value
	 * @return Social_View
	 */
	public function set($key, $value = null) {
		if (is_array($key)) {
			foreach ($key as $name => $value) {
				$this->_data[$name] = $value;
			}
		}
		else {
			$this->_data[$key] = $value;
		}

		return $this;
	}

	/**
	 * Renders the view.
	 *
	 * @throws Exception
	 * @param  string  $file
	 * @return string
	 */
	public function render($file = null) {
		if ($file !== null) {
			$this->set_file($file);
		}

		if (empty($this->_file)) {
			throw new Exception(__('You must set a file to be used before rendering.', Social::$i18n));
		}

		$this->_data = apply_filters('social_view_data', $this->_data, $this->_file);
		extract($this->_data, EXTR_SKIP);
		ob_start();
		try {
			include $this->_file;
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}

		return ob_get_clean();
	}

	/**
	 * Sets the file to use for the view.
	 *
	 * @throws Exception
	 * @param  string  $file
	 * @return void
	 */
	private function set_file($file) {
		$file = apply_filters('social_view_set_file', $file);
		
		if (file_exists($file)) {
			$this->_file = $file;
		}
		else {
			$view = SOCIAL_PATH.'views/'.$file.'.php';
			if (file_exists($view)) {
				$this->_file = $view;
			}
		}

		if ($this->_file === null) {
			throw new Exception(sprintf(__('View %s does not exist.', Social::$i18n), $file));
		}
	}

} // End Social_View
