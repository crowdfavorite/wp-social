<?php
/**
 * Twitter integration.
 *
 * @author Crowd Favorite
 * @copyright (c) 2010 Crowd Favorite. All Rights Reserved.
 * @package Social
 */
add_filter(Social::$prefix.'register_service', array('Social_Twitter', 'register_service'));

final class Social_Twitter extends Social_Service implements Social_IService {

	/**
	 * Registers this service with Social.
	 *
	 * @static
	 * @param  array  $services
	 * @return array
	 */
	public static function register_service(array $services) {
		$services += array(
			'twitter' => new Social_Twitter
		);

		return $services;
	}

	/**
	 * @var  string  the service
	 */
	public $service = 'twitter';

	/**
	 * @var string  the UI display value
	 */
	public $title = 'Twitter';

	/**
	 * @var  array  service's accounts
	 */
	protected $accounts = array();

	/**
	 * The max length a post can be when broadcasted.
	 *
	 * @return int
	 */
	public function max_broadcast_length() {
		return 140;
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
		return parent::do_request('twitter', $account, $api, $params, $method);
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

		return Social_Helper::create_user('twitter', $account->user->screen_name);
	}

	/**
	 * Updates the user's status.
	 *
	 * @param  int|object  $account
	 * @param  string      $status  status message
	 * @return array
	 */
	public function status_update($account, $status) {
		return $this->request($account, 'statuses/update', array('status' => $status), 'POST');
	}

	/**
	 * Returns the URL to the user's account.
	 *
	 * @param  object  $account
	 * @return string
	 */
	public function profile_url($account) {
		return 'http://twitter.com/'.$account->user->screen_name;
	}

	/**
	 * Returns the user's display name.
	 *
	 * @param  object  $account
	 * @return string
	 */
	public function profile_name($account) {
		return $account->user->screen_name;
	}

	/**
	 * Builds the user's avatar.
	 *
	 * @param  int|object  $account
	 * @return string
	 */
	public function profile_avatar($account) {
		if (is_int($account)) {
			$account = $this->account($account);
		}
		return $account->user->profile_image_url;
	}

	/**
	 * Searches the service to find any replies to the blog post.
	 *
	 * @param  int         $post_id
	 * @param  array       $urls
	 * @param  array|null  $broadcasted_ids
	 * @return array|bool
	 */
	function search_for_replies($post_id, array $urls, $broadcasted_ids = null) {
		// Search by URL
		$url = 'http://search.twitter.com/search.json?q='.implode('+OR+', $urls);
		$request = wp_remote_get($url);
		if (!is_wp_error($request)) {
			$response = json_decode($request['body']);

			if (count($response->results)) {
				// Load the comments already stored for this post
				$post_comments = get_post_meta($post_id, Social::$prefix.'aggregated_replies', true);
				if (empty($post_comments)) {
					$post_comments = array();
				}

				$results = array();
				foreach ($response->results as $result) {
					if (!in_array($result->id, array_values($post_comments))) {
						$post_comments[] = $result->id;
						$results[] = $result;
					}
				}

				if (count($results)) {
					update_post_meta($post_id, Social::$prefix.'aggregated_replies', $post_comments);
					return $results;
				}
			}
		}

		// TODO Search by broadcast IDs
		//$this->request();

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
					'id' => $reply->from_user_id,
					'screen_name' => $reply->from_user,
				)
			);
			wp_insert_comment(array(
				'comment_post_ID' => $post_id,
				'comment_type' => $this->service,
				'comment_author' => $reply->from_user,
				'comment_author_email' => $this->service.'.'.$reply->id.'@example.com',
				'comment_author_url' => $this->profile_url($account),
				'comment_content' => $reply->text,
				'comment_date' => gmdate('Y-m-d H:i:s', strtotime($reply->created_at)),
			));
		}
	}

} // End Social_Twitter
