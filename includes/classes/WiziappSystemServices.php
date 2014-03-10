<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage AdminWebServices
* @author comobix.com plugins@comobix.com
*/

class WiziappSystemServices{

	/**
	* Authenticate the request against the plugin token.
	* if the authentication fail, throw 404.
	*
	* @return bool
	*/
	public function checkSystemAuth(){
		// Verify the plugin token against our plugin_token
		$token = $_SERVER['HTTP_PLUGIN'];
		if ( $token != WiziappConfig::getInstance()->plugin_token ){
			header("HTTP/1.0 404 Not Found");
			exit;
		}
		return TRUE;
	}

	public function checkInstalledPlugin(){
		$header = array(
			'action' => 'checkInstalledPlugin',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		echo json_encode(array('header' => $header, 'version' => WIZIAPP_P_VERSION));
		exit;
	}

	public function updateThumbsConfiguration(){
		$this->checkSystemAuth();

		$thumbsJson = stripslashes($_POST['settings']);
		$thumbs = json_decode($thumbsJson, TRUE);
		$message = '';

		WiziappLog::getInstance()->write('DEBUG', "The params are ".print_r($_POST, TRUE), "system_webservices.wiziapp_updateThumbsConfiguration");

		if ( !$thumbs ){
			$status = FALSE;
			$message = 'Unable to decode thumbs configuration: '.$thumbsJson;
		} else {
			WiziappConfig::getInstance()->startBulkUpdate();
			// The request must be with the exact keys
			WiziappConfig::getInstance()->full_image_height = $thumbs['full_image_height'];
			WiziappConfig::getInstance()->full_image_width  = $thumbs['full_image_width'];

			WiziappConfig::getInstance()->images_thumb_height = $thumbs['images_thumb_height'];
			WiziappConfig::getInstance()->images_thumb_width  = $thumbs['images_thumb_width'];

			WiziappConfig::getInstance()->posts_thumb_height = $thumbs['posts_thumb_height'];
			WiziappConfig::getInstance()->posts_thumb_width  = $thumbs['posts_thumb_width'];

			WiziappConfig::getInstance()->featured_post_thumb_height = $thumbs['featured_post_thumb_height'];
			WiziappConfig::getInstance()->featured_post_thumb_width  = $thumbs['featured_post_thumb_width'];

			WiziappConfig::getInstance()->mini_post_thumb_height = $thumbs['mini_post_thumb_height'];
			WiziappConfig::getInstance()->mini_post_thumb_width  = $thumbs['mini_post_thumb_width'];

			WiziappConfig::getInstance()->comments_avatar_height = $thumbs['comments_avatar_height'];
			WiziappConfig::getInstance()->comments_avatar_width  = $thumbs['comments_avatar_width'];

			WiziappConfig::getInstance()->album_thumb_width  = $thumbs['album_thumb_width'];
			WiziappConfig::getInstance()->album_thumb_height = $thumbs['album_thumb_height'];

			WiziappConfig::getInstance()->video_album_thumb_width  = $thumbs['video_album_thumb_width'];
			WiziappConfig::getInstance()->video_album_thumb_height = $thumbs['video_album_thumb_height'];

			WiziappConfig::getInstance()->audio_thumb_width  = $thumbs['audio_thumb_width'];
			WiziappConfig::getInstance()->audio_thumb_height = $thumbs['audio_thumb_height'];

			//$status = update_option('wiziapp_settings', $options);
			$status = WiziappConfig::getInstance()->bulkSave();
			if ( !$status )  {
				$message = 'Unable to update thumbs settings';
			} else {
				$ce = new WiziappContentEvents();
				$ce->updateCacheTimestampKey();
			}
		}

		$header = array(
			'action' => 'thumbs',
			'status' => $status,
			'code' => ($status) ? 200 : 4004,
			'message' => $message,
		);

		echo json_encode(array('header' => $header));
		exit;
	}

	/**
	* Used to update the wiziapp_settings option
	* Used by the system control services in case of changes done in the account.
	* POST /wiziapp/system/settings
	*
	* @return regular json status header.
	*/
	public function updateConfiguration(){
		$this->checkSystemAuth();
		$status = FALSE;
		$key = $_POST['key'];

		if ( in_array($key, WiziappConfig::getInstance()->integer_values) ){
			$value = intval($_POST['value']);
		} elseif ( $key === 'push_message' ){
			preg_match('/^[A-Z\d\!\.\,\s]{10,105}/i', $_POST['value'], $matches);
			$value = $matches[0];
		} else {
			$value = stripslashes($_POST['value']);

			if ( in_array( $key, array( 'adsense', 'admob', 'analytics', ) ) ){
				$value = json_decode($value, TRUE);
			}
		}

		if ( isset(WiziappConfig::getInstance()->$key) ){
			$status = WiziappConfig::getInstance()->saveUpdate($key, $value);
			if ( $status ){
				$message = __('Settings updated', 'wiziapp');
				$ce = new WiziappContentEvents();
				$ce->updateCacheTimestampKey();

				ob_start();
				if ($key == 'app_icon'){
					// We need to re-download the icon if we are to use it locally
					$r = new WiziappHTTPRequest();
					$response = $r->api(array(), '/application/'.WiziappConfig::getInstance()->app_id.'/icons', 'GET');
					if ( !is_wp_error($response) ){
						// Save this in the application configuration file
						$file 	 = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').'/resources/icons.zip';
						$dirPath = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').'/resources/icons';
						require_once(ABSPATH . 'wp-admin/includes/file.php');
						if ( ( $creds = request_filesystem_credentials('') ) !== FALSE && WP_Filesystem($creds) ){
							global $wp_filesystem;
							if ( $wp_filesystem->put_contents($file, $response['body'], FS_CHMOD_FILE) && @file_exists($file) ){
								@unzip_file($file, $dirPath);
								@unlink($file);
							}
						}
					}
				}
				ob_end_clean();
			} else {
				$message = __('Unable to update settings', 'wiziapp');
			}
		} else {
			$message = __('Unknown key', 'wiziapp');
		}

		$header = array(
			'action' => 'screens',
			'status' => $status,
			'code' => ($status) ? 200 : 5000,
			'message' => $message,
		);

		echo json_encode(array('header' => $header));
		exit;
	}

	/**
	* Used to update the wiziapp_screens option
	* Used by the system control services in case of changes done in the template the app uses.
	* POST /wiziapp/system/screens
	* @todo add validation to the content of the screens
	*
	* @returns regular json status header.
	*/
	public function updateScreenConfiguration(){
		$this->checkSystemAuth();
		$screensJson = stripslashes($_POST['screens']);
		$screens = json_decode($screensJson, TRUE);
		$message = '';

		if ( !$screens ){
			$status = FALSE;
			$message = __('Unable to decode screens: ', 'wiziapp').$screensJson;
		} else {
			$status = update_option('wiziapp_screens', $screens);
			if ( !$status )  {
				$message = __('Unable to update screens', 'wiziapp');
			} else {
				$ce = new WiziappContentEvents();
				$ce->updateCacheTimestampKey();
			}
		}

		$header = array(
			'action' => 'screens',
			'status' => $status,
			'code' => ($status) ? 200 : 4004,
			'message' => $message,
		);

		echo json_encode(array('header' => $header));
		exit;
	}

	/**
	* Used to update the wiziapp_components option
	*
	* Used by the system control services in case of changes done in the
	* theme customization the app uses.
	*
	* POST /wiziapp/system/components
	*
	* @todo add validation to the content of the components
	*
	* @returns regular json status header.
	*/
	public function updateComponentsConfiguration(){
		$this->checkSystemAuth();
		$componentsJson = stripslashes($_POST['components']);
		$components = json_decode($componentsJson, TRUE);
		$message = '';

		if ( $components ){
			$status = update_option('wiziapp_components', $components);

			if ( $status )  {
				$ce = new WiziappContentEvents();
				$ce->updateCacheTimestampKey();
			} else {
				$message = __('Unable to update components, may be just the old value equal the new value', 'wiziapp');
			}
		} else {
			$status = FALSE;
			$message = __('Unable to decode components: ', 'wiziapp').$componentsJson;
		}

		$header = array(
			'action' => 'components',
			'status' => $status,
			'code' => ($status) ? 200 : 4004,
			'message' => $message,
		);

		echo json_encode(array('header' => $header));
		exit;
	}

	/**
	* Used to update the wiziapp_pages option
	*
	* Used by the system control services to defined which pages we are showing where
	*
	* POST /wiziapp/system/pages
	*
	* @todo add validation to the content of the pages
	*
	* @returns regular json status header.
	*/
	public function updatePagesConfiguration(){
		$this->checkSystemAuth();
		$options = get_option('wiziapp_pages');

		$pagesJson = stripslashes($_POST['pages']);
		$pages = json_decode($pagesJson, TRUE);

		if ( !$pages ){
			$status = FALSE;
			$message = __('Unable to decode pages: ', 'wiziapp').$pagesJson;
		} else {
			if ( empty($options) ){
				$options = $pages;
				$status = add_option('wiziapp_pages', $options, '', 'no');
				$message = __('Unable to create pages configuration', 'wiziapp');
			} else {
				$options = $pages;
				$status = update_option('wiziapp_pages', $options);
				$message = __('Unable to update pages configuration', 'wiziapp');
			}

			if ( $status )  {
				$ce = new WiziappContentEvents();
				$ce->updateCacheTimestampKey();
			}
		}

		$header = array(
			'action' => 'pages',
			'status' => $status,
			'code' => ($status) ? 200 : 4004,
			'message' => $message,
		);

		echo json_encode(array('header' => $header));
		exit;
	}

	public function listLogs(){
		$this->checkSystemAuth();
		WiziappSupport::getInstance()->listLogs();
	}

	public function getLogFile($log){
		$this->checkSystemAuth();
		WiziappSupport::getInstance()->getLog($log);
	}
}
