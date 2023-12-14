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
	}
}
