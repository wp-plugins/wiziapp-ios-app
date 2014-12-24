<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Handles the display of the application, checks if the request for the blog came from a
* supported known application and if so directs it to the CMS Plugin theme.
* When displaying posts inside our templates makes sure to convert what is needed
*
* @package WiziappWordpressPlugin
* @subpackage ContentDisplay
* @author comobix.com plugins@comobix.com
*/
class WiziappContentHandler {

	private $mobile = FALSE;
	private $inApp = FALSE;
	private $_is_ipad_device = FALSE;
	private $_is_access_checked = FALSE;
	private $inSave = FALSE;

	static $shouldDisplayAppstoreLinkFlag = TRUE;

	private static $_instance = null;

	private $originalTemplateDir = '';
	private $originalTemplateDirUri = '';
	private $originalStylesheetDir = '';
	private $originalStylesheetDirUri = '';

	private $_blog_properties = array(
		'id' => 0,
		'url' => '',
		'data_files_dir' => '',
		'data_files_url' => '',
	);

	/**
	* Apply all of the classes hooks to the right requests,
	* we don't need to start this request every time, just when it is possibly needed
	*/
	private function __construct() {
		$uploads_dir = wp_upload_dir();

		if ( function_exists('is_multisite') && is_multisite() ) {
			$this->_blog_properties['id'] = get_current_blog_id();
			$url = get_blogaddress_by_id( $this->_blog_properties['id'] );
		} else {
			$this->_blog_properties['id'] = 0;
			$url = home_url();
		}

		$this->_blog_properties['url'] = untrailingslashit( $url );
		$this->_blog_properties['data_files_dir'] = $uploads_dir['basedir'].'/wiziapp_data_files';
		$this->_blog_properties['data_files_url'] = $uploads_dir['baseurl'].'/wiziapp_data_files';

		add_action('plugins_loaded', array(&$this, 'detectAccess'), 99);
		add_action('plugins_loaded', array(&$this, 'avoidWpTouchIfNeeded'), 1);

		if ( strpos($_SERVER['REQUEST_URI'], '/wp-admin') === FALSE	&& strpos($_SERVER['REQUEST_URI'], 'xmlrpc') === FALSE ) {
			// Don't change the template directory when in the admin panel
			add_filter('stylesheet', array(&$this, 'get_stylesheet'), 99);
			add_filter('theme_root', array(&$this, 'theme_root'), 99);
			add_filter('theme_root_uri', array(&$this, 'theme_root_uri'), 99);
			add_filter('template', array(&$this, 'get_template'), 99);

			add_filter( 'template_directory', array( &$this, 'save_template_directory' ), 1);
			add_filter( 'template_directory_uri', array( &$this, 'save_template_directory_uri' ), 1);
			add_filter( 'stylesheet_directory', array( &$this, 'save_stylesheet_directory' ), 1);
			add_filter( 'stylesheet_directory_uri', array( &$this, 'save_stylesheet_directory_uri' ), 1);

			add_filter( 'template_directory', array( &$this, 'reset_template_directory' ), 99);
			add_filter( 'template_directory_uri', array( &$this, 'reset_template_directory_uri' ), 99);
			add_filter( 'stylesheet_directory', array( &$this, 'reset_stylesheet_directory' ), 99);
			add_filter( 'stylesheet_directory_uri', array( &$this, 'reset_stylesheet_directory_uri' ), 99);

			add_filter('the_content', array(&$this, 'trigger_before_content'), 1);
			add_filter('the_content', array(&$this, 'convert_content'), 999);
			add_filter('the_category', array(&$this, 'convert_categories_links'), 99);

			// Switch off the "a page as Home Page" WP feature to avoid bug "Stacking on the Splash" in the Webapp,
			// if the Page was set as the Webapp First Tab.
			add_filter('pre_option_show_on_front', array(&$this, 'disable_show_on_front'), 1);
		} elseif ( strpos($_SERVER['REQUEST_URI'], 'wiziapp') !== FALSE ) {
			// Avoid cache in the admin
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Expires: " . gmdate("D, d M Y H:i:s", time() - 3600) . " GMT");
			add_filter('admin_head', array(&$this, 'do_admin_head_section'), 99);
		}
	}

	public function setInSave() {
		$this->inSave = TRUE;
		$this->removeKnownFilters();
	}

	public function isHTML() {
		return $this->mobile && ! $this->inApp;
	}

	public function isInApp() {
		return $this->inApp;
	}

	public function is_ipad_device() {
		return $this->_is_ipad_device;
	}

	public function isInSave() {
		return $this->inSave;
	}

	public function get_blog_property($property_name) {
		if ( array_key_exists($property_name, $this->_blog_properties) ) {
			return $this->_blog_properties[$property_name];
		} else {
			return '';
		}
	}

	/**
	* We are doing some of the functionality ourselves so reduce the overhead...
	*/
	public function removeKnownFilters() {
		remove_filter('the_content', 'addthis_social_widget');
		remove_filter('the_content', 'A2A_SHARE_SAVE_to_bottom_of_content', 98);
		remove_filter("gettext", "ws_plugin__s2member_translation_mangler", 10, 3);
		remove_filter('the_content', 'shrsb_position_menu');
		remove_action('wp_head',   'dl_copyright_protection');
		remove_action('wp_footer', 'thisismyurl_noframes_killframes');
		remove_action('wp_head', array('anchor_utils', 'ob_start'), 10000);
	}

