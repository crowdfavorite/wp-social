<?php
/**
 * Twitter implementation for Social.
 *
 * @package Social
 * @subpackage plugins
 */
if (class_exists('Social') and !class_exists('Social_Twitter')) {

final class Social_Twitter {

	/**
	 * Registers Twitter to Social.
	 *
	 * @static
	 * @wp-filter  social_register_service
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services[] = 'twitter';
		return $services;
	}

	/**
	 * Adds to the avatar comment types array.
	 *
	 * @static
	 * @param  array  $types
	 * @return array
	 */
	public static function get_avatar_comment_types(array $types) {
		return array_merge($types, array('social-twitter'));
	}

	/**
	 * Pre-processor to the comments.
	 *
	 * @wp-filter social_comments_array
	 * @static
	 * @param  array  $comments
	 * @param  int    $post_id
	 * @return array
	 */
	public static function comments_array(array $comments, $post_id) {
		global $wpdb;

        $comment_ids = array();
        foreach ($comments as $comment) {
            $comment_ids[] = $comment->comment_ID;
        }

        if (count($comment_ids)) {
	        $results = $wpdb->get_results("
                SELECT meta_key, meta_value, comment_id
                  FROM $wpdb->commentmeta
                 WHERE comment_id IN (".implode(',', $comment_ids).")
                   AND meta_key = 'social_in_reply_to_status_id'
                    OR meta_key = 'social_status_id'
                    OR meta_key = 'social_raw_data'
                    OR meta_key = 'social_profile_image_url'
            ");

			$broadcasted_ids = get_post_meta($post_id, '_social_broadcasted_ids', true);
	        if (empty($broadcasted_ids)) {
		        $broadcasted_ids = array();
	        }

            $social_items = array();
            if (isset($comments['social_items'])) {
                $social_items = $comments['social_items'];
                unset($comments['social_items']);
            }

            $_results = array();
            $in_reply_ids = array();
            $comment_raw = array();
            $formatted_content = array();
            foreach ($results as $result) {
				if ($result->meta_key == 'social_in_reply_to_status_id') {
					if (!isset($in_reply_ids[$result->meta_value])) {
						$in_reply_ids[$result->meta_value] = array();
					}
					$in_reply_ids[$result->meta_value][] = $result->comment_id;
				}
				else if ($result->meta_key == 'social_raw_data') {
					$raw = json_decode(base64_decode($result->meta_value));
                    $comment_raw[$result->comment_id] = $raw;
					if (isset($broadcasted_ids['twitter']) and isset($raw->retweeted_status) and isset($raw->retweeted_status->id)) {
						foreach ($broadcasted_ids['twitter'] as $broadcasted) {
							if (isset($broadcasted[$raw->retweeted_status->id])) {
								self::add_to_social_items($result->comment_id, $comments, $social_items);
							}
						}
					}
                    else {
                        $service = Social::instance()->service('twitter');
                        if ($service !== false) {
                            if (empty($formatted_content)) {
                                foreach ($broadcasted_ids['twitter'] as $broadcasted) {
                                    foreach ($broadcasted as $id => $data) {
                                        $message = self::strip_retweet_data($data['message'], $data['username'], false);
                                        $formatted_content[$id] = array(
                                            'username' => $data['username'],
                                            'message' => $message,
                                        );
                                    }
                                }
                            }

                            foreach ($formatted_content as $data) {
                                $content = self::strip_retweet_data($raw->text, $data['username']);
                                if ($content == $data['message']) {
                                    self::add_to_social_items($result->comment_id, $comments, $social_items);
                                }
                            }

                        }
                    }
				}
				else {
					$_results[] = $result;
				}
            }

			if (count($_results)) {
				$parents = array();
				foreach ($_results as $key => $result) {
                    $unset = false;
					if (in_array($result->meta_key, array('social_status_id', 'social_profile_image_url'))) {
                        if (isset($social_items['twitter']) and isset($social_items['twitter'][$result->comment_id])) {
                            $social_items['twitter'][$result->comment_id]->{$result->meta_key} = $result->meta_value;
                            $unset = true;
                        }

                        if ($result->meta_key == 'social_status_id' and isset($in_reply_ids[$result->meta_value])) {
                            foreach ($in_reply_ids[$result->meta_value] AS $comment_id) {
                                $parents[$comment_id] = $result->comment_id;
                            }
                            $unset = true;
                        }
                    }

                    if ($unset) {
                        $_results[$key];
                    }
				}
			}
            sort($_results);

            $_comments = array();
            if (!empty($parents)) {
                foreach ($comments as $key => $comment) {
                    if (is_object($comment)) {
                        if (isset($parents[$comment->comment_ID])) {
                            $comment->comment_parent = $parents[$comment->comment_ID];
                        }
                        else {
                            $comment->social_hashed_content = self::strip_retweet_data($comment->comment_content, $comment->comment_author);
                        }
                    }

                    $_comments[$key] = $comment;
                }
                $comments = $_comments;

                // Now attempt to match retweets of children comments
                $retweets = array();
                foreach ($comments as $key => $comment) {
                    if (is_object($comment) and substr($comment->comment_content, 0, 4) == 'RT @') {
                        $found = false;
                        foreach ($comments as $_comment) {
                            $hashed = self::strip_retweet_data($comment->comment_content, $_comment->comment_author);
                            $_comment_hashed = self::strip_retweet_data($_comment->comment_content, $_comment->comment_author, false);
                            if ($hashed == $_comment_hashed) {
                                if (!isset($_comments[$_comment->comment_ID])) {
                                    $retweets[$_comment->comment_ID] = array();
                                }

                                foreach ($_results as $result) {
                                    $comment->{$result->meta_key} = $result->meta_value;
                                }

                                $found = true;
                                $retweets[$_comment->comment_ID][] = $comment;
                                break;
                            }
                        }

                        if ($found) {
                            unset($comments[$key]);
                        }
                    }
                }

                if (count($retweets)) {
                    $_comments = array();
                    foreach ($comments as $key => $comment) {
                        if (is_object($comment) and isset($retweets[$comment->comment_ID])) {
                            $comment->social_items = $retweets[$comment->comment_ID];
                        }

                        $_comments[$key] = $comment;
                    }

                    $comments = $_comments;
                }
            }

            if (count($social_items)) {
                $comments['social_items'] = $social_items;
            }
        }
		return $comments;
    }

    /**
     * Enqueues the @Anywhere script.
     *
     * @static
     * @return void
     */
	public static function enqueue_assets() {
		$api_key = Social::option('twitter_anywhere_api_key');
		if (!empty($api_key)) {
			wp_enqueue_script('twitter_anywhere', 'http://platform.twitter.com/anywhere.js?id='.$api_key, array('social_js'), Social::$version, true);
		}
	}

    /**
     * Strips extra retweet data before comparing.
     *
     * @static
     * @param  string  $text
     * @param  string  $username
     * @param  bool    $retweet   is this a reply comment?
     * @return string
     */
    private static function strip_retweet_data($text, $username, $retweet = true) {
        $text = explode(' ', trim($text));
        $content = '';
        foreach ($text as $_content) {
            if (!empty($_content) and strpos($_content, 'http://') === false) {
                if ($retweet and in_array($_content, array('RT', '@'.$username.':'))) {
                    continue;
                }
                
                $content .= $_content.' ';
            }
        }

        return md5(trim($content));
    }

    /**
     * Adds a retweet to the original broadcasted post social items stack.
     *
     * @static
     * @param  int    $comment_id
     * @param  array  $comments
     * @param  array  $social_items
     */
    private static function add_to_social_items($comment_id, &$comments, &$social_items) {
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
            if (!isset($social_items['twitter'])) {
                $social_items['twitter'] = array();
            }

            $social_items['twitter'][$comment_id] = $object;
        }
    }

} // End Social_Twitter

define('SOCIAL_TWITTER_FILE', __FILE__);

// Filters
add_filter('social_register_service', array('Social_Twitter', 'register_service'));
add_filter('get_avatar_comment_types', array('Social_Twitter', 'get_avatar_comment_types'));
add_filter('social_comments_array', array('Social_Twitter', 'comments_array'), 10, 2);
add_action('wp_enqueue_scripts', array('Social_Twitter', 'enqueue_assets'));

}