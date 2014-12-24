<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappLicenseUpdater {

	public function register() {
		$message = 'Invalid license key';

		try {
			if ( empty( $_POST['key'] ) ) {
				throw new Exception('Key is not sent');
			}

			// We have a license, try to inform the admin
			$r = new WiziappHTTPRequest();
			$params = array( 'key' => $_POST['key'], );
			$response = $r->api($params, '/cms/license?app_id=' . WiziappConfig::getInstance()->app_id, 'POST');

			if ( is_wp_error($response) ) {
				throw new Exception('There was a problem trying to register the license: '.print_r($response, TRUE));
			}

			$result = json_decode($response['body'], TRUE);

			if ( ! ( $result && isset( $result['header']['status'] ) && isset( $result['header']['message'] ) ) ) {
				throw new Exception('There was a problem trying to register the license: '.print_r($response, TRUE));
			}

			if ( ! $result['header']['status'] ) {
				preg_match('@^xxxxx([a-z\s\.\,]+)xxxxx@i', $result['header']['message'], $matches);
				$message = ( isset( $matches[1] ) ) ? $matches[1] : $message;

				throw new Exception($result['header']['message']);
			}

			$success = TRUE;
		} catch (Exception $e) {
			WiziappLog::getInstance()->write('ERROR', $e->getMessage(), 'WiziappLicenseUpdater.register');
			$success = FALSE;
		}

		$header = array(
			'action' => 'registerLicense',
			'status' => $success,
			'code' => $success ? 200 : 500,
			'message' => $message,
		);

		echo json_encode( array( 'header' => $header ) );
		exit;
	}
}