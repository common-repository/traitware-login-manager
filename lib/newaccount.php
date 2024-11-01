<?php
/**
 * New Account
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

// used to complete an ajax call.
function traitware_out( $error, $out ) {
	$out['error'] = $error;
	echo wp_json_encode( $out );
	wp_die();
}

$out = array(
	'error' => '',
);

if ( traitware_is_active() ) {
	traitware_out( 'Account already active. Please log in again.', $out ); }

if ( ! isset( $_REQUEST['_twnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_twnonce'] ), 'traitware_new_account' ) ) {
	traitware_out( 'Invalid request.', $out );
}

$phone = '';
if ( isset( $_POST['phone'] ) ) {
	$phone = trim( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );
}

$the_current_user = wp_get_current_user();
if ( ! ( $the_current_user instanceof WP_User ) ) {
	traitware_out( 'Invalid user. Please log in again.', $out ); }

if ( ! Traitware_API::create_account( $the_current_user, $phone ) ) {
	traitware_out( 'Connection to TraitWare Failed', $out );
	return;
}

traitware_out( '', $out ); // all good
