<?php

/**
* Plugin Name: Wiziapp iOS App
* Description: Create your own free HTML5 mobile App for iPhone, Android and WP8 users. Publish your App as a native App to the App Store and Google Play Market!
* Author: Wiziapp Solutions Ltd.
* Version: v2.1.3c
* Author URI: http://www.wiziapp.com/
*/
/**
* This is the plugin entry script, it checks for compatibility and if compatible
* it will loaded the needed files for the CMS plugin
* @package WiziappWordpressPlugin
* @author comobix.com plugins@comobix.com
*
*/

// Run only once
if ( ! defined('WP_WIZIAPP_BASE') ) {
	define('WP_WIZIAPP_BASE', plugin_basename(__FILE__));
	define('WP_WIZIAPP_PROFILER', FALSE);
	define('WIZI_ABSPATH', realpath(ABSPATH));
	define('WIZI_DIR_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR );
	define('WIZIAPP_ENV', 'prod'); // can be dev/test/prod
	define('WIZIAPP_VERSION', 'v2.1.3c');   // MAKE SURE TO UPDATE BOTH THIS AND THE UPPER VALUE
	define('WIZIAPP_P_VERSION', '2.1.3');   // The platform version
	define('WIZIAPP_ANDROID_APP', '72dcc186a8d3d7b3d8554a14256389a4');

	$wiziapp_plugin_supported = array(
		'php_version' => '5.2',
		'wp_version' => '3.3',
	);

	if ( version_compare(PHP_VERSION, $wiziapp_plugin_supported['php_version'], '<') ) {
		if  ( is_admin() ) {
			register_shutdown_function('wiziapp_shutdownWrongPHPVersion', $wiziapp_plugin_supported['php_version']);
		}
	} elseif ( version_compare(get_bloginfo("version"), $wiziapp_plugin_supported['wp_version'], '<') ) {
		if  ( is_admin() ) {
			register_shutdown_function('wiziapp_shutdownWrongWPVersion', $wiziapp_plugin_supported['wp_version']);
		}
	} else {
		include dirname (__FILE__) . "/includes/blocks.inc.php";
		include dirname (__FILE__) . "/includes/hooks.inc.php";
	}
} else {
	function wiziapp_getDuplicatedInstallMsg() {
		return '<div class="error">'
		. __( 'An older version of the plugin is installed and must be deactivated. To do this, locate the old WiziApp plugin in the WordPress plugins interface and click Deactivate, then activate the new plugin.', 'wiziapp')
		.'</div>';
	}

	die(wiziapp_getDuplicatedInstallMsg());
}

function wiziapp_shutdownWrongPHPVersion($php_version) {
	?>
	<script type="text/javascript">alert("<?php echo __('You need PHP version '.$php_version.' or higher to use the WiziApp plugin.', 'wiziapp'); ?>")</script>
	<?php
}

function wiziapp_shutdownWrongWPVersion($wp_version) {
	?>
	<script type="text/javascript">alert("<?php echo __('You need WordPress '.$wp_version.' or higher to use the WiziApp plugin.', 'wiziapp'); ?>")</script>
	<?php
}