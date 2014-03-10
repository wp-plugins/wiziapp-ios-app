<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappSupport {

	private $path = '';
	private static $instance = null;

	private function __construct() {
		$this->path = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR;
	}

	public static function getInstance() {
		if (is_null(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function listLogs() {
		if ( ! is_dir($this->path) || ! is_readable($this->path) ) {
			$this->alert(500, "The log directory does not exists", 'listLogs');
		}

		if ( ! ( $logsDir = opendir($this->path) ) ) {
			$this->alert(500, "Could not open log directory", 'listLogs');
		}

		$logs = array();

		while ( ($log = readdir($logsDir)) !== FALSE ) {
			if (preg_match("/\.log(.)*\.php$/", $log) ) {
				$logs[] = array(
					'name' => $log,
					'date' => filemtime($this->path . $log),
					'size' => filesize($this->path . $log),
				);

			}
		}

		$this->array_sort($logs, 'name', 'date');
		rsort($logs);
		$this->returnResults(array('logs' => $logs), 'listLogs');
	}

	public function getLog($log) {
		$file = $this->path.$log;

		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($file));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));
			ob_clean();
			flush();
			readfile($file);
		} else {
			header("HTTP/1.0 404 Not Found");
		}

		exit;
	}

	protected function alert($code, $msg, $action='') {
		$status = array(
			'action' => $action,
			'status' => false,
			'code' => $code,
			'message' => Yii::t('yii',$msg),
		);

		// API request should never be cached
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		echo json_encode(array('header' => $status));

		exit();
	}

	protected function returnResults($body, $action='') {
		$status = array(
			'action' => $action,
			'status' => true,
			'code' => 200,
			'message' => '',
		);

		$result = array_merge(array('header' => $status), $body);

		header('Content-type: application/json');
		echo json_encode($result);

		exit();
	}

	function array_sort_func($a, $b=NULL) {
		static $keys;
		if ( $b === NULL ) {
			return $keys = $a;
		}

		foreach ( $keys as $k ) {
			if ( @$k[0] == '!' ) {
				$k = substr($k,1);
				if ( @$a[$k] !== @$b[$k] ) {
					return strcmp(@$b[$k],@$a[$k]);
				}
			} elseif ( @$a[$k] !== @$b[$k] ) {
				return strcmp(@$a[$k],@$b[$k]);
			}
		}

		return 0;
	}

	function array_sort(&$array) {
		if ( ! $array )
			return '';
		$keys=func_get_args();
		array_shift($keys);
		$this->array_sort_func($keys);
		usort($array,"array_sort_func");
	}
}