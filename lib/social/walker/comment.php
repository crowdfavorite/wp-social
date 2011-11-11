<?php
/**
 * Custom comment walker.
 *
 * @package Social
 * @subpackage walkers
 */
final class Social_Walker_Comment extends Walker_Comment {

	/**
	 * @see Walker::start_lvl()
	 * @since 2.7.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of comment.
	 * @param array $args Uses 'style' argument for type of HTML list.
	 */
	public function start_lvl(&$output, $depth, $args) {
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
	public function end_lvl(&$output, $depth, $args) {
		$GLOBALS['comment_depth'] = $depth + 1;

		switch ($args['style']) {
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

