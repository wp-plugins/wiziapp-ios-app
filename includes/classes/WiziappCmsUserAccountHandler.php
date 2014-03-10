<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappCmsUserAccountHandler{

	public function registration() {
		if(!empty($_POST)) {
			$_REQUEST['action'] = '';
			$username = $_REQUEST['user_login'];
			$email = $_REQUEST['user_email'];

			ob_start();
			require_once ABSPATH . 'wp-includes/registration.php';
			require_once ABSPATH . 'wp-login.php';
			ob_end_clean();

			WiziappLog::getInstance()->write('INFO', 'Before register user: ' . $username, 'account.wiziapp_user_registration');
			$user_id = register_new_user($username, $email);
			WiziappLog::getInstance()->write('INFO', 'After register user: ' . $username, 'account.wiziapp_user_registration');

			if (is_int($user_id)) {
				$result = __('Registration successfull', 'wiziapp');
			} else {
				$result = implode('<br>', $user_id->get_error_messages());
			}
		} else {
			$result = '';
		}

		return ($result);
	}

	public function forgotPassword() {
		if(!empty($_POST)) {
			$_REQUEST['action'] = '';
			$_POST['user_login'] = $_REQUEST['user_login'];
			$_POST['user_email'] = $_REQUEST['user_email'];

			ob_start();
			require_once ABSPATH . 'wp-includes/registration.php';
			require_once ABSPATH . 'wp-login.php';
			ob_end_clean();

			$status = retrieve_password();
			if ($status === true) {
				$result = __("Success", 'wiziapp');
			} else {
				$result = implode('<br>', $status->get_error_messages());
			}
		} else {
			$result = '';
		}

		return ($result);
	}

	/*
	* wiziapp_user_push_subscription
	*
	* Handles the push notifications subscriptions for the user
	* POST   /user/track/{key}/{value}
	* DELETE /user/track/{key}/{value}
	*/
	public function pushSubscription($key = '', $value = '') {
		$lot_keys = array('authors', 'categories', 'tags',);
		$result = array('action' => '', 'status' => TRUE, 'code' => 200, 'message' => '',);

		try {
			$udid = isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : $_SERVER['HTTP_UDID'];
			$endUser = new WiziappUserServices;
			$wiziapp_push_settings = $endUser->getUserPushSettings($udid);

			if ($wiziapp_push_settings == '') {
				$wiziapp_push_settings = array();
			} elseif ( ! is_array($wiziapp_push_settings) ) {
				// If $wiziapp_push_settings not empty, but not Array, it not proper condition.
				$wiziapp_push_settings = array();
				ob_start();
				var_dump($wiziapp_push_settings);
				WiziappLog::getInstance()->write('WARNING', 'Got $wiziapp_push_settings = ' . ob_get_clean(), "WiziappCmsUserAccountHandler.pushSubscription");
			}

			// Main Process
			if ($_SERVER['REQUEST_METHOD'] === 'GET' && $key === '') {
				$result['action'] = 'Get the user tracking list';

				foreach ($lot_keys as $lot_key) {
					if ( ! isset($wiziapp_push_settings[$lot_key]) )
						continue;

					if ( is_array($wiziapp_push_settings[$lot_key]) ) {
						// Check, if Items exist in blog yet.
						$wiziapp_push_settings[$lot_key] = array_filter($wiziapp_push_settings[$lot_key], array($this, '_check_' . $lot_key));

						if ( empty($wiziapp_push_settings[$lot_key]) ) {
							unset($wiziapp_push_settings[$lot_key]);
						}

						sort($wiziapp_push_settings[$lot_key]);
					} else {
						// do we really need this block of code? (AK)
						// If $wiziapp_push_settings['authors'] not empty, but not Array, it not proper condition.
						ob_start();
						var_dump($wiziapp_push_settings[$lot_key]);
						WiziappLog::getInstance()->write('WARNING', 'Got $wiziapp_push_settings = ' . ob_get_clean(), "WiziappCmsUserAccountHandler.pushSubscription");
						unset($wiziapp_push_settings[$lot_key]);
					}
				}
				$result['tracking'] = $wiziapp_push_settings;

			} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
				if (in_array($key, $lot_keys)) {
					$result['action'] = 'Subscribe the user for a specific list';

					if (preg_match('/\d+(,\d+)*/', $value)) {
						$wiziapp_push_settings = array_merge($wiziapp_push_settings, array($key => explode(',', $value),));
					} else {
						// delete $wiziapp_push_settings for $key (Assaf)
						$wiziapp_push_settings[$key] = '';
					}
				} elseif ($key === '') {
					$result['action'] = 'Update the user tracking list';

					foreach ($_POST as $key => $value) {
						if (in_array($key, $lot_keys) && is_array($value) && !empty($value)) {
							for ($i = 0, $count = count($value); $i < $count; $i++) {
								if (!ctype_digit($value[$i]) || $value[$i] <= 0) {
									throw new Exception('Got not proper value = ' . $value[$i] . ' for key = ' . $key . '[' . $i . ']');
								}
							}
							$wiziapp_push_settings = array_merge($wiziapp_push_settings, array($key => $value,));
						}
					}
				}

				// Assaf: DELETE is not in use. deletion is done via POST by sending an empty list
			} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && in_array($key, $lot_keys)) {
				if ($value === '') {
					$result['action'] = ' Un-subscribe the user for a specific list';
					unset($wiziapp_push_settings[$key]);
				} elseif (ctype_digit($value) && $value > 0 && ($found_key = array_search($value, $wiziapp_push_settings[$key])) !== FALSE) {
					unset($wiziapp_push_settings[$key][$found_key]);
					sort($wiziapp_push_settings[$key]);
					if (empty($wiziapp_push_settings[$key])) {
						unset($wiziapp_push_settings[$key]);
					}
				} else {
					throw new Exception('Not found to delete value = ' . $value . ' for key = ' . $key);
				}
			} else {
				throw new Exception('Service request is not proper');
			}

			$params = array();
			foreach ($lot_keys as $lot_key) {
				if (!isset($wiziapp_push_settings[$lot_key])) {
					$params[$lot_key] = '';
				} else {
					$params[$lot_key] = json_encode($wiziapp_push_settings[$lot_key]);
				}
			}

			$endUser->updateUserData($udid, $params);
		} catch (Exception $e) {
			$error_message = $e->getMessage();
			WiziappLog::getInstance()->write('ERROR', $error_message, "WiziappCmsUserAccountHandler.pushSubscription");
			$result['status'] = FALSE;
			$result['code'] = 500;
			$result['message'] = $error_message;
		}

		echo json_encode($result);
		exit;
	}

	private function _check_authors($author) {
		global $wpdb;
		return (bool) $wpdb->query("SELECT `ID` FROM ".$wpdb->posts." WHERE `post_author` = ".intval($author)." LIMIT 1");
	}

	private function _check_categories($category) {
		global $wpdb;
		$query =
		"SELECT `term_taxonomy_id` FROM ".$wpdb->term_taxonomy . " WHERE `term_id` = ".intval($category)." AND `taxonomy` = 'category' LIMIT 1";
		return (bool) $wpdb->query($query);
	}

	private function _check_tags($tag) {
		global $wpdb;
		$query =
		"SELECT `term_taxonomy_id` FROM ".$wpdb->term_taxonomy." WHERE `term_id` = ".intval($tag)." AND `taxonomy` = 'post_tag' LIMIT 1";
		return (bool) $wpdb->query($query);
	}
}