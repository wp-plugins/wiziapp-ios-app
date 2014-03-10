<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Handles compatibility with other plugins, adjusting configurations of other
* plugins as necessary
*
* @package WiziappWordpressPlugin
* @subpackage Utils
* @author comobix.com plugins@comobix.com
*/
class WiziappPluginCompatibility {
	private static $_instance = null;

	/**
	* @static
	* @return WiziappPluginCompatibility
	*/
	public static function getInstance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new WiziappPluginCompatibility();
		}

		return self::$_instance;
	}

	private function __construct() {
	}

	public function pluginGuard() {
		/* Disable known conflicting plugins and popups incompatible with mobile display */

		/* WP super popup */
		remove_action('wp_print_styles', 'smp_add_styles');
		remove_action('wp_enqueue_scripts', 'smp_add_js');
		remove_action('wp_head', 'smp_add_head_code');

		/* XYZ Lightbox pop*/
		remove_action('get_footer', 'lbx_lightbox_create');
		remove_action('wp_footer', 'xyz_lbx_credit');

		/* Zopim live chat */
		remove_action('get_footer', 'zopimme');

		/* Shareaholic Sexy Bookmarks */
		remove_action('wp_head', 'shrsb_add_ogtags_head', 10);
		remove_action('wp_print_scripts', 'shrsb_publicScripts');
		remove_filter('the_content', 'shrsb_position_menu');
		remove_action('wp_print_styles', 'shrsb_publicStyles');
		remove_action('wp_footer', 'shrsb_write_js_params');
		remove_action('wp_footer', 'shrsb_get_topbar');
		remove_action('wp_footer', 'shrsb_tb_write_js_params');
		remove_filter('the_content', 'shrsb_get_recommendations');
		remove_action('wp_footer', 'shrsb_rd_write_js_params');
		remove_filter('the_content', 'shrsb_get_cb');
		remove_action('wp_footer', 'shrsb_cb_write_js_params');

		/* WP Touch */
		if (isset($_GET['androidapp']) && $_GET['androidapp'] === '1') {
			global $wptouch_plugin;
			remove_filter( 'wp', array(&$wptouch_plugin, 'bnc_do_redirect') );
		}
	}

	public function install() {
		$this->checkW3TotalCache(true);
		$this->checkQuickCache(true);
	}

	public function uninstall() {
		/* Currently unused. Turn off Wiziapp-specific fixes? */
	}

	public function notices() {
		if ($this->checkW3TotalCache(false)) {
?>
		<div class="error fade">
			<p style="line-height: 150%">
				The W3 Total Cache plugin has been detected on this blog. For the Wiziapp WebApp to function correctly, W3 Total Cache must be properly configured.
			</p>
			<p>
				<input id="wiziappConfigureW3TotalCache" type="button" class="button" value="Configure now" />
			</p>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("#wiziappConfigureW3TotalCache").click(function(){
						var params = {
							action: 'wiziapp_plugin_compatibility',
							plugin: 'w3_total_cache'
						};
						jQuery.post(ajaxurl, params, function(){
							jQuery("#wiziappConfigureW3TotalCache").closest(".error").remove();
						});
					});
				});
			</script>
		</div>
<?php
		}
		if ($this->checkW3TotalCacheMinify(false)) {
?>
		<div class="error fade">
			<p style="line-height: 150%">
				The W3 Total Cache plugin has JavaScript minification enabled. This
				may harm the WebApp display.
			</p>
			<p>
				<input id="wiziappConfigureW3TotalCacheMinify" type="button" class="button" value="Disable it" />
			</p>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("#wiziappConfigureW3TotalCacheMinify").click(function(){
						var params = {
							action: 'wiziapp_plugin_compatibility',
							plugin: 'w3_total_cache_minify'
						};
						jQuery.post(ajaxurl, params, function(){
							jQuery("#wiziappConfigureW3TotalCacheMinify").closest(".error").remove();
						});
					});
				});
			</script>
		</div>
