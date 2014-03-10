<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Our integration with the wordpress CMS.
* This file attaches the plugin to events in wordpress by using filters and actions
*
* @todo Figure out which method is better, one place of inside the class like contentHandler
*
* @package WiziappWordpressPlugin
* @author comobix.com plugins@comobix.com
*/

function wiziapp_attach_hooks(){
	$ce = new WiziappContentEvents();

	add_action( 'admin_menu', array( 'WiziappAdminDisplay', 'setup' ) );

	$setting_metabox = new WiziappSettingMetabox;
	if ( is_admin() ) {
		add_action( 'admin_init', array( $setting_metabox, 'admin_init' ) );
	}

	/* Add a custom column to the users table to indicate that the user
	* logged in from his mobile device via our app
	* NOTE: Some plugins might not handle other plugins columns very nicely and cause the data not to show.
	*/
	add_filter('manage_users_columns', array('WiziappUserList', 'column'));
	add_filter('manage_users_custom_column', array('WiziappUserList', 'customColumn'), 10, 3);

	add_action('new_to_publish', 	 array(&$ce, 'savePost'));
	add_action('pending_to_publish', array(&$ce, 'savePost'));
	add_action('draft_to_publish', 	 array(&$ce, 'savePost'));
	add_action('private_to_publish', array(&$ce, 'savePost'));
	add_action('future_to_publish',  array(&$ce, 'savePost'));
	add_action('publish_to_publish', array(&$ce, 'savePost'));

	if ( strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== FALSE && isset($_GET['output']) && $_GET['output'] == 'html' ) {
		$comment_screen = new WiziappCommentsScreen();
		add_action('set_comment_cookies', array(&$comment_screen, 'runBySelf'));
		add_filter('wp_die_handler', array(&$comment_screen, 'set_error_function'), 1);
	}

	add_action( 'wp_insert_post', array( 'WiziappPush', 'create_push_notification' ), 10, 2 );

	add_action( 'edit_post', array( &$ce, 'updateCacheTimestampKey' ) );

	add_action('deleted_post', array(&$ce, 'deletePost'));
	add_action('trashed_post', array(&$ce, 'deletePost'));

	add_action('untrashed_post', array(&$ce, 'recoverPost'));

	add_action('created_term', array(&$ce, 'updateCacheTimestampKey'));
	add_action('edited_term',  array(&$ce, 'updateCacheTimestampKey'));

	// The hook to avoid the Collision with the WP Super Cache
	add_filter('supercacherewriteconditions', array('WiziappHelpers', 'add_wiziapp_condition'));

	// Add a Wiziapp daily Cron jobs
	add_action('wiziapp_daily_function_hook', array(WiziappLog::getInstance(), 'delete_old_files'));
	add_action('wiziapp_daily_function_hook', array(WiziappCache::getCacheInstance(), 'delete_old_files'));
	add_action('wiziapp_daily_function_hook', array('WiziappCms', 'check_playstore_url'));

	// Handle uninstallation functions
	register_deactivation_hook(WP_WIZIAPP_BASE, array('WiziappInstaller', 'uninstall'));
	add_action('delete_blog', array('WiziappInstaller', 'deleteBlog'), 10, 2);

	/**
	* Admin ajax hooks
	*/
	// Post install
	add_action('wp_ajax_wiziapp_batch_process_posts',	array('WiziappPostInstallDisplay', 'batchProcess_Posts'));
	add_action('wp_ajax_wiziapp_batch_process_pages',	array('WiziappPostInstallDisplay', 'batchProcess_Pages'));
	add_action('wp_ajax_wiziapp_batch_process_finish',	array('WiziappPostInstallDisplay', 'batchProcess_Finish'));
	add_action('wp_ajax_wiziapp_report_issue',			array('WiziappPostInstallDisplay', 'reportIssue'));

	// Upgrade
	add_action('wp_ajax_wiziapp_upgrade_database', 		array('WiziappUpgradeDisplay', 'upgradeDatabase'));
	add_action('wp_ajax_wiziapp_upgrade_configuration', array('WiziappUpgradeDisplay', 'upgradeConfiguration'));
	add_action('wp_ajax_wiziapp_create_directories', 	array('WiziappUpgradeDisplay', 'create_wiziapp_directories'));
	add_action('wp_ajax_wiziapp_upgrading_finish', 		array('WiziappUpgradeDisplay', 'upgradingFinish'));

	// admin
	add_action('wp_ajax_wiziapp_hide_verify_msg', 		array('WiziappAdminNotices', 'hideVerifyMsg'));
	add_action('wp_ajax_wiziapp_plugins_page',	   		array('WiziappGeneratorDisplay', 'wiziapp_plugins_page'));
	add_action('wp_ajax_wiziapp_plugin_compatibility',	array(WiziappPluginCompatibility::getInstance(), 'configure'));

	// Wizard
	add_action('wp_ajax_wiziapp_register_license', array('WiziappLicenseUpdater', 'register'));

	add_filter('wiziapp_3rd_party_plugin', array('WiziappApi', 'externalPluginContent'), 1, 3);

	// QR Code Widget hook
	if ( ! empty( WiziappConfig::getInstance()->appstore_url ) &&  ! empty( WiziappConfig::getInstance()->app_name ) ) {
		// Run in Wiziapp Application will be available on Appstore case only
		add_action( 'widgets_init', create_function( '', 'register_widget("WiziappQRCodeWidget");' ) );
	}
}

if ( !defined('WP_WIZIAPP_HOOKS_ATTACHED') ) {
	define('WP_WIZIAPP_HOOKS_ATTACHED', TRUE);
	wiziapp_attach_hooks();
}