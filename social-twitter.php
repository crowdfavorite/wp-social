<?php
/**
 * Twitter integration for Social.
 *
 * @package Social
 */
add_filter(Social::$prefix.'register_service', array('Social_Twitter', 'register_service'));
add_filter(Social::$prefix.'request_body', array('Social_Twitter', 'request_body'));

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
	 * Hack to fix the "Twitpocalypse" bug on 32-bit systems.
	 *
	 * @static
	 * @param  string  $body
	 * @return string
	 */
	public static function request_body($body) {
		return preg_replace('/"id":(\d+)/', '"id":"$1"', $body);
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
	 * @param  int         $comment_id
	 * @return string
	 */
	public function profile_avatar($account, $comment_id = null) {
		if (is_int($account)) {
			$account = $this->account($account);
		}
		else if (!$account and $comment_id !== null) {
			return get_comment_meta($comment_id, Social::$prefix.'profile_image_url', true);
		}
		return $account->user->profile_image_url;
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
		$url = 'http://search.twitter.com/search.json?q='.implode('+OR+', $urls);
		$request = wp_remote_get($url);
		if (!is_wp_error($request)) {
			$response = json_decode($request['body']);

			if (count($response->results)) {
				$results = array();
				foreach ($response->results as $result) {
					if (!in_array($result->id, array_values($post_comments))) {
						$post_comments[] = $result->id;
						$results[] = $result;
					}
				}
			}
		}

		// Load the post author and their Twitter accounts
		if ($broadcasted_ids !== null) {
			$accounts = get_user_meta($post->post_author, Social::$prefix.'accounts', true);
			if (isset($accounts['twitter'])) {
				foreach ($accounts['twitter'] as $account) {
					if (isset($broadcasted_ids[$account->user->id])) {
						$tweets = $this->request($account, 'statuses/mentions', array(
							'since_id' => $broadcasted_ids[$account->user->id],
							'count' => 200
						));

						if (count($tweets->response)) {
							foreach ($tweets->response as $tweet) {
								if ($tweet->in_reply_to_status_id == $broadcasted_ids[$account->user->id]) {
									if (!in_array($tweet->id, array_values($post_comments))) {
										$post_comments[] = $tweet->id;
										$results[] = (object) array(
											'id' => $tweet->id,
											'from_user_id' => $tweet->user->id,
											'from_user' => $tweet->user->screen_name,
											'text' => $tweet->text,
											'created_at' => $tweet->created_at
										);
									}
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
	public function save_replies($post_id, array $replies) {
		foreach ($replies as $reply) {
			$account = (object) array(
				'user' => (object) array(
					'id' => $reply->from_user_id,
					'screen_name' => $reply->from_user,
				)
			);
			$comment_id = wp_insert_comment(array(
				'comment_post_ID' => $post_id,
				'comment_type' => $this->service,
				'comment_author' => $reply->from_user,
				'comment_author_email' => $this->service.'.'.$reply->id.'@example.com',
				'comment_author_url' => $this->profile_url($account),
				'comment_content' => $reply->text,
				'comment_date' => gmdate('Y-m-d H:i:s', strtotime($reply->created_at)),
			));
			update_comment_meta($comment_id, Social::$prefix.'account_id', $reply->from_user_id);
			update_comment_meta($comment_id, Social::$prefix.'profile_image_url', $reply->profile_image_url);
		}
	}

	/**
	 * Checks to see if the account has been deauthed based on the request response.
	 *
	 * @param  mixed   $response
	 * @param  object  $account
	 * @return bool
	 */
	public function check_deauthed($response, $account) {
		if ($response->result == 'error') {
			if ($response->response == 'Could not authenticate with OAuth.') {
				$deauthed = get_option(Social::$prefix.'deauthed', array());
				$deauthed[$this->service][$account->user->id] = 'Unable to publish to '.$this->title().' with account '.$this->profile_name($account).'. Please <a href="'.Social_Helper::settings_url().'">re-authorize</a> this account.';
				update_option(Social::$prefix.'deauthed', $deauthed);

				// Remove the account from the users
				unset($this->accounts[$account->user->id]);
				$this->save();
			}

			return false;
		}

		return true;
	}

} // End Social_Twitter
