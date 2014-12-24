<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappHelpers{

	private static $_wiziapp_rule = 'RewriteCond %{QUERY_STRING} !^wiziapp';

	public static function removeShorttags($matches) {
		$replacement = preg_replace('#\[[^\]]{4,60}\]#', '', $matches);

		if ( is_array($replacement) ) {
			$replacement = implode(' ', $replacement);
		}

		return $replacement;
	}

	public static function makeShortString($str, $len) {
		if ( function_exists('mb_strlen') && function_exists('mb_substr') ) {
			if ( mb_strlen($str) <= $len ){
				return $str;
			}
			$str = mb_substr($str, 0, $len) . '...';

			return $str;
		}

		if ( strlen($str) <= $len ){
			return $str;
		}
		$str = substr($str, 0, $len).'...';

		return $str;
	}


	/**
	* Add the "RewriteCond" for the Wiziapp plugin,
	* to avoid collision with the WP Super Cache plugin.
	*
	* @param array $condition_rules - The multiple "RewriteCond"-s of the WP Super Cache plugin, from his .htaccess file
	*/
	public static function add_wiziapp_condition( $condition_rules ) {
		if ( ! is_array($condition_rules) ) {
			return $condition_rules;
		}

		// Avoid Wiziapp interference
		$condition_rules[] = self::$_wiziapp_rule;

		return $condition_rules;
	}

	public static function check_rewrite_rules() {
		global $wp_cache_mod_rewrite, $super_cache_enabled;

		if ( $super_cache_enabled !== FALSE && $wp_cache_mod_rewrite === 1 && function_exists('extract_from_markers') ) {
			// The is the WP Super Cache plugin activated and it use the mod_rewrite.
			$wp_super_cashe_rules = extract_from_markers( get_home_path().'.htaccess', 'WPSuperCache' );
			$filtered_rules = array_filter( $wp_super_cashe_rules, array( 'WiziappHelpers', 'rules_filter') );

			if ( empty($filtered_rules) ){
				// The Wiziapp rules are not exist into the WP Super Cache Rewrite MODE rules
				return
				'The WebApp could not be installed, it might be a conflict with the WP Super Cache Plugin issue.
				Please click the "Update Mod_Rewrite Rules" button on the WP Super Cache - advanced tab and try the Wiziapp plugin again.
				If it will not help, please contact the Wiziapp support.';
			}
		}

		return '';
	}

	public static function rules_filter($rule) {
		return strpos($rule, self::$_wiziapp_rule) !== FALSE;
	}

	public static function get_adsense() {
		$result_array = array(
			'upper_mask' => 1,
			'lower_mask' => 2,
			'css' => '',
			'code' => '',
			'show_in_post' => 0,
			'is_shown' => FALSE,
		);

		$adsense = WiziappConfig::getInstance()->adsense;
		$admob 	 = WiziappConfig::getInstance()->admob;
		$proper_condition =
		isset($adsense['id']) && is_array($adsense['id']) && strlen($adsense['id']['id']) > 5 &&
		isset($adsense['show_in_post']) && $adsense['show_in_post'] > 0 &&
		// Do not show the AdSense if the Application Plan is not premium
		WiziappConfig::getInstance()->is_paid !== '0' &&
		// Do not show the AdSense in the iPhone Native App
		WiziappContentHandler::getInstance()->isHTML() && ! WiziappContentHandler::getInstance()->isInApp() &&
		// Do not show the AdSense in the Android native App, if the AdMob set
		! (	! ( isset($_GET['webapp']) && $_GET['webapp'] === '1' )	&& isset($admob['id']) && strlen($admob['id']) > 5 ) &&
		// This is not iPad
		! WiziappContentHandler::getInstance()->is_ipad_device();

		if ( ! $proper_condition ) {
			return $result_array;
		}

		ob_start();
		?>
		<style type="text/css">
			.page_content.wiziapp_google_adsenes ins{
				margin-<?php echo WiziappConfig::getInstance()->is_rtl() ? 'right' : 'left'; ?> : -7px !important;
			}
		</style>
		<?php
		$result_array['css'] = ob_get_clean();
		ob_start();
		?>
		<script type="text/javascript"><!--
			google_ad_client = "ca-pub-<?php echo $adsense['id']['id']; ?>";
			/* Wiziapp */
			google_ad_slot = "<?php echo ( isset($adsense['id']['slot']) && strlen($adsense['id']['slot']) > 5 ) ? $adsense['id']['slot'] : ""; ?>";
			google_ad_width = 320;
			google_ad_height = 50;
			//-->
		</script>
		<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script>
		<?php
		$result_array['code'] = ob_get_clean();
		$result_array['show_in_post'] = intval($adsense['show_in_post']);
		$result_array['is_shown'] = TRUE;

		return $result_array;
	}

	public static function get_analytics() {
		$result_array = array(
			'code' => '',
			'is_shown' => FALSE,
		);

		$analytics = WiziappConfig::getInstance()->analytics;
		if ( ! isset($analytics['id']) || strlen($analytics['id']) < 6 ) {
			return $result_array;
		}

		ob_start();
		?>
		<script type="text/javascript">
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', 'UA-<?php echo $analytics['id']; ?>']);
			_gaq.push(['_trackPageview']);

			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
		</script>
		<?php
		$result_array['code'] = ob_get_clean();
		$result_array['is_shown'] = TRUE;

		return $result_array;
	}

	public static function check_open_x_condition() {
		if ( session_id() == '' ) {
			session_start();
		}

		$proper_condition =
		// The Open X Ad has not been show less than a week ago
		! ( isset($_COOKIE['wiziapp_openxad_shown']) && $_COOKIE['wiziapp_openxad_shown'] === '1' ) &&
		// The Intro Page has not been show by this Session
		! ( isset($_SESSION['wizi_intro_page_shown']) && $_SESSION['wizi_intro_page_shown'] === '1' ) &&
		// This is not native application - there is not "androidapp=1
		! ( isset($_GET['androidapp']) && $_GET['androidapp'] === '1' );

		if ( ! $proper_condition ){
			return FALSE;
		}

		$_SESSION['wizi_intro_page_shown'] = '1';
		return TRUE;
	}

	public static function get_pixelSRC_attr(){
		$wiziapp_plugin_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );
		return $wiziapp_plugin_url.'/themes/webapp/images/pixel.png';
	}
}