<?php if (!defined('WP_WIZIAPP_BASE')) exit();
	/**
	* Class WiziappQRCodeWidget implement "Wordpress Widgets API"
	* to provide "Wiziapp QR Code Widget".
	* "Wiziapp QR Code Widget" show Appstore download link
	* to Wiziapp Application on the blog pages.
	* "Wiziapp QR Code Widget" appear in the "Admin Panel Widgets area"
	* after Wiziapp Application will be available on Appstore only.
	*/
	class WiziappQRCodeWidget extends WP_Widget {

		public function __construct() {
			parent::__construct(
				WiziappConfig::getInstance()->wiziapp_qrcode_widget_id_base,
				WiziappConfig::getInstance()->wiziapp_qrcode_widget_name,
				array( 'description' => WiziappConfig::getInstance()->wiziapp_qrcode_widget_decription, )
			);
		}

		/**
		* To display the widget on the screen.
		*/
		public function widget( $args, $instance ) {
			$appstore_url = WiziappConfig::getInstance()->appstore_url;

			$query_string_array = array(
				'cht' => 'qr',
				'chs' => '150x150',
				'chl' => $appstore_url,
			);

			// Before widget, defined by themes. Display the widget title, before and after defined by themes.
			echo $args['before_widget'];
			echo $args['before_title'] . 'Download our iPhone App:' . $args['after_title'];
		?>
		<br />
		<div>
			<a href="<?php echo $appstore_url; ?>">
				<img src="<?php echo plugins_url( 'themes/AppInfo_Appstore_icon.jpg', WP_WIZIAPP_BASE ); ?>" alt="" />
			</a>
		</div>
		<div>
			<a href="<?php echo $appstore_url; ?>">
				<img src="https://chart.googleapis.com/chart?<?php echo http_build_query($query_string_array); ?>" alt="QR Code" />
			</a>
		</div>
		<?php
			// After widget (defined by themes).
			echo $args['after_widget'];
		}

	}

?>