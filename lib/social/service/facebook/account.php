<?php
/**
 * @package Social
 * @subpackge services
 */
final class Social_Service_Facebook_Account extends Social_Service_Account implements Social_Interface_Service_Account {

	/**
	 * @var  array
	 */
	protected $_pages = array();

	/**
	 * @var  bool
	 */
	protected $_use_personal_pages = false;

	/**
	 * @var  bool
	 */
	protected $_use_universal_pages = false;

	/**
	 * @var  object  broadcast page
	 */
	protected $_broadcast_page = null;

	/**
	 * Sets the use pages flag.
	 *
	 * @param  object  $account
	 */
	public function __construct($account) {
		parent::__construct($account);

		if (isset($account['use_personal_pages'])) {
			$this->_use_personal_pages = (bool) $account['use_personal_pages'];
		}

		if (isset($account['use_universal_pages'])) {
			$this->_use_universal_pages = (bool) $account['use_universal_pages'];
		}

		if (isset($account['pages'])) {
			$this->_pages = $account['pages'];
		}
		else {
			$this->_pages = array(
				'personal' => array(),
				'universal' => array()
			);
		}
	}

	/**
	 * Returns an array object of the account.
	 *
	 * @return array
	 */
	public function as_array() {
		$array = parent::as_array();
		$array['use_personal_pages'] = $this->_use_personal_pages;
		$array['use_universal_pages'] = $this->_use_universal_pages;
		$array['pages'] = $this->_pages;
		return $array;
	}

	/**
	 * Returns whether the account uses pages as well.
	 *
	 * @abstract
	 * @param  bool       $personal check
	 * @param  bool|null  $use_pages
	 * @return Social_Service_Account|bool
	 */
	public function use_pages($personal = false, $use_pages = null) {
		if ($use_pages === null) {
			if ($personal) {
				return $this->_use_personal_pages;
			}
			
			return $this->_use_universal_pages;
		}

		if ($personal) {
			$this->_use_personal_pages = $use_pages;
		}
		else {
			$this->_use_universal_pages = $use_pages;
		}

		return $this;
	}

	/**
	 * Gets the ID of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function id() {
		return $this->_user['id'];
	}

	/**
	 * Gets the name of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function name() {
		if ($this->has_user()) {
			return $this->_user['name'];
		}

		return parent::_name();
	}

	/**
	 * Gets the URL of the account.
	 *
	 * @return string
	 */
	public function url() {
		$url = 'http://facebook.com/';
		if ($this->has_user()) {
			$url .= 'profile.php?id='.$this->_user['id'];
		}

		return $url;
	}

	/**
	 * Gets the avatar of the account.
	 *
	 * @return string
	 */
	public function avatar() {
		if ($this->has_user()) {
			return 'http://graph.facebook.com/'.$this->_user['id'].'/picture';
		}

		return parent::_avatar();
	}

	/**
	 * Gets the username of the account.
	 *
	 * @return string
	 */
	public function username() {
		if ($this->has_user()) {
			if (!isset($this->_user['username'])) {
				$this->_user['username'] = $this->_user['name'].'.'.$this->_user['id'];
			}

			return $this->_user['username'];
		}

		return parent::_username();
	}

	/**
	 * Sets and gets the page.
	 *
	 * @param  object|int  $page
	 * @param  bool        $is_profile
	 * @return bool|Social_Service_Facebook_Account
	 */
	public function page($page, $is_profile = false) {
		if (is_object($page)) {
			if ($is_profile) {
				$this->_pages['personal'][$page->id] = $page;
			}
			else {
				$this->_pages['universal'][$page->id] = $page;
			}
			return $this;
		}
		else {
			if ($is_profile and isset($this->_pages['personal'][$page])) {
				return $this->_pages['personal'][$page];
			}
			else if (!$is_profile and isset($this->_pages['universal'][$page])) {
				return $this->_pages['universal'][$page];
			}
		}

		return false;
	}

	/**
	 * Gets all of the pages.
	 *
	 * @param  array  $pages
	 * @param  bool   $is_profile
	 * @return array|Social_Service_Facebook_Account
	 */
	public function pages(array $pages = null, $is_profile = false) {
		if ($pages === null) {
			if ($is_profile === true) {
				return $this->_pages['personal'];
			}
			else if ($is_profile === false) {
				return $this->_pages['universal'];
			}
			else if ($is_profile === 'combined') {
				$pages = $this->_pages['personal'];
				foreach ($this->_pages['universal'] as $page) {
					if (!isset($pages[$page->id])) {
						$pages[$page->id] = $page;
					}
				}
				return $pages;
			}

			return array();
		}

		if (count($pages)) {
			foreach ($pages as $_page) {
				if ($is_profile) {
					$this->_pages['personal'][$_page->id] = $_page;
				}
				else {
					$this->_pages['universal'][$_page->id] = $_page;
				}
			}
		}
		else {
			if ($is_profile) {
				$this->_pages['personal'] = array();
			}
			else {
				$this->_pages['universal'] = array();
			}
		}
		
		return $this;
	}

	/**
	 * Sets and gets the page to broadcast to.
	 *
	 * @param  object  $page
	 * @return object|Social_Service_Facebook_Account
	 */
	public function broadcast_page($page = null) {
		if ($page === null) {
			return $this->_broadcast_page;
		}

		$this->_broadcast_page = $page;
		return $this;
	}

} // End Social_Service_Facebook_Account
