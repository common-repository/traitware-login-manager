<?php
/**
 * Pollscan WpLogin
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

function traitware_auth_cookie_expiration( $seconds, $userid, $remember ) {
	return 2 * 7 * 24 * 60 * 60; // two weeks
}

// used in both pollscan.php and also fromend.php for direct login
function traitware_wplogin( $redirectUri ) {
	$token_error = Traitware_API::token( $redirectUri );
	if ( is_string( $token_error ) ) {
		return $token_error;
	}

	$parts = wp_parse_url( $redirectUri );
	wp_parse_str( $parts['query'], $uridata );

	// find user by traitwareUserId
	global $wpdb;
	$results = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
			*
		FROM
			' . $wpdb->prefix . 'traitwareusers
		WHERE
			traitwareid = %s',
			trim( $uridata['traitwareUserId'] )
		),
		OBJECT
	);

	if ( count( $results ) === 0 ) {
		return 'You do not have a TraitWare account on this site.'; }
	if ( $results[0]->activeaccount === 0 ) {
		return 'Your TraitWare account is not active.'; }
	$wpuserid = $results[0]->userid;

	$user = get_user_by( 'id', $wpuserid );
	if ( ! $user ) {
		return 'You do not have an account on this site.'; }

	if ( ! Traitware_API::update_site_user( $wpuserid ) ) {
		return 'Connection to TraitWare failed (4)';
	}

	// set cookie expitation to two weeks for TraitWare logins only. Leave normal logins alone
	add_filter( 'auth_cookie_expiration', 'traitware_auth_cookie_expiration', 99, 3 );

	// log into wp
	wp_set_current_user( $wpuserid, $user->user_login );
	wp_set_auth_cookie( $wpuserid );
	do_action( 'wp_login', $user->user_login, $user );

	// remove the above cookie filter after login
	remove_filter( 'auth_cookie_expiration', 'traitware_auth_cookie_expiration', 99 );

	traitware_addlogin( $results[0]->id ); // log action

	return '';
}
