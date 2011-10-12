<?php
/**
 * @package Social
 * @subpackge services
 */
final class Social_Service_Facebook_Account extends Social_Service_Account implements Social_Interface_Service_Account {

	/**
	 * @var  bool  use pages flag
	 */
	protected $_use_pages = false;

	/**
	 * @var array
	 */
	protected $_pages = array();

	/**
	 * Sets the use pages flag.
	 *
	 * @param  object  $account
	 */
	public function __construct($account) {
		parent::__construct($account);

		if (isset($account->use_pages)) {
			$this->_use_pages = true;
		}

		if (isset($account->pages)) {
			$this->_pages = $account->pages;
		}
		else {
			$this->_pages = (object) array(
				'personal' => array(),
				'universal' => array()
			);
		}
	}

	/**
	 * Returns an array object of the account.
	 *
	 * @return object
	 */
	public function as_object() {
		$object = parent::as_object();
		$object->use_pages = $this->_use_pages;
		$object->pages = $this->_pages;
		return $object;
	}

	/**
	 * Returns whether the account uses pages as well.
	 *
	 * @abstract
	 * @param  bool|null  $use_pages
	 * @return Social_Service_Account|bool
	 */
	public function use_pages($use_pages = null) {
		if ($use_pages === null) {
			return $this->_use_pages;
		}

		$this->_use_pages = $use_pages;
		return $this;
	}

	/**
	 * Gets the ID of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function id() {
		return $this->_user->id;
	}

	/**
	 * Gets the name of the account.
	 *
	 * @abstract
	 * @return string
	 */
	public function name() {
		return $this->_user->name;
	}

	/**
	 * Gets the URL of the account.
	 *
	 * @return string
	 */
	public function url() {
		return 'http://facebook.com/profile.php?id='.$this->_user->id;
	}

	/**
	 * Gets the avatar of the account.
	 *
	 * @return string
	 */
	public function avatar() {
		return 'http://graph.facebook.com/'.$this->_user->id.'/picture';
	}

	/**
	 * Gets the username of the account.
	 *
	 * @return string
	 */
	public function username() {
		if (!isset($this->_user->username)) {
			$this->_user->username = $this->_user->name.'.'.$this->_user->id;
		}

		return $this->_user->username;
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
				$this->_pages->personal[$page->id] = $page;
			}
			else {
				$this->_pages->universal[$page->id] = $page;
			}
			return $this;
		}
		else {
			if ($is_profile and isset($this->_pages->personal[$page])) {
				return $this->_pages->personal[$page];
			}
			else if (!$is_profile and isset($this->_pages->universal[$page])) {
				return $this->_pages->universal[$page];
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
			if ($is_profile == 'combined') {
				$pages = $this->_pages->personal;
				foreach ($this->_pages->universal as $page) {
					if (!isset($pages[$page->id])) {
						$pages[$page->id] = $page;
					}
				}
				return $pages;
			}
			else if ($is_profile) {
				return $this->_pages->personal;
			}
			else {
				return $this->_pages->universal;
			}
		}

		if (count($pages)) {
			foreach ($pages as $_page) {
				if ($is_profile) {
					$this->_pages->personal[$_page->id] = $_page;
				}
				else {
					$this->_pages->universal[$_page->id] = $_page;
				}
			}
		}
		else {
			$this->_pages = (object) array(
				'personal' => array(),
				'universal' => array()
			);
		}
		
		return $this;
	}

} // End Social_Service_Facebook_Account
