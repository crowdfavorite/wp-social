<?php
/**
 * Class Social plugins will extend.
 *
 * @package Social
 * @subpackage plugins
 */
abstract class Social_Plugin {

	/**
	 * @param  string  $key
	 * @param  int     $comment_id
	 * @param  array   $comments
	 * @param  bool    $parent
	 * @return void
	 */
	public static function add_to_social_items($key, $comment_id, array &$comments, $parent = false) {
		$object = null;
		$_comments = array();
		foreach ($comments as $id => $comment) {
			if (is_int($id)) {
				if ($comment->comment_ID == $comment_id) {
					$object = $comment;
				}
				else {
					$_comments[] = $comment;
				}
			}
			else {
				if (isset($_comments[$id])) {
					$_comments[$id] = array_merge($_comments[$id], $comment);
				}
				else {
					$_comments[$id] = $comment;
				}
			}
		}
		$comments = $_comments;

		if ($object !== null) {
			if ($parent) {
				if (!isset($comments['social_items']['parent'])) {
					$comments['social_items']['parent'] = array();
				}

				if (!isset($comments['social_items']['parent'][$key])) {
					$comments['social_items']['parent'][$key] = array();
				}

				$comments['social_items']['parent'][$key][] = $object;
			}
			else {
				if (!isset($comments['social_items'])) {
					$comments['social_items'] = array();
				}

				if (!isset($comments['social_items'][$key])) {
					$comments['social_items'][$key] = array();
				}

				$comments['social_items'][$key][$comment_id] = $object;
			}
		}
	}

	public static function add_social_items_count($items, &$groups) {
		foreach ($items as $group => $_items) {
			if ($group == 'parent') {
				self::add_social_items_count($_items, $groups);
			}
			else {
				if (!isset($groups['social-'.$group])) {
					$groups['social-'.$group] = 0;
				}

				$groups['social-'.$group] = $groups['social-'.$group] + count($_items);
			}
		}
	}

} // End Social_Plugin