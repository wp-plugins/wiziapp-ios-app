<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Checks the user login information
*
* This webservice checks the sent user login information and can return either
* true/false or the user basic information to the calling application
*
* @package WiziappWordpressPlugin
* @subpackage AppWebServices
* @author comobix.com plugins@comobix.com
*
* @param boolean $only_validate a flag indicating if the function should return a full response or just true/false
* @return boolean|array if $only_validate the function will return true/false but if not,
* the websrvice will return the user information: (id, name, package, next_billing, direction)
* along with the usual information
*
* @todo Calculate the next billing date according to the membership plugin
* @todo Get this from the user/blog
*/
class WiziappLoginServices{

	public function check($only_validate=FALSE){
		@header('Content-Type: application/json');
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		$deviceToken = $_REQUEST['device_token'];
		$appToken = $_SERVER['HTTP_APPLICATION'];
		$udid = $_SERVER['HTTP_UDID'];

		// if the request doesn't contain all that we need - leave
		if ( !empty($username) && !empty($password) && !empty($appToken) && !empty($udid)){
			$user = wp_authenticate($username, $password);
			if ( is_wp_error($user) ){
				$status = FALSE;
			} else {
				// check if wiziapp_users has user info
				$wus = new WiziappUserServices();
				if (!$wus->checkUserId($udid)){
					// no user data yet - add 'em
					$wp_user_id = get_current_user_id(); //are we logged in yet?
					$params = array(
						'wp_user_id' => $wp_user_id,
						'username' => $username,
						'password' => $password,
					);
					$wus->updateUserData($udid, $params);
				}
				/*
				* Notify the global admin of the CMS user id that is connected
				* to the device token
				*/
				if ( !empty($deviceToken) ){
					$params = array(
						'device_token' => $deviceToken,
					);
					$headers = array(
						'udid' => $udid,
					);
					$r = new WiziappHTTPRequest();
					$response = $r->api($params, '/push/user/'.$user->ID, $method='POST', $headers);

					// Mark the user so we will know he has a device token
					update_usermeta($user->ID, 'wiziapp_got_valid_mobile_token', $deviceToken);
				}

				$status = TRUE;
			}
			if ( $only_validate ){
				return ($status) ? $user : FALSE;
			} else {
				// id, name, package, next_billing
				$result = array();
				if ( $status ){
					$result = array(
						"id" => $user->ID,
						"name" => $user->display_name,
						"package" => $user->user_level,
						"next_billing" => null,
						"direction" => "LTR",
					);
				}

				$header = array(
					'action' => 'login',
					'status' => $status,
					'code' => ($status) ? 200 : 4004,
					'message' => ($status) ? '' : __('Incorrect username or password', 'wiziapp'),
				);

				echo json_encode(array_merge(array('header' => $header), $result));
				exit;
			}
		} else {
			WiziappLog::getInstance()->write('ERROR', "Something in the request was missing: !empty($username) && !empty($deviceToken) && !empty($appToken) && !empty($udid)", 'WiziappLoginServices::login');
		}
	}
}