<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage AdminDisplay
* @author comobix.com plugins@comobix.com
*/

class WiziappAdminDisplay {
	/**
	* Sets up the admin menu according to the application configuration state.
	* For a fully installed app we show a full menu,
	* but until then way make things more complicated for the user.
	*/
	public static function setup(){
		$configured = WiziappConfig::getInstance()->settings_done;

		if ( isset($_GET['wiziapp_configured']) && $_GET['wiziapp_configured'] == 1 ){
			$configured = TRUE;
		}

		if ( current_user_can('administrator') ){
			WiziappAdminNotices::set_admin_notices();

			$iconPath = WiziappConfig::getInstance()->getCdnServer() . "/images/cms/WiziSmallIcon.png";
			$installer = new WiziappInstaller();

			if ( WiziappConfig::getInstance()->finished_processing === FALSE || is_null($configured) ){
				add_action( 'admin_enqueue_scripts', 	  array( 'WiziappPostInstallDisplay', 'styles_javascripts' ) );
				add_action( 'admin_print_footer_scripts', array( 'WiziappPostInstallDisplay', 'google_analytics' ), 1 );
				add_menu_page('Wiziapp iOS App', 'Wiziapp iOS App', 'administrator', 'wiziapp', array('WiziappPostInstallDisplay', 'display'), $iconPath);
			} elseif ( $installer->needUpgrade() ){
				add_menu_page('Wiziapp iOS App', 'Wiziapp iOS App', 'administrator', 'wiziapp', array('WiziappUpgradeDisplay', 	   'display'), $iconPath);
			} elseif ($configured === FALSE){
				add_action( 'admin_enqueue_scripts', array( 'WiziappGeneratorDisplay', 'styles_javascripts' ) );
				add_menu_page('Wiziapp iOS App', 'Wiziapp iOS App', 'administrator', 'wiziapp', array('WiziappGeneratorDisplay',   'display'), $iconPath);
			} else {
				add_menu_page('Wiziapp iOS App', 'Wiziapp iOS App', 'administrator', 'wiziapp', array('WiziappAdminDisplay', 'dashboardDisplay'), $iconPath);

				// This is to avoid having the top menu duplicated as a sub menu
				add_submenu_page('wiziapp', '', '', 'administrator', 'wiziapp', '');

				if (WiziappConfig::getInstance()->app_live !== FALSE){
					add_submenu_page('wiziapp', __('Statistics'), __('Statistics'), 'administrator', 'wiziapp_statistics_display', array('WiziappAdminDisplay', 'statisticsDisplay'));
				}

				add_submenu_page('wiziapp', __('App Info'),   __('App Info'),   'administrator', 'wiziapp_app_info_display',   array('WiziappAdminDisplay', 'appInfoDisplay'));
				add_submenu_page('wiziapp', __('My Account'), __('My Account'), 'administrator', 'wiziapp_my_account_display', array('WiziappAdminDisplay', 'myAccountDisplay'));
				add_submenu_page('wiziapp', __('Settings'),   __('Settings'),   'administrator', 'wiziapp_settings_display',   array('WiziappAdminDisplay', 'settingsDisplay'));
			}

			$sd = new WiziappSupportDisplay();
			add_submenu_page('wiziapp', __('Support'), __('Support'), 'administrator', 'wiziapp_support_display', array($sd, 'display'));
		}

		global $submenu;
		if ( (isset($submenu['wiziapp'][2][0]) && $submenu['wiziapp'][2][0] == 'My Account') ||
			(isset($submenu['wiziapp'][3][0]) && $submenu['wiziapp'][3][0] == 'My Account') ){
			if ($submenu['wiziapp'][0][0] == 'Create your App'){
				array_shift($submenu['wiziapp']);
			}
		} else {
			$submenu['wiziapp'][0][0] = __('Create your App');
			$submenu['wiziapp'][0][1] = __('administrator');
			$submenu['wiziapp'][0][2] = __('admin.php?page=wiziapp');
			$submenu['wiziapp'][0][3] = __('Create your App');
		}
	}

	public static function dashboardDisplay(){
		self::includeGeneralDisplay('dashboard');
	}

	public static function statisticsDisplay(){
		self::includeGeneralDisplay('statistics');
	}

	public static function settingsDisplay(){
		self::includeGeneralDisplay('settings');
	}

	public static function myAccountDisplay(){
		self::includeGeneralDisplay('myAccount');
	}

	public static function appInfoDisplay(){
		self::includeGeneralDisplay('appInfo', TRUE);
	}

