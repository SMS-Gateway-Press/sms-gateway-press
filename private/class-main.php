<?php

namespace SMS_Gateway_Press;

require_once __DIR__ . '/class-rest-api.php';
require_once __DIR__ . '/custom-post-type/class-utils.php';
require_once __DIR__ . '/custom-post-type/class-device.php';
require_once __DIR__ . '/custom-post-type/class-sms.php';
require_once __DIR__ . '/class-sms-gateway-press.php';

abstract class Main {

	public const DATETIME_LOCAL_FORMAT = 'Y-m-d\TH:i';

	public static function run(): void {
		Custom_Post_Type\Device::register();
		Custom_Post_Type\Sms::register();

		Rest_Api::register_endpoints();

		add_filter('update_plugins_www.sms-gateway-press.com', array( __CLASS__, 'check_for_updates' ), 10, 3);
	}

	public static function check_for_updates( $update, $plugin_data, $plugin_file ) {
        static $response = false;

        if ( empty( $plugin_data['UpdateURI'] ) || ! empty( $update ) ) {
			return $update;
		}

        if ( $response === false ) {
			$response = wp_remote_get( $plugin_data['UpdateURI'] );
		}

        if ( empty( $response['body'] ) ) {
			return $update;
		}

        $custom_plugins_data = json_decode( $response['body'], true );

        if ( ! empty( $custom_plugins_data[ $plugin_file ] ) ) {
			return $custom_plugins_data[ $plugin_file ];
		} else {
			return $update;
		}
	}
}
