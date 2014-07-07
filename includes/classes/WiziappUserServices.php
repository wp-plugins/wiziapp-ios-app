<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage AppWebServices
* @author avi@wiziapp.com
*/

class WiziappUserServices{

	private $user_table;

	public function __construct(){
		$this->user_table = 'wiziapp_user_info';
	}

	/**
	* register new user with the udid we got from the app
	* @param string $udid
	*/
	public function newUdidUser($udid){
		global $wpdb;
		$tableName = $wpdb->prefix.$this->user_table;
		WiziappLog::getInstance()->write('DEBUG', "About to add {$udid} to {$tableName}", 'WiziappUserServices.newUser');
		$userInfo['udid']=$udid;
		$userInfo['appId']=WiziappConfig::getInstance()->app_id;
		$wpdb->insert($tableName, $userInfo, array('%s'));
		return $wpdb->insert_id;
	}

	/**
	* check existance of udid
	* @param string $udid
	*/
	public function checkUdid($udid){
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->user_table} AS c WHERE c.udid = %s", $udid);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappUserServices.checkUdid');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return true;
		} else {
			return false;
		}
	}

	/**
	* check existance of wp_user_id
	* @param string $udid
	*/
	public function checkUserId($udid){
		global $wpdb;

		$sql = $wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}{$this->user_table} AS c WHERE c.udid = %s", $udid);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappUserServices.checkUserId');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result[0]['wp_user_id'];
		} else {
			return false;
		}
	}

	/**
	* updated the local wiziapp_users table
	* with any of the following:
	* - udid
	* - wp_user_id
	* - (wp) username
	* - (wp) (password)
	* - user push notification (as json strings)
	* -- authors
	* -- categories
	* -- tags
	*/
	public function updateUserData($udid, $params, $updateAdmin = true){
		global $wpdb;
		$userInfo = array();
		$format = array();
		$tableName = $wpdb->prefix.$this->user_table;
		foreach ($params as $key => $value){
			if ( $key === 'wp_user_id' && is_null($value) ) {
				// To avoid error "Incorrect integer value: '' for column 'wp_user_id'"
				continue;
			}

			// this part is somewhat superfluos
			$userInfo[$key] = $value;
			// this too is superfluos; it's the default behaviour
			$format[] = '%s';
		}
		$where['udid']=$udid;
		WiziappLog::getInstance()->write('DEBUG', "About to update {$tableName} with users params", 'WiziappUserServices.updateUserData');

		$result = $wpdb->update($tableName, $userInfo, $where, $format);

		// If success - update wiziapp server (for backup purpose)
		if ($result && $updateAdmin){
			$this->updateWiziappServer($udid, $params);
		}

		// could be 0 or false or actual result
		return $result;
	}

	/**
	* updated the wiziapp server (wiziapp_end_users table)
	* with any of the following:
	* - udid
	* - user push notification
	* -- authors
	* -- categories
	* -- tags
	*/
	public function updateWiziappServer($udid, $params){
		$app_id=WiziappConfig::getInstance()->app_id;
		$params['udid']=$udid;
		$jsonParams = array(
			'params' => json_encode($params)
		);

		$r = new WiziappHTTPRequest();
		//$response = $r->api($profile, '/setUserInfo/' . $jsonParams , 'POST');
		//$response = $r->api($params, '/setUserInfo?app_id=' . WiziappConfig::getInstance()->app_id , 'POST');
		//https://api.apptelecom.local/application/64/setUserInfo?params={"username":"wiziapp_foobar"}
		$url = '/application/'. $app_id . '/setUserInfo';
		//$url .= '?params='.json_encode($params);
		$response = $r->api($jsonParams, $url , 'POST');

		WiziappLog::getInstance()->write('DEBUG', "The response is " . print_r($response, TRUE), "WiziappUserServices.updateWiziappServer");
		if (is_wp_error($response)) {
			return FALSE;
		}
		$tokenResponse = json_decode($response['body'], TRUE);
		if (empty($tokenResponse) || ! $tokenResponse['header']['status']) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	* restores all current blog's user data
	* (currently udid & push notifications)
	* from the wiziapp server (wiziapp_end_users table)
	*/
	public function restoreUserData(){
		$params['app_id']=WiziappConfig::getInstance()->app_id;
		$url = '/application';
		$url .= '/'. $params['app_id'] ;
		$url .= '/getUserInfo/';
		$r = new WiziappHTTPRequest();
		$response = $r->api($params, $url, 'POST');

		WiziappLog::getInstance()->write('DEBUG', "The response is " . print_r($response, TRUE), "WiziappUserServices.updateWiziappServer");
		if (is_wp_error($response)) {
			return FALSE;
		}
		$userList = json_decode($response['body'], TRUE);

		if (empty($userList)) { // || ! $tokenResponse['header']['status']) {
			return FALSE;
		}

		foreach ($userList as $user){
			if ( ! isset( $user['udid'] ) ) {
				continue;
			}

			$udid = $user['udid'];

			// To avoid error "Duplicate entry for key 'udid'"
			if ( ! $this->checkUdid($udid) ) {
				// newUser()
				$this->newUdidUser($udid);
			}

			//$params = array();
			//$params = $user['params'];
			//updateUserData
			$this->updateUserData($udid, $user, false);
			//do NOT updateWiziappServer()!!!
		}
	}

	public function getUserData($udid){
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->user_table} AS c WHERE c.udid = %s", $udid);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappUserServices.getUserData');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if (!$result) {
			return false;
		}
		return $result;
	}

	/**
	* load user push settings
	* first try loading from wiziapp_end_users table.
	* end_users is more important b/c it stores according to udid (and not by user_id),
	* so it works also for unregisterred users.
	*/
	public function getUserPushSettings($udid){
		$pushSettings = array();

		// getUserData
		$userData = $this->getUserData($udid);
		if ($userData){
			if (!isset($userData[0]['authors'])) {
				$userData[0]['authors']='';
			}
			if (!isset($userData[0]['categories'])) {
				$userData[0]['categories']='';
			}
			if (!isset($userData[0]['tags'])) {
				$userData[0]['tags']='';
			}
			$pushSettings['authors']=json_decode($userData[0]['authors']);
			$pushSettings['categories']=json_decode($userData[0]['categories']);
			$pushSettings['tags']=json_decode($userData[0]['tags']);
		}

		if ( empty($pushSettings) ){
			// If not exist - try loading from user_meta
			$userId = $this->checkUserId($udid);
			if ($userId){
				$wiziapp_push_settings = get_user_meta($userId, 'wiziapp_push_settings', TRUE);
				if (is_array($wiziapp_push_settings)) {
					//TODO: fix
					//$metaPushSettings = $wiziapp_push_settings['whatsTheVariableName'];
					$pushSettings['authors']=$wiziapp_push_settings['authors'];
					$pushSettings['categories']=$wiziapp_push_settings['categories'];
					$pushSettings['tags']=$wiziapp_push_settings['tags'];
				}

				// -- if exists in user_meta (and not in end_users (we wouldn't have checked user_meta otherwise))
				// -- add to end_users (and backup to server)
				if (!empty($pushSettings)){
					$params = array();
					foreach ($pushSettings as $key => $value){
						$params[$key] = json_encode($value);
					}
					//on the way, let's also add the username;
					$userInfo = get_userdata($userId);
					if ($userInfo){
						$params['username'] = $userInfo->user_login;
					}
					$this->updateUserData($udid, $params);
				}
			}
		}

		return $pushSettings;
	}

	public function getAllUdids(){
		global $wpdb;

		$sql = "SELECT udid FROM {$wpdb->prefix}{$this->user_table}";
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappUserServices.getAllUdids');
		$result = $wpdb->get_results($sql, OBJECT);

		if (!$result) {
			return false;
		}
		foreach ($result as $line){
			$set[] = $line->udid;
		}
		return $set;
	}
}