<?php
/**
 * Admin notices for Multi Page Auto Advance.
 *
 * Rules:
 * - The "Gravity Forms is missing" notice shows only on the Plugins screen.
 * - Every other notice shows only on Gravity Forms screens, never site-wide.
 * - At most one notice is shown at a time.
 * - Dismissals are stored per user and are permanent ("Maybe later" snoozes the review ask for 30 days).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AAFG_NOTICE_META', 'aafg_notice_state' );
define( 'AAFG_INSTALLED_OPTION', 'aafg_installed_at' );
define( 'AAFG_REVIEW_URL', 'https://wordpress.org/support/plugin/auto-advance-for-gravity-forms/reviews/#new-post' );
define( 'AAFG_PRICING_URL', 'https://www.multipagepro.com/pricing/?utm_source=wp-admin&utm_medium=notice&utm_campaign=free-upgrade' );

/**
 * Record when the plugin was first seen so notices can wait before asking for anything.
 */
function aafg_record_install_time() {
	if ( false === get_option( AAFG_INSTALLED_OPTION ) ) {
		add_option( AAFG_INSTALLED_OPTION, time(), '', false );
	}
}
add_action( 'admin_init', 'aafg_record_install_time' );

function aafg_days_installed() {
	$installed = (int) get_option( AAFG_INSTALLED_OPTION, time() );
	return ( time() - $installed ) / DAY_IN_SECONDS;
}

function aafg_get_notice_state( $notice_id ) {
	$state = get_user_meta( get_current_user_id(), AAFG_NOTICE_META, true );
	return ( is_array( $state ) && isset( $state[ $notice_id ] ) ) ? $state[ $notice_id ] : null;
}

function aafg_set_notice_state( $notice_id, $value ) {
	$user_id = get_current_user_id();
	$state   = get_user_meta( $user_id, AAFG_NOTICE_META, true );
	if ( ! is_array( $state ) ) {
		$state = array();
	}
	$state[ $notice_id ] = $value;
	update_user_meta( $user_id, AAFG_NOTICE_META, $state );
}

/** A notice is hidden once it is marked done, or while a snooze timestamp is in the future. */
function aafg_notice_is_hidden( $notice_id ) {
	$state = aafg_get_notice_state( $notice_id );
	if ( 'done' === $state ) {
		return true;
	}
	return is_numeric( $state ) && (int) $state > time();
}

function aafg_is_gravity_forms_screen() {
	return class_exists( 'GFForms' ) && method_exists( 'GFForms', 'is_gravity_page' ) && GFForms::is_gravity_page();
}

function aafg_is_free_user() {
	return function_exists( 'aafgf_fs' ) && aafgf_fs()->is_not_paying();
}

/**
 * Pick the single notice (if any) to show on this screen.
 */
function aafg_current_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! aafg_is_gravity_forms_screen() ) {
		return null;
	}

	$days = aafg_days_installed();

	if ( $days >= 14 && ! aafg_notice_is_hidden( 'review' ) ) {
		return 'review';
	}

	if ( $days >= 3 && aafg_is_free_user() && ! aafg_notice_is_hidden( 'upgrade' ) ) {
		return 'upgrade';
	}

	return null;
}

function aafg_render_admin_notices() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
		if ( $screen && 'plugins' === $screen->id && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Multi Page Auto Advance needs Gravity Forms installed and active.', 'gf-autoadvanced' ) . '</p></div>';
		}
		return;
	}

	$notice = aafg_current_notice();
	if ( ! $notice ) {
		return;
	}

	if ( 'review' === $notice ) {
		$message = __( 'Is Multi Page Auto Advance saving your visitors some clicks? A quick review on WordPress.org helps other Gravity Forms users find it.', 'gf-autoadvanced' );
		$actions = array(
			array( 'done', __( 'Leave a review', 'gf-autoadvanced' ), AAFG_REVIEW_URL, 'button button-primary' ),
			array( 'snooze', __( 'Maybe later', 'gf-autoadvanced' ), '', 'button' ),
			array( 'done', __( 'I already did', 'gf-autoadvanced' ), '', 'button-link' ),
		);
	} else {
		$message = __( 'Need auto-advance on checkboxes, text and number fields, conditional logic, or hidden Next buttons? Pro and Plus add them.', 'gf-autoadvanced' );
		$actions = array(
			array( 'done', __( 'Compare plans', 'gf-autoadvanced' ), AAFG_PRICING_URL, 'button button-primary' ),
			array( 'done', __( 'No thanks', 'gf-autoadvanced' ), '', 'button-link' ),
		);
	}

	// Gravity Forms hides notices on its screens unless they carry the gf-notice class.
	printf( '<div class="notice notice-info gf-notice aafg-notice" data-notice="%s"><p>%s</p><p>', esc_attr( $notice ), esc_html( $message ) );
	foreach ( $actions as $action ) {
		list( $state, $label, $url, $class ) = $action;
		printf(
			'<a href="%s" class="%s" data-aafg-action="%s"%s style="margin-right:8px">%s</a>',
			esc_url( $url ? $url : '#' ),
			esc_attr( $class ),
			esc_attr( $state ),
			$url ? ' target="_blank" rel="noopener noreferrer"' : '',
			esc_html( $label )
		);
	}
	echo '</p></div>';

	add_action( 'admin_footer', 'aafg_print_notice_script' );
}
add_action( 'admin_notices', 'aafg_render_admin_notices' );

function aafg_print_notice_script() {
	?>
	<script>
	( function () {
		var notice = document.querySelector( '.aafg-notice' );
		if ( ! notice ) { return; }
		notice.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-aafg-action]' );
			if ( ! link ) { return; }
			if ( link.getAttribute( 'href' ) === '#' ) { event.preventDefault(); }
			var body = new FormData();
			body.append( 'action', 'aafg_notice_action' );
			body.append( 'notice', notice.getAttribute( 'data-notice' ) );
			body.append( 'state', link.getAttribute( 'data-aafg-action' ) );
			body.append( '_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'aafg_notice' ) ); ?> );
			fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: body } );
			notice.parentNode.removeChild( notice );
		} );
	} )();
	</script>
	<?php
}

function aafg_ajax_notice_action() {
	check_ajax_referer( 'aafg_notice' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	$notice = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';
	$state  = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';

	if ( ! in_array( $notice, array( 'review', 'upgrade' ), true ) || ! in_array( $state, array( 'done', 'snooze' ), true ) ) {
		wp_send_json_error( null, 400 );
	}

	aafg_set_notice_state( $notice, 'snooze' === $state ? time() + 30 * DAY_IN_SECONDS : 'done' );
	wp_send_json_success();
}
add_action( 'wp_ajax_aafg_notice_action', 'aafg_ajax_notice_action' );
