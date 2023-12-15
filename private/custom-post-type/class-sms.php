<?php

namespace SMS_Gateway_Press\Custom_Post_Type;

use DateTime;
use WP_Post;
use SMS_Gateway_Press\Main;

abstract class Sms {

	public const POST_TYPE            = 'smsgp_sms';
	public const DEFAULT_INACTIVITY   = 30; // seconds
	public const COLUMN_STATUS        = 'status';
	public const NONCE_ACTION_METABOX = 'nonce_metabox';

	public const META_BOX_OPTIONS = 'options';
	public const META_BOX_LOGS    = 'logs';

	public const META_KEY_PHONE_NUMBER      = '_phone_number';
	public const META_KEY_TEXT              = '_text';
	public const META_KEY_SEND_AT           = '_send_at';
	public const META_KEY_SENT_AT           = '_sent_at';
	public const META_KEY_DELIVERED_AT      = '_delivered_at';
	public const META_KEY_EXPIRES_AT        = '_expires_at';
	public const META_KEY_INACTIVE_AT       = '_inactive_at';
	public const META_KEY_CONFIRMED_AT      = '_confirmed_at';
	public const META_KEY_TOKEN             = '_token';
	public const META_KEY_LOGS              = '_logs';
	public const META_KEY_SENDING_IN_DEVICE = '_sending_in_device';

	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_QUEUED    = 'queued';
	public const STATUS_SENDING   = 'sending';
	public const STATUS_SENT      = 'sent';
	public const STATUS_DELIVERED = 'delivered';
	public const STATUS_EXPIRED   = 'expired';

	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'on_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
	}

	public static function on_init(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'               => array(
					'name'          => __( 'SMS', 'sms_gateway_press' ),
					'singular_name' => __( 'SMS', 'sms_gateway_press' ),
				),
				'public'               => false,
				'show_ui'              => true,
				'show_in_menu'         => true,
				'supports'             => array( '' ),
				'register_meta_box_cb' => array( __CLASS__, 'register_meta_box' ),
			)
		);
	}

	public static function on_admin_init(): void {
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'on_save_post' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'manage_posts_columns' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'manage_posts_custom_column' ), 10, 2 );
		add_action( 'wp_ajax_update_sms_gateway_press_sms_list', array( __CLASS__, 'ajax_update_sms_gateway_press_sms_list' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
	}

	public static function admin_enqueue_scripts( string $page ): void {
		global $post_type;

		if ( 'edit.php' !== $page || self::POST_TYPE !== $post_type ) {
			return;
		}

		$handle = self::POST_TYPE . '-list-sms';

		wp_enqueue_script( $handle, SMS_GATEWAY_PRESS_URL . '/js/list-sms.js', array(), '1.0.0', true );

		wp_localize_script(
			$handle,
			'app',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'action' => 'update_sms_gateway_press_sms_list',
				'nonce'  => wp_create_nonce( 'update_sms_gateway_press_sms_list' ),
			)
		);
	}

	public static function ajax_update_sms_gateway_press_sms_list(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'update_sms_gateway_press_sms_list' ) ) {
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
				'status'       => self::get_status_badge( $post->ID ),
				'sent_at'      => self::get_list_column_sent_at( $post->ID ),
				'delivered_at' => self::get_list_column_delivered_at( $post->ID ),
			);
		}

		wp_send_json_success( $result );
		wp_die();
	}

	public static function manage_posts_columns( array $columns ): array {
		unset( $columns['date'] );
		unset( $columns['title'] );

		$columns[ self::META_KEY_PHONE_NUMBER ] = esc_html__( 'Target Phone Number', 'sms_gateway_press' );
		$columns[ self::COLUMN_STATUS ]         = esc_html__( 'Status', 'sms_gateway_press' );
		$columns[ self::META_KEY_SEND_AT ]      = esc_html__( 'Send At', 'sms_gateway_press' );
		$columns[ self::META_KEY_EXPIRES_AT ]   = esc_html__( 'Expires At', 'sms_gateway_press' );
		$columns[ self::META_KEY_SENT_AT ]      = esc_html__( 'Sent At', 'sms_gateway_press' );
		$columns[ self::META_KEY_DELIVERED_AT ] = esc_html__( 'Delivered At', 'sms_gateway_press' );

		$columns['author'] = esc_html__( 'Author' );

		return $columns;
	}

	public static function manage_posts_custom_column( string $column, int $post_id ): void {
		$format = 'Y-m-d H:i:s';

		switch ( $column ) {
			case self::META_KEY_PHONE_NUMBER:
				$phone_number = get_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, true );
				?>
					<strong>
						<a class="row-title" href="<?php echo esc_attr( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( $phone_number ); ?></a>
					</strong>
				<?php
				break;

			case self::COLUMN_STATUS:
				echo wp_kses( self::get_status_badge( $post_id ), array( 'span' => array( 'style' => array() ) ) );
				break;

			case self::META_KEY_SEND_AT:
				$send_at = get_post_meta( $post_id, self::META_KEY_SEND_AT, true );

				if ( is_numeric( $send_at ) ) {
					$dt = new DateTime();
					$dt->setTimestamp( $send_at );

					echo esc_html( $dt->format( $format ) );
				}
				break;

			case self::META_KEY_EXPIRES_AT:
				$expires_at = get_post_meta( $post_id, self::META_KEY_EXPIRES_AT, true );

				if ( is_numeric( $expires_at ) ) {
					$dt = new DateTime();
					$dt->setTimestamp( $expires_at );

					echo esc_html( $dt->format( $format ) );
				}
				break;

			case self::META_KEY_SENT_AT:
				echo esc_html( self::get_list_column_sent_at( $post_id ) );
				break;

			case self::META_KEY_DELIVERED_AT:
				echo esc_html( self::get_list_column_delivered_at( $post_id ) );
				break;
		}
	}

	public static function get_list_column_sent_at( $post_id ) {
		$sent_at = get_post_meta( $post_id, self::META_KEY_SENT_AT, true );

		if ( $sent_at ) {
			return Utils::format_elapsed_time( $sent_at );
		}
	}

	public static function get_list_column_delivered_at( $post_id ) {
		$delivered_at = get_post_meta( $post_id, self::META_KEY_DELIVERED_AT, true );

		if ( $delivered_at ) {
			return Utils::format_elapsed_time( $delivered_at );
		}
	}

	public static function get_status_badge( int $post_id ): string {
		$badge = '';

		switch ( self::get_sms_status( $post_id ) ) {
			case self::STATUS_SCHEDULED:
				$badge = '<span style="background-color:orange;color:white;padding:5px;">' . esc_html__( 'Scheduled', 'sms_gateway_press' ) . '</span>';
				break;

			case self::STATUS_QUEUED:
				$badge = '<span style="background-color:royalblue;color:white;padding:5px;">' . esc_html__( 'Queued', 'sms_gateway_press' ) . '</span>';
				break;

			case self::STATUS_SENDING:
				$sending_in_device_id = get_post_meta( $post_id, self::META_KEY_SENDING_IN_DEVICE, true );
				$badge                = '<span style="background-color:darkviolet;color:white;padding:5px;">' . esc_html__( 'Sending', 'sms_gateway_press' ) . ':<a href="' . esc_url( get_edit_post_link( $sending_in_device_id ) ) . '" target="_blank" style="color:white;text-decoration:underline">' . esc_html( $sending_in_device_id ) . '</a></span>';
				break;

			case self::STATUS_SENT:
				$badge = '<span style="background-color:lightgreen;color:#2d2d2d;padding:5px;">' . esc_html__( 'Sent', 'sms_gateway_press' ) . '</span>';
				break;

			case self::STATUS_DELIVERED:
				$badge = '<span style="background-color:green;color:white;padding:5px;">' . esc_html__( 'Delivered', 'sms_gateway_press' ) . '</span>';
				break;

			case self::STATUS_EXPIRED:
				$badge = '<span style="background-color:brown;color:white;padding:5px;">' . esc_html__( 'Expired', 'sms_gateway_press' ) . '</span>';
				break;
		}

		return $badge;
	}

	public static function register_meta_box(): void {
		add_meta_box(
			self::META_BOX_OPTIONS,
			esc_html__( 'SMS Options', 'sms_gateway_press' ),
			array( __CLASS__, 'print_meta_box_content_options' ),
		);

		global $post;

		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$logs = get_post_meta( $post->ID, self::META_KEY_LOGS, true );

		if ( ! $logs || ! is_array( $logs ) ) {
			return;
		}

		add_meta_box(
			self::META_BOX_LOGS,
			esc_html__( 'Logs', 'sms_gateway_press' ),
			array( __CLASS__, 'print_meta_box_content_logs' ),
		);
	}

	public static function on_save_post( int $post_id ): void {
		if ( ! wp_verify_nonce( $_POST[ self::NONCE_ACTION_METABOX ], self::NONCE_ACTION_METABOX ) ) {
			return;
		}

		if ( isset( $_POST[ self::META_KEY_PHONE_NUMBER ] ) ) {
			update_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, sanitize_text_field( $_POST[ self::META_KEY_PHONE_NUMBER ] ) );
		}

		if ( isset( $_POST[ self::META_KEY_TEXT ] ) ) {
			update_post_meta( $post_id, self::META_KEY_TEXT, sanitize_text_field( $_POST[ self::META_KEY_TEXT ] ) );
		}

		if ( isset( $_POST[ self::META_KEY_SEND_AT ] ) ) {
			$send_at_dt = DateTime::createFromFormat( Main::DATETIME_LOCAL_FORMAT, $_POST[ self::META_KEY_SEND_AT ] );

			if ( $send_at_dt ) {
				update_post_meta( $post_id, self::META_KEY_SEND_AT, $send_at_dt->getTimestamp() );
			}
		}

		if ( isset( $_POST[ self::META_KEY_EXPIRES_AT ] ) ) {
			$expires_at_dt = DateTime::createFromFormat( Main::DATETIME_LOCAL_FORMAT, $_POST[ self::META_KEY_EXPIRES_AT ] );

			if ( $expires_at_dt ) {
				update_post_meta( $post_id, self::META_KEY_EXPIRES_AT, $expires_at_dt->getTimestamp() );
			}
		}
	}

	public static function print_meta_box_content_options(): void {
		$post_id = get_the_ID();

		$sent_at           = get_post_meta( $post_id, self::META_KEY_SENT_AT, true );
		$sending_in_device = get_post_meta( $post_id, self::META_KEY_SENDING_IN_DEVICE, true );

		$is_read_only = is_numeric( $sent_at ) || is_numeric( $sending_in_device ) ? true : false;

		$send_at_dt    = new DateTime();
		$expires_at_dt = new DateTime( '+1 hour' );

		$send_at = get_post_meta( $post_id, self::META_KEY_SEND_AT, true );

		if ( is_numeric( $send_at ) ) {
			$send_at_dt->setTimestamp( $send_at );
		}

		$expires_at = get_post_meta( $post_id, self::META_KEY_EXPIRES_AT, true );

		if ( is_numeric( $expires_at ) ) {
			$expires_at_dt->setTimestamp( $expires_at );
		}

		?>
			<?php wp_nonce_field( self::NONCE_ACTION_METABOX, self::NONCE_ACTION_METABOX ); ?>
			<table class="form-table">
				<body>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"><?php echo esc_html__( 'Phone Number', 'sms_gateway_press' ); ?></label></th>
						<td>
							<input
								id="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"
								name="<?php echo esc_attr( self::META_KEY_PHONE_NUMBER ); ?>"
								type="tel"
								value="<?php echo esc_attr( get_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, true ) ); ?>"
								class="regular-text"
								<?php
								if ( $is_read_only ) {
									echo 'disabled';}
								?>
							>
							<p class="description"><?php echo esc_html__( 'This is the phone number that should received the SMS.', 'sms_gateway_press' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_TEXT ); ?>"><?php echo esc_html__( 'Text', 'sms_gateway_press' ); ?></label></th>
						<td>
							<textarea
								name="<?php echo esc_attr( self::META_KEY_TEXT ); ?>"
								id="<?php echo esc_attr( self::META_KEY_TEXT ); ?>"
								rows="5"
								class="regular-text"
								<?php
								if ( $is_read_only ) {
									echo 'disabled';}
								?>
							><?php echo esc_textarea( get_post_meta( $post_id, self::META_KEY_TEXT, true ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_SEND_AT ); ?>"><?php echo esc_html__( 'Send At', 'sms_gateway_press' ); ?></label></th>
						<td>
							<input
								id="<?php echo esc_attr( self::META_KEY_SEND_AT ); ?>"
								name="<?php echo esc_attr( self::META_KEY_SEND_AT ); ?>"
								type="datetime-local"
								value="<?php echo esc_attr( $send_at_dt->format( Main::DATETIME_LOCAL_FORMAT ) ); ?>"
								class="regular-text"
								<?php
								if ( $is_read_only ) {
									echo 'disabled';}
								?>
							>
							<p class="description"><?php echo esc_html__( 'The SMS will be sent after this moment.', 'sms_gateway_press' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::META_KEY_EXPIRES_AT ); ?>"><?php echo esc_html__( 'Expires At', 'sms_gateway_press' ); ?></label></th>
						<td>
							<input
								id="<?php echo esc_attr( self::META_KEY_EXPIRES_AT ); ?>"
								name="<?php echo esc_attr( self::META_KEY_EXPIRES_AT ); ?>"
								type="datetime-local"
								value="<?php echo esc_attr( $expires_at_dt->format( Main::DATETIME_LOCAL_FORMAT ) ); ?>"
								class="regular-text"
								<?php
								if ( $is_read_only ) {
									echo 'disabled';}
								?>
							>
							<p class="description"><?php echo esc_html__( 'After this moment the sending of this SMS will be cancelled.', 'sms_gateway_press' ); ?></p>
						</td>
					</tr>
				</body>
			</table>
		<?php
	}

	public static function print_meta_box_content_logs(): void {
		$post_id = get_the_ID();
		$logs    = get_post_meta( $post_id, self::META_KEY_LOGS, true );

		if ( ! is_array( $logs ) ) {
			return;
		}

		foreach ( $logs as $log ) {
			if ( is_string( $log ) ) {
				echo '<p>' . esc_html( $log ) . '</p>';
			} elseif ( is_array( $log ) && isset( $log['time'] ) && isset( $log['text'] ) ) {
				$time = ( new DateTime() )->setTimestamp( $log['time'] );
				echo esc_attr( '<p><strong>' . $time->format( 'Y-m-d H:i:s' ) . ':</strong>' . $log['text'] . '</p>' );
			}
		}
	}

	public static function add_log( int $post_id, string $log ): void {
		$logs = get_post_meta( $post_id, self::META_KEY_LOGS, true );
		$logs = is_array( $logs ) ? $logs : array();

		$logs[] = array(
			'time' => time(),
			'text' => $log,
		);

		update_post_meta( $post_id, self::META_KEY_LOGS, $logs );
	}

	public static function get_sms_status( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$now          = time();
		$send_at      = get_post_meta( $post_id, self::META_KEY_SEND_AT, true );
		$expires_at   = get_post_meta( $post_id, self::META_KEY_EXPIRES_AT, true );
		$sent_at      = get_post_meta( $post_id, self::META_KEY_SENT_AT, true );
		$delivered_at = get_post_meta( $post_id, self::META_KEY_DELIVERED_AT, true );

		if ( is_numeric( $sent_at ) ) {
			if ( is_numeric( $delivered_at ) ) {
				return self::STATUS_DELIVERED;
			}

			return self::STATUS_SENT;
		}

		if ( $now < $send_at ) {
			return self::STATUS_SCHEDULED;
		}

		if ( $now >= $send_at && $now < $expires_at ) {
			$sending_in_device_id = get_post_meta( $post_id, self::META_KEY_SENDING_IN_DEVICE, true );

			if ( is_numeric( $sending_in_device_id ) ) {
				return self::STATUS_SENDING;
			}

			return self::STATUS_QUEUED;
		}

		if ( $now > $expires_at ) {
			return self::STATUS_EXPIRED;
		}
	}

	public static function get_sms_info( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return array(
			'post_id'           => $post_id,
			'status'            => self::get_sms_status( $post_id ),
			'phone_number'      => get_post_meta( $post_id, self::META_KEY_PHONE_NUMBER, true ),
			'text'              => get_post_meta( $post_id, self::META_KEY_TEXT, true ),
			'send_at'           => get_post_meta( $post_id, self::META_KEY_SEND_AT, true ),
			'delivered_at'      => get_post_meta( $post_id, self::META_KEY_DELIVERED_AT, true ),
			'expires_at'        => get_post_meta( $post_id, self::META_KEY_EXPIRES_AT, true ),
			'sending_in_device' => get_post_meta( $post_id, self::META_KEY_SENDING_IN_DEVICE, true ),
		);
	}
}
