<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappInstaller{

	public static function create_wiziapp_directories() {
		$wiziapp_data_directories = WiziappConfig::getInstance()->wiziapp_data_files;
		if ( empty($wiziapp_data_directories) || ! is_array($wiziapp_data_directories) ){
			throw new Exception('Wiziapp installation encountered errors. Try deactivate the Wiziapp plugin and activate it again');
		}

		$uploads_dir = wp_upload_dir();
		self::_create_data_directories(WiziappConfig::getInstance()->wiziapp_data_files, $uploads_dir['basedir']);
	}

	public function needUpgrade() {
		// We are not installed, we don't have nothing to upgrade, we need a full scan.
		return ( WiziappDB::getInstance()->needUpgrade() || WiziappConfig::getInstance()->needUpgrade() );
	}

	public function upgradeDatabase() {
		if ( WiziappDB::getInstance()->needUpgrade() ) {
			WiziappDB::getInstance()->upgrade();
		}

		return TRUE;
	}

	public function upgradeConfiguration() {
		$upgraded = TRUE;

		if ( WiziappConfig::getInstance()->needUpgrade() ) {
			$upgraded = WiziappConfig::getInstance()->upgrade();
		}

		return $upgraded;
	}

	public static function post_install(){
		WiziappDB::getInstance()->install();
		WiziappConfig::getInstance()->install();
		WiziappPluginCompatibility::getInstance()->install();

		// Create the Wiziapp plugin directories
		self::create_wiziapp_directories();

		if ( ! wp_next_scheduled('wiziapp_daily_function_hook') ) {
			// Register Wiziapp cron job
			wp_schedule_event(time(), 'daily', 'wiziapp_daily_function_hook');
		}

		// Activate the blog with the global services
		$cms = new WiziappCms();
		$cms->activate();

		/*
		$restoreHandler = new WiziappUserServices();
		$restoreHandler->restoreUserData();
		*/
	}

	/**
	* Revert the installation to remove everything the plugin added
	*/
	public function uninstall(){
		if ( ! ( function_exists('is_multisite') && is_multisite() ) ) {
			self::_doUninstall();

			return;
		}

		// If it is a network de-activation - if so, run the de-activation function for each blog id
		global $wpdb;

		$old_blog = $wpdb->blogid;
		// Get all blog ids
		$blogids = $wpdb->get_col( 'SELECT `blog_id` FROM '.$wpdb->blogs );

		foreach ($blogids as $blog_id) {
			switch_to_blog($blog_id);
			self::_doUninstall();
		}

		switch_to_blog($old_blog);
	}

	public function deleteBlog($blog_id, $drop){
		global $wpdb;
		$switched = false;
		$currentBlog = $wpdb->blogid;
		if ( $blog_id != $currentBlog ) {
			switch_to_blog($blog_id);
			$switched = true;
		}

		self::_doUninstall();

		if ( $switched ) {
			switch_to_blog($currentBlog);
		}
	}

	private static function _create_data_directories( array $directories, $parent_path){
		if ( empty($directories) ){
			return;
		}

		foreach ( $directories as $directory => $sub_directories ){
			$directory = $parent_path.DIRECTORY_SEPARATOR.$directory;

			if ( ! file_exists($directory) ) {
				if ( ! @mkdir($directory) ) {
					WiziappLog::getInstance()->write('ERROR', 'Could not create the Wiziapp Data Files directory: '.$directory, "WiziappInstaller._create_data_directories");
					throw new Exception('Could not create the Wiziapp Data Files directory: '.$directory);
				}

				if ( ! @chmod($directory, 0777) ) {
					WiziappLog::getInstance()->write('ERROR', 'The Wiziapp Data Files directory exists, but its not readable or not writable: '.$directory, "WiziappInstaller._create_data_directories");
					throw new Exception('It seems the PHP user can\'t set permission 777 for the folder: '.$directory.' . Please set this permission or contact your server hosting support in order to do it.');
				}
			} elseif ( ! @is_readable($directory) || ! @is_writable($directory) ) {
				if ( ! @chmod($directory, 0777) ) {
					WiziappLog::getInstance()->write('ERROR', 'The Wiziapp Data Files directory exists, but its not readable or not writable: '.$directory, "WiziappInstaller._create_data_directories");
					throw new Exception('It seems the PHP user can\'t set permission 777 for the folder: '.$directory.' . Please set this permission or contact your server hosting support in order to do it.');
				}
			}

			self::_create_data_directories($sub_directories, $directory);
		}
	}

	private static function _delete($path){
		if ( ! file_exists($path) ) {
			return;
		}

		$directoryIterator = new DirectoryIterator($path);

		foreach ( $directoryIterator as $fileInfo ){
			$filePath = $fileInfo->getPathname();

			if ( $fileInfo->isDot() ){
				continue;
			}

			if ( $fileInfo->isFile() ){
				@unlink($filePath);
			} elseif ( $fileInfo->isDir() ){
				self::_delete($filePath);
			}
		}

		@rmdir($path);
	}

	private static function _doUninstall(){
		$uploads_dir = wp_upload_dir();
		// Delete the wiziapp_data_files directory of the Wiziapp plugin current version
		self::_delete($uploads_dir['basedir'].DIRECTORY_SEPARATOR.'wiziapp_data_files');

		WiziappDB::getInstance()->uninstall();
		WiziappPluginCompatibility::getInstance()->uninstall();

		// Remove Wiziapp cron job
		wp_clear_scheduled_hook('wiziapp_daily_function_hook');

		// Deactivate the blog with the global services
		try{
			$cms = new WiziappCms();
			$cms->deactivate();
		} catch(Exception $e){
			// If it failed, it's ok... move on
		}

		// Remove option of the "Wiziapp QR Code Widget" on it exist case.
		if ( get_option( $wiziapp_qrcode_widget_option = 'widget_' . WiziappConfig::getInstance()->wiziapp_qrcode_widget_id_base ) ) {
			delete_option( $wiziapp_qrcode_widget_option );
		}

		// Remove all options - must be done last
		delete_option('wiziapp_screens');
		delete_option('wiziapp_components');
		delete_option('wiziapp_pages');
		delete_option('wiziapp_last_processed');
		delete_option('wiziapp_featured_post');

		WiziappConfig::getInstance()->uninstall();
	}
}