	protected static function includeGeneralDisplay($display_action, $includeSimOverlay = TRUE){
		$r = new WiziappHTTPRequest();
		$response = $r->api(array(), '/generator/getToken?app_id=' . WiziappConfig::getInstance()->app_id, 'POST');
		if ( is_wp_error($response) ){
			WiziappLog::getInstance()->write('ERROR', 'There was an error getting the token from the admin: '.print_r($response, TRUE), 'WiziappAdminDisplay.includeGeneralDisplay');
			/**
			* @todo get the design for the failure screen
			*/
			echo '<div class="error">'.__('There was a problem contacting wiziapp services, please try again in a few minutes',
				'wiziapp').'</div>';
			exit();
		}

		// We are here, so all is good and the main services are up and running
		$tokenResponse = json_decode($response['body'], TRUE);
		if (!$tokenResponse['header']['status']){
			// There was a problem with the token
			WiziappLog::getInstance()->write('ERROR', 'Got the token from the admin but something is not right::'.print_r($response, TRUE), 'WiziappAdminDisplay.includeGeneralDisplay');
			echo '<div class="error">' . $tokenResponse['header']['message'] . '</div>';
		} else {
			WiziappLog::getInstance()->write('INFO', 'Got the token going to render the display', 'WiziappAdminDisplay.includeGeneralDisplay');
			$token = $tokenResponse['token'];
			$httpProtocol = 'https';
			if ( $includeSimOverlay ){
				?>
				<script type="text/javascript" src="<?php echo esc_attr(plugins_url('themes/admin/scripts/jquery.tools.min.js', dirname(dirname(__FILE__)))); ?>"></script>
				<style>
					#wpadminbar{
						z-index: 99;
					}
					.overlay_close {
						background-image:url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/close.png);
						position:absolute; right:-17px; top:-17px;
						cursor:pointer;
						height:35px;
						width:35px;
					}
					#wiziappBoxWrapper{
						width: 390px;
						height: 760px;
						margin: 0px auto;
						padding: 0px;
					}
				</style>
				<script type="text/javascript">
					var WIZIAPP_HANDLER = (function(){
						jQuery(document).ready(function(){
							jQuery('.report_issue').click(reportIssue);
							jQuery('.retry_processing').click(retryProcessing);

							jQuery('#general_error_modal').bind('closingReportForm', function(){
								jQuery(this).removeClass('s_container')
							});
						});

						function wiziappReceiveMessage(event){
							// Just wrap our handleRequest
							if ( event.origin == '<?php echo "http://" . WiziappConfig::getInstance()->api_server ?>' ||
								event.origin ==  '<?php echo "https://" . WiziappConfig::getInstance()->api_server ?>' ){
								WIZIAPP_HANDLER.handleRequest(event.data);
							}
						};

						if ( window.addEventListener ){
							window.addEventListener("message", wiziappReceiveMessage, false);
						}

						function retryProcessing(event){
							event.preventDefault();
							document.location.reload(true);
							return false;
						};

						function reportIssue(event){
							// Change the current box style so it will enable containing the report form
							event.preventDefault();
							var $box = jQuery('#general_error_modal');
							var $el = $box.find('.report_container');

							var params = {
								action: 'wiziapp_report_issue',
								data: $box.find('.wiziapp_error').text()
							};

							$el.load(ajaxurl, params, function(){
								var $mainEl = jQuery('#general_error_modal');
								$mainEl
								.removeClass('s_container')
								.find(".errors_container").hide().end()
								.find(".report_container").show().end();
								$mainEl = null;
							});

							var $el = null;
							return false;
						};

						var actions = {
							changeTab: function(params){
								top.document.location.replace('<?php echo get_admin_url();?>admin.php?page='+params.page);
							},
							informGeneralError: function(params){
								var $box = jQuery('#'+params.el);
								$box
								.find('.wiziapp_error').text(params.message).end();

								if ( parseInt(params.retry) == 0 ){
									$box.find('.retry_processing').hide();
								} else {
									$box.find('.retry_processing').show();
								}

								if ( parseInt(params.report) == 0 ){
									$box.find('.report_issue').hide();
								} else {
									$box.find('.report_issue').show();
								}

								if (!$box.data("overlay")){
									$box.overlay({
										fixed: true,
										top: 200,
										left: (screen.width / 2) - ($box.outerWidth() / 2),
										/**mask: {
										color: '#fff',
										loadSpeed: 200,
										opacity: 0.1
										},*/
										// disable this for modal dialog-type of overlays
										closeOnClick: false,
										closeOnEsc: false,
										// load it immediately after the construction
										load: true,
										onBeforeLoad: function(){
											var $toCover = jQuery('#wpbody');
											var $mask = jQuery('#wiziapp_error_mask');
											if ( $mask.length == 0 ){
												$mask = jQuery('<div></div>').attr("id", "wiziapp_error_mask");
												jQuery("body").append($mask);
											}

											$mask.css({
												position:'absolute',
												top: $toCover.offset().top,
												left: $toCover.offset().left,
												width: $toCover.outerWidth(),
												height: $toCover.outerHeight(),
												display: 'block',
												opacity: 0.9,
												backgroundColor: '#444444'
											});

											$mask = $toCover = null;
										}
									});
								}
								else {
									$box.show();
									$box.data("overlay").load();
								}
								$box = null;
							},
							showProcessing: function(params){
								var $box = jQuery('#'+params.el);
								$box
								.find('.error').hide().end()
								.find('.close').hide().end()
								.find('.processing_message').show().end();

								if ( !$box.data("overlay") ){
									$box.overlay({
										fixed: true,
										top: 200,
										left: (screen.width / 2) - ($box.outerWidth() / 2),
										mask: {
											color: '#444444',
											loadSpeed: 200,
											opacity: 0.9
										},

										// disable this for modal dialog-type of overlays
										closeOnClick: false,
										// load it immediately after the construction
										load: true
									});
								}
								else {
									$box.show();
									$box.data("overlay").load();
								}

								$box = null;
							},
							showSim: function(params){
								var url = decodeURIComponent(params.url);
								var $box = jQuery("#wiziappBoxWrapper");
								if ( $box.length == 0 ){
									$box = jQuery("<div id='wiziappBoxWrapper'><div class='close overlay_close'></div><iframe id='wiziappBox'></iframe>");
									$box.find("iframe").attr('src', url+"&preview=1");

									$box.appendTo(document.body);

									$box.find("iframe").css({
										'border': '0px none',
										'height': '760px',
										'width': '390px'
									});

									$box.overlay({
										top: 20,
										fixed: false,
										mask: {
											color: '#444',
											loadSpeed: 200,
											opacity: 0.8
										},
										closeOnClick: true,
										onClose: function(){
											jQuery("#wiziappBoxWrapper").remove();
										},
										load: true
									});
								}
								else {
									$box.show();
									$box.data("overlay").load();
								}

								$box = null;
							},
							resizeGeneratorIframe: function(params){
								jQuery("#wiziapp_frame").css({
									'height': (parseInt(params.height) + 50) + 'px'
								});
							}
						};

						return {
							handleRequest: function(q){
								var paramsArray = q.split('&');
								var params = {};
								for ( var i = 0; i < paramsArray.length; ++i){
									var parts = paramsArray[i].split('=');
									params[parts[0]] = decodeURIComponent(parts[1]);
								}
								if ( typeof(actions[params.action]) == "function" ){
									actions[params.action](params);
								}
								params = q = paramsArray = null;
							}
						};
					})();
				</script>
				<!--Google analytics-->
				<script type="text/javascript">
					var _gaq = _gaq || [];
					if (typeof(_gaq.splice) == 'function'){
						_gaq.splice(0, _gaq.length);
					}
					var analytics_account = '<?php echo WiziappConfig::getInstance()->analytics_account; ?>';
					var url = '<?php echo WiziappConfig::getInstance()->api_server; ?>';

