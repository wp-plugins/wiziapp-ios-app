<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* This class handles incoming request to the CMS.
* It will check if the request is ours and handle the
* related web services
*
* @package WiziappWordpressPlugin
* @subpackage Core
* @author comobix.com plugins@comobix.com
*
*/
class WiziappRequestHandler {

	private $errorReportingLevel = 0;

	function __construct(){
		add_action('parse_request', array(&$this, 'handleRequest'));
		add_action('init', array(&$this, 'logInitRequest'), 1);
	}

	function logInitRequest(){
		if (strpos($_SERVER['QUERY_STRING'], 'wiziapp/') !== FALSE){
			global $restricted_site_access;
			if ( !empty($restricted_site_access) ){
				// Avoid site restrictions by restricted site access plugin, this plugin will prevent our hooks from running
				remove_action('parse_request', array($restricted_site_access, 'restrict_access'), 1);
			}
            remove_action('wp_head', array('anchor_utils', 'ob_start'), 10000);
		}
	}

	/*
	* Intercept any incoming request to the blog, if the request is for our web services
	* which are identified by the wiziapp prefix pass it on to processing, if not
	* do nothing with it and let wordpress handle it
	*
	* @see WiziappRequestHandler::_routeRequest
	* @params WP object  the main wordpress object is passed by reference
	*/
	function handleRequest($wp){
		$request = $wp->request;
		if ( empty($request) ){
			// doesn't rewrite the requests, try to get the query string
			$request = urldecode($_SERVER['QUERY_STRING']);
		}
		WiziappLog::getInstance()->write('DEBUG', "Got a request for the blog: ".print_r($request, TRUE), "WiziappRequestHandler.handleRequest");

		if ( ( $pos = strpos($request, 'wiziapp/') ) === FALSE ){
			return;
		}

		if ( $pos != 0 ){
			$request = substr($request, $pos);
		}

		$request = str_replace('?', '&', $request);

		// check udid
		$udid = isset( $_SERVER['HTTP_UDID'] ) ? $_SERVER['HTTP_UDID'] : '';
		$wus =  new WiziappUserServices();
		if ( ! $wus->checkUdid($udid) ){
			// if doesn't exist - add it
			$wus->newUdidUser($udid);
		}

		//TODO -
		// 4. store push settings... where is it done?

		$this->_routeRequest($request);
	}

	public function handleGeneralError(){
		$error = error_get_last();

		if(($error['type'] === E_ERROR) || ($error['type'] === E_USER_ERROR)){
			ob_end_clean();
			$header = array(
				'action' => 'handleGeneralError',
				'status' => FALSE,
				'code' => 500,
				'message' => 'There was a critical error running the service',
			);

			if(stripos($error['message'], 'Allowed memory size of ') === false){
				WiziappLog::getInstance()->write('Error', "Caught an error: " . print_r($error, TRUE), "WiziappRequestHandler.handleGeneralError");
			}

			if ( $this->errorReportingLevel !== 0 ){
				$header['message'] = implode('::', $error);
			}

			echo json_encode(array('header' => $header));
			exit();
		}
	}

