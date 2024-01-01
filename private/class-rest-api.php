<?php

namespace SMS_Gateway_Press;

use SMS_Gateway_Press\Custom_Post_Type\Device;
use SMS_Gateway_Press\Custom_Post_Type\Sms;
use WP_Post;
use WP_REST_Request;
use SMS_Gateway_Press;

abstract class Rest_Api {

	const ROUTE_NAMESPACE = 'sms-gateway-press/v1';

	public static function register_endpoints(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
	}

	public static function rest_api_init(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/device-actions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_device_actions' ),
				'permission_callback' => array( __CLASS__, 'check_device_auth' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/device-auth',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'device_auth' ),
				'permission_callback' => array( __CLASS__, 'check_device_auth' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/update-sms',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_sms' ),
				'permission_callback' => array( __CLASS__, 'check_device_auth' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/sms/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_sms' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => array( __CLASS__, 'check_user' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'send_sms' ),
				'permission_callback' => array( __CLASS__, 'check_user' ),
			)
		);
	}

	public static function check_user(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_sms( WP_REST_Request $request ): void {
		$post_id  = $request['id'];
		$sms_info = Sms::get_sms_info( $post_id );

		if ( ! $sms_info ) {
			wp_send_json_error();
			wp_die();
		}

		wp_send_json_success( $sms_info );
		wp_die();
	}

	public static function send_sms( WP_REST_Request $request ): void {
		$phone_number = $request->get_param( 'phone_number' );
		$text         = $request->get_param( 'text' );

		$post_id = SMS_Gateway_Press::send( $phone_number, $text );

		sleep( 2 );

		$sms_info = Sms::get_sms_info( $post_id );

		wp_send_json_success( $sms_info );
		wp_die();
	}

	public static function check_device_auth( WP_REST_Request $request ): bool {
		$device_id    = $request->get_header( 'X-Device-Id' );
		$device_token = $request->get_header( 'X-Device-Token' );

		if ( ! is_numeric( $device_id ) || ! $device_token ) {
			return false;
		}

		$device_post = get_post( $device_id );

		if ( ! $device_post instanceof WP_Post ||
			Device::POST_TYPE !== $device_post->post_type
		) {
			return false;
		}

		$token = get_post_meta( $device_id, Device::META_KEY_TOKEN, true );

		if ( $device_token !== $token ) {
			return false;
		}

		update_post_meta( $device_id, Device::META_KEY_LAST_ACTIVITY_AT, time() );
		delete_post_meta( $device_id, Device::META_KEY_CURRENT_SMS_ID );

		return true;
	}

	public static function device_auth(): void {
		wp_send_json_success();
		wp_die();
	}

	public static function get_device_actions( WP_REST_Request $request ): void {
		$request_time       = sanitize_text_field( $_SERVER['REQUEST_TIME'] );
		$max_execution_time = ini_get( 'max_execution_time' );
		$request_time_limit = $request_time + $max_execution_time;
		$polling_time_limit = $request_time_limit - 2;
		$device_id          = $request->get_header( 'X-Device-Id' );

		wp_suspend_cache_addition();

		// start the long polling.
		while ( time() <= $polling_time_limit ) {
			if ( connection_aborted() ) {
				exit;
			}

			$now = time();

			update_post_meta( $device_id, Device::META_KEY_LAST_ACTIVITY_AT, $now );

			$sms_posts = get_posts(
				array(
					'post_type'   => Sms::POST_TYPE,
					'numberposts' => 1,
					'meta_query'  => array(
						'relation' => 'AND',
						array(
							'key'     => Sms::META_KEY_SEND_AT,
							'compare' => '<=',
							'value'   => $now,
							'type'    => 'NUMERIC',
						),
						array(
							'key'     => Sms::META_KEY_EXPIRES_AT,
							'compare' => '>',
							'value'   => $now,
							'type'    => 'NUMERIC',
						),
						array(
							'key'     => Sms::META_KEY_SENT_AT,
							'compare' => 'NOT EXISTS',
						),
						array(
							'relation' => 'OR',
							array(
								'key'     => Sms::META_KEY_INACTIVE_AT,
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'     => Sms::META_KEY_INACTIVE_AT,
								'compare' => '<',
								'value'   => $now,
								'type'    => 'NUMERIC',
							),
						),
						array(
							'relation' => 'OR',
							array(
								'key'     => Sms::META_KEY_SENDING_IN_DEVICE,
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'     => Sms::META_KEY_INACTIVE_AT,
								'compare' => 'NOT EQUAL',
								'value'   => $device_id,
								'type'    => 'NUMERIC',
							),
						),
					),
				)
			);

			if ( ! count( $sms_posts ) ) {
				sleep( 1 );
				continue;
			}

			$sms_post    = $sms_posts[0];
			$inactive_at = get_post_meta( $sms_post->ID, Sms::META_KEY_INACTIVE_AT, true );

			if ( is_numeric( $inactive_at ) &&
				$inactive_at > 0 &&
				$now > $inactive_at
			) {
				$sending_in_device = get_post_meta( $sms_post->ID, Sms::META_KEY_SENDING_IN_DEVICE, true );

				delete_post_meta( $sending_in_device, Device::META_KEY_CURRENT_SMS_ID );
				delete_post_meta( $sms_post->ID, Sms::META_KEY_SENDING_IN_DEVICE );
				delete_post_meta( $sms_post->ID, Sms::META_KEY_INACTIVE_AT );

				Sms::add_log( $sms_post->ID, 'Inactivity.' );
			}

			$sms_token = bin2hex( random_bytes( 8 ) );

			update_post_meta( $sms_post->ID, Sms::META_KEY_TOKEN, $sms_token );
			update_post_meta( $sms_post->ID, Sms::META_KEY_SENDING_IN_DEVICE, $device_id );
			update_post_meta( $sms_post->ID, Sms::META_KEY_INACTIVE_AT, $now + SMS::DEFAULT_INACTIVITY );

			update_post_meta( $device_id, Device::META_KEY_CURRENT_SMS_ID, $sms_post->ID );

			$response = array(
				array(
					'type' => 'send_sms',
					'data' => array(
						'id'                    => $sms_post->ID,
						'token'                 => $sms_token,
						'phone_number'          => get_post_meta( $sms_post->ID, Sms::META_KEY_PHONE_NUMBER, true ),
						'text'                  => get_post_meta( $sms_post->ID, Sms::META_KEY_TEXT, true ),
						'expires_at'            => get_post_meta( $sms_post->ID, Sms::META_KEY_EXPIRES_AT, true ),
						'server_time'           => $now,
						'max_confirmation_time' => $now + 5,
						'time_limit_at'         => $now + 30,
					),
				),
			);

			Sms::add_log(
				$sms_post->ID,
				"Sending requested to device '{$device_id}'."
			);

			wp_send_json( $response );
			wp_die();
		}

		wp_send_json( null, 204 );
		wp_die();
	}

	public static function update_sms( WP_REST_Request $request ): void {
		if ( ! $request->has_param( 'sms_id' ) ||
			! $request->has_param( 'sms_token' )
		) {
			wp_send_json_error( null, 400 );
			wp_die();
		}

		$sms_id   = $request->get_param( 'sms_id' );
		$sms_post = get_post( $sms_id );

		if ( ! $sms_post || Sms::POST_TYPE !== $sms_post->post_type ) {
			wp_send_json_error( null, 404 );
			wp_die();
		}

		$sms_token = get_post_meta( $sms_id, Sms::META_KEY_TOKEN, true );

		if ( $sms_token !== $request->get_param( 'sms_token' ) ) {
			wp_send_json_error( null, 403 );
			wp_die();
		}

		$device_id  = $request->get_header( 'X-Device-Id' );
		$expires_at = get_post_meta( $sms_id, Sms::META_KEY_EXPIRES_AT, true );
		$now        = time();

		if ( $now > $expires_at ) {
			wp_send_json_error( null, 403 );
			wp_die();
		}

		if ( 'true' === $request->get_param( 'confirmed' ) ) {
			update_post_meta( $sms_id, Sms::META_KEY_CONFIRMED_AT, $now );
			update_post_meta( $sms_id, Sms::META_KEY_INACTIVE_AT, $now + SMS::DEFAULT_INACTIVITY );

			Sms::add_log(
				$sms_id,
				"The device '{$device_id}' confirm and accept the sending."
			);

			wp_send_json_success();
			wp_die();
		}

		if ( 'true' === $request->get_param( 'sent' ) ) {
			update_post_meta( $sms_id, Sms::META_KEY_SENT_AT, $now );
			delete_post_meta( $device_id, Device::META_KEY_CURRENT_SMS_ID );

			Sms::add_log( $sms_id, 'Sent.' );

			wp_send_json_success();
			wp_die();
		}

		wp_send_json_error( null, 400 );
		wp_die();
	}
}