<?php
		}
		if ($this->checkAllInOneEventCalendar()) {
			WiziappLog::getInstance()->write('Error', 'the all-in-one-event-calendar plugin old version problem', "WiziappPluginCompatibility.notices");
/*
?>
		<div class="error fade">
			<p style="line-height: 150%">
				For the Wiziapp plugin to function properly, please upgrade the all-in-one-event-calendar plugin to version 1.8.4 (or higher)
			</p>
		</div>
<?php
*/
		}
		if ($this->checkQuickCache(false)) {
?>
		<div class="error fade">
			<p style="line-height: 150%">
				The Quick Cache plugin has been detected on this blog. For the Wiziapp WebApp to function correctly, Quick Cache must be properly configured.
			</p>
			<p>
				<input id="wiziappConfigureQuickCache" type="button" class="button" value="Configure now" />
			</p>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("#wiziappConfigureQuickCache").click(function(){
						var params = {
							action: 'wiziapp_plugin_compatibility',
							plugin: 'quick_cache'
						};
						jQuery.post(ajaxurl, params, function(){
							jQuery("#wiziappConfigureQuickCache").closest(".error").remove();
						});
					});
				});
			</script>
		</div>
<?php
		}
	}

	public function configure() {
		switch ($_POST['plugin']) {
			case 'w3_total_cache':
				$this->checkW3TotalCache(true);
				break;
			case 'w3_total_cache_minify':
				$this->checkW3TotalCacheMinify(true);
				break;
			case 'quick_cache':
				$this->checkQuickCache(true);
				break;
		}
		$header = array(
			'action' => 'configure',
			'status' => true,
			'code' => 200,
			'message' => '',
		);
		echo json_encode(array('header' => $header));
		exit;
	}

	private function checkW3TotalCache($patch = false) {
		if (!function_exists('w3_instance')) {
			return false;
		}
		$agents = array(
			'(iPhone|iPod).*Mac\ OS\ X',
			'Mac\ OS\ X.*(iPhone|iPod)',
			'Android.*AppleWebKit',
			'AppleWebKit.*Android',
			'Windows.*IEMobile.*Phone',
			'Windows.*Phone.*IEMobile',
			'IEMobile.*Windows.*Phone',
			'IEMobile.*Phone.*Windows',
			'Phone.*Windows.*IEMobile',
			'Phone.*IEMobile.*Windows',
		);
		$config = w3_instance('W3_Config');
		$groups = $config->get_array('mobile.rgroups');
		if (isset($groups['wiziapp'])) {
			$group = $groups['wiziapp'];
			if (count($group) === 4 && isset($group['theme']) && isset($group['enabled']) && isset($group['redirect']) && isset($group['agents']) &&
				$group['theme'] === '' && $group['enabled'] === true && $group['redirect'] === '' && count($group['agents']) === count($agents)) {
				$found = false;
				foreach ($agents as $agent) {
					if (!in_array($agent, $group['agents'])) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					/* Our group is already present and enabled */
					return false;
				}
			}
		}
		if ($patch) {
			$groups['wiziapp'] = array(
				'theme' => '',
				'enabled' => true,
				'redirect' => '',
				'agents' => $agents,
			);
			$config->set('mobile.rgroups', $groups);
			$config->save(false);
		}
		return true;
	}


	private function checkW3TotalCacheMinify($patch = false) {
		if (!function_exists('w3_instance')) {
			return false;
		}
		$config = w3_instance('W3_Config');
		$minify = $config->get_boolean('minify.enabled');
		$minify_manual = !$config->get_boolean('minify.auto');
		if (!$minify || ($minify_manual && !$config->get_boolean('minify.js.enabled'))) {
			return false;
		}
		if ($patch) {
			if ($minify_manual) {
				$config->set('minify.js.enabled', false);
			}
			else {
				$config->set('minify.enabled', false);
			}
			$config->save(false);
		}
		return true;
	}

	private function checkAllInOneEventCalendar() {
		return (defined('AI1EC_VERSION') && AI1EC_VERSION < '1.8.4');
	}

	private function checkQuickCache($patch = false) {
		if (!class_exists ('c_ws_plugin__qcache_menu_pages')) {
			return false;
		}
		$prev_agents = preg_split ("/[\r\n\t]+/", $GLOBALS['WS_PLUGIN__']['qcache']['o']['dont_cache_these_agents']);
		$agents = $prev_agents;
		foreach (array('iPhone', 'iPod', 'Android', 'IEMobile') as $agent) {
			if (!in_array($agent, $agents)) {
				$agents[] = $agent;
			}
		}
		if ($agents === $prev_agents) {
			return false;
		}
		if ($patch) {
			c_ws_plugin__qcache_menu_pages::update_all_options(array('ws_plugin__qcache_dont_cache_these_agents' => implode(PHP_EOL, $agents)), true, true, false);
		}
		return true;
	}
}
