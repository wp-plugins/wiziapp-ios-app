<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage Configuration
* @author comobix.com plugins@comobix.com
*
*/
class WiziappComponentsConfiguration{
	private $config = array();
	private static $instance;
	private $_post_types = array(
		'is_prepared' => FALSE,
		'types' => array(),
	);

	private function __construct() {
		$this->config = get_option('wiziapp_components');
	}

	public static function getInstance(){
		if ( ! isset(self::$instance) ) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	function getAttrToAdd($name){
		$attrs = array();
		if ( isset($this->config[$name]) && isset($this->config[$name]['extra']) ){
			$attrs = $this->config[$name]['extra'];
		}

		return $attrs;
	}

	function getAttrToRemove($name){
		$attrs = array();
		if ( isset($this->config[$name]) && isset($this->config[$name]['remove']) ){
			$attrs = $this->config[$name]['remove'];
		}

		return $attrs;
	}

	public function get_post_types(){
		if ( ! $this->_post_types['is_prepared'] ){
			$this->_set_post_types();
		}

		return $this->_post_types['types'];
	}

	private function _set_post_types(){
		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$post_types = array_values( get_post_types( $args ) );
		$post_types[] = 'post';

		$key = array_search('wiziapp', $post_types);
		if ($key !== false) {
			// This is just trick.
			// Remove CPT 'wiziapp' to avoid collision with the "Wiziapp Mobile" plugin
			unset($post_types[$key]);
		}

		$this->_post_types = array(
			'is_prepared' => TRUE,
			'types' => $post_types,
		);
	}
}