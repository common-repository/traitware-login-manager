<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

function traitware_recovery( $msg ) {
	$out['msg'] = $msg;
	echo wp_json_encode( $out );
	wp_die();
}

if ( ! isset( $_POST['_twnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_twnonce'] ), 'traitware_ajax' ) ) {
	traitware_recovery( 'Invalid request' );
}

$email = '';
if ( isset( $_POST['email'] ) ) {
	$email = sanitize_email( wp_unslash( $_POST['email'] ) );
}

// check if email is well-formed.
if ( ! is_email( $email ) ) {
	traitware_recovery( 'Invalid email address' );
}

// check dns of the email domain. No MX -> invalid for sure.
$emailparts = explode( '@', $email );
if ( ! checkdnsrr( $emailparts[1], 'MX' ) ) {
	traitware_recovery( 'Invalid email provider' );
}

// check if the email exists in the system.
$user = get_user_by( 'email', $email );
if ( ! ( $user instanceof WP_User ) ) {
	traitware_recovery( 'No user with that email' );
}

global $wpdb;
$sql = 'SELECT id,activeaccount,usertype FROM ' . $wpdb->prefix . 'traitwareusers WHERE userid = %d';
$stmt = $wpdb->prepare( $sql, array( $user->ID ) );
$twuser = $wpdb->get_results(  $stmt, OBJECT );
if ( count( $twuser ) === 0 ) {
	traitware_recovery( 'No TraitWare user with that email' );
}

$twuser = $twuser[0];
if ( intval( $twuser->activeaccount ) === 0 ) {
	traitware_recovery( 'TraitWare user is not active' );
}

if ( $twuser->usertype !== 'scrub' ) {
	traitware_recovery( 'TraitWare user is not allowed to recover here' );
	die();
}

// This is the counter that keeps track of how many recovery attempts have been made
$throttleCounter = (int) get_user_meta( $user->ID, 'traitware_scrubrecoverythrottlecounter', true );
// This is the timeout option which represents a currently active timeout
// It is a UNIX timestamp representing when the timeout is over, NOT a duration
$throttleTimeout = (int) get_user_meta( $user->ID, 'traitware_scrubrecoverythrottletimeout', true );
// This is a UNIX timestamp of the last recovery attempt made
$throttleLast = (int) get_user_meta( $user->ID, 'traitware_scrubrecoverythrottlelast', true );
// The current time
$currentTime = (int) current_time( 'timestamp' );

// If there is a timeout in place and it HAS ended
if ( $throttleTimeout && $currentTime >= $throttleTimeout ) {
	// Reset all the throttle options
	$throttleCounter = 0;
	update_user_meta( $user->ID, 'traitware_scrubrecoverythrottlecounter', 0 );
	$throttleLast = 0;
	delete_user_meta( $user->ID, 'traitware_scrubrecoverythrottlelast' );
	delete_user_meta( $user->ID, 'traitware_scrubrecoverythrottletimeout' );
} elseif ( $throttleTimeout && $currentTime < $throttleTimeout ) {
	// If timeout has not ended, kill execution
	traitware_recovery( 'Too many recovery attempts have been made' );
	die();
}

// If there was a recovery attempt made within the timeout period (ex. 24 hours)
if ( $throttleLast && $throttleLast + traitware_get_var( 'scrubRecoveryTimeout' ) >= $currentTime ) {
	// If the counter exceeds the limit
	if ( $throttleCounter + 1 >= traitware_get_var( 'scrubRecoveryLimit' ) ) {
		// Create a timeout
		update_user_meta(
			$user->ID,
			'traitware_scrubrecoverythrottletimeout',
			$currentTime + traitware_get_var( 'scrubRecoveryTimeout' )
		);
	} else {
		// Increment the counter, record this attempt
		update_user_meta(
			$user->ID,
			'traitware_scrubrecoverythrottlecounter',
			$throttleCounter + 1
		);
		update_user_meta(
			$user->ID,
			'traitware_scrubrecoverythrottlelast',
			$currentTime
		);
	}
} else {
	// There has never been an attempt made, or the last attempt made exceeds the timeout period (ex. 24 hours)
	update_user_meta(
		$user->ID,
		'traitware_scrubrecoverythrottlecounter',
		1
	);
	update_user_meta(
		$user->ID,
		'traitware_scrubrecoverythrottlelast',
		$currentTime
	);
}

$recoveryHash = hash( 'sha256', wp_rand() );

$path = '';
if ( isset( $_POST['path'] ) ) {
	$path = trim( $_POST['path'] ); } // passed in from js
if ( strlen( $path ) > 0 ) {
	$path = explode( '?', $path );
	$path = $path[0];
}
if ( strlen( $path ) === 0 ) { // backup method if js is not working
	$path = trim( get_option( 'traitware_alternatelogin', '' ) );
	if ( strlen( $path ) === 0 ) {
		$path = 'wp-login.php'; }
	$path = get_site_url() . '/' . $path;
}

$recoveryRes = Traitware_API::recovery_scrub( $twuser, $email, $path, $recoveryHash );

if ( array_key_exists( 'error', $recoveryRes ) ) {
	traitware_recovery( $recoveryRes['error'] );
	die( '' );
}

traitware_recovery( 'Please check your email and click the recovery link.' );
