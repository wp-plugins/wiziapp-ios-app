<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage AdminDisplay
* @author comobix.com plugins@comobix.com
*/

class WiziappGeneratorDisplay{

	private static $_slugs = array(
		'interstitial-app-wall-ads-for-a-better-mobile-monetization',
		'exclude-or-include-pages-tags-posts-categories-integrate-with-wiziapp',
		'nextgen-to-wiziapp',
	);

	public static function styles_javascripts($hook) {
		if ( $hook !== 'toplevel_page_wiziapp' ){
			return;
		}

		$wiziapp_plugin_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );

		wp_enqueue_style( 'wiziapp_generator_display', $wiziapp_plugin_url.'/themes/admin/styles/wiziapp_generator_display.css');

		wp_enqueue_script('jquery_tools', $wiziapp_plugin_url.'/themes/admin/scripts/jquery.tools.min.js');
		wp_enqueue_script('wiziapp_generator_display', $wiziapp_plugin_url.'/themes/admin/scripts/wiziapp_generator_display.js');
	}

	public static function display(){
		if ( ! defined('WIZIAPP_P_VERSION') || WIZIAPP_P_VERSION === '1.2.2' ){
			ob_start();
			?>
			The Wiziapp plugin needs to be upgraded, please see a
			<a href="http://www.wiziapp.com/blog/guides-tutorials/update-the-wiziapp-plugin/" target="_blank">
				guide
			</a>
			for upgrading it manually.
			<?php
			$error = ob_get_clean();
			self::_show_error($error);
			return;
		}

		$maint = FALSE;
		if ( function_exists("is_maintenance") ){
			$maint = is_maintenance();
		}
		if ( $maint ){
			// The plugin is in maintenance mode.
			self::_show_error('Your website is running in maintenance mode. While in this mode, the WiziApp plugin cannot run.');
			return;
		}

		// Before opening this display get a one time usage token
		$r = new WiziappHTTPRequest();
		$response = $r->api(array(), '/generator/getToken?app_id=' . WiziappConfig::getInstance()->app_id, 'POST');

		if ( is_wp_error($response) ) {
			self::_show_error($response->get_error_message());
			return;
		}

		if ( ! is_array($response) || empty($response['body']) ){
			self::_show_error('Connection problem, please contact the Wiziapp support.');
			return;
		}

		$tokenResponse = json_decode($response['body'], TRUE);
		if ( ! $tokenResponse['header']['status'] || ! isset($tokenResponse['token']) ){
			// There was a problem with the token.
			self::_show_error($tokenResponse['header']['message']);
			return;
		}

		$iframeSrc = 'https://'.WiziappConfig::getInstance()->api_server.'/generator/index/'.$tokenResponse['token'].'?v='.WIZIAPP_P_VERSION;
		if ( WiziappConfig::getInstance()->webapp_installed || WiziappConfig::getInstance()->skip_reload_webapp ){
			$iframeSrc .= '&webapp_installed=1';
		}
		?>

		<style type="text/css">
			.overlay_close {
				background-image:url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/close.png);
			}
			#wiziappBoxWrapper{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/simulator/phone.png) no-repeat scroll 8px 8px;
			}
			.processing_modal{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/Pament_Prossing_Lightbox.png) no-repeat top left;
			}
			#enter_license_modal{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/popwin.png) no-repeat top left;
			}
			#enter_license_modal .error{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/validetion_error_Icon.png) no-repeat left center;
			}
			#enter_license_modal .success{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/V_Icon.png) no-repeat left center;
			}
			#enter_license_modal input{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/email.png) no-repeat top left;
			}
			#enter_license_modal .wizi_button{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/CancelBTN.png) no-repeat top left !important;
			}
			#create_account_modal .error{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/validetion_error_Icon.png) no-repeat 0 5px;
			}
			.processing_modal .loading_indicator{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/lightgrey_counter.gif) no-repeat;
			}
			#wiziapp_main_tabs{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/menu_shadow_line.jpg) no-repeat bottom center;
			}
			#wiziapp_main_tabs li{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/Menu_Close_Tabe.png) no-repeat bottom center;
			}
			#wiziapp_main_tabs li.active{
				background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/Menu_open_tab.png) no-repeat bottom center;
			}
		</style>

		<script type="text/javascript">
			/*<![CDATA[*/
			var WIZIAPP_HANDLER_ADDITIONAL_PARAMS = {
				iframeSrc: "<?php echo $iframeSrc; ?>",
				iframeId: "<?php echo 'wiziapp_generator' . time(); ?>",
				ajaxLoader: "<?php echo WiziappConfig::getInstance()->getCdnServer().'/images/generator/lightgrey_counter.gif'; ?>",
				http_api_server:  "<?php echo 'http://' .  WiziappConfig::getInstance()->api_server; ?>",
				https_api_server: "<?php echo 'https://' . WiziappConfig::getInstance()->api_server; ?>"
			};
			/*]]>*/
		</script>

		<div id="wiziapp_generator_container"></div>

		<div class="hidden wiziapp_errors_container s_container" id="general_error_modal">
			<div class="errors_container">
				<div class="errors">
					<div class="wiziapp_error"></div>
				</div>
				<div class="buttons">
					<a href="javascript:void(0);" class="report_issue">Report a Problem</a>
					<a class="retry_processing close" href="javascript:void(0);">Retry</a>
				</div>
			</div>
			<div class="report_container hidden">

			</div>
		</div>

		<div class="processing_modal" id="reload_modal">
			<p class="processing_message">It seems your session has timed out.</p>
			<p>please <a href="javascript:top.document.location.reload(true);">refresh</a> this page to try again</p>
			<p class="error" class="errorMessage hidden"></p>
			<a class="close hidden" href="javascript:void(0);">Go back</a>
		</div>

		<div class="processing_modal" id="publish_modal">
			<p class="processing_message">Please wait while we process your request...</p>
			<div class="loading_indicator"></div>
			<p class="error" class="errorMessage hidden"></p>
			<a class="close hidden" href="javascript:void(0);">Go back</a>
		</div>
		<?php
	}

	private static function _show_error($message){
		?>
		<div class="wiziapp_errors_container s_container" style="top:40%;">
			<div class="errors">
				<div class="wiziapp_error">
					<?php echo $message; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function wiziapp_plugins_page(){
		include( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		$api = plugins_api( 'query_plugins', array( 'search' => 'wiziapp', ) );
		array_walk( self::$_slugs, array( 'WiziappGeneratorDisplay', '_filter_by_slug'), $api->plugins );

		$wp_list_table = _get_list_table('WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install', ) );
		$wp_list_table->items = self::$_slugs;
		$wp_list_table->display();

		wp_die();
	}

	private static function _filter_by_slug( & $item, $key, $api_plugins) {
		foreach ( $api_plugins as $api_plugin ) {
			if ( ! empty($api_plugin->slug) && $item === $api_plugin->slug ) {
				$item = $api_plugin;
				return;
			}
		}
	}
}