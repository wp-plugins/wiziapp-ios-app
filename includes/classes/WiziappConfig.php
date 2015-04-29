<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* @property integer $show_badge_number
* @property integer $trigger_sound
* @property integer $show_notification_text
* @property integer $notify_on_new_page
* @property integer $notify_on_new_post
* @property string  $push_message
* @property integer $thumb_size
* @property boolean $use_post_preloading
* @property integer $comments_list_limit
* @property integer $links_list_limit
* @property integer $posts_list_limit
* @property integer $pages_list_limit
* @property integer $tags_list_limit
* @property integer $categories_list_limit
* @property integer $authors_list_limit
* @property integer $videos_list_limit
* @property integer $audios_list_limit
* @property integer $comments_avatar_height // Replace the comments_avatar_size
* @property integer $comments_avatar_width
* @property integer $album_thumb_width
* @property integer $album_thumb_height
* @property integer $video_album_thumb_width
* @property integer $video_album_thumb_height
* @property integer $audio_thumb_width
* @property integer $audio_thumb_height
* @property integer $post_processing_batch_size
* @property integer $search_limit
* @property integer $search_limit_pages
* @property string  $sep_color
* @property integer $main_tab_index
* @property string  $api_server
* @property boolean $configured
* @property integer $app_id
* @property string  $plugin_token
* @property string  $app_name
* @property string  $app_icon
* @property string  $version
* @property string  $icon_url
* @property string  $categories_title
* @property string  $tags_title
* @property string  $albums_title
* @property string  $videos_title
* @property string  $audio_title
* @property string  $links_title
* @property string  $pages_title
* @property string  $favorites_title
* @property string  $about_title
* @property string  $search_title
* @property string  $archive_title
* @property string  $appstore_url
* @property string  $playstore_url
* @property string  $apk_file_url
* @property string  $android_app_version
* @property boolean $app_live
* @property boolean $allow_grouped_lists
* @property boolean $zebra_lists
* @property string  $wiziapp_theme_name
* @property integer $count_minimum_for_appear_in_albums
* @property integer $full_image_height
* @property integer $full_image_width
* @property integer $multi_image_height
* @property integer $multi_image_width
* @property integer $images_thumb_height
* @property integer $images_thumb_width
* @property integer $posts_thumb_height
* @property integer $posts_thumb_width
* @property integer $featured_post_thumb_height
* @property integer $featured_post_thumb_width
* @property integer $max_thumb_check
* @property boolean $settings_done
* @property boolean $finished_processing
* @property string  $is_paid
* @property boolean $email_verified
* @property boolean $verify_email_notice
* @property boolean $install_notice_showed
* @property boolean $upgrade_notice_new_mode
* @property integer $last_recorded_save
* @property boolean $webapp_installed
* @property boolean $webapp_active
* @property boolean $skip_reload_webapp
* @property integer $thumb_min_size
* @property integer $display_download_from_appstore
* @property integer $endorse_download_android_app
* @property integer $rtl
* @property integer $wiziapp_log_threshold
* @property string  $wiziapp_qrcode_widget_id_base
* @property string  $wiziapp_qrcode_widget_name
* @property string  $wiziapp_qrcode_widget_decription
* @property array   $wiziapp_data_files
* @property array   $adsense
* @property array   $admob
* @property array   $analytics
*/
class WiziappConfig implements WiziappIInstallable{

	private $options = array();
	private $saveAsBulk = FALSE;
	private $name = 'wiziapp_settings';
	private $internalVersion = WIZIAPP_VERSION;
	private static $_instance = null;

	public $integer_values = array(
		'thumb_min_size',
		'display_download_from_appstore',
		'endorse_download_android_app',
		'rtl',
		'notify_on_new_post',
		'notify_on_new_page',
	);