	public function forceInApp() {
		WiziappLog::getInstance()->write('INFO', "Forcing the application display", "WiziappContentHandler.forceInApp");
		$this->setInApp();
	}

	private function setInApp() {
		$this->mobile = TRUE;
		$this->inApp = TRUE;
		$this->removeKnownFilters();
	}

	public function avoidWpTouchIfNeeded() {
		$this->detectAccess();
		if ( $this->inApp ) {
			remove_action( 'plugins_loaded', 'wptouch_create_object' );
		}
	}

	public function disable_show_on_front($show_on_front) {
		$this->detectAccess();

		if ( ! $this->mobile) {
			return $show_on_front;
		}

		return 'posts';
	}

	/**
	* Detect if we have been access from the application, the application uses a pre-defined protocol for it's
	* requests, so if something is not there its not the application.
	*/
	public function detectAccess() {
		if ( $this->_is_access_checked ) {
			return;
		}
		$this->_is_access_checked = TRUE;

		$appToken = isset($_SERVER['HTTP_APPLICATION']) ? $_SERVER['HTTP_APPLICATION'] : '';
		$udid 	  = isset($_SERVER['HTTP_UDID']) 		? $_SERVER['HTTP_UDID'] 	   : '';

		$this->mobile = FALSE;
		$this->inApp  = FALSE;

		if (strpos($_SERVER['QUERY_STRING'], 'wiziapp/') !== FALSE) {
			$this->inApp = TRUE;
		}

		$is_webapp_ready =
		WiziappConfig::getInstance()->webapp_installed &&
		( WiziappConfig::getInstance()->webapp_active || ( isset($_GET['androidapp']) && $_GET['androidapp'] === '1' ) );

		if ( isset($_GET['output']) && $_GET['output'] == 'html' && $is_webapp_ready ) {
			$this->mobile = TRUE;
			$this->inApp = FALSE;
		}

		if ( ( ! empty($appToken) && ! empty($udid) ) || $this->inApp ) {
			WiziappLog::getInstance()->write('INFO', "In the application display", "WiziappContentHandler.detectAccess");

			$this->setInApp();
		} elseif ( isset($_SERVER['HTTP_USER_AGENT']) && ! $this->_desktop_site_mode() && $is_webapp_ready ) {
			add_filter('body_class', array($this, 'getDeviceClass'), 10, 1);

			$is_iPhone 		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone')  !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')	   !== FALSE;
			$is_iPod 		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPod')    !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')	   !== FALSE;
			$is_iPad 		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPad')    !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')	   !== FALSE;
			$is_android 	= stripos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'AppleWebKit') !== FALSE;
			$is_windows 	= stripos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'IEMobile')	   !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Phone') !== FALSE;
			$is_android_app	= $_SERVER['HTTP_USER_AGENT'] === WIZIAPP_ANDROID_APP;
			$this->_is_ipad_device = $is_iPad;

			if ( $is_iPhone || $is_iPod || ( $is_iPad && isset($_GET['androidapp']) && $_GET['androidapp'] === '1' )  || $is_android || $is_android_app || $is_windows ) {
				$this->mobile = TRUE;

				$this->_show_splash();
			}
		}

		if ( $this->mobile || $this->inApp ) {
			add_action('init', array(WiziappPluginCompatibility::getInstance(), 'pluginGuard'), 9999);
		}
	}

	public function getDeviceClass($classes) {
		if ( strstr($_SERVER['HTTP_USER_AGENT'], 'iPad') ) {
			$classes[] = 'ipad_general';
		} elseif ( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') ) {
			$classes[] = 'iphone_general';
		}

		return $classes;
	}

	/**
	* Handle the links converting, will convert images and post links according to the app protocol.
	*
	* @param array $matches the array returned from preg_replace_callback
	* @return string the link found after converting to the app format
	*/
	function getNewURL($url) {
		$post_id = url_to_postid($url);
		if ($post_id) {
			$post = get_post($post_id);
			if ($post->post_type == 'page') {
				return WiziappLinks::pageLinkFromURL($url);
			} elseif ($post->post_type == 'post') {
				return WiziappLinks::postLinkFromURL($url);
			}
		} elseif ( strpos($url, '.png') !== FALSE || strpos($url, '.gif') !== FALSE || strpos($url, '.jpg') !== FALSE || strpos($url, '.jpeg') !== FALSE ) {
			// If it is an image, convert to open image
			return WiziappLinks::linkToImage($url);
		}

		return $url;
	}

	function _getAdminImagePath() {
		return 'http://' . WiziappConfig::getInstance()->getCdnServer() . '/images/app/themes/' . WiziappConfig::getInstance()->wiziapp_theme_name . '/';
	}

	function add_header_to_content($content) {
		global $post;
		return get_post_meta($post->ID, 'wpzoom_post_embed_code', true) . $content;
	}

	function trigger_before_content($content) {
		if ($this->inApp || $this->mobile) {
			WiziappLog::getInstance()->write('INFO', "Triggering before the content", 'WiziappContentHandler.trigger_before_content');
			$content = apply_filters('wiziapp_before_the_content', $content);
		}

		if ($this->inApp || $this->inSave || $this->mobile) {
			$content = $this->add_header_to_content($content);
		}
		return $content;
	}

	/**
	* Convert the known content to a predefined format used by the application.
	* Called from 'the_content' filter of wordpress, running last.
	*
	* @param string $content the initial content
	* @return string $content the processed content
	*/
	function convert_content($content) {
		WiziappLog::getInstance()->write('INFO', "In the_content filter callback the contentHandler", "WiziappContentHandler.convert_content");

		// Avoid collision with "Events Manager" plugin,
		// as the "Simple HTML DOM" lib corrupt the File Input of the "Events Manager" plugin form
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$is_collision =
		is_plugin_active('events-manager/events-manager.php') &&
		strpos($content, "<input id='event-image' name='event_image' id='event_image' type='file' size='40' />") !== FALSE;
		if ( ( ! $this->inApp && ! $this->mobile ) || $is_collision ) {
			return $content;
		}
		WiziappLog::getInstance()->write('INFO', "Converting content like we are inside the app", "WiziappContentHandler.convert_content");

		global $post;

		ob_start();
		wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) );
		$content .= ob_get_clean();

		// Load DOM tree
		WiziappProfiler::getInstance()->write("Content processing for post {$post->ID}", "WiziappContentHandler.convert_content");
		$html = new simple_html_dom_wiziapp();
		$html->load($content);

		// Handle the Phone links
		WiziappProfiler::getInstance()->write("Conversion the phone numbers to links for post {$post->ID}", "WiziappContentHandler.convert_content");
		$this->_conversion_phone_links($html, $post->ID);
		WiziappProfiler::getInstance()->write("Done Conversion the phone numbers to links for post {$post->ID}", "WiziappContentHandler.convert_content");

		// Handle Audio
		WiziappProfiler::getInstance()->write("Getting the Audio elements code for post {$post->ID}", "WiziappContentHandler.convert_content");
		$this->_handle_audio($html, $post->ID);
		WiziappProfiler::getInstance()->write("Done Getting the Audio elements code for post {$post->ID}", "WiziappContentHandler.convert_content");

		// Handle Video
		WiziappProfiler::getInstance()->write("Getting the Video elements code for post {$post->ID}", "WiziappContentHandler.convert_content");
		$this->_handle_video($html, $post->ID);
		WiziappProfiler::getInstance()->write("Done Getting the Video elements code for post {$post->ID}", "WiziappContentHandler.convert_content");

		// Reload DOM tree - Unfortunately, simple_html_dom_wiziapp doesn't update the DOM tree in response to changes
		$content = $html->save();
		$html->clear();
		$html->load($content);

		// Handle images
		WiziappProfiler::getInstance()->write("Getting the images code for post {$post->ID}", "WiziappContentHandler.convert_content");
		$this->_handle_images($html, $post->ID);
		WiziappProfiler::getInstance()->write("Done Getting the images code for post {$post->ID}", "WiziappContentHandler.convert_content");

		// Reload DOM tree - Unfortunately, simple_html_dom_wiziapp doesn't update the DOM tree in response to changes
		$content = $html->save();
		$html->clear();
		$html->load($content);

		// Handle images final part - mark all images as handled
		foreach ($html->find('img') as $tag) {
			$this->addClass($tag, 'done');
		}

		// Remove unsupported flash objects
		foreach ($html->find('embed') as $tag) {
			$p = $tag->parent();
			while ($p && !$this->hasClass($p, 'data-wiziapp-iphone-support')) {
				$p = $p->parent();
			}
			if (!$p) {
				$tag->outertext = '';
			}
		}
		foreach ($html->find('object') as $tag) {
			$p = $tag->parent();
			while ($p && !$this->hasClass($p, 'data-wiziapp-iphone-support')) {
				$p = $p->parent();
			}
			if (!$p) {
				$tag->outertext = '';
			}
		}

		// Use HTML5 input types (This will fall back to text on old browsers)
		foreach ($html->find('input[type="text"]') as $tag) {
			if ((isset($tag->class) && strpos($tag->class, 'email') !== FALSE) || (isset($tag->name) && strpos($tag->name, 'email') !== FALSE)) {
				$tag->type = 'email';
			}
		}

		// Handle links
		WiziappProfiler::getInstance()->write("Handling links for post {$post->ID}", "WiziappContentHandler.convert_content");
		foreach ($html->find('a') as $a_tag) {
			if ( ! isset($a_tag->href) ) {
				continue;
			}
			$a_tag->href = $this->getNewURL($a_tag->href);
			$a_tag->setAttribute('data-transition', 'slide');
		}
		WiziappProfiler::getInstance()->write("Done Handling links for post {$post->ID}", "WiziappContentHandler.convert_content");

		// Reload DOM tree - Unfortunately, simple_html_dom_wiziapp doesn't update the DOM tree in response to changes
		$content = $html->save();
		$html->clear();
		$html->load($content);

		WiziappLog::getInstance()->write('INFO', "Remove Sharing From Content", "WiziappContentHandler.removeSharingFromContent");
		$this->_removeSharingFromContent($html, $post->ID);

		// Dumps the internal DOM tree back into string
		$script_elements = $html->find('script');
		$content = $html->save();
		$html->clear();

		// Remove unused shortcodes from the content.
		if ( is_array($script_elements) && count($script_elements) > 0 ) {
			$pattern = '#(?>.+(?=<script\s)|(?<=<\/script>).+)#is';
			$content = preg_replace_callback($pattern, array( 'WiziappHelpers', 'removeShorttags' ), $content);
		} else {
			$content = WiziappHelpers::removeShorttags($content);
		}

		WiziappProfiler::getInstance()->write("Done Content processing for post {$post->ID}", "WiziappContentHandler.convert_content");
		WiziappLog::getInstance()->write('INFO', "Returning the converted content", "WiziappContentHandler.convert_content");
		return $content;
	}

	/**
	* Calculate the new size of an image based on the current and the requested size proportionally.
	*
	* @param int $width
	* @param int $height
	*
	* @return array $size
	*/
	function calcResize($width, $height, $settings = array()) {
		$settings += array('max_width' => 288, 'max_height' => -1);

		WiziappLog::getInstance()->write('info', "Resizing an image with width " . $width . " and height " . $height, "calcResize");
		WiziappLog::getInstance()->write('info', "The options are: max_width: " . $settings['max_width'] . " & max_height: " . $settings['max_height'], "calcResize");

		$size = array('width' => $width, 'height' => $height);

		$width = intval($width);
		$height = intval($height);

		if ($settings['max_width'] < 0) {
			if ($settings['max_height'] >= 0 && $height > $settings['max_height']) {
				$size['height'] = $settings['max_height'];
				$size['width'] = intval($size['height']*$width/$height);
			}
		}
		else if ($settings['max_height'] < 0 || $settings['max_height']*$width > $settings['max_width']*$height) {
			if ($width > $settings['max_width']) {
				$size['width'] = $settings['max_width'];
				$size['height'] = intval($size['width']*$height/$width);
			}
		}
		else if ($height > $settings['max_height']) {
			$size['height'] = $settings['max_height'];
			$size['width'] = intval($size['height']*$width/$height);
		}

		return $size;
	}

	function addClass($node, $class) {
		// Sanity check
		if (!$node) {
			return;
		}
		if (!isset($node->class) || preg_match('!^\\s*$!Dsu', $node->class)) {
			$node->class = $class;
			return;
		}
		if ($this->hasClass($node, $class)) {
			return;
		}
		$node->class .= ' '.$class;
	}

	function hasClass($node, $class) {
		if (!$node || !isset($node->class)) {
			return FALSE;
		}
		return !!preg_match('!(^|\\s)'.preg_quote($class, '!').'($|\\s)!Dsu', $node->class);
	}

	function setStyle($node, $style, $value) {
		// Sanity check
		if (!$node) {
			return;
		}
		if (!isset($node->style) || preg_match('!^\\s*$!Dsu', $node->style)) {
			$node->style = $style.':'.$value;
			return;
		}
		$match = array();
		$old_style = $node->style;
		if (preg_match('!(?:;|^)()\\s*'.preg_quote($style).'\\s*:[^;]*()!Dsui', $old_style, $match, PREG_OFFSET_CAPTURE)) {
			$node->style = substr($old_style, 0, $match[1][1]).$style.':'.$value.substr($old_style, $match[2][1]);
			return;
		}
		if (preg_match('!(;|^)()\\s*$!Dsu', $old_style, $match, PREG_OFFSET_CAPTURE)) {
			$node->style = substr($old_style, 0, $match[1][1]).$style.':'.$value;
			return;
		}
		$node->style .= ';'.$style.':'.$value;
	}

	function hasStyle($node, $style, $value) {
		if (!$node || !isset($node->style)) {
			return FALSE;
		}
		return !!preg_match('!(^|;)\\s*'.preg_quote($style, '!').'\\s*:\\s*'.preg_quote($value, '!').'\\s*($|;)!Dsui', $node->style);
	}

	function unsetStyle($node, $style) {
		// Sanity check
		if (!$node || !isset($node->style) || preg_match('!^\\s*$!Dsu', $node->style)) {
			return;
		}
		$old_style = $node->style;
		$new_style = preg_replace('!(;|^)\\s*'.preg_quote($style).'\s*:[^;]*!Dsu', '', $old_style);
		if ($new_style != $old_style) {
			$node->style = $new_style;
		}
	}

	function convert_categories_links($data1) {
		return $data1;
	}

	function do_admin_head_section() {
		?>
		<link rel="stylesheet" href="<?php echo plugins_url( dirname( WP_WIZIAPP_BASE ) ).'/themes/admin/styles/style.css'; ?>" type="text/css" />
		<?php
	}

	function get_stylesheet( $stylesheet ) {
		if ($this->inApp === TRUE) {
			$stylesheet = 'blank';
		} elseif ( $this->mobile ) {
			// @todo Detect device to load the right stylesheet
			$stylesheet = 'webapp';
		}
		return $stylesheet;
	}

	function get_template( $template ) {
		$this->detectAccess();

		if ($this->inApp) {
			$template = 'iphone';
		} elseif ( $this->mobile ) {
			$template = 'webapp';
		}

		return $template;
	}

	function get_template_directory( $value ) {
		$this->detectAccess();

		if ( $this->inApp || $this->mobile ) {
			$value = WIZI_DIR_PATH . 'themes';
		}

		return $value;
	}

	function theme_root( $value ) {
		$this->detectAccess();

		if ( $this->inApp || $this->mobile ) {
			$value = WIZI_DIR_PATH . 'themes';
		}

		return $value;
	}

	public function save_template_directory($val) {
		$this->originalTemplateDir = $val;

		return $val;
	}

	public function reset_template_directory($val) {
		$this->detectAccess();
		if ( $this->inApp || $this->mobile ) {
			$val = $this->originalTemplateDir;
		}

		return $val;
	}

	public function save_template_directory_uri($val) {
		$this->originalTemplateDirUri = $val;

		return $val;
	}

	public function reset_template_directory_uri($val) {
		$this->detectAccess();
		if ( $this->inApp || $this->mobile ) {
			$val = $this->originalTemplateDirUri;
		}

		return $val;
	}

	public function save_stylesheet_directory_uri($val) {
		$this->originalStylesheetDirUri = $val;

		return $val;
	}

	public function reset_stylesheet_directory_uri($val) {
		$this->detectAccess();
		if ( $this->inApp || $this->mobile ) {
			$val = $this->originalStylesheetDirUri;
		}

		return $val;
	}

	public function save_stylesheet_directory($val) {
		$this->originalStylesheetDir = $val;

		return $val;
	}

	public function reset_stylesheet_directory($val) {
		$this->detectAccess();
		if ( $this->inApp || $this->mobile ) {
			$val = $this->originalStylesheetDir;
		}

		return $val;
	}

	function theme_root_uri( $url ) {
		$this->detectAccess();

		if ($this->inApp || $this->mobile) {
			$url = plugins_url( dirname( WP_WIZIAPP_BASE ) ) . '/themes';
		}

		return $url;
	}

	/**
	* @static
	* @return WiziappContentHandler
	*/
	public static function getInstance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new WiziappContentHandler();
		}

		return self::$_instance;
	}

	private function _removeSharingFromContent( & $html, $post_id) {
		// Remove add to any.
		for ($i=1; $i<=10; $i++){
			$divName = "div[id=wpa2a_{$i}]";
			$e = $html->find($divName, 0);
			if ( isset($e->outertext) ) {
				$e->outertext = '';
			}
		}

		// Remove sexybookmarks
		$className = '.shr-publisher-'.$post_id;
		$e = $html->find($className);
		if ( isset($e->outertext) ) {
			$e->outertext = '';
		}
	}

	private function _desktop_site_mode() {
		$is_desktop_site_mode = FALSE;

		if ( session_id() == '' ) {
			session_start();
		}

		$get_desktop_site 	  = isset($_GET['setsession']) 		&& $_GET['setsession'] === 'desktopsite';
		$session_desktop_site = isset($_SESSION['output_mode']) && $_SESSION['output_mode'] === 'desktop_site';

		if ( $get_desktop_site ) {
			$_SESSION['output_mode'] = 'desktop_site';
		}

		if ( $get_desktop_site || $session_desktop_site ) {
			$this->mobile = FALSE;
			$is_desktop_site_mode = TRUE;
		}

		return $is_desktop_site_mode;
	}

	/**
	* If it new review of the Webapp, started by not Home URL from the Bookmark or the Desktop Shortcut for example,
	* show the Splash.
	*/
	private function _show_splash() {
		$abort_show_splash =
		is_admin() ||
		( session_id() == '' && ! session_start() ) ||
		( isset($_SESSION['not_first_request']) && $_SESSION['not_first_request'] === '1' ) ||
		( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ) ||
		( ! empty($_SERVER['HTTP_REFERER']) ) ||
		( isset($_GET['androidapp']) && $_GET['androidapp'] === '1' );

		if( $abort_show_splash ) {
			return;
		}

		$_SESSION['not_first_request'] = '1';
		header('Location: '.$this->_blog_properties['url'], TRUE, 302);
		exit;
	}

	private function _conversion_phone_links( & $html, $post_id) {
		$phones = $html->find("text[plaintext*='/^\s*\+?[\d\-\s]{4,12}\d{2}\s*$/']");
		$phones_amount = count($phones);
		if ( $phones_amount === 0 ) {
			return;
		}

		for ( $i=0; $i<$phones_amount; $i++ ) {
			if ( $phones[$i]->parent()->tag === 'a' ) {
				// To avoid insert link into another link
				continue;
			}

			ob_start();
			?>
			<a href="tel:<?php echo $phones[$i]->plaintext; ?>">
				<?php echo $phones[$i]->plaintext; ?>
			</a>
			<?php
			$phones[$i]->outertext = ob_get_clean();
		}
	}

	private function _handle_audio( & $html, $post_id) {
		$audios_by_props = array();
		$all_audios = $html->find('a');

		foreach ($all_audios as $audio) {
			if ( ! isset($audio->attr['href']) ) {
				continue;
			}

			$key = md5($audio->attr['href']);
			$audios_by_props[$key] = $audio;
		}

		$content_audios = WiziappDB::getInstance()->get_post_audios($post_id);
		foreach ( $content_audios as $audio ) {
			if ( ! isset($audio['attachment_info'])) {
				continue;
			}
			$attachment_info = json_decode($audio['attachment_info'], true);

			if ( ! isset($attachment_info['actionURL']) ) {
				continue;
			}
			$url_parts = explode('/', $attachment_info['actionURL']);
			$audio_href = urldecode($url_parts[4]);
			$key = md5($audio_href);

			if ( ! isset( $audios_by_props[ $key ] ) ) {
				continue;
			}

			if ( empty($info['imageURL'])) {
				$style = '';
			} else {
				$style = "background-image: url({$this->_getAdminImagePath()}{$info['imageURL']}.png);";
			}

			if (strlen($attachment_info['title']) > 35) {
				$title = substr($attachment_info['title'], 0, 35) . '...';
			} else {
				$title = $attachment_info['title'];
			}

			ob_start();
			?>
			<a href="<?php echo $audio_href; ?>">
				<div class='audioCellItem'>
					<div class='col1'>
						<div class="imageURL" style="<?php echo $style; ?>"></div>
					</div>
					<div class='col2'>
						<p class="title"><?php echo $title; ?></p>
						<p class="duration"><?php echo $attachment_info['duration']; ?></p>
					</div>
					<div class="col3">
						<div class="playButton"></div>
					</div>
				</div>
			</a>
			<?php
			$audios_by_props[ $key ]->outertext = ob_get_clean();
		}
	}

	private function _handle_video( & $html, $post_id) {
		$videos_by_props = array();
		$all_videos = $html->find('iframe');

		foreach ($all_videos as $video) {
			if ( ! isset($video->attr['src']) ) {
				continue;
			}

			$video_id_pattern = '/^http:\/\/(?:\w+\.)?(?:youtu|vimeo)[\w\/\.]*(?:\/|\?v=)([\w\-]+)/i';
			preg_match($video_id_pattern, $video->attr['src'], $match);

			if ( empty($match) || empty($match[1]) ) {
				continue;
			}

			$videos_by_props[$match[1]] = $video;
		}

		$content_videos = WiziappDB::getInstance()->get_post_videos($post_id);
		foreach ( $content_videos as $video ) {
			if ( ! isset($video['attachment_info'])) {
				continue;
			}
			$attachment_info = json_decode($video['attachment_info'], true);

			if ( ! isset($attachment_info['actionURL']) ) {
				continue;
			}

			$url_parts = explode('/', $attachment_info['actionURL']);

			if ( ! isset( $videos_by_props[ $url_parts[5] ] ) && ( $url_parts[4] !== 'youtube' || $url_parts[4] !== 'vimeo' ) ) {
				continue;
			}

			if ( $url_parts[4] === 'youtube' ) {
				$url_parts[4] = 'video';
				$video_url = 'http://www.youtube.com/watch?v=';
			} elseif ( $url_parts[4] === 'vimeo' ) {
				$video_url = 'http://vimeo.com/';
			}

			ob_start();
			if ( isset($_GET['sim']) && $_GET['sim'] == 1 && isset($attachment_info['thumb']) ) {
				?>
				<div class="video_wrapper_container">
					<div class="video_wrapper_sim" data-video="video_<?php echo $video['id']; ?>">
						<img src="<?php echo $attachment_info['thumb']; ?>" width="340" alt="Video Thumbnail" />
						<div class="video_effect"></div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="<?php echo $url_parts[4]; ?>_wrapper data-wiziapp-iphone-support">
					<?php
					if ( $this->isHTML() ) {
						?>
						<div class="iframe_protect_screen" style="position: absolute;" data-video-url="<?php echo $this->_blog_properties['url'].'/?wiziapp/content/video/'.$video['id'].WiziappLinks::getAppend(); ?>"></div>
						<?php
					}
					echo wp_oembed_get( $video_url.$url_parts[5], array('width' => 300, ) );
					?>
				</div>
				<?php
			}

			$videos_by_props[ $url_parts[5] ]->outertext = ob_get_clean();
		}
	}

	private function _handle_images( & $html, $post_id) {
		// Handle images part 1 - Prepare the single images
		$img_by_props = array();
		$thumbnail_size = FALSE;
		$all_images = $html->find('img');
		if (count($all_images) >= WiziappConfig::getInstance()->count_minimum_for_appear_in_albums) {
			$thumbnail_size = WiziappConfig::getInstance()->getImageSize('album_thumb');
		}
		foreach ($all_images as $img) {
			$key = json_encode(array(isset($img->src)?$img->src:FALSE, isset($img->width)?$img->width:FALSE, isset($img->height)?$img->height:FALSE));
			if ( ! isset($img_by_props[$key]) ) {
				$img_by_props[$key] = array();
			}
			$img_by_props[$key][] = $img;
			$handler = new WiziappImageHandler($img->src);
			if ($thumbnail_size !== FALSE) {
				$img->setAttribute('data-image-thumb', $handler->getResizedImageUrl($img->src, $thumbnail_size['width'], $thumbnail_size['height']));
			}
			if ( ! isset($img->width) || ! isset($img->height) ) {
				$handler->load();
				if ( ! isset($img->width) ) {
					$img->width = $handler->getNewWidth();
				}
				if ( ! isset($img->height) ) {
					$img->height = $handler->getNewHeight();
				}
			}
		}

		$content_images = WiziappDB::getInstance()->get_content_images($post_id);
		if ( $content_images ) {
			foreach ($content_images as $img) {
				$attrs = json_decode($img['attachment_info'], true);
				if ( ! is_array($attrs) || ! isset($attrs['attributes']) || ! is_array($attrs['attributes']) ) {
					continue;
				}
				$attrs = $attrs['attributes'];
				$key = json_encode( array( isset($attrs['src']) ? $attrs['src'] : FALSE, isset($attrs['width']) ? $attrs['width'] : FALSE, isset($attrs['height']) ? $attrs['height'] : FALSE, ) );
				if ( ! empty($img_by_props[$key]) ) {
					$img_by_props[$key][0]->setAttribute('data-wiziapp-id', $img['id']);
					array_shift($img_by_props[$key]);
				}
			}
		}
		WiziappProfiler::getInstance()->write("Done Getting the images code for post {$post_id}", "WiziappContentHandler.convert_content");

		// Handle images part 2 - Converts the known gallery structure images to a format our multi image component can handle
		foreach ($html->find('.gallery br') as $tag) {
			$tag->outertext = '';
		}
		foreach ($html->find('.gallery dl a') as $tag) {
			$this->addClass($tag, 'wiziapp_gallery');
		}

		// Handle images part 3 - Detect multi-images
		$multi_images = array();
		$non_multi_images = array();
		$last_add = NULL;
		$prev = NULL;
		foreach ( $html->find('img') as $tag ) {
			$p = $tag->parent();

			if ( ! $p || !$this->hasClass($p, 'wiziapp_gallery') ) {
				$non_multi_images[] = $tag;
				continue;
			}

			if ( $prev && $prev->parent()->nextSibling() == $p ) {
				$this->addClass($prev->parent(), 'multi');
				$this->addClass($p, 'multi');
				if ( $prev != $last_add ) {
					$multi_images[] = array($prev, $tag);
					$prev->parent()->setAttribute('data-multi-index', count($multi_images));
				} else {
					$multi_images[count($multi_images)-1][] = $tag;
				}
				$p->setAttribute('data-multi-index', count($multi_images));
				$last_add = $tag;
			} elseif ($prev && $prev != $last_add) {
				$non_multi_images[] = $prev;
			}

			$prev = $tag;
		}
		if ($prev && $prev != $last_add) {
			$non_multi_images[] = $prev;
		}

		// Handle images part 4 - Resize and center all non-multi images
		foreach ($non_multi_images as $image) {
			$anchor = $image;
			if (!$this->hasClass($image, 'alignleft') && !$this->hasClass($image, 'alignright') && !$this->hasClass($image, 'aligncenter') && !$this->hasClass($image, 'alignnone') && !$this->hasClass($image, 'ngg-center')) {
				$p = $image->parent();
				for ($i = 0; $i < 2 && $p; $i++, $p = $p->parent()) {
					if ($this->hasClass($p, 'alignleft') || $this->hasClass($p, 'alignright') || $this->hasClass($p, 'aligncenter') || $this->hasClass($p, 'alignnone') || $this->hasClass($p, 'ngg-left') || $this->hasClass($p, 'ngg-right')) {
						$anchor = $p;
					}
				}
			}

			$url = FALSE;
			if ( ! $image->find_ancestor_tag('a') ) {
				$url = $image->src;
			}

			if ($this->hasClass($anchor, 'alignleft') || $this->hasClass($anchor, 'alignright') || $this->hasClass($anchor, 'ngg-left') || $this->hasClass($anchor, 'ngg-right') || $this->hasStyle($anchor, 'float', 'right') || $this->hasStyle($anchor, 'float', 'left')) {
				if ($image->width > 90 && $image->height > 90) {
					$multiplier = $this->_is_ipad_device ? 300 : 100;
					$new_height = intval($multiplier*$image->height/$image->width);
					$image->width = $multiplier;
					$image->height = $new_height;

					$this->setStyle($anchor, 'width', ($multiplier + 10).'px');
				} else {
					$size = $this->calcResize($image->width, $image->height);
					$image->width = $size['width'];
					$image->height = $size['height'];
				}
				$image->border = '0';

				if ($this->hasClass($anchor, 'alignright') || (isset($anchor->align) && !strcasecmp($anchor->align, 'right')) || $this->hasClass($anchor, 'ngg-right') || $this->hasStyle($anchor, 'float', 'right')) {
					$this->setStyle($anchor, 'float', 'right');
					$this->setStyle($anchor, 'margin', '5px 6px 4px 5px');
					$this->unsetStyle($anchor, 'margin-top');
					$this->unsetStyle($anchor, 'margin-right');
					$this->unsetStyle($anchor, 'margin-bottom');
					$this->unsetStyle($anchor, 'margin-left');
				} elseif ($this->hasClass($anchor, 'alignleft') || (isset($anchor->align) && !strcasecmp($anchor->align, 'left')) || $this->hasClass($anchor, 'ngg-left') || $this->hasStyle($anchor, 'float', 'left')) {
					$this->setStyle($anchor, 'float', 'left');
					$this->setStyle($anchor, 'margin', '5px 6px 4px 5px');
					$this->unsetStyle($anchor, 'margin-top');
					$this->unsetStyle($anchor, 'margin-right');
					$this->unsetStyle($anchor, 'margin-bottom');
					$this->unsetStyle($anchor, 'margin-left');
				}

				if ($url !== FALSE) {
					ob_start();
					?>
					<a href="<?php echo esc_attr($url); ?>"><?php echo $image->outertext; ?></a>
					<?php
					$image->outertext = ob_get_clean();
				}
			} else {
				$large = ($image->width > 90 && $image->height > 90);

				if ( $this->_is_ipad_device ) {
					$this->setStyle($image, 'height', 'auto');
					$this->setStyle($image, 'max-width', '100%');
				} else {
					// First thing's first - Rescale the image before we change the DOM structure around it, because the attributes cannot be edited afterwards
					$size = $this->calcResize($image->width, $image->height);
					$image->width = $size['width'];
					$image->height = $size['height'];
				}
				$image->border = '0';

				if($this->hasClass($anchor, 'ngg-center') || $this->hasStyle($anchor, 'float', 'center')) {
					$this->addClass($anchor, 'aligncenter');
					$wrap = true;
				}
				else if ($this->hasClass($anchor, 'aligncenter')) {
					$wrap = true;
				}
				else if (!$anchor->find_ancestor_tag('table')) {
					$this->setStyle($anchor, 'float', 'none');
					if ($large) {
						$wrap = true;
					}
				}

				if ($url !== FALSE) {
					ob_start();
					?>
					<a href="<?php echo esc_attr($url); ?>"><?php echo $image->outertext; ?></a>
					<?php
					$image->outertext = ob_get_clean();
				}
				if ($wrap) {
					ob_start();
					?>
					<p class="center" style="text-align: center"><?php echo $anchor->outertext; ?></p>
					<?php
					$anchor->outertext = ob_get_clean();
				}
			}
		}

		// Handle images part 5 - Create multi-image component or gallery
		$multi_image_height = WiziappConfig::getInstance()->multi_image_height;
		$galleryPrefix = WiziappLinks::postImagesGalleryLink($post_id).'%2F';
		foreach ($multi_images as $multi_key => $images) {
			if (count($images) < 5) {
				// Make gallery
				$ind = '';
				$maxHeight = 0;
				foreach ($images as $image) {
					$size = $this->calcResize($image->width, $image->height, array('max_height' => $multi_image_height));
					$ind .= $image->getAttribute('data-wiziapp-id').'_';
					if ($maxHeight < $size['height']) {
						$maxHeight = $size['height'];
					}
				}
				$maxHeight += 20;
				if ($maxHeight > $multi_image_height) {
					$maxHeight = $multi_image_height;
				}
				// FIXME: There has to be a way to make this adaptable to device size
				$width = 303 * count($images);
				$height = $maxHeight + 50;

				ob_start();
				?>
				<div class="wiziAppMultiImage" style="height:<?php echo esc_attr($height); ?>px;">
					<div class="wiziAppMultiImageScrolling ignoreFullPageSwipe" data-index="<?php echo esc_attr($multi_key+1); ?>" style="left: 0px; width: <?php echo esc_attr($width); ?>px">
						<?php
						foreach ($images as $image) {
							$size = $this->calcResize($image->width, $image->height, array('max_height' => $maxHeight));
							$image->width = $size['width'];
							$image->height = $size['height'];
							$image->border = '0';
							unset($image->align);
							$this->setStyle($image, 'float', 'none');
							$this->setStyle($image, 'display', 'block');
							$this->unsetStyle($image, 'margin-top');
							$this->unsetStyle($image, 'margin-right');
							$this->unsetStyle($image, 'margin-bottom');
							$this->unsetStyle($image, 'margin-left');
							$top = 10+$maxHeight-$size['height'];
							if ($top < 10) {
								$top = 10;
							}
							$this->setStyle($image, 'margin', $top.'px auto 0px');

							$image->parent()->href = $galleryPrefix.$image->getAttribute('data-wiziapp-id').'%2F'.$image->getAttribute('data-wiziapp-id').'%2F%26ids%3D'.$ind;
							echo $image->parent()->outertext;
							if ($this->hasClass($image, 'unsupported_video_format')) {
								?>
								<span class="notice unsupported_notice">Unsupported video format</span>
								<?php
							}
						}
						?>
					</div>

					<div class="multiImageNavItems">
						<?php
						foreach ($images as $key => $image) {
							?>
							<a href="<?php echo esc_attr($galleryPrefix.$ind.'%2F'.$image->getAttribute('data-wiziapp-id')); ?>" data-total="<?php echo esc_attr(count($images)); ?>" data-image-index="<?php echo esc_attr($key); ?>" class="multiImageNav<?php echo ($key == 0)?' active':''; ?> swap">.</a>
							<?php
						}
						?>
					</div>
				</div>
				<?php
				$replacement = ob_get_clean();

				foreach ($images as $key => $image) {
					$image->parent->outertext = ( $key == 0 ) ? $replacement : '';
				}
			} else {
				// Make album
				$ind = '';
				foreach ($images as $image) {
					$ind .= $image->getAttribute('data-wiziapp-id').'_';
				}

				ob_start();
				?>
				<ul class="wiziapp_bottom_nav albums_list">
					<li>
						<a class="albumURL" href="<?php echo esc_attr($galleryPrefix.'%26ids%3D'.$ind); ?>">
							<div class="imagesAlbumCellItem album_item">
								<div class="attribute imageURL album_item_image" style="background-image: url(<?php echo esc_attr($images[0]->getAttribute('data-image-thumb')); ?>)"></div>
								<div class="album_item_decor"></div>
								<p class="attribute text_attribute title album_item_title">Open image gallery</p>
								<div class="numOfImages attribute text_attribute album_item_numOfImages"><?php echo esc_html(count($images)); ?> photos</div>
								<span class="rowCellIndicator"></span>
							</div>
						</a>
					</li>
				</ul>
				<?php
				$replacement = ob_get_clean();

				foreach ($images as $key => $image) {
					$image->parent->outertext = ( $key == 0 ) ? $replacement : '';
				}
			}
		}
	}
}

require_once(dirname(dirname(__FILE__)) . '/libs/simpleHtmlDom/simple_html_dom.php');