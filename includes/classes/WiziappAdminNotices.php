<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* Handles the WP Pointers feature
*/
class WiziappAdminNotices {

	public static $_notices_texts = array(
		'install' => array(
			'title' => 'Finish the installing process',
			'content' => 'WiziApp needs one more step to finish the installing process, click <a href="admin.php?page=wiziapp">here</a>.',
			'pointer_width' => 500,
		),
		'upgrade' => array(
			'title' => 'Finish the upgrading process',
			'content' => 'WiziApp needs one more step to finish the upgrading process, click <a href="admin.php?page=wiziapp">here</a> to upgrade your database.<br />Make sure to update as soon as you can to enjoy the security, bug fixes and new features this update contain.',
			'pointer_width' => 800,
		),
		'verify_email' => array(
			'title' => 'Verify Email',
			'content' => 'The Email address you have registered with the Wiziapp service is not verified yet. We have sent you a verification Email, please go to your Email account and click the verify link.<br />In case you haven\'t got this email please go to <a href="admin.php?page=wiziapp_my_account_display">my account</a> and click "verify email".',
			'pointer_width' => 900,
		),
	);

	public static function set_admin_notices() {
		global $wp_version;
		$wp_ponters_compatible = version_compare($wp_version, '3.4', '>=');
		$installer = new WiziappInstaller();

		if ( ! WiziappConfig::getInstance()->install_notice_showed ) {
			WiziappConfig::getInstance()->install_notice_showed = TRUE;

			if ( $wp_ponters_compatible ) {
				self::_set_wp_pointer('finish_install_pointer');
			} else {
				add_action('admin_notices', array('WiziappAdminNotices', 'finish_install_regular_notice'));
			}
		} elseif ( $installer->needUpgrade() && ! ( isset($_GET['page']) && $_GET['page'] === 'wiziapp' ) ) {
			if ( $wp_ponters_compatible && WiziappConfig::getInstance()->upgrade_notice_new_mode ) {
				// Show the WP Notice by new design (WP Pointer)
				// as the WP Version > 3.4 and it is the first show of the Notice (WiziappConfig::getInstance()->upgrade_notice_new_mode == TRUE)
				WiziappConfig::getInstance()->upgrade_notice_new_mode = FALSE;

				self::_set_wp_pointer('finish_upgrade_pointer');
			} else {
				add_action('admin_notices', array('WiziappAdminNotices', 'finish_upgrade_regular_notice'));
			}
		}

		$print_message_condition =
		! ( isset($_GET['page']) && $_GET['page'] === 'wiziapp' ) &&
		! WiziappConfig::getInstance()->email_verified &&
		WiziappConfig::getInstance()->settings_done &&
		WiziappConfig::getInstance()->verify_email_notice &&
		WiziappConfig::getInstance()->finished_processing;
		if ($print_message_condition) {
			if ( $wp_ponters_compatible ) {
				self::_set_wp_pointer('verify_email_pointer');
			} else {
				add_action('admin_notices', array('WiziappAdminNotices', 'verify_email_regular_notice'));
			}
		}

		add_action('admin_notices', array(WiziappPluginCompatibility::getInstance(), 'notices'));

		// Add CSS and Javascript for the Wiziapp Admin Notices on the Admin panel => Plugins
		add_action( 'admin_enqueue_scripts', array('WiziappAdminNotices', 'styles_javascripts' ) );
	}

	public static function finish_install_regular_notice() {
		include WIZI_DIR_PATH.'/themes/admin/finish_install_notice.php';
	}

	public static function finish_install_pointer() {
		self::_wp_pointer_js('install');
	}

	public static function finish_upgrade_regular_notice() {
		include WIZI_DIR_PATH.'/themes/admin/finish_upgrade_notice.php';
	}

	public static function finish_upgrade_pointer() {
		self::_wp_pointer_js('upgrade');
	}

	public static function verify_email_regular_notice() {
		include WIZI_DIR_PATH.'/themes/admin/verify_email_notice.php';
	}

	public static function verify_email_pointer() {
		self::_wp_pointer_js('verify_email');
	}

	public static function styles_javascripts($hook) {
		$plugins_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );
		wp_enqueue_script( 'active_plugin_notice', $plugins_url . '/themes/admin/scripts/wiziapp_admin_notices.js', 'jquery' );
		wp_enqueue_style(  'active_plugin_notice', $plugins_url . '/themes/admin/styles/wiziapp_admin_notices.css' );
	}

	public static function hideVerifyMsg() {
		WiziappConfig::getInstance()->verify_email_notice = FALSE;
	}

	private static function _set_wp_pointer($method_name) {
		// Bind pointer print function
		add_action( 'admin_print_footer_scripts', array( 'WiziappAdminNotices', $method_name ) );

		// Add pointers script and style to queue
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
	}

	private static function _wp_pointer_js($action) {
		ob_start();
		?>
		<h3>
			<?php echo self::$_notices_texts[$action]['title']; ?>
		</h3>
		<p>
			<?php echo self::$_notices_texts[$action]['content']; ?>
		</p>
		<?php

		$options = json_encode( array( 'content' => ob_get_clean(), 'position' => array( 'edge' => 'top', 'align' => 'center', ), 'pointerWidth' => self::$_notices_texts[$action]['pointer_width'], ) );

		?>
		<script type="text/javascript">
			//<![CDATA[
			(function($){
				$(document).ready(function() {

					var extended_options = {
						"install":	{},
						"upgrade":	{},
						"verify_email":	{
							close: function() {
								$.post( ajaxurl, { action: 'wiziapp_hide_verify_msg' } );
							}
						}
					};
					var action = "<?php echo $action; ?>";
					var options = <?php echo $options; ?>

					$("#wpadminbar")
					.pointer( $.extend( options, extended_options[action] ) )
					.pointer('open');

				});
			})( jQuery );
			//]]>
		</script>
		<?php
	}
}