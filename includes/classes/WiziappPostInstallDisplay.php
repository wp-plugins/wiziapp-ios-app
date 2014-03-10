<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage Core
* @author comobix.com plugins@comobix.com
*/

class WiziappPostInstallDisplay{

	/**
	* The last line of defense against the fatal errors that might be caused by external plugins.
	* This methods is registered in the batch processing function and will handle situations
	* when the batch script ended due to a fatal error by alerting on the error to the client
	*/
	public function batchShutdown(){
		$error = error_get_last();
		if ($error['type'] != 1){
			return;
		}

		if (isset($GLOBALS['wiziapp_post']) && $GLOBALS['wiziapp_post']){
			ob_end_clean();

			$header = array(
				'action' => 'batch_shutdown',
				'status' => FALSE,
				'code' => 500,
				'message' => 'Unable to process post ' . $GLOBALS['wiziapp_post'],
			);

			header("HTTP/1.0 200 OK");
			echo json_encode(array('header' => $header, 'post' => $GLOBALS['wiziapp_post']));
		} elseif (isset($GLOBALS['wiziapp_page']) && $GLOBALS['wiziapp_page']){
			ob_end_clean();

			$header = array(
				'action' => 'batch_shutdown',
				'status' => FALSE,
				'code' => 500,
				'message' => 'Unable to process page ' . $GLOBALS['wiziapp_page'],
			);

			header("HTTP/1.0 200 OK");
			echo json_encode(array('header' => $header, 'page' => $GLOBALS['wiziapp_page']));
		}

		exit();
	}

	public function batchProcess_Posts(){
		WiziappLog::getInstance()->write('DEBUG', "Got a request to process posts as a batch: " . print_r($_POST, TRUE),
			"post_install.wiziapp_batch_process_posts");

		global $wpdb;
		$status = TRUE;
		$message = '';

		if ( ! isset($_POST['posts']) ){
			$status = FALSE;
			$message = 'incorrect usage';
		} else {
			ob_start();
			ini_set('display_errors', 0);
			register_shutdown_function(array('WiziappPostInstallDisplay', 'batchShutdown'));

			$postsIds = explode(',', $_POST['posts']);
			foreach ($postsIds as $id){
				WiziappLog::getInstance()->write('INFO', "Processing post: {$id} inside the batch",
					"post_install.wiziapp_batch_process_posts");
				$GLOBALS['wiziapp_post'] = $id;

				if ( ! empty($id) ){
					$ce = new WiziappContentEvents();
					$ce->savePost($id);
				} else {
					WiziappLog::getInstance()->write('ERROR', "Received an empty post id: {$id} inside the batch",
						"post_install.wiziapp_batch_process_posts");
				}

				WiziappLog::getInstance()->write('INFO', "Finished processing post: {$id} inside the batch",
					"post_install.wiziapp_batch_process_posts");
			}
		}

		$header = array(
			'action' => 'batch_process_posts',
			'status' => $status,
			'code' => ($status) ? 200 : 500,
			'message' => $message,
		);

		WiziappLog::getInstance()->write('DEBUG', "Finished processing the requested post batch, going to return: " . print_r($_POST['posts'], TRUE).' '.print_r($header, TRUE),
			"post_install.wiziapp_batch_process_posts");

		echo json_encode(array('header' => $header));
		exit();
	}

	public function batchProcess_Pages(){
		WiziappLog::getInstance()->write('DEBUG', "Got a request to process pages as a batch: " . print_r($_POST, TRUE),
			"post_install.wiziapp_batch_process_pages");
		global $wpdb;
		$status = TRUE;
		$message = '';

		if ( ! isset($_POST['pages']) ){
			$status = FALSE;
			$message = 'incorrect usage';
		} else {
			ob_start();
			ini_set('display_errors', 0);
			register_shutdown_function(array('WiziappPostInstallDisplay', 'batchShutdown'));

			$pagesIds = explode(',', $_POST['pages']);
			foreach ($pagesIds as $id){
				WiziappLog::getInstance()->write('INFO', "Processing page: {$id} inside the batch",
					"post_install.wiziapp_batch_process_pages");
				$GLOBALS['wiziapp_page'] = $id;

				if ( ! empty($id) ){
					$ce = new WiziappContentEvents();
					$ce->savePage($id);
				} else {
					WiziappLog::getInstance()->write('ERROR', "Received an empty page id: {$id} inside the batch",
						"post_install.wiziapp_batch_process_pages");
				}

				WiziappLog::getInstance()->write('INFO', "Finished processing page: {$id} inside the batch",
					"post_install.wiziapp_batch_process_pages");
			}
		}

		$header = array(
			'action' => 'batch_process_posts',
			'status' => $status,
			'code' => ($status) ? 200 : 500,
			'message' => $message,
		);

		WiziappLog::getInstance()->write('DEBUG', "Finished processing the requested page batch:" . print_r($_POST['pages'], TRUE) .", going to return: " . print_r($header, TRUE),
			"post_install.wiziapp_batch_process_pages");

		echo json_encode(array('header' => $header));
		exit();
	}

	public function batchProcess_Finish(){
		WiziappLog::getInstance()->write('INFO', "The batch processing is finished - 1",
			"post_install.wiziapp_batch_process_finish");

		// Send the profile again, and allow it to fail since it's just an update
		$cms = new WiziappCms();
		$cms->activate();

		// Mark the processing as finished
		WiziappConfig::getInstance()->finished_processing = TRUE;

		$status = TRUE;

		$header = array(
			'action' => 'batch_processing_finish',
			'status' => $status,
			'code' => ($status) ? 200 : 500,
			'message' => '',
		);

		WiziappLog::getInstance()->write('INFO', "The batch processing is finished - 2",
			"post_install.wiziapp_batch_process_finish");

		echo json_encode(array('header' => $header));
		exit;
	}

