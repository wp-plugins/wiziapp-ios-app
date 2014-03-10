<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage Cache
* @author comobix.com plugins@comobix.com
*
* @todo add support for memcache configuration, will require configuration, maybe from wordpress cache plugins?
*/

class WiziappCacheAPC extends WiziappCache
{

	public function __construct( array $options = array()) {
		parent::__construct($options);

		WiziappLog::getInstance()->write('INFO', "APC caching system is active", "WiziappCacheAPC.__construct");
	}

	/**
	* start cache if need of print content form cache
	* @param array $output
	* @param array $options
	* @return void
	*/
	protected function _getFromCache( & $output) {
		if ( ! apc_exists($output['key'])) return;

		$output['headers'] = @unserialize( apc_fetch($output['key'].'_headers') );
		$output['content'] = @unserialize( apc_fetch($output['key']) );
	}

	public function delete_old_files() {}

	/**
	* save content
	* @param array $output
	* @return void
	*/
	protected function _renew_cache($output) {
		WiziappLog::getInstance()->write('INFO', 'Saving cache key: '.$output['key'],
			"WiziappCacheAPC._renew_cache");

		apc_delete($output['key'].'_headers');
		apc_delete($output['key']);

		if (
			! apc_store( $output['key'].'_headers', serialize($output['headers']), $this->_options['duration'] ) ||
			! apc_store( $output['key'], 		    serialize($output['content']), $this->_options['duration'] )
		) {
			WiziappLog::getInstance()->write('ERROR', "Cant save cache: {$output['key']}",
				"WiziappCacheAPC._renew_cache");
			return;
		}

		WiziappLog::getInstance()->write('DEBUG', "Saving cache key: {$output['key']}",
			"WiziappCacheAPC._renew_cache");
	}

}