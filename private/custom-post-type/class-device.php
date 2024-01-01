<?php

namespace SMS_Gateway_Press\Custom_Post_Type;

use DateTime;

abstract class Device {

	public const POST_TYPE            = 'smsgp_device';
	public const COLUMN_STATUS        = 'status';
	public const NONCE_ACTION_METABOX = 'nonce_metabox';

	public const META_KEY_TOKEN            = '_token';
	public const META_KEY_PHONE_NUMBER     = '_phone_number';
	public const META_KEY_LAST_ACTIVITY_AT = '_last_activity_at';
	public const META_KEY_CURRENT_SMS_ID   = '_current_sms_id';

	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'on_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
	}

	public static function on_init(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'               => array(
					'name'          => __( 'Devices', 'sms_gateway_press' ),
					'singular_name' => __( 'Device', 'sms_gateway_press' ),
				),
				'public'               => false,
				'show_ui'              => true,
				'show_in_menu'         => true,
				'supports'             => array( 'title', 'thumbnail' ),
				'register_meta_box_cb' => array( __CLASS__, 'register_meta_box' ),
			)
		);
	}

	public static function on_admin_init(): void {
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'on_save_post' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'manage_posts_columns' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'manage_posts_custom_column' ), 10, 2 );
		add_action( 'wp_ajax_update_sms_gateway_press_device_form', array( __CLASS__, 'ajax_update_sms_gateway_press_device_form' ) );
		add_action( 'wp_ajax_update_sms_gateway_press_device_list', array( __CLASS__, 'ajax_update_sms_gateway_press_device_list' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
	}

	public static function admin_enqueue_scripts( string $page ): void {
		global $post_type;

		if ( 'edit.php' !== $page || self::POST_TYPE !== $post_type ) {
			return;
		}

		$handle = self::POST_TYPE . '-list-device';

		wp_enqueue_script( $handle, SMS_GATEWAY_PRESS_URL . '/js/list-device.js', array(), '1.0.0', true );

		wp_localize_script(
			$handle,
			'app',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'action' => 'update_sms_gateway_press_device_list',
				'nonce'  => wp_create_nonce( 'update_sms_gateway_press_device_list' ),
			)
		);
	}

	public static function on_save_post( int $post_id ): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_ACTION_METABOX ] ) ), self::NONCE_ACTION_METABOX ) ) {
			return;
		}

		if ( isset( $_POST[ self::META_KEY_TOKEN ] ) ) {
			update_post_meta( $post_id, self::META_KEY_TOKEN, sanitize_text_field( $_POST[ self::META_KEY_TOKEN ] ) );
		}

		if ( isset( $_POST[ self::META_KEY_PHONE_NUMBER ] ) ) {
			update_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, sanitize_text_field( $_POST[ self::META_KEY_PHONE_NUMBER ] ) );
		}
	}

	public static function manage_posts_columns( array $columns ): array {
		unset( $columns['date'] );

		$columns[ self::COLUMN_STATUS ]             = esc_html__( 'Status', 'sms_gateway_press' );
		$columns[ self::META_KEY_LAST_ACTIVITY_AT ] = esc_html__( 'Last Activity', 'sms_gateway_press' );

		$columns['author'] = esc_html__( 'Author' );

		return $columns;
	}

	public static function get_status_badge( int $post_id ): ?string {
		$post = get_post( $post_id );

		if ( $post && self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$last_activity_at   = get_post_meta( $post_id, self::META_KEY_LAST_ACTIVITY_AT, true );
		$disconnected_badge = '<span style="background-color:brown;color:white;padding:5px;">' . esc_html__( 'Disconnected', 'sms_gateway_press' ) . '</span>';
		$connected_badge    = '<span style="background-color:green;color:white;padding:5px;">' . esc_html__( 'Connected', 'sms_gateway_press' ) . '</span>';

		if ( ! $last_activity_at ) {
			return $disconnected_badge;
		}

		$last_activity_at_dt = new DateTime();
		$last_activity_at_dt->setTimestamp( $last_activity_at );

		$now             = new DateTime();
		$elapsed_seconds = $now->getTimestamp() - $last_activity_at_dt->getTimestamp();

		if ( $elapsed_seconds > 5 ) {
			return $disconnected_badge;
		}

		$current_sms_id = get_post_meta( $post_id, self::META_KEY_CURRENT_SMS_ID, true );

		return is_numeric( $current_sms_id ) ?
			'<span style="background-color:darkviolet;color:white;padding:5px;">' . esc_html__( 'Sending', 'sms_gateway_press' ) . ':<a href="' . esc_url( get_edit_post_link( $current_sms_id ) ) . '" target="_blank" style="color:white;text-decoration:underline">' . esc_html( $current_sms_id ) . '</a></span>' :
			$connected_badge;
	}

	public static function manage_posts_custom_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case self::META_KEY_LAST_ACTIVITY_AT:
				echo esc_html( Utils::format_elapsed_time( get_post_meta( $post_id, self::META_KEY_LAST_ACTIVITY_AT, true ) ) );
				break;

			case self::COLUMN_STATUS:
				echo wp_kses( self::get_status_badge( $post_id ), array( 'span' => array( 'style' => array() ) ) );
				break;
		}
	}

	public static function register_meta_box(): void {
		wp_enqueue_script( 'qrcode', SMS_GATEWAY_PRESS_URL . '/js/qrcode.min.js', array(), '1.0.0', true );

		add_meta_box(
			'options',
			esc_html__( 'Device Options', 'sms_gateway_press' ),
			array( __CLASS__, 'print_meta_box_content' ),
		);
	}

	public static function ajax_update_sms_gateway_press_device_form(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'update_sms_gateway_press_device_form' ) ) {
			wp_send_json_error( null, 403 );
			wp_die();
		}

		$post = get_post( intval( sanitize_text_field( $_POST['post_id'] ) ) );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			die;
		}

		$data = array(
			'status_badge'                    => self::get_status_badge( $post->ID ),
			'elapsed_time_from_last_activity' => Utils::format_elapsed_time( get_post_meta( $post->ID, self::META_KEY_LAST_ACTIVITY_AT, true ) ),
		);

		wp_send_json( $data );
		wp_die();
	}

	public static function ajax_update_sms_gateway_press_device_list(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'update_sms_gateway_press_device_list' ) ) {
			wp_send_json_error( null, 403 );
			wp_die();
		}

		$id_list = explode( ',', $_POST['id_list'] );
		$result  = array();

		foreach ( $id_list as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || self::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$result[ $post_id ] = array(
				'status_badge'                    => self::get_status_badge( $post->ID ),
				'elapsed_time_from_last_activity' => Utils::format_elapsed_time( get_post_meta( $post->ID, self::META_KEY_LAST_ACTIVITY_AT, true ) ),
			);
		}

		wp_send_json_success( $result );
		wp_die();
	}

	public static function print_meta_box_content(): void {
		$post_id = get_the_ID();

		$token = get_post_meta( $post_id, self::META_KEY_TOKEN, true );

		if ( ! $token ) {
			$token = bin2hex( random_bytes( 16 ) );
		}

		?>
			<script>
				function generateRandomString( length ) {
					let result = '';
					const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
					const charactersLength = characters.length;
					for ( let i = 0; i < length; i++ ) {
						result += characters.charAt( Math.floor( Math.random() * charactersLength ) );
					}
					return result;
				};

				function renewToken() {
					if ( confirm( '<?php echo esc_html__( 'Are you sure you want to renew this token?. The device must be connected again.', 'sms_gateway_press' ); ?>' ) ) {
						document.getElementById( '<?php echo esc_js( self::META_KEY_TOKEN ); ?>' ).value = generateRandomString( 32 );
						showQr();
					}
				}

				function showQr() {
					const element = document.getElementById( 'qrcode' );

					if ( ! element ) {
						return;
					}

					element.innerHTML = "";

					new QRCode( document.getElementById( 'qrcode' ), JSON.stringify( {
						url: '<?php echo esc_url( get_site_url() ); ?>',
						device_id: '<?php echo esc_js( $post_id ); ?>',
						device_token: document.getElementById( '<?php echo esc_js( self::META_KEY_TOKEN ); ?>' ).value,
						request_timeout: <?php echo esc_js( ini_get( 'max_execution_time' ) + 20 ); ?>,
					} ) );
				}

				document.addEventListener( 'DOMContentLoaded', function () {
					showQr();
				} );
			</script>
			<table class="form-table">
				<?php wp_nonce_field( self::NONCE_ACTION_METABOX, self::NONCE_ACTION_METABOX ); ?>
				<body>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_TOKEN ); ?>"><?php echo esc_html__( 'Token', 'sms_gateway_press' ); ?></label></th>
						<td>
							<input
								name="<?php echo esc_attr( self::META_KEY_TOKEN ); ?>"
								type="text"
								id="<?php echo esc_attr( self::META_KEY_TOKEN ); ?>"
								value="<?php echo esc_attr( $token ); ?>"
								class="regular-text"
							>
							<button
								class="button button-secondary"
								type="button"
								onclick="renewToken()"
							><?php echo esc_html__( 'Renew', 'sms_gateway_press' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"><?php echo esc_html__( 'Phone Number', 'sms_gateway_press' ); ?></label></th>
						<td>
							<input
								id="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"
								name="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"
								type="text"
								value="<?php echo esc_attr( get_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, true ) ); ?>"
								class="regular-text"
							>
							<p class="description"><?php echo esc_html__( '(Optional) This is the celular phone number of the device. This value may be specified also in the title.', 'sms_gateway_press' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label><?php echo esc_html__( 'QR Connector', 'sms_gateway_press' ); ?></label></th>
						<td>
							<?php if ( 'publish' === get_post_status( $post_id ) ) : ?>
								<p class="description"><?php echo esc_html__( 'Scan this QR from the Android app to connect this device.', 'sms_gateway_press' ); ?></p>
								<br>
								<div id="qrcode"></div>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'The QR code will be appear when this post is published.', 'sms_gateway_press' ); ?></p>
							<?php endif ?>
						</td>
					</tr>
					<?php if ( 'publish' === get_post_status( $post_id ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Last Activity', 'sms_gateway_press' ); ?></th>
							<td id="last_activity"><?php echo esc_html( Utils::format_elapsed_time( get_post_meta( $post_id, self::META_KEY_LAST_ACTIVITY_AT, true ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Status', 'sms_gateway_press' ); ?></th>
							<td id="status_badge"><?php echo wp_kses( self::get_status_badge( $post_id ), array( 'span' => array( 'style' => array() ) ) ); ?></td>
						</tr>
						<script>
							document.addEventListener( 'DOMContentLoaded', () => {
								setInterval( () => {
									const url = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

									const requestBody = new FormData();
									requestBody.set( 'action', 'update_sms_gateway_press_device_form' );
									requestBody.set( 'nonce', '<?php echo esc_js( wp_create_nonce( 'update_sms_gateway_press_device_form' ) ); ?>' );
									requestBody.set( 'post_id', <?php echo esc_js( $post_id ); ?> );

									const options = {
										method: 'POST',
										body: requestBody,
									};

									const last_activity = document.getElementById( 'last_activity' );
									const status_badge = document.getElementById( 'status_badge' );

									fetch( url, options ).then( response => {
										response.json().then( json => {
											last_activity.innerHTML = json.elapsed_time_from_last_activity;
											status_badge.innerHTML = json.status_badge;
										} );
									} );
								}, 1000 );
							} );
						</script>
					<?php endif ?>
				</body>
			</table>
		<?php
	}
}
