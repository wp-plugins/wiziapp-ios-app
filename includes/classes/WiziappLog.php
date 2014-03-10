<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* Basic log class for the wordpress plugin
*
* This log class can help us trace runtime information,
* from debugging to errors this class supports errors, debug, info, and all
* usually all logging will be disabled, but in case the problem comes knocking
* on our doors we can enable the Log and see whats up in every step of the way.
*
* @package WiziappWordpressPlugin
* @subpackage Utils
* @author comobix.com plugins@comobix.com
*
* @todo Add log files rotation management
*/

final class WiziappLog {
	/**
	* The desired logging level
	*
	* @var integer
	*/
	private $threshold = 2;

	/**
	* Is the log enabled?
	*
	* @var boolean
	*/
	private $enabled = TRUE; // = WP_WIZIAPP_DEBUG;

	/**
	* The log levels
	*
	* @var array
	*/
	private $levels = array('DISABLED' => 0, 'ERROR' => 1, 'WARNING' => 2, 'DEBUG' => 3, 'INFO' => 4, 'ALL' => 5,);

	/**
	* The file maximum size in bytes
	*
	* @var integer
	*
	*/
	private $max_size = 1048576; // 1MB

	/**
	* @var integer
	*
	*/
	private $max_days = 10;

	private $path = '';

	private static $_instance = null;

	/**
	* @static
	* @return WiziappLog
	*/
	public static function getInstance(){
		if( is_null(self::$_instance) ){
			self::$_instance = new WiziappLog();
		}

		return self::$_instance;
	}

	private function  __clone(){
		// Prevent cloning
	}

	private function __construct(){
		$this->path = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR;
		if (!$this->checkPath() || $this->isIIS()){
			$this->enabled = FALSE;
		}
		/*
		if (isset(WiziappConfig::getInstance()->wiziapp_log_threshold)){
		$this->threshold = intval(WiziappConfig::getInstance()->wiziapp_log_threshold);
		}
		*/
	}

	public function checkPath(){
		return is_writable($this->path);
	}

