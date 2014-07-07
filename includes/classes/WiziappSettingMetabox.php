<?php

class WiziappSettingMetabox {

	private $_plugin_dir_url;

	public function __construct() {
		$this->_plugin_dir_url = plugins_url( dirname( WP_WIZIAPP_BASE ) );
	}

	/**
	* Trigger of the hooks on the Site Back End.
	*
	* @return void
	*/
	public function admin_init() {
		// Define the Wiziapp side metabox
		add_action( 'add_meta_boxes', array( &$this, 'add_setting_box' ) );
		// Add Javascripts
		add_action( 'admin_enqueue_scripts', array( &$this, 'styles_javascripts' ) );
		// Save the Push Notification message entered in "Wiziapp Metabox" on the Publish post
		add_action( 'save_post', array( &$this, 'save_push_message' ), 10, 2 );
	}

	/**
	* Adds a box to the main column on the Post edit screens
	*/
	public function add_setting_box() {
		global $post;
		if ( ! $this->_is_proper_type($post) ) {
			return;
		}

		add_meta_box(
			'wiziapp_setting_box',
			'WiziApp iOS',
			array( &$this, 'setting_box_view' ),
			'',
			'side'
		);
	}

	public function save_push_message( $post_id, $post ) {
		$push_message = isset($_POST['wiziapp_push_message']) ? $_POST['wiziapp_push_message'] : '';
		if ( function_exists('mb_strlen') ) {
			if ( mb_strlen($push_message) < 5 || mb_strlen($push_message) > 105 ) {
				return;
			}
		} else {
			if ( strlen($push_message) < 5 || strlen($push_message) > 105 ) {
				return;
			}
		}

		$is_send_wiziapp_push = ( isset($_POST['is_send_wiziapp_push']) && $_POST['is_send_wiziapp_push'] === '1' ) ? 'true' : 'false';

		if ( ! $this->_is_proper_type($post) ||	wp_is_post_revision( $post_id ) ) {
			// The Post is a revision, or other not proper type
			return;
		}

		update_post_meta( $post_id, 'wiziapp_push_message', $push_message );
		update_post_meta( $post_id, 'is_send_wiziapp_push', $is_send_wiziapp_push );
	}

	public static function get_push_message($post_id) {
		$wiziapp_push_message = get_post_meta($post_id, 'wiziapp_push_message', TRUE);

		if ( empty($wiziapp_push_message) ) {
			return WiziappConfig::getInstance()->push_message;
		}

		return $wiziapp_push_message;
	}

	public static function get_is_send_wiziapp_push($post_id) {
		$is_send_wiziapp_push = get_post_meta($post_id, 'is_send_wiziapp_push', TRUE);

		$post_type = get_post_type($post_id);
		if ( empty($is_send_wiziapp_push) && $post_type === "post" ) {
			// The default value for the regular post must be "TRUE".
			$is_send_wiziapp_push = 'true';
		}

		return $is_send_wiziapp_push === 'true';
	}

	public function styles_javascripts($hook) {
		$is_request_edit_post =	isset($_GET['post']) && ctype_digit($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit';

		if ( ( $hook === 'post.php' && $is_request_edit_post ) || ( $hook === 'post-new.php' ) ) {
			wp_enqueue_style(  'wiziapp_metabox', $this->_plugin_dir_url . '/themes/admin/styles/wiziapp_metabox.css' );
			wp_enqueue_script( 'wiziapp_metabox', $this->_plugin_dir_url . '/themes/admin/scripts/wiziapp_metabox.js' );
		}
	}

	/**
	* Prints the box content
	* @param Object $post
	*/
	public function setting_box_view($post) {
		$push_message 				= self::get_push_message($post->ID);
		$send_wiziapp_push_checked 	= self::get_is_send_wiziapp_push($post->ID) ? 'checked="checked"' : '';

		$path_to_view = realpath( dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'admin' );
		require $path_to_view.DIRECTORY_SEPARATOR.'setting_metabox.php';
	}

	private function _is_proper_type($post) {
		$post_types = WiziappComponentsConfiguration::getInstance()->get_post_types();
		return is_object($post) && property_exists($post, 'post_type') && in_array($post->post_type, $post_types);
	}
}