	/*
	* Serves as a routing table, if the incoming request has our prefix,
	* check if we can handle the requested method, if so call the method.
	*
	* This is the first routing table function, it will separate the
	* content requests from the webservices requests.
	*
	* One major difference between the webservices and the content requests
	* is that the content requests are getting cached on the server side
	* and webservices requests should not ever be cached as a whole.
	*/
	private function _routeRequest($request){
		$this->errorReportingLevel = error_reporting(0);
		register_shutdown_function(array($this, 'handleGeneralError'));

		$fullReq = explode('&', $request);
		$req = explode('/', $fullReq[0]);

		$service = $req[1];
		$action = $req[2];

		if ( $service == 'user' ){
			if ( $action == 'check' || $action == 'login' ){
				$this->runService('Login', 'check', FALSE);
			} elseif ( $action == 'track' ){
				$parameter_key = (isset($req[3])) ? $req[3] : '';
				$parameter_value = (isset($req[4])) ? $req[4] : '';
				$cms_user_account_handler = new WiziappCmsUserAccountHandler;
				$cms_user_account_handler->pushSubscription($parameter_key, $parameter_value);
			} elseif ( $action == 'forgot_pass' ){
				$this->runScreenBy('System', 'ForgotPassword', null);
			}
		} elseif ( $service == 'content' || $service == 'search' ){
			ob_start();
			// Content requests should trigger a the caching
			$cache = WiziappCache::getCacheInstance(array('duration' => 1800));

			// Prepare the Key to search the Content in Cache.
			$key = str_replace('/', '_', $request);
			if (function_exists('is_multisite') && is_multisite()){
				global $wpdb;
				$key .= $wpdb->blogid;
			}
			$key .= WiziappContentEvents::getCacheTimestampKey();
			global $wiziappLoader;
			$key .= $wiziappLoader->getVersion();

			// Added the accept encoding headers, so we won't try to return zip when we can't
			$encoding = '';
			if ( isset($_SERVER["HTTP_ACCEPT_ENCODING"]) ){
				$encoding = $_SERVER["HTTP_ACCEPT_ENCODING"];
			} elseif ( isset($_SERVER["HTTP_X_CEPT_ENCODING"]) ){
				$encoding = $_SERVER["HTTP_X_CEPT_ENCODING"];
			}
			if ( strpos($encoding, 'x-gzip') !== FALSE ){
				$encoding = 'x-gzip';
			} elseif ( strpos($encoding,'gzip') !== FALSE ){
				$encoding = 'gzip';
			}
			$key .= $encoding;

			// Get eTag from Header
			$eTagIncoming = (isset($_SERVER['HTTP_IF_NONE_MATCH'])) ? $cache->getEtagFromHeader($_SERVER['HTTP_IF_NONE_MATCH']) : '';
			$key .= $eTagIncoming;

			// First, try to get the Content from Cache.
			$output = array(
				'headers' => array(),
				'e_tag_incoming' => $eTagIncoming,
				'e_tag_stored' => '',
				'key' => md5($key),
				'encoding' => $encoding,
				'content' => '',
				'is_new_content' => '0',
			);

			$cache->getContent($output);

			if ($output['content'] == ''){
				/**
				* If the Content, the corresponding to Request, has not been found in Cache,
				* or has been exceeded the Duration,
				* or has been read file problem
				* get new Content by Request Processing.
				*/
				$this->_routeContent($output, $req);
			}

			$cache->endCache($output);

			ob_end_flush();

			/**
			* The Content Services are the only thing that will expose themselves and do a clean exit,
			* the rest of the Services will pass the handling to wordpress
			* if they weren't able to process the request due to missing parameters and such.
			*/
			exit();
		} elseif ($service == 'getrate'){
			//wiziapp_the_rating_wrapper($req);
			echo " "; // Currently disabled
			exit();
		} elseif ($service == "getimage"){
			WiziappImageServices::getByRequest();
			exit();
		} elseif ($service == "getthumb"){
			$wth = new WiziappThumbnailHandler($action);
			$wth->doPostThumbnail();
			exit();
		} elseif($service == 'post'){
			if ($req[3] == "comments"){
				$this->runService('Comment', 'getCount', $action);
			}
		} elseif($service == 'comment'){
			$this->runService('Comment', 'add', $request);
		} elseif ($service == 'keywords'){
			$this->runScreenBy('Search', 'Keywords', null);
		} elseif($service == "intropage"){
			if ($action === 'screen'){
				$this->runScreen('IntroPage');
			} elseif ($action === 'information'){
				WiziappIntroPageScreen::get_intro_page_info();
			}
			exit;
		} elseif ($service == 'system'){
			if ($action == 'screens'){
				$this->runService('System', 'updateScreenConfiguration');
			} else if ($action == 'components'){
				$this->runService('System', 'updateComponentsConfiguration');
			} else if ($action == 'pages'){
				$this->runService('System', 'updatePagesConfiguration');
			} else if ($action == 'frame'){
				$this->runScreenBy('System', 'Frame');
			} else if ($action == 'settings'){
				$this->runService('System', 'updateConfiguration');
			} else if ($action == 'thumbs'){
				$this->runService('System', 'updateThumbsConfiguration');
			} else if ($action == 'check'){
				$this->runService('System', 'checkInstalledPlugin');
			} else if ( $action == 'logs' ){
				$this->runService('System', 'listLogs');
			} else if ( $action == 'getLog' ){
				$this->runService('System', 'getLogFile', $req[3]);
			}
		} elseif ($service == 'external'){
			$this->runScreenBy('External', 'Link', urldecode($req[2]));
		}
	}

