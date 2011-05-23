<?php
/**
 * @author Crowd Favorite
 * @copyright (c) 2010 Crowd Favorite. All Rights Reserved.
 * @package Social
 */
add_filter(Social::$prefix.'register_service', array('Social_Facebook', 'register_service'));

final class Social_Facebook extends Social_Service implements Social_IService {

	/**
	 * Registers this service with Social.
	 *
	 * @static
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services += array(
			'facebook' => new Social_Facebook
		);

		return $services;
	}

	/**
	 * @var  string  the service
	 */
	public $service = 'facebook';

	/**
	 * @var string  the UI display value
	 */
	public $title = 'Facebook';

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 400;
	}

	/**
	 * Executes the request for the service.
	 *
	 * @param  int|object  $account  account to use
	 * @param  string      $api      API endpoint to request
	 * @param  array       $params   parameters to pass to the API
	 * @param  string      $method   GET|POST, default: GET
	 * @return array
	 */
	function request($account, $api, array $params = array(), $method = 'GET') {
		return parent::do_request('facebook', $account, $api, $params, $method);
	}

	/**
	 * Creates a WordPress User
	 *
	 * @param  int|object  $account  account to use to create WP account
	 * @return int
	 */
	function create_user($account) {
		if (is_int($account)) {
			$account = $this->account($account);
		}

		return Social_Helper::create_user('facebook', $account->user->username);
	}

	/**
	 * Updates the user's status.
	 *
	 * @param  int|object  $account
	 * @param  string      $status  status message
	 * @return array
	 */
	public function status_update($account, $status) {
		return $this->request($account, 'feed', array('message' => $status), 'POST');
	}

	/**
	 * Returns the URL to the user's account.
	 *
	 * @param  object  $account
	 * @return string
	 */
	public function profile_url($account) {
		return $account->user->link;
	}

	/**
	 * Returns the user's display name.
	 *
	 * @param  object  $account
	 * @return string
	 */
	public function profile_name($account) {
		return $account->user->name;
	}

	/**
	 * Builds the user's avatar.
	 *
	 * @param  int|object  $account
	 * @param  int         $comment_id
	 * @return string
	 */
	function profile_avatar($account, $comment_id = null) {
		if (is_int($account)) {
			$account = $this->account($account);
		}
		else if (!$account and $comment_id !== null) {
			$id = get_comment_meta($comment_id, Social::$prefix.'account_id', true);
			return 'http://graph.facebook.com/'.$id.'/picture';
		}
		return 'http://graph.facebook.com/'.$account->user->id.'/picture';
	}

	/**
	 * Searches the service to find any replies to the blog post.
	 *
	 * @param  object      $post
	 * @param  array       $urls
	 * @param  array|null  $broadcasted_ids
	 * @return array|bool
	 */
	function search_for_replies($post, array $urls, $broadcasted_ids = null) {
		// Load the comments already stored for this post
		$results = array();
		$post_comments = get_post_meta($post->ID, Social::$prefix.'aggregated_replies', true);
		if (empty($post_comments)) {
			$post_comments = array();
		}

		// Search by URL
		foreach ($urls as $url) {
			$url = 'https://graph.facebook.com/search?type=post&q='.$url;
			$request = wp_remote_get($url);
			if (!is_wp_error($request)) {
				$response = json_decode($request['body']);

				if (count($response->data)) {
					$results = array();
					foreach ($response->data as $result) {
						if (!in_array($result->id, array_values($post_comments))) {
							$post_comments[] = $result->id;
							$results[] = $result;
						}
					}
				}
			}
		}

		// Load the post author and their Twitter accounts
		if ($broadcasted_ids !== null) {
			$accounts = get_user_meta($post->post_author, Social::$prefix.'accounts', true);
			if (isset($accounts['facebook'])) {
				foreach ($accounts['facebook'] as $account) {
					if (isset($broadcasted_ids[$account->user->id])) {
						$comments = $this->request($account, $broadcasted_ids[$account->user->id])->comments;

						if (count($comments->data)) {
							foreach ($comments->data as $comment) {
								if (!in_array($comment->id, array_values($post_comments))) {
									$post_comments[] = $comment->id;
									$results[] = $comment;
								}
							}
						}
					}
				}
			}
		}

		if (count($results)) {
			update_post_meta($post->ID, Social::$prefix.'aggregated_replies', $post_comments);
			return $results;
		}

		return false;
	}

	/**
	 * Saves the replies as comments.
	 *
	 * @param  int    $post_id
	 * @param  array  $replies
	 * @return void
	 */
	function save_replies($post_id, array $replies) {
		foreach ($replies as $reply) {
			$account = (object) array(
				'user' => (object) array(
					'id' => $reply->from->id,
					'name' => $reply->from->name,
				)
			);
			$comment_id = wp_insert_comment(array(
				'comment_post_ID' => $post_id,
				'comment_type' => $this->service,
				'comment_author' => $reply->from->name,
				'comment_author_email' => $this->service.'.'.$reply->id.'@example.com',
				'comment_author_url' => $this->profile_url($account),
				'comment_content' => $reply->message,
				'comment_date' => gmdate('Y-m-d H:i:s', strtotime($reply->created_time)),
			));
			update_comment_meta($comment_id, Social::$prefix.'account_id', $reply->from->id);
		}
	}

} // End Social_Facebook