	/**
	* @static
	* @return WiziappConfig
	*/
	public static function getInstance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new WiziappConfig();
		}

		return self::$_instance;
	}

	private function __clone() {
		// Prevent cloning
	}

	private function __construct() {
		$this->load();
	}

	private function load() {
		$options = get_option($this->name);

		if ( is_array($options) ) {
			$this->options = $options;
		}
	}

	public function upgrade() {
		/**
		* This is depended per version, each version might remove or add values...
		*/
		// Add here the keys to reset to the default value;
		$resetOptions = array();
		// Add here the keys add with the default value, if they don't already exists;
		$addOptions = array();
		// Add here the keys to remove from the options array;
		$removeOptions = array();

		$newDefaults = $this->getDefaultConfig();
		foreach($addOptions as $optionName) {
			if ( !isset($this->options[$optionName]) ) {
				$this->options[$optionName] = $newDefaults[$optionName];
			}
		}

		foreach($resetOptions as $optionName) {
			$this->options[$optionName] = $newDefaults[$optionName];
		}

		foreach($removeOptions as $optionName) {
			unset($this->options[$optionName]);
		}

		// save the updated options
		$this->options['options_version'] = $this->internalVersion;

		return $this->save();
	}

	public function needUpgrade() {
		return ( !isset($this->options['options_version']) || $this->internalVersion !== $this->options['options_version'] );
	}

	public function uninstall() {
		delete_option( $this->name );
	}

	public function install() {
		if ( $this->isInstalled() ) {
			return;
		}

		$this->loadDefaultOptions();
		$this->options['options_version'] = $this->internalVersion;
		$this->save();
	}

	public function isInstalled() {
		// Make sure we are loaded
		$this->load();
		return ( ! empty($this->options) && isset($this->options['options_version']) );
	}

	private function loadDefaultOptions() {
		$this->options =  $this->getDefaultConfig();
	}

	public function startBulkUpdate() {
		$this->saveAsBulk = TRUE;
	}

	public function bulkSave() {
		$this->saveAsBulk = FALSE;
		return $this->save();
	}

	private function save() {
		return update_option($this->name, $this->options);
	}

	public function __get($option) {
		if ( $option === 'playstore_url' ) {
			return $this->_proper_playstore_url();
		}

		$value = null;

		if ( isset($this->options[$option]) ) {
			$value = $this->options[$option];
		}

		return $value;
	}

	public function is_rtl() {
		$rtl = intval($this->rtl);

		if ( $rtl & bindec('100') ){
			// Get the Direction from manual selection
			return ( $rtl & bindec('10') );
		}

		// Get the Direction from automatic selection
		return  ( $rtl & 1 );
	}

	public function saveUpdate($option, $value) {
		$saved = FALSE;

		$option_isset = array_key_exists($option, $this->options) && in_array( $option, array( 'appstore_url', 'playstore_url', 'apk_file_url', 'android_app_version', 'adsense', 'admob', 'analytics', ) );
		$is_proper_condition = isset($this->options[$option]) || $option_isset;
		if ( $is_proper_condition ) {
			$this->options[$option] = $value;
			$this->save();
			// If the value is the same it will not be updated but thats still ok.
			$saved = TRUE;
		}

		return $saved;
	}

	public function __isset($option) {
		$option_isset = array_key_exists($option, $this->options) && in_array( $option, array( 'appstore_url', 'playstore_url', 'apk_file_url', 'android_app_version', ) );

		return isset($this->options[$option]) || $option_isset;

	}

	public function __set($option, $value) {
		$saved = FALSE;

		//if ( isset($this->options[$option]) ) {
		$this->options[$option] = $value;
		if ( !$this->saveAsBulk ) {
			$saved = $this->save();
		}
		//}

		return $saved;
	}

	public function usePostsPreloading() {
		if ( isset($_GET['ap']) && $_GET['ap']==1 ) {
			return FALSE;
		} else {
			return $this->options['use_post_preloading'];
		}
	}

	public function getImageSize($type) {
		if ( ! isset($this->options[$type . '_width']) || ! isset($this->options[$type . '_height']) ) {
			WiziappLog::getInstance()->write('ERROR', '! isset($this->options[$type . \'_width\']) || ! isset($this->options[$type . \'_height\'])', 'WiziappConfig::getImageSize');

			return array(
				'width'  => 50,
				'height' => 50,
			);
		}

		return array(
			'width'  => $this->options[$type . '_width'],
			'height' => $this->options[$type . '_height'],
		);
	}

	public function getScreenTitle($screen) {
		$title = '';
		if ( isset($this->options[$screen.'_title']) ) {
			$title = stripslashes($this->options[$screen.'_title']);
		}
		return $title;
	}

	public function getCdnServer() {
		if ( isset($this->options['cdn_server']) ) {
			$cdn = $this->options['cdn_server'];
		} else {
			require WIZI_DIR_PATH . 'includes/blocks/conf/' . WIZIAPP_ENV . '_config.inc.php';
			$cdn = $envSettings['cdn_server'];
		}

		$protocol = 'http://';
		if ( isset($_GET['secure']) && $_GET['secure'] == 1 ) {
			$cdn = $this->options['secure_cdn_server'];
			$protocol = 'https://';
		}

		return $protocol.$cdn;
	}

	public function getCommonApiHeaders() {
		$plugin_token = $this->options['plugin_token'];

		$headers = array(
			'Application' => $plugin_token,
			'wiziapp_version' => WIZIAPP_P_VERSION,
			'app_version' => 2,
			'udid' => 'wordpress-cms',
		);

		if ( !empty($this->options['api_key']) ) {
			$headers['Authorization'] = 'Basic '.$this->options['api_key'];
		}

		return $headers;
	}

	public function getAppIcon() {
		$url = $this->options['app_icon'];
		if ($url == '') {
			$url = $this->options['icon_url'];
		}

		if ( strpos($url, 'http') !== 0) {
			$url = 'https://'.$this->options['api_server'].$url;
		}
		return $url;
	}

	public function getWiziappBranding() {
		?>
		<div style="text-align: center; font-family: Helvetica, Arial; font-size: 14px; margin-bottom: 20px;">
			WordPress mobile theme by <a href="http://www.wiziapp.com/" target="_blank">WiziApp</a>
		</div>
		<?php
	}

	function getDefaultConfig() {
		$envSettings = array();
		require WIZI_DIR_PATH . 'includes/blocks/conf/' . WIZIAPP_ENV . '_config.inc.php';

		$settings = array(
			// Push notifications
			'show_badge_number' => 1,
			'trigger_sound' => 1,
			'show_notification_text' => 1,
			'notify_on_new_post' => 1,
			'notify_on_new_page' => 0,
			'push_message' => 'New Post Published',

			// Rendering
			'main_tab_index' => 't1',
			'sep_color' => '#bbbbbbff',
			'full_image_height' => 480,
			'full_image_width'  => 320,
			'multi_image_height' => 320,  // 350-30 pixels for the scroller and surrounding space
			'multi_image_width'  => 298,  // 300-2 pixels for the rounded border
			'images_thumb_height' => 55,
			'images_thumb_width'  => 73,
			'posts_thumb_height' => 55,
			'posts_thumb_width'  => 73,
			'featured_post_thumb_height' => 800,
			'featured_post_thumb_width'  => 800,

			// Control Panel - Settings
			'thumb_min_size' => 80,
			'display_download_from_appstore' => 1,
			'endorse_download_android_app' => 1,
			'rtl' => 0,

			'comments_avatar_height' => 58,
			'comments_avatar_width' => 58,
			'album_thumb_width' => 64,
			'album_thumb_height' => 51,
			'video_album_thumb_width' => 64,
			'video_album_thumb_height' => 51,
			'audio_thumb_width' => 60,
			'audio_thumb_height' => 60,

			'thumb_size' => 80,
			'use_post_preloading' => TRUE,

			'comments_list_limit' => 20,
			'links_list_limit' => 20,
			'pages_list_limit' => 20,
			'posts_list_limit' => 10,
			'categories_list_limit' => 20,
			'tags_list_limit' => 20,
			'authors_list_limit' => 20,
			'videos_list_limit' => 20,
			'audios_list_limit' => 20,

			'max_thumb_check' => 6,
			'count_minimum_for_appear_in_albums' => 5,

			// Theme
			'allow_grouped_lists' => FALSE,
			'zebra_lists' => TRUE,
			'wiziapp_theme_name' => 'default',

			// App
			'plugin_token' => '',
			'app_id' => 0,
			'app_name' => get_bloginfo('name'),
			'app_icon' => '',
			'version' => '',
			'icon_url' => '/images/app/themes/default/about-placeholder.png',

			// Screens titles
			'categories_title' => 'Categories',
			'tags_title' => 'Tags',
			'albums_title' => 'Albums',
			'videos_title' => 'Videos',
			'audio_title' => 'Audio',
			'links_title' => 'Links',
			'pages_title' => 'Pages',
			'favorites_title' => 'Favorites',
			'about_title' => 'About',
			'search_title' => 'Search Results',
			'archive_title' => 'Archives',

			// General
			'last_recorded_save' => time(),
			'search_limit' => 50,
			'search_limit_pages' => 20,
			'post_processing_batch_size' => 3,
			'finished_processing' => FALSE,
			'is_paid' => '0',
			'configured' => FALSE,
			'app_live' => FALSE,
			'appstore_url'  => '',
			'playstore_url' => '',
			'apk_file_url' => '',
			'android_app_version' => '',
			'email_verified' => FALSE,
			'verify_email_notice' => TRUE,
			'install_notice_showed' => FALSE,
			'upgrade_notice_new_mode' => TRUE,
			'wiziapp_log_threshold' => 2, // Initial default level
			'wiziapp_data_files' => array(
				'wiziapp_data_files' => array(
					'cache' => array(
						'images' => array(),
						'content' => array(),
					),
					'logs' => array(),
					'resources' => array(),
				),
			),
			'adsense' 	=> array(),
			'admob' 	=> array(),
			'analytics' => array(),

			// Wiziapp QR Code Widget
			'wiziapp_qrcode_widget_id_base' => 'wiziapp_qr_code',
			'wiziapp_qrcode_widget_name' => 'Wiziapp QR Code',
			'wiziapp_qrcode_widget_decription' => 'Provide Wiziapp Application Download Link on the Blog Page by QR Code picture.',

			// Webapp
			'webapp_installed' => FALSE,
			'webapp_active' => TRUE,
			'skip_reload_webapp' => FALSE,
		);

		return array_merge($settings, $envSettings);
	}

	private function _proper_playstore_url() {
		if ( empty($this->options['playstore_url']) ) {
			return '';
		}

		$app_id = intval($this->app_id);
		if ( $app_id <= 0 ) {
			return '';
		}

		$proper_url = 'https://play.google.com/store/apps/details?id=com.wiziapp.app'.$app_id;

		if ( $this->options['playstore_url'] !== $proper_url ) {
			$this->options['playstore_url'] = '';
			$this->save();
			return '';
		}

		return $proper_url;
	}
}