					_gaq.push(['_setAccount', analytics_account]);
					_gaq.push(['_setDomainName', url.replace('api.', '.')]);
					_gaq.push(['_setAllowLinker', true]);
					_gaq.push(['_setAllowHash', false]);
					_gaq.push(['_trackPageview', '/ActivePluginGoal.php']);

					(function(){
						var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
						ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
						var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
					})();
				</script>
				<?php
			}
			?>
			<style>
				#wiziapp_container{
					background: #fff;
				}
				.processing_modal{
					background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/generator/Pament_Prossing_Lightbox.png) no-repeat top left;
					display:none;
					width:486px;
					height: 53px;
					padding:35px;
				}
				#general_error_modal{
					z-index: 999;
				}
			</style>
			<div id="wiziapp_container">
				<?php
				$iframeSrc = $httpProtocol . '://' . WiziappConfig::getInstance()->api_server . '/cms/controlPanel/' . $display_action . '?app_id=' .
				WiziappConfig::getInstance()->app_id . '&t=' . $token . '&v='.WIZIAPP_P_VERSION;
				WiziappLog::getInstance()->write('INFO', 'The iframe src is: '.$iframeSrc, 'WiziappAdminDisplay.includeGeneralDisplay');
				?>

				<iframe id="wiziapp_frame" src=""
					style="overflow: hidden; width:100%; height: 880px; border:0px none;" frameborder="0"></iframe>
				<script type="text/javascript">
					var iframe_src = "<?php echo $iframeSrc; ?>";
					document.getElementById("wiziapp_frame").src = iframe_src;
				</script>
			</div>

			<div class="wiziapp_errors_container s_container hidden" id="general_error_modal">
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
			<?php
		}
	}
}