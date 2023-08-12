<?php
/**
 * Plugin Name:  SMS Gateway Press
 * Plugin URI:   https://www.sms-gateway-press.com
 * Description:  Self-hosted SMS Gateway. Send SMS with your own Android devices across your WordPress site.
 * Version:      0.1.0
 * Requires PHP: 7.0
 * Author:       Andy Daniel Navarro Taño
 * Author URI:   https://www.andaniel05.com
 * License:      GPLv2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  sms_gateway_press
 */

require_once __DIR__.'/private/class-main.php';

define( 'SMS_GATEWAY_PRESS_URL', plugin_dir_url( __FILE__ ) );

\SMS_Gateway_Press\Main::run();