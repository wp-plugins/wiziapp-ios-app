<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage Cache
* @author comobix.com plugins@comobix.com
*
* @todo add support for memcache configuration, will require configuration, maybe from wordpress cache plugins?
*/

abstract class WiziappCache {
	/**
	* defaults options
	* @var array
	*
	* $default_options['duration'](second) - Cache life time
	*/
	protected $_options = array('duration' => 3600);

	/**
	* Stack of caches for nesting cache
	* @var array
	*/
	private $cache_stack = array();

	protected $_is_enabled = TRUE;

	public function __construct( array $options) {
		$this->_options = array_merge($this->_options, $options);
	}

	/**
	* Choose type of the Cache
	*/
	public static function getCacheInstance( array $options = array()) {
		if ( function_exists('apc_exists') && WiziappConfig::getInstance()->wiziapp_cache_enabled ) {
			return new WiziappCacheAPC($options);
		} else {
			return new WiziappCacheFile($options);
		}
	}
	/**
	* Getter to protected Property.
	*/
	public function is_cache_enabled() {
		return $this->_is_enabled;
	}

	/**
	* start cache if need of print content form cache
	* @param array $output
	* @param array $options
	* @return void
	*/
	public function getContent(array & $output) {
		ob_start();

		//add to stack information about cache block, need for inherited caching
		array_push($this->cache_stack, array('key' => $output['key'], 'options' => $this->_options));

		/**
		* First make sure we are able to cache.
		* If not - return FALSE, so let the caller know it needs to continue with the rest of the code.
		*/
		$this->_getFromCache($output);

		if ( ! is_array($output['headers']) || empty($output['headers']) || empty($output['content'])) {
			$output['headers'] = array();
			$output['content'] = '';
			return;
		}

		// eTag value getting from headers.
		// If there is not eTag, do Output empty.
		for ($h=0, $total=count($output['headers']), $found=FALSE; $h<$total && !$found; ++$h) {
			if ( isset($output['headers'][$h]) && strpos($output['headers'][$h], 'ETag:') !== FALSE ) {
				$output['e_tag_stored'] = $this->getEtagFromHeader($output['headers'][$h]);
				$found = TRUE;
			}
		}
		if ($output['e_tag_stored'] == '') {
			$output['headers'] = array();
			$output['content'] = '';
		}
	}

	public function endCache($output) {
		WiziappLog::getInstance()->write('DEBUG', "The content is: {$output['content']}",
			"WiziappCache._getFromCache");

		$is_must_to_send = TRUE;

		// If eTag is proper, there is no need to return content.
		// We send code 304 Not Modified".
		WiziappLog::getInstance()->write('DEBUG', "The if not matched header is: {$output['e_tag_incoming']}", "WiziappCache.endCache");
		if ($output['e_tag_incoming'] != '' && $output['e_tag_incoming'] === $output['e_tag_stored']) {
			WiziappLog::getInstance()->write('DEBUG', "It's a match!!!", "WiziappCache.endCache");
			$this->_setUseCachedResponse();
			$is_must_to_send = FALSE;
		} else {
			// The headers do not match
			WiziappLog::getInstance()->write('DEBUG', "The headers do not match: " . $output['e_tag_incoming'] . " and the etag was {$output['e_tag_stored']}",
				"WiziappCache.endCache");
		}

		if ($output['is_new_content'] === '1') {
			$output['content'] = ob_get_clean();
		}

		if ($is_must_to_send) {
			// Send Content
			$this->_sendContent($output);
		}

		// If Content is new, we must to renew it in Cache.
		if ($output['is_new_content'] === '1' && $output['content'] != '') {
			// Because a Headers might be sent by other plugins also,
			// we must to get all sent Headers, before store them to Cache.
			// But we must not put to Cache 304 Not Modified header.
			$output['headers'] = array_filter( array_merge( headers_list(), $output['headers'] ), array($this, '_headersFilter') );
			$output['headers'] = array_unique($output['headers']);
			sort($output['headers']);

			$this->_renew_cache($output);
		}
	}

	public function getEtagFromHeader($string) {
		preg_match('/[a-z0-9]{30,}/', $string, $eTagIncomingMatches);
		if (isset($eTagIncomingMatches[0]) && $eTagIncomingMatches[0] != '') {
			return $eTagIncomingMatches[0];
		}
		return '';
	}

	private function _sendContent($output) {
		// First - send Headers
		for ($h=0, $total=count($output['headers']); $h<$total; ++$h) {
			header($output['headers'][$h]);
		}

		// Check, if compressed Content expected - sent compressed
		if ( $output['encoding'] !== '' ) {
			/**
			* Although gzip encoding is best handled by the zlib.output_compression,
			* our clients sometimes send a different accept encoding header like X-cpet-Encoding
			* In that case the only way to catch it is to manually handle the compression and headers check.
			*/
            $zlib = FALSE;
            $iniValue = ini_get('zlib.output_compression');
            if ( !empty($iniValue) ){
                $zlib = TRUE;
            }

			// Don't gzip the content if it was warped with ob_gzhandler like done in WordPress Gzip Compression, ticket #623
			if ( !in_array('ob_gzhandler', ob_list_handlers()) && !$zlib ){
				$len = strlen($output['content']);
				header('Content-Encoding: '.$output['encoding']);
				$output['content'] = gzencode($output['content'], 9);
			}
		}

		// After Headers send Content
		echo $output['content'];
	}

	private function _setUseCachedResponse(){
		WiziappLog::getInstance()->write('INFO', "Nothing to output the app should use the cache",
			"WiziappCache.setUseCachedResponse");
		/**
		* IIS needs us to be very specific
		*/
		header('Content-Length: 0');
		WiziappLog::getInstance()->write('INFO', "Sent the content-length",
			"WiziappCache.setUseCachedResponse");

		header("HTTP/1.1 304 Not Modified");
		WiziappLog::getInstance()->write('INFO', "sent 304 Not Modified for the app",
			"WiziappCache.setUseCachedResponse");
	}

	private function _headersFilter($header_value) {
		return ! in_array($header_value, array('Content-Length: 0', 'HTTP/1.1 304 Not Modified',));
	}

	abstract protected function delete_old_files();
	abstract protected function _getFromCache( & $output);
	abstract protected function _renew_cache($output);
}