	private function _routeContent(array & $output, $req){
		// We are running our content web services make sure we have a clean buffer
		ob_end_clean();
		ob_start();
		$output['headers'][] = 'Cache-Control: no-cache, must-revalidate';
		$output['headers'][] = 'Expires: ' . gmdate("D, d M Y H:i:s", time() + (60 * 60 * 24)) . ' GMT';
		$type = $req[2];

		if ( $type != 'video' ){
			if ( WiziappContentHandler::getInstance()->isHTML() ){
				// WebApp
				$output['headers'][] = 'Content-Type: text/html; charset: utf-8';
			} else {
				$output['headers'][] = 'Content-Type: application/json; charset: utf-8';
			}
		} else {
			$output['headers'][] = 'Content-Type: text/html; charset: utf-8';
		}

		if ($req[1] == 'search'){
			$this->runScreenBy('Search', 'Query', null);
		} else {
			if ($type == "scripts"){
				$this->runService('ContentScripts', 'get');
			} elseif ( $type == 'video' ){
				$this->runScreenBy('Video', 'Id', $req[3]);
			} elseif ($type == "list"){
				$sub_type = $req[3];
				WiziappLog::getInstance()->write('INFO', "Listing... The sub type is: {$sub_type}",
					"WiziappRequestHandler._routeContent");
				if ($sub_type == "categories"){
					$this->runScreen('Categories');
				} elseif($sub_type == "allcategories"){
					$this->runService('Lists', 'categories');
				} elseif($sub_type == "tags"){
					$this->runScreen('Tags');
				} elseif($sub_type == "alltags"){
					$this->runService('Lists', 'tags');
				} elseif($sub_type == "allauthors"){
					$this->runService('Lists', 'authors');
				} elseif($sub_type == "posts"){
					$show_by = $req[4];
					if ($show_by == 'recent'){
						$this->runScreenBy('Posts', 'Recent');
					}
				} elseif ($sub_type == "pages"){
					$this->runScreen('Pages');
				} elseif ($sub_type == "allpages"){
					$this->runService('Lists', 'pages');
				} elseif ($sub_type == "post"){
					// list/post/{id}/comments
					$show = $req[5];
					if ($show == "comments"){
						if (isset($req[6]) && $req[6] != 0){
							$this->runScreenBy('Comments', 'Comment', array($req[4], $req[6]));
						} else {
							$this->runScreenBy('Comments', 'Post', $req[4]);
						}
					} elseif ($show == "categories"){
						$this->runScreenBy('Categories', 'Post', $req[4]);
					} elseif ($show == "tags"){
						$this->runScreenBy('Tags', 'Post', $req[4]);
					} elseif ($show == "images"){
						if(isset($_GET['ids']) && !empty($_GET['ids'])){
							$this->runScreenBy('Images', 'Post', array($req[4], $_GET['ids']));
						} else {
							$this->runScreenBy('Images', 'Post', $req[4]);
						}
					}
				} elseif ($sub_type == "category"){
					$this->runScreenBy('Posts', 'Category', $req[4]);
				} elseif ($sub_type == "tag"){
					$this->runScreenBy('Posts', 'Tag', $req[4]);
				} elseif ($sub_type == "user"){
					$show = $req[5];
					if ($show == "comments"){
						$this->runScreenBy('Comments', 'MyComments', $req[4]);
					} elseif ($show == "commented"){
						$this->runScreenBy('Posts', 'AuthorCommented', $req[4]);
					}
				} elseif ($sub_type == 'author'){
					$authorId = $req[4];
					if ($req[5] == 'posts'){
						$this->runScreenBy('Posts', 'Author', $authorId);
					}
				} elseif( $sub_type == 'alllinks' ){
					$this->runService('Lists', 'links');
				} elseif ($sub_type == 'links'){
					if (!empty($req[4])){
						$show = $req[4];
						if ($show == 'categories'){
							$this->runScreen('LinksCategories');
						} elseif ($show == 'category'){
							$this->runScreenBy('Links', 'Category', $req[5]);
						}
					} else {
						$this->runScreen('Links');
					}
				} elseif ($sub_type == "archive"){
					$year = $req[4];
					$month = $req[5];
					$dayOfMonth = $req[6];
					// Year
					if (isset($year)){
						// Month
						if (isset($month)){
							// Day of month
							if (isset($dayOfMonth)){
								$this->runScreenBy('Posts', 'DayOfMonth', array($year, $month, $dayOfMonth));
							} else {
								$this->runScreenBy('Posts', 'Month', array($year, $month));
							}
						} else {
							// Just year, no month
							$this->runScreenBy('Archives', 'Year', $year);
						}
					} else {
						$this->runScreen('Archives');
					}
				} elseif ($sub_type == "favorites"){
					$this->runScreenBy('Posts', 'Ids', $_GET['pids']);
				} elseif ($sub_type == "media"){
					$show = $req[4];
					if ($show == "images"){
						$this->runScreen('Images');
					} elseif($show == 'videos'){
						$this->runScreen('Videos');
						//} elseif ($show == 'video'){

						// } elseif ($show == 'videoembed'){
						// 		$vid_id = $req[5];
						//      wiziapp_buildVideoEmbedPage($vid_id);
					} elseif ($show == 'audios'){
						$this->runScreen('Audios');
					}
				} elseif ($sub_type == "galleries"){
					$this->runScreen('Albums');
				} elseif ($sub_type == "gallery"){
					$plugin = $req[4];
					$plugin_item_id = $req[5];
					if ($plugin == 'videos' && $plugin_item_id == 'all_videos'){
						$this->runScreen('Videos');
					} else {
						$this->runScreenBy('Albums', 'Plugin', array($plugin, $plugin_item_id));
					}
				} elseif ($sub_type == "attachment"){
					$attachmentId = $req[4];
					$show = $req[5];
					if ($show == "posts"){
						$this->runScreenBy('Posts', 'Attachment', $attachmentId);
					}
				}
			}
		}

		$contents = ob_get_clean();
		WiziappLog::getInstance()->write('DEBUG', "BTW the get params were:".print_r($_GET, TRUE), "WiziappRequestHandler._routeContent");
		if (isset($_GET['callback'])){
			WiziappLog::getInstance()->write('DEBUG', "The callback GET param set:".$_GET["callback"] . "(" . $contents . ")", "WiziappRequestHandler._routeContent");
			// Support cross-domain ajax calls for webclients
			// @todo Add a check to verify this is a web client
			$output['headers'][] = 'Content-Type: text/javascript; charset: utf-8';
			$contents = $_GET["callback"] . '(' . $contents . ')';
		} else {
			WiziappLog::getInstance()->write('INFO', "The callback GET param is not set", "WiziappRequestHandler._routeContent");
		}
		echo $contents;

		// Set e-tag
		if (isset($GLOBALS['WiziappEtagOverride']) && !empty($GLOBALS['WiziappEtagOverride'])){
			$output['e_tag_stored'] = md5($GLOBALS['WiziappEtagOverride']);
		} else {
			$output['e_tag_stored'] = md5($contents);
		}
		$output['e_tag_stored'] .= WiziappContentEvents::getCacheTimestampKey();
		WiziappLog::getInstance()->write('DEBUG', "The checksum for the content is: {$output['e_tag_stored']}", "WiziappRequestHandler._routeContent");

		$output['headers'][] = 'ETag: "' . $output['e_tag_stored'] . '"';
		$output['is_new_content'] = '1';
	}

	public function runService($service_type, $service_method, $param=null){
		$serviceClassName = "Wiziapp{$service_type}Services";
		$serviceClass = new $serviceClassName();
		if ( is_callable(array($serviceClass, $service_method))){
			if ( $param == null ){
				$serviceClass->$service_method();
			} else {
				$serviceClass->$service_method($param);
			}
		}
	}

	public function runScreen($screen_class_name){
		$className = "Wiziapp{$screen_class_name}Screen";
		$screen = new $className();
		$screen->run();
	}

	public function runScreenBy($screen_class_name, $by_func_name, $param=null){
		$className = "Wiziapp{$screen_class_name}Screen";
		$funcName = "runBy{$by_func_name}";

		$screen = new $className();
		if ( $param == null ){
			$screen->$funcName();
		} else {
			$screen->$funcName($param);
		}
	}

}