<?php

class WiziappIntroPageScreen{

	public function run() {
		if ( ! isset($_GET['device']) || ! in_array( $_GET['device'], array( 'iphone', 'android', TRUE ) ) ) {
			wp_redirect( WiziappContentHandler::getInstance()->get_blog_property('url') );
			return;
		}

		$is_android_device = FALSE;
		$is_update = isset($_GET['update-application']) && $_GET['update-application'] === '1';

		switch ( $_GET['device'] ) {
			case 'iphone':
				$download_text = 'Get the ultimate Apple experience<br>with our new App!';
				$button_image = 'appstore.png';
				$store_url = WiziappConfig::getInstance()->appstore_url;
				$delay_period = 30*6;
				break;
			case 'android':
				$is_android_device = TRUE;
				$playstore_condition = ! empty(WiziappConfig::getInstance()->playstore_url);
				$download_text = $is_update ? 'A new version of our App is now available,<br>would you like to download it now?' : 'Get the ultimate Android experience<br>with our new App!';
				$download_note = ( isset($_GET['gingerbread']) && $_GET['gingerbread'] === '1' ) ? 'In order to allow installation of non-Market application Go to settings > Applications and allow the "Unknown source"' : 'In order to allow installation of apps from sources other than the play store please Go to settings > security and allow the "Unknown source"';

				if ($playstore_condition) {
					$button_image = 'playstore.png';
					$store_url = WiziappConfig::getInstance()->playstore_url;
					$delay_period = 30*6;
				} else {
					$button_image = 'android.png';
					$store_url = WiziappConfig::getInstance()->apk_file_url;
					$delay_period = 30;
				}
				break;
		}

		$is_show_desktop =
		WiziappConfig::getInstance()->webapp_installed &&
		( WiziappConfig::getInstance()->webapp_active || ( isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] === WIZIAPP_ANDROID_APP ) );

		$wiziapp_plugin_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );
		$app_icon = WiziappContentHandler::getInstance()->get_blog_property('data_files_url').'/resources/icons/'.basename(WiziappConfig::getInstance()->getAppIcon());

		$http_referer = filter_var($_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL);
		$site_url = $http_referer ? $http_referer : WiziappContentHandler::getInstance()->get_blog_property('url');
		if ( $is_update ) {
			$site_url .= ( strpos($site_url, '?') === FALSE ? '?' : '&' ).'androidapp=1';
		}
		$desktop_site_url = $site_url.( strpos($site_url, '?') === FALSE ? '?' : '&' ).'setsession=desktopsite';

		include WIZI_DIR_PATH.'/themes/intropage/index.php';
	}

	public static function get_intro_page_info() {
		$is_not_passed =
		empty($_POST['device_type']) ||
		! isset($_POST['wizi_show_store_url']) ||
		intval(WiziappConfig::getInstance()->display_download_from_appstore) !== 1;
		if ( $is_not_passed ) {
			// Not passed the Error Checking
			return;
		}
		if ( session_id() == '' ) {
			session_start();
		}

		$retun_string = 'update-application=';
		$response = '';
		$query_string = array( 'androidapp' => '0', 'abv' => '', );
		parse_str( str_replace('?', '', $_POST['query_string']), $query_string );

		switch ( $_POST['device_type'] ) {
			case 'iphone':
				if ( ! empty(WiziappConfig::getInstance()->appstore_url) && $_POST['wizi_show_store_url'] !== '1' ) {
					$response = $retun_string.'0';
				}

				break;
			case 'android':
				if ( isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] === WIZIAPP_ANDROID_APP ) {
					// This is the native Android App

					/*
					$proper_condition =
					empty(WiziappConfig::getInstance()->playstore_url) &&
					! empty(WiziappConfig::getInstance()->android_app_version) && ! empty($query_string['abv']) &&
					! ( isset($_SESSION['wiziapp_android_download']) && $_SESSION['wiziapp_android_download'] === 'none' ) &&
					version_compare( WiziappConfig::getInstance()->android_app_version, $query_string['abv'], '>' );

					if ( $proper_condition ) {
					$_SESSION['wiziapp_android_download'] = 'none';
					$response =  $retun_string.'1';
					}
					*/
				} else {
					// This is the Webapp
					$proper_condition =
					( ! empty(WiziappConfig::getInstance()->playstore_url) || ! empty(WiziappConfig::getInstance()->apk_file_url) ) &&
					intval(WiziappConfig::getInstance()->endorse_download_android_app) === 1 &&
					$_POST['wizi_show_store_url'] != '1';

					if ( $proper_condition ) {
						$response = $retun_string.'0';
					}
				}

				break;
		}

		echo $response;
	}
}