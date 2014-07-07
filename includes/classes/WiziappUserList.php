<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage AdminDisplay
* @author comobix.com plugins@comobix.com
*/

class WiziappUserList{

	public function column($cols){
		$cols['wiziapp_got_valid_mobile_token'] = __('Mobile?', 'wiziapp');
		return $cols;
	}

	public function customColumn($curr_val, $column_name, $user_id){
		if ( strpos($column_name, 'wiziapp_') !== FALSE ){
			$val = get_user_meta($user_id, $column_name);
			return ( $val!='' ) ? $val : 'NO';
		}

		// We are here so it wasn't our column, return the current value
		return $curr_val;
	}

}