	public function reportIssue(){
		$report = new WiziappIssueReporter($_POST['data']);

		ob_start();
		$report->render();
		$content = ob_get_clean();
		echo $content;

		exit();
	}

	public function display(){
		global $wpdb;

		$from_php_to_js = array(
			'can_run' => 0,
			'profile_step' => 0,
			'post_ids' => array(),
			'page_ids' => array(),
			'total_items' => 0,
		);

		try{
			// Wiziapp plugin installation
			WiziappInstaller::post_install();

			// If we are here, we already seen the message...
			WiziappConfig::getInstance()->install_notice_showed = TRUE;

			$post_types_string = '';
			$post_types_array = WiziappComponentsConfiguration::getInstance()->get_post_types();
			for ($i=0, $amount=count($post_types_array); $i<$amount; $i++){
				$post_types_string .= '"'.$post_types_array[$i].'",';
			}
			$post_types_string = rtrim($post_types_string, ',');

			$querystr =
			"SELECT DISTINCT(wposts.id), wposts.post_title
			FROM $wpdb->posts wposts
			WHERE wposts.ID not in (
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key = 'wiziapp_processed' AND meta_value = '1'
			)
			AND wposts.post_status = 'publish'
			AND wposts.post_type IN (".$post_types_string.")
			ORDER BY wposts.post_date DESC
			LIMIT 0, 50";

			$posts = $wpdb->get_results($querystr, OBJECT);
			$posts_amount = count($posts);
			$postsIds = array();
			$postsNames = array();
			foreach($posts as $post){
				$postsIds[] = $post->id;
				$postsNames[] = $post->post_title;
			}

			WiziappLog::getInstance()->write('DEBUG', "Going to process the following posts ids: " 	 . print_r($postsIds, TRUE),   "post_install.wiziapp_activate_display");
			WiziappLog::getInstance()->write('DEBUG', "Going to process the following posts names: " . print_r($postsNames, TRUE), "post_install.wiziapp_activate_display");

			$pagesQuery =
			"SELECT DISTINCT(wposts.id), wposts.post_title
			FROM $wpdb->posts wposts
			WHERE wposts.ID not in (
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key = 'wiziapp_processed' AND meta_value = '1'
			)
			AND wposts.post_status = 'publish'
			AND wposts.post_type = 'page'
			ORDER BY wposts.post_date DESC
			LIMIT 0, 20";

			$pages = $wpdb->get_results($pagesQuery, OBJECT);
			$pages_amount = count($pages);
			$pagesIds = array();
			$pagesNames = array();

			foreach ($pages as $page){
				// Get the parent
				$shouldAdd = TRUE;

				if ( isset($page->post_parent) ){
					$parentId = (int)$page->post_parent;

					if ($parentId > 0){
						$parent = get_page($parentId);

						if ($parent->post_status != 'publish'){
							$shouldAdd = FALSE;
						}
					}
				}

				if ($shouldAdd){
					$pagesIds[] = $page->id;
					$pagesNames[] = $page->post_title;
				}
			}

			WiziappLog::getInstance()->write('DEBUG', "Going to process the following pages ids: " .   print_r($pagesIds,   TRUE), "post_install.wiziapp_activate_display");
			WiziappLog::getInstance()->write('DEBUG', "Going to process the following pages names: " . print_r($pagesNames, TRUE), "post_install.wiziapp_activate_display");

			// Test for compatibilities issues with this installation
			$checker = new WiziappCompatibilitiesChecker();
			$errorsHtml = $checker->scanningTestAsHtml();
		} catch (Exception $e) {
			$errorsHtml = array(
				'text' => $e->getMessage(),
				'is_critical' => TRUE,
			);
		}

		if ( ! empty($errorsHtml['text']) ){
			$errorsHtml = WiziappCompatibilitiesChecker::create_error_block( $errorsHtml );
		} else {
			$errorsHtml = '';
		}

		$from_php_to_js['can_run'] = intval( $errorsHtml === '' );
		$from_php_to_js['profile_step'] = intval( ! WiziappConfig::getInstance()->finished_processing );
		$from_php_to_js['post_ids'] = $postsIds;
		$from_php_to_js['page_ids'] = $pagesIds;
		$from_php_to_js['total_items'] = $posts_amount + $pages_amount;

		include WIZI_DIR_PATH.'/themes/admin/post_install_display.php';
	}

	public static function styles_javascripts($hook) {
		if ( $hook !== 'toplevel_page_wiziapp' ) {
			return;
		}

		$plugins_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );

		wp_enqueue_style(  'post_install_display', $plugins_url.'/themes/admin/styles/post_install_display.css' );

		wp_enqueue_script( 'jquery_tools',		   $plugins_url.'/themes/admin/scripts/jquery.tools.min.js' );
		wp_enqueue_script( 'post_install_display', $plugins_url.'/themes/admin/scripts/post_install_display.js', 'jquery_tools' );
	}

	public static function google_analytics() {
		if ( ! isset($GLOBALS['hook_suffix']) || $GLOBALS['hook_suffix'] !== 'toplevel_page_wiziapp' ) {
			return;
		}

		$plugins_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );

		wp_enqueue_script( 'wiziapp_google_analytics', $plugins_url.'/themes/admin/scripts/wiziapp_google_analytics.js' );
		wp_localize_script(	'wiziapp_google_analytics', 'wiziapp_name_space', array( 'analytics_account' => WiziappConfig::getInstance()->analytics_account, 'url' => WiziappConfig::getInstance()->api_server, ) );
	}
}