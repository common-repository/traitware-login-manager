<?php
/**
 * Recovery
 *
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
	$email = sanitize_email( wp_unslash( $_POST['email'] ) ); }

// check if email is well-formed
if ( ! is_email( $email ) ) {
	traitware_recovery( 'Invalid email address' ); }

// check dns of the email domain. No MX -> invalid for sure
$emailparts = explode( '@', $email );
if ( ! checkdnsrr( $emailparts[1], 'MX' ) ) {
	traitware_recovery( 'Invalid email provider' ); }

// does this email exist in the system?
$user = get_user_by( 'email', $email );
if ( ! ( $user instanceof WP_User ) ) {
	traitware_recovery( 'No user with that email' ); }

global $wpdb;
$sql = 'SELECT id,activeaccount,accountowner FROM ' . $wpdb->prefix . 'traitwareusers WHERE userid = %d';
$stmt = $wpdb->prepare( $sql, array( $user->ID ) );
$twuser = $wpdb->get_results( $stmt, OBJECT );
if ( count( $twuser ) === 0 ) {
	traitware_recovery( 'No TraitWare user with that email' );
}
$twuser = $twuser[0];
if ( $twuser->activeaccount === 0 ) {
	traitware_recovery( 'TraitWare user is not active' ); }
if ( $twuser->accountowner != 1 ) {
	traitware_recovery( 'TraitWare user is not allowed to recover here' );
	die(); }

$recoveryHash = hash( 'sha256', wp_rand() );

$path = '';
if ( isset( $_POST['path'] ) ) {
	$path = trim( wp_unslash( $_POST['path'] ) ); } // passed in from js.
if ( strlen( $path ) > 0 ) {
	$path = explode( '?', $path );
	$path = $path[0];
}
if ( strlen( $path ) === 0 ) { // backup method if js is not working.
	$path = trim( get_option( 'traitware_alternatelogin', '' ) );
	if ( strlen( $path ) === 0 ) {
		$path = 'wp-login.php'; }
	$path = get_site_url() . '/' . $path;
}

$recoveryRes = Traitware_API::recovery( $twuser, $email, $path, $recoveryHash );

if ( array_key_exists( 'error', $recoveryRes ) ) {
	traitware_recovery( $recoveryRes['error'] );
	die( '' );
}

traitware_recovery( 'Please check your email and click the recovery link.' );
