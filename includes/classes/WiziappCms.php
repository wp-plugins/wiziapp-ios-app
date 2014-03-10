<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappCms {

	private $_wiziapp_user = 'wiziappuser';

	public static function check_playstore_url() {
		$app_id = intval(WiziappConfig::getInstance()->app_id);
		if ( $app_id <= 0 ) {
			return;
		}

		if ( ! empty(WiziappConfig::getInstance()->playstore_url) ) {
			return;
		}

		$playstore_url = 'https://play.google.com/store/apps/details?id=com.wiziapp.app'.$app_id;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $playstore_url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_NOBODY, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_exec($ch);
		if ( ! curl_errno($ch) ) {
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		}
		curl_close($ch);

		if ( $http_code !== 200 ) {
			return;
		}

		$is_saved = WiziappConfig::getInstance()->saveUpdate('playstore_url', $playstore_url);
		if ( ! $is_saved ) {
			return;
		}

		$r = new WiziappHTTPRequest();
		$r->api(array('market_link' => $playstore_url), '/application/'. $app_id . '/updatemarketlink', 'POST');
	}

	public function activate() {
		$profile = $this->generateProfile();
		$blogUrl = WiziappContentHandler::getInstance()->get_blog_property('url');
		$urlData = explode('://', $blogUrl);

		// Inform the admin server
		$r = new WiziappHTTPRequest();
		$response = $r->api($profile, '/cms/activate/' . $urlData[0] . '?version=' . WIZIAPP_VERSION . '&url=' . urlencode($urlData[1]), 'POST');
		WiziappLog::getInstance()->write('DEBUG', "The response is " . print_r($response, TRUE), "cms.activate");
		if (is_wp_error($response)) {
			return FALSE;
		}

		$tokenResponse = json_decode($response['body'], TRUE);
		if ( empty($tokenResponse) || ! $tokenResponse['header']['status'] ) {
			return FALSE;
		}

		WiziappConfig::getInstance()->startBulkUpdate();

		/**
		* Set Common config
		*/
		$common_settings = array(
			'plugin_token',	'app_id', 'main_tab_index', 'settings_done', 'app_live', 'app_description', 'appstore_url', 'playstore_url', 'apk_file_url', 'android_app_version', 'app_icon', 'app_name', 'email_verified', 'thumb_min_size', 'display_download_from_appstore', 'endorse_download_android_app', 'notify_on_new_post', 'notify_on_new_page', 'push_message', 'webapp_active', 'analytics', 'admob', 'adsense', 'rtl', 'is_paid',
		);
		$this->_apply_setting($common_settings, $tokenResponse);

		/**
		* Set Thumbnails config
		*/
		$thumbs = json_decode($tokenResponse['thumbs'], TRUE);
		$thumbs_settings = array(
			'full_image_height', 'full_image_width', 'images_thumb_height', 'images_thumb_width', 'posts_thumb_height', 'posts_thumb_width', 'featured_post_thumb_height', 'featured_post_thumb_width', 'mini_post_thumb_height', 'mini_post_thumb_width', 'comments_avatar_height', 'comments_avatar_width', 'album_thumb_height', 'album_thumb_width', 'video_album_thumb_height', 'video_album_thumb_width', 'audio_thumb_height', 'audio_thumb_width',
		);
		$this->_apply_setting($thumbs_settings, $thumbs);

		/**
		* Set Titles config, if the app is configured
		*/
		if ( ! empty($tokenResponse['screen_titles']) ) {
			$titles_settings = array('categories_title', 'tags_title', 'albums_title', 'videos_title', 'audio_title', 'links_title', 'pages_title', 'favorites_title', 'about_title', 'search_title', 'archive_title',);
			$this->_apply_setting($titles_settings, $tokenResponse['screen_titles']);
		}

		/**
		* Set Titles config
		*/
		$elements = array('pages', 'screens', 'components',);
		$this->_update_elements($elements, $tokenResponse);

		WiziappConfig::getInstance()->bulkSave();
	}

	public function deactivate() {
		// Inform the system control
		$blogUrl = WiziappContentHandler::getInstance()->get_blog_property('url');
		$urlData = explode('://', $blogUrl);

		$r = new WiziappHTTPRequest();
		$response = $r->api(array(), '/cms/deactivate?app_id=' . WiziappConfig::getInstance()->app_id . '&url=' . urlencode($urlData[1]), 'POST');

		$this->deleteUser();
	}

	private function deleteUser() {
		$userId = username_exists($this->_wiziapp_user);

		if ( ! $userId) {
			return;
		}

		if ( ! wp_delete_user($userId) ) {
			WiziappLog::getInstance()->write('ERROR', "Error deleting user wiziapp", "install.delete_user_wiziapp");
		}
	}

	/**
	*  If the blog allows to create users, we register our user to be able to give to apple for appstore approval
	*/
	private function registerUser() {
		$userData = array();
		$blogAllowRegistration = intval( get_option('users_can_register') );
		$password = 'ERROR';

		if ( $blogAllowRegistration ) {
			$blogName = get_bloginfo('name');
			$userId = username_exists($this->_wiziapp_user);

			// $password = wp_generate_password(12, false);
			$password = substr(str_replace(" " , "", $blogName), 0, 5) . '1324';
			if ( ! $userId) {
				$userId = wp_create_user($this->_wiziapp_user, $password);
				if (!$userId) {
					WiziappLog::getInstance()->write('ERROR', "Error creating user " . $this->_wiziapp_user, "install.register_user_wiziapp");
				} else {
					WiziappLog::getInstance()->write('INFO', "User " . $this->_wiziapp_user . " created successfully.", "install.register_user_wiziapp");
				}
			} else {
				// Might be our user... should see if we can login with our password
				$user = wp_authenticate($this->_wiziapp_user, $password);
				if ( is_wp_error($user) ) {
					$password = 'ERROR';
					WiziappLog::getInstance()->write('ERROR', "User " . $this->_wiziapp_user . " already exists and was NOT created.", "install.register_user_wiziapp");
				}
			}
		}

		$userData['blog_allows_registration'] = $blogAllowRegistration;
		$userData['blog_username'] = $this->_wiziapp_user;
		$userData['blog_password'] = $password;

		return $userData;
	}

	protected function generateProfile() {
		$version = get_bloginfo('version');
		$admin_email = get_option('admin_email');
		/**
		* @todo check if wp_touch is installed and try to get it's configuration
		* for blog name and description
		*/
		$checker = new WiziappCompatibilitiesChecker();

		$profile = array(
			'cms' => 'wordpress',
			'cms_version' => floatval($version),
			'name' => get_bloginfo('name'),
			'tag_line' => get_bloginfo('description'),
			'profile_data' => json_encode(array(
				'plugins' => $this->getActivePlugins(),
				'pages' => $this->getPagesList(),
				'stats' => $this->getCMSProfileStats(),
				'os' => $checker->testOperatingSystem(),
				'write_permissions' => $checker->testWritingPermissions(false),
				'gd_image_magick' => $checker->testPhpGraphicRequirements(false),
				'allow_url_fopen' => $checker->testAllowUrlFopen(false),
				'web_server' => $checker->testWebServer(false),
			)),
			'comment_registration' => get_option('comment_registration') ? 1 : 0,
			'admin_email' => empty($admin_email) ? '' : $admin_email,
			'installation_source' => WiziappConfig::getInstance()->installation_source,
			'rtl' => intval(is_rtl()),
		);

		$profile = array_merge($profile, $this->registerUser());

		return $profile;
	}

	/**
	* Gets the list of the active plugins installed in the blogs
	*
	* @return array|bool $listActivePlugins or false on error
	*/
	protected function getActivePlugins() {
		/**
		* Uses the wordpress function - get_plugins($plugin_folder = [null])
		* to retrieve all plugins installed in the defined directory
		* filters out the none active plugins and then stores the name and version
		* of the active plugins in $listPlugins array and returns it.
		*/
		$listActivePlugins = array();
		if ($folder_plugins = get_plugins()) {
			foreach($folder_plugins as $plugin_file => $data) {
				if(is_plugin_active($plugin_file)) {
					$listActivePlugins[$data['Name']] = $data['Version'];
				}
			}
			return $listActivePlugins;
		}
		else return false;
	}

	/**
	* Gets the pages in the blog
	*
	* @return array $list of pages
	*/
	protected  function getPagesList() {
		$pages = get_pages(array(
			'number' => 15,
		));
		$list = array();

		foreach ($pages as $p) {
			$list[] = $p->post_title;
		}

		return $list;
	}

	/**
	* Gets the statistics for the CMS profile
	* @return array $stats
	*/
	protected  function getCMSProfileStats() {
		WiziappLog::getInstance()->write('INFO', "Getting the CMS profile", "wiziapp_getCMSProfileStats");

		$audiosAlbums = array();
		$playlists = array();

		ob_start();
		//    $imagesAlbums = apply_filters('wiziapp_images_albums_request', $imagesAlbums);
		$imagesAlbums = WiziappDB::getInstance()->get_albums_count();
		$audioAlbums = apply_filters('wiziapp_audios_albums_request', $audiosAlbums);
		$playlists = apply_filters('wiziapp_playlists_request', $playlists);
		ob_end_clean();

		$numOfCategories = count(get_categories(array(
			'number' => 15,
		)));

		$numOfTags = count(get_tags(array(
			'number' => 15,
		)));
		$postImagesAlbums = WiziappDB::getInstance()->get_images_post_albums_count(5);
		$videosCount = WiziappDB::getInstance()->get_videos_count();
		$postAudiosAlbums = WiziappDB::getInstance()->get_audios_post_albums_count(2);
		$linksCount = count(get_bookmarks(array(
			'limit' => 15,
		)));

		$stats = array(
			'numOfCategories' => $numOfCategories,
			'numOfTags' => $numOfTags,
			'postImagesAlbums' => $postImagesAlbums,
			'pluginImagesAlbums' => count($imagesAlbums),
			'videosCount' => $videosCount,
			'postAudiosAlbums' => $postAudiosAlbums,
			'pluginAudioAlbums' => count($audioAlbums),
			'pluginPlaylists' => count($playlists),
			'linksCount' => $linksCount,
		);
		WiziappLog::getInstance()->write('DEBUG', "About to return the CMS profile: " . print_r($stats, TRUE), "wiziapp_getCMSProfileStats");

		return $stats;
	}

	private function _apply_setting(array $variables, $incoming) {
		foreach ($variables as $variable) {
			if ( isset($incoming[$variable]) ) {
				WiziappConfig::getInstance()->$variable = $incoming[$variable];
			}
		}
	}

	private function _update_elements(array $elements_names, $tokenResponse) {
		for ($i=0, $elements_amount=count($elements_names); $i<$elements_amount; $i++) {
			$element = array();

			if ( isset($tokenResponse[ $elements_names[$i] ]) ) {
				$element = json_decode( stripslashes( $tokenResponse[ $elements_names[$i] ] ), TRUE );
			}

			update_option('wiziapp_'.$elements_names[$i], $element, '', 'no');
		}
	}
}