	public function isIIS (){
		if (isset($_SERVER['SERVER_SOFTWARE'])){ // Microsoft-IIS/x.x (Windows xxxx)
			if (stripos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== FALSE){
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	// Checks the file size, if file to big starts new one.
	private function toLarge($filepath){
		if (!file_exists($filepath)){
			return false;
		}
		if(@filesize($filepath) > $this->max_size){
			return true;
		}else{
			return false;
		}
	}

	private function getFileNamePrefix(){
		$prefix = 'wiziapplog-';
		if (function_exists('is_multisite') && is_multisite()){
			global $wpdb;
			$details = get_blog_details((int) $wpdb->blogid, true);
			$msPath = str_replace(array('/', '.', ':'), '_', $details->siteurl);

			$prefix = $prefix . $msPath . '-';
		}

		return $prefix;
	}

	// @todo Change this method to put the old file as .X and not the new, save the scanning of all the log files when adding a new message
	private function getFilePath(){
		$fileprefix = $this->getFileNamePrefix();

		$filepath = $this->path . $fileprefix . date('Y-m-d') . '.log.php';

		if(@filesize($filepath) > $this->max_size){
			$file_indx = 1;
			$new_filepath = $this->path . $fileprefix . date('Y-m-d') . '.log' . $file_indx . '.php';
			while ($this->toLarge($new_filepath)){
				$file_indx++;
				$new_filepath = $this->path . $fileprefix . date('Y-m-d') . '.log' . $file_indx . '.php';
			}
			return $new_filepath;
		}else{
			return $filepath;
		}
	}

	public function delete_old_files(){
		$oldest_date = mktime(0, 0, 0, date('m'), date('d') - $this->max_days, date('Y'));
		$dirHandle = opendir($this->path);

		while ( $file = readdir($dirHandle) ){
			$fileinfo = pathinfo($file);
			$basename = $fileinfo['basename'];

			if ( preg_match("/^wiziapplog-/", $basename) ){
				// $date = strtotime(substr($basename, strlen($this->getFileNamePrefix()), 10));
				$date = filemtime($this->path . $fileinfo['basename']);
				if ( $date <= $oldest_date ){
					@unlink($this->path . $fileinfo['basename']);
				}
			}
		}
	}

	/**
	* writes a log message to the log file
	*
	* The messages sent to this method will be filtered according to their level
	* if the level meets the threshold and the logging is enabled the message
	* will be written to a log file. The method also receives the component
	* related to this log message to ease the reading of the log file itself
	* If you want to keep your sanity make sure to send this "optional" parameter
	*
	* @param string $level The log message level
	* @param string $msg The log message
	* @param string $component The component related to this message
	* @return boolean success
	*/
	function write($level = 'error', $msg, $component=''){
		/**
		* NOTICE: Since this function is being run everywhere is, it might be run under the
		* an output handling method that wraps the entire content to do one last search and replace
		* like W3 Total Cache plugin with CDN configured, they replace the hostname with the CDN host name
		* From that reason, this function must never use output buffering
		*/
		@clearstatcache(); // We need to clear the cache of the file size function so that the size checks will work

		if ($this->enabled === FALSE){
			//ob_end_clean();
			return FALSE;
		}

		// Don't trust the user to use the right case, switch to upper
		$level = strtoupper($level);

		// If the wanted level is above the threshold nothing to do
		if (!isset($this->levels[$level]) || ($this->levels[$level] > $this->threshold)){
			//ob_end_clean();
			return FALSE;
		}

		$filepath = $this->getFilePath();
		$message  = '';

		// Prevent direct access to the log, to avoid security issues
		if (!file_exists($filepath)){
			$message .= "<?php if (!defined('WP_WIZIAPP_BASE')) exit(); ?" . ">\n\n";
			$message .= print_r($this->writeServerConfiguration(), TRUE);
			$message .= '==================================================================';
			$message .= PHP_EOL . PHP_EOL;
		}

		// If we can't open the file for appending there isn't much we can do
		if (!$fp = @fopen($filepath, 'ab')){
			//ob_end_clean();
			return FALSE;
		}

		$date = date('Y-m-d H:i:s');
		$message .= "[$level][{$date}][$component]$msg\n";

		@flock($fp, LOCK_EX);
		@fwrite($fp, $message);
		@flock($fp, LOCK_UN);
		@fclose($fp);

		@chmod($filepath, 0666);

		//ob_end_clean();
		return TRUE;
	}

	function writeServerConfiguration(){
		global $wpdb;

		// mysql version
		$sqlversion = $wpdb->get_var("SELECT VERSION() AS version");

		// sql mode
		$mysqlinfo = $wpdb->get_results("SHOW VARIABLES LIKE 'sql_mode'");
		if (is_array($mysqlinfo)){
			$sql_mode = $mysqlinfo[0]->Value;
		}

		if (empty($sql_mode)){
			$sql_mode = 'Not Set';
		}

		$config = array(
			'php_os' => PHP_OS,
			'sql version' => $sqlversion,
			'sql mode' => $sql_mode,
			'safe_mode' => ini_get('safe_mode'),
			'output buffer size' => ini_get('pcre.backtrack_limit') ? ini_get('pcre.backtrack_limit') : 'NA',
			'post_max_size'  => ini_get('post_max_size') ? ini_get('post_max_size') : 'NA',
			'max_execution_time' => ini_get('max_execution_time') ? ini_get('max_execution_time') : 'NA',
			'memory_limit' => ini_get('memory_limit') ? ini_get('memory_limit') : 'NA',
			'memory_get_usage' => function_exists('memory_get_usage') ? round(memory_get_usage() / 1024 / 1024, 2).'MByte' : 'NA',
			'server config' => $_SERVER,
			'display_errors' => ini_get('display_error'),
			'error_reporting' => ini_get('error_reporting'),
		);

		return $config;
	}
}