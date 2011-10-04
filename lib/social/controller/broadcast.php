<?php
/**
 * Broadcast Controller
 *
 * @package Social
 * @subpackage controllers
 */
final class Social_Controller_Broadcast extends Social_Controller {

	/**
	 * Displays the broadcast options form.
	 * 
	 * @return void
	 */
	public function action_options() {
		$post = get_post($this->request->post('post_ID'));
		if ($post === null or get_post_meta($post->ID, '_social_notify', true) != '1') {
			if ($post === null) {
				wp_redirect(admin_url());
			}
			
			return;
		}

		$errors = array();
		$services = $this->social->services();
		$broadcasted_ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}

		$accounts_selected = false;
		if ($this->request->post('social_action') !== null) {
			foreach ($services as $key => $service) {
				$content = $this->request->post('social_'.$key.'_content');
                if (count($service->accounts()) and $this->request->post('social_'.$key.'_accounts') !== null) {
                    if (empty($content)) {
                        $errors[$key] = sprintf(__('Please enter some content for %s.', Social::$i18n), $service->title());
                    }
                    else if (strlen($content) > $service->max_broadcast_length()) {
                        $errors[$key] = sprintf(__('Content for %s must not be longer than %s characters.', Social::$i18n), $service->title(), $service->max_broadcast_length());
                    }
                }

				if ($this->request->post('social_'.$key.'_accounts') !== null) {
					$accounts_selected = true;
				}
			}

			if (!in_array($post->post_status, array('future', 'pending')) and !$accounts_selected and !isset($errors['rebroadcast'])) {
				$errors['rebroadcast'] = __('Please select at least one account to broadcast to.', Social::$i18n);
			}

			if (!count($errors)) {
				$broadcast_accounts = array();
				foreach ($services as $key => $service) {
					if ($this->request->post('social_'.$key.'_accounts') !== null) {
						$accounts = array();
						foreach ($this->request->post('social_'.$key.'_accounts') as $account) {
							$account = explode('|', $account);

							$accounts[] = (object) array(
								'id' => $account[0],
								'universal' => isset($account[1]),
							);
						}

						if (!empty($accounts)) {
							$broadcast_accounts[$key] = $accounts;
						}

						update_post_meta($post->ID, '_social_'.$key.'_content', $_POST['social_'.$key.'_content']);
					}
				}

				update_post_meta($post->ID, '_social_broadcast_accounts', $broadcast_accounts);

				if (!in_array($this->request->post('social_action'), array('Schedule', 'Update'))) {
					$this->action_run($post);
				}

				$location = $this->request->post('location');
				if ($location == null) {
					$location = get_edit_post_link($post->ID, false);
				}
				
				wp_redirect($location);
				exit;
			}
		}

		$step_text = 'Publish';
		if ($this->request->post('social_broadcast') !== null or $this->request->post('social_action') !== null) {
			if (($this->request->post('social_action') === null and $this->request->post('social_broadcast') == 'Edit') or
				$this->request->post('social_action') == 'Update')
			{
				$step_text = 'Update';
			}
			else if ($this->request->post('social_action') != 'Publish') {
				$step_text = 'Broadcast';
			}
		}
		else if ($post->post_status == 'future' or ($this->request->post('publish') == 'Schedule')) {
			$step_text = 'Schedule';
		}

		$broadcast_accounts = array();
		$_broadcast_accounts = get_post_meta($post->ID, '_social_broadcast_accounts', true);
		if (!empty($_broadcast_accounts) and count($_broadcast_accounts)) {
			foreach ($_broadcast_accounts as $service => $accounts) {
				foreach ($accounts as $account) {
					if (!isset($broadcast_accounts[$service])) {
						$broadcast_accounts[$service] = array();
					}

					$broadcast_accounts[$service][$account->id] = true;
				}
			}
		}

