<?php
/**
 * Pollscan New site
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

/**
 * @param $cookies
 * @param $incoming
 * @return array
 */
function traitware_pollscanAction( $cookies, $incoming ) {

	$current_user = wp_get_current_user();
	if ( ! ( $current_user instanceof WP_User ) ) {
		return array( 'error' => 'Please log in again' ); }

	// redirectUri has data about the tw account that SCANNED including the email of that tw user
	// if that email doesn't match the current wp user then the api call below should not be allowed to continue
	// $parts['query'] = { code, state, traitwareUserId, emailAddress, clientId }
	$parts     = wp_parse_url( $incoming['redirectUri'] );
	$pairs     = explode( '&', $parts['query'] );
	$scanemail = '';
	foreach ( $pairs as $i ) {
		list($name,$value) = explode( '=', $i, 2 );
		if ( $name == 'emailAddress' ) {
			$scanemail = urldecode(strtolower( trim( $value ) ));
			break;
		}
	}

	$accountemail = strtolower( trim( $current_user->user_email ) );
	if ( $scanemail !== $accountemail ) {
		return array( 'error' => 'The TraitWare account used to scan the QR Code (' . esc_html( $scanemail ) . ') must <b>match</b> the WordPress user (' . esc_html( $accountemail ) . ').<br><br><b>This website has not been activated</b>.<br><br>Please <a href="admin.php?page=traitware-setup">refresh this page</a> and try again.' );
	}

	$createRes = Traitware_API::create_site( $current_user, $cookies );

	if ( array_key_exists( 'error', $createRes ) ) {
		return array( 'error' => $createRes['error'] );
	}

	return array(
		'error' => '',
		'url'   => 'admin.php?page=traitware-welcome',
	);
}
