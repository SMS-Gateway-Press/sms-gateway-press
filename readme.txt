=== SMS Gateway Press ===
Contributors: andaniel05
Donate link: https://tppay.me/lnq34pqn
Tags: sms, smsgateway
Requires at least: 6.0
Tested up to: 6.3
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted SMS Gateway. Send SMS with your own Android devices across your WordPress site.

== Description ==

This plugin extends the capabilities of WordPress to turn it into a complete text messaging (SMS) gateway.

Features:

1. Add endpoints to the Rest API to send and check the status of SMS.
2. Allows you to connect an unlimited number of Android devices. The more devices connected, the higher the availability of the gateway.
3. Unlimited sending. Since SMS are sent with the mobile lines of Android devices, the costs applied will depend on them. It's good to keep in mind that in many countries local text messages are free.
4. Shows real-time and refined information on the status of devices as well as SMS.
5. Allows you to schedule SMS sending.

== Get Started ==

1. Install this plugin.

2. Create one device from "Devices" > "Add New" in the WordPress admin. Define only a title and publish the post. Once the post is published, a QR code will appear that you must scan from the app to connect the device.

3. Install the Android app client.

4. Open the installed app and press the "Edit Credentials" button. Then press "Scan QR" and focus on the QR of the device. Once the scan has been completed satisfactorily, you can press the "Test Connection" button to test the connection. If successful, it will indicate below the device's QR that it has already been connected. Then press "Save". Then press "Connect" in the app and some information about the recent connection activity will be displayed below.

== Frequently Asked Questions ==

= How to send SMS manually? =

Simply create a new SMS type entry from the menu and indicate the required parameters such as the destination number, text and time restrictions. Once said entry is published, the SMS will be delivered to the first connected device that is free for sending. Once the submission process begins, a debugging box will display the process.

= How to send SMS via API? =

To use the API endpoints, it is first necessary to create an application password to authorize the user.

Once you have done this you will be able to send with API calls similar to the following:

`curl -X POST --user <username>:"<password>" https://<your-domain>/wp-json/wp-sms-gateway/v1/send -d phone_number=123456789 -d text="Hi World"`

The response will contain information about the new SMS.

= How to check the status of an SMS via API? =

`curl -X GET --user <username>:"<password>" https://<your-domain>/wp-json/wp-sms-gateway/v1/sms/<id>`

== Screenshots ==

1. screenshot-1.png SMS List.
2. screenshot-2.png New SMS.
3. screenshot-3.png SMS Sent.
4. screenshot-4.png Device info.
5. screenshot-5.png Main app view.
6. screenshot-6.png Edit credentials.
7. screenshot-7.png App running.

== Changelog ==

TODO