		$default_accounts = apply_filters('social_default_accounts', Social::option('default_accounts'), $post);
		echo Social_View::factory('wp-admin/post/broadcast/options', array(
			'errors' => $errors,
			'services' => $services,
			'post' => $post,
			'default_accounts' => $default_accounts,
			'broadcasted_ids' => $broadcasted_ids,
			'broadcast_accounts' => $broadcast_accounts,
			'step_text' => $step_text,
			'location' => $this->request->post('location'),
		));
		exit;
	}

    /**
     * Broadcasts a post to the services.
     *
     * @param  int|WP_Post  $post_id  post id or post object
     * @return void
     */
	public function action_run($post_id = null) {
		if ($post_id === null) {
			$post_id = intval($this->request->query('post_ID'));
		}

		$post = $post_id;
		if (is_int($post_id)) {
			$post = get_post($post_id);
		}

		if ($post === null) {
			Social::log('Failed to broadcast post :post_id.', array(
				'post_id' => $post_id,
			));
			return;
		}

		// Load content to broadcast (accounts, broadcast message, etc)
		$personal_accounts = null;
		$broadcast_accounts = get_post_meta($post->ID, '_social_broadcast_accounts', true);
		$errored_accounts = false;
		$broadcasted_ids = get_post_meta($post->ID, '_social_broadcasted_ids', true);
		if (empty($broadcasted_ids)) {
			$broadcasted_ids = array();
		}
		foreach ($broadcast_accounts as $key => $accounts) {
			$service = $this->social->service($key);
			if ($service) {
				$message = null;
				foreach ($accounts as $account) {
					if ($account->universal != '1') {
						if ($personal_accounts === null) {
							$personal_accounts = get_user_meta($post->post_author, 'social_accounts', true);
						}

						if (isset($personal_accounts[$key][$account->id])) {
							$class = 'Social_Service_'.$key.'_Account';
							$account = new $class($personal_accounts[$key][$account->id]);
						}
						else {
							$account = false;
						}
					}
					else {
						$account = $service->account($account->id);
					}

					if ($account !== false) {
						// Load the message
						if ($message === null) {
							$message = get_post_meta($post->ID, '_social_'.$key.'_content', true);
						}

						if (!empty($message)) {
							Social::log('Broadcasting to :username, account #:id. (:service)', array(
								'id' => $account->id(),
								'username' => $account->name(),
								'service' => $service->title(),
							));
							
							$response = $service->broadcast($account, $message, array(), $post->ID);
							if ($response->limit_reached()) {
								if (!isset($errored_accounts[$key])) {
									$errored_accounts[$key] = array();
								}

								$reason = 'Rate limit reached.';
								$errored_accounts[$key][] = (object) array(
									'account' => $account,
									'reason' => $reason,
								);
								Social::log('Broadcasting to :username, account #:id FAILED. Reason: :reason', array(
									'id' => $account->id(),
									'username' => $account->name(),
									'reason' => $reason,
								));
							}
							else if ($response->duplicate_status()) {
								if (!isset($errored_accounts[$key])) {
									$errored_accounts[$key] = array();
								}

								$reason = 'Duplicate status.';
								$errored_accounts[$key][] = (object) array(
									'account' => $account,
									'reason' => $reason,
								);
								Social::log('Broadcasting to :username, account #:id FAILED. Reason: :reason', array(
									'id' => $account->id(),
									'username' => $account->name(),
									'reason' => $reason,
								));
							}
							else if ($response->deauthorized()) {
								if (!isset($errored_accounts[$key])) {
									$errored_accounts[$key] = array();
								}

								$reason = 'Account deauthorized.';
								$errored_accounts[$key][] = (object) array(
									'account' => $account,
									'reason' => $reason,
									'deauthed' => true,
								);
								Social::log('Broadcasting to :username, account #:id FAILED. Reason: :reason', array(
									'id' => $account->id(),
									'username' => $account->name(),
									'reason' => $reason,
								));
							}
							else if ($response->general_error()) {
								if (!isset($errored_accounts[$key])) {
									$errored_accounts[$key] = array();
								}

								$reason = 'Unknown error.';
								$errored_accounts[$key][] = (object) array(
									'account' => $account,
									'reason' => $reason,
								);
								Social::log('Broadcasting to :username, account #:id FAILED. Reason: :reason'."\n\n".'Response:'."\n\n".':response', array(
									'id' => $account->id(),
									'username' => $account->name(),
									'reason' => $reason,
									'response' => print_r($response, true),	
								));
							}
							else {
								if (!isset($broadcasted_ids[$key])) {
									$broadcasted_ids[$key] = array();
								}

								$this->social->add_broadcasted_id($post->ID, $key, $response->id(), $message, $account->id(), $account->username());

								do_action('social_broadcast_response', $response, $key);

								Social::log('Broadcasting to :username, account #:id COMPLETE. (:service)', array(
									'id' => $account->id(),
									'username' => $account->name(),
									'service' => $service->title(),
								));
							}
						}
					}
				}
			}

			if (!isset($broadcasted_ids[$key])) {
				delete_post_meta($post->ID, '_social_'.$key.'_content');
			}
		}

		// Errored accounts?
		if ($errored_accounts !== false) {
			$deauthed_accounts = false;
			$_broadcast_accounts = array();
			foreach ($errored_accounts as $key => $accounts) {
				foreach ($accounts as $account) {
					if (isset($account->deauthed)) {
						if (!isset($deauthed_accounts[$key])) {
							$deauthed_accounts[$key] = array();
						}

						$deauthed_accounts[$key][] = $account;
					}

					if (isset($broadcasted_ids[$key]) and isset($broadcast_accounts[$key][$account->id])) {
						if (!isset($_broadcast_accounts[$key])) {
							$_broadcast_accounts[$key] = array();
						}

						$service = $this->social->service($key);
						if ($service !== false) {
							$account = $service->account($account->id);
							if ($account !== false) {
								$_broadcast_accounts[$key][$account->id] = (object) array(
									'id' => $account->id,
									'universal' => $account->universal(),
								);
							}
						}
					}
				}
			}

			update_post_meta($post->ID, '_social_broadcast_error', $errored_accounts);
			if (count($_broadcast_accounts)) {
				update_post_meta($post->ID, '_social_broadcast_accounts', $_broadcast_accounts);
			}
			else {
				delete_post_meta($post->ID, '_social_broadcast_accounts');
			}

			// Retry?
			$retry = Social::option('retry_broadcast');
			if (is_array($retry) and !in_array($post->ID, $retry)) {
				$retry[] = $post->ID;
				Social::option('retry_broadcast', $retry);
			}

			// Deauthed accounts?
			if ($deauthed_accounts !== false) {
				$this->send_publish_error($post, $deauthed_accounts);
			}
		}
		else {
			delete_post_meta($post->ID, '_social_broadcast_accounts');
			foreach ($this->social->services() as $service) {
				delete_post_meta($post->ID, '_social_'.$service->key().'_content');
			}
		}
	}

	/**
	 * Sends an email to notify the post author about deauthorized accounts.
	 *
	 * @param  object  $post
	 * @param  array   $accounts
	 * @return void
	 */
	private function send_publish_error($post, $accounts) {
		$author = get_userdata($post->post_author);
		$message = Social_View::factory('wp-admin/post/broadcast/error/email', array(
			'social' => $this->social,
			'accounts' => $accounts,
			'post' => $post,
		));

		$subject = sprintf(__('%s: Failed to broadcast post with Social.', Social::$i18n), get_bloginfo('name'));

		wp_mail($author->user_email, $subject, $message);
	}

} // End Social_Controller_Broadcast
