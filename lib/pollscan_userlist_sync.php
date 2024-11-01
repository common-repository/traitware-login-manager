<?php
/**
 * Pollscan Userlist Sync
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

$args['method'] = 'GET';
$args['body']   = wp_json_encode( $data );

$response = wp_remote_request(
	traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_users' ),
	$args
);

file_put_contents( '/home/sendpdf/public_html/wp-content/plugins/traitware-login-manager/lib/response.txt', var_export( $response, true ) );
die();

$body = json_decode( $response['body'], true );
if (
	is_null( $body ) ||
	$response['response']['code'] != 200
) {
	return 'Please log in again (' . $response['response']['code'] . ')'; }

global $wpdb;
$db_users  = $wpdb->prefix . 'traitwareusers';
$db_logins = $wpdb->prefix . 'traitwarelogins';

// go through each record in the traitwareusers table and see if it still is active(del if not) and if accountowner
$results = $wpdb->get_results( 'SELECT id,userid,traitwareid,accountowner FROM ' . $db_users, OBJECT );
for ( $n = 0;$n < count( $results );$n++ ) {
	// find index in twusers
	$twindex = -1;
	for ( $t = 0;$t < count( $twusers );$t++ ) {
		if ( $results[ $n ]->traitwareid == $twusers[ $t ]['traitwareUserId'] ) {
			$twindex = $t;
			break; }
	}
	// match twuser rec to wpuser
	$user = get_userdata( (int) $results[ $n ]->userid );
	if ( ! ( $user instanceof WP_User ) ) {
		$twindex = -1; }

	if ( $twindex == -1 ) { // not found >> delete from db
		$wpdb->delete(
			$db_logins,
			array( 'twuserid' => $results[ $n ]->id )
		);
		$wpdb->delete(
			$db_users,
			array( 'id' => $results[ $n ]->id )
		);
		continue;
	}

	// check for if firstname/lastname/email/meta has changed for the wp user. If ANY change then re-sync with the tw server for that user
	$user_firstname = $user->user_firstname;
	if ( is_null( $user_firstname ) ) {
		$user_firstname = ''; }
	$user_lastname = $user->user_lastname;
	if ( is_null( $user_lastname ) ) {
		$user_lastname = ''; }
	$accountowner = false;
	if ( $results[ $n ]->accountowner == 1 ) {
		$accountowner = true; }
	$wordpressUserMeta = traitware_getUserMeta( (int) $results[ $n ]->userid );

	$update = false;
	if ( $twusers[ $t ]['firstName'] != $user_firstname ) {
		$update = true; }
	if ( $twusers[ $t ]['lastName'] != $user_lastname ) {
		$update = true; }
	if ( $twusers[ $t ]['emailAddress'] != $user->user_email ) {
		$update = true; }
	if ( $twusers[ $t ]['isAccountOwner'] != $accountowner ) {
		$update = true; }
	if ( $twusers[ $t ]['wordpressUserMeta'] != $wordpressUserMeta ) {
		$update = true; }

	if ( $update ) {
		$data = array(
			'firstName'         => $user_firstname,
			'lastName'          => $user_lastname,
			'emailAddress'      => $user->user_email,
			'mobilePhone'       => '',
			'isAccountOwner'    => $accountowner,
			'userName'          => $user->user_login,
			'wordpressUserMeta' => $wordpressUserMeta,
		);

		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/users',
			$args
		);

		$body = json_decode( $response['body'], true );
		if (
			is_null( $body ) ||
			$response['response']['code'] != 200
		) {
			continue; }
	}
}

// go through response array and see if TW console has added wp accounts -> create traitwareusers rec
for ( $t = 0;$t < count( $twusers );$t++ ) {
	global $wpdb;
	$results = $wpdb->get_results(
		$wpdb->prepare(
			'
		SELECT
			id
		FROM
			' . $db_users . '
		WHERE
			traitwareid = %s',
			$twusers[ $t ]['traitwareUserId']
		),
		OBJECT
	);
	if ( count( $results ) == 0 ) {
		// look for a username currently in the wpusers table
		$user = get_user_by( 'login', $twusers[ $t ]['userName'] );
		if ( ! ( $user instanceof WP_User ) ) {
			// nothing there. Make one and assign to $user
		}
		// add new rec in traitwareusers
		// do not merge wordpressUserMeta here
		$accountowner = 0;
		if ( $twusers[ $t ]['isAccountOwner'] ) {
			$accountowner = 1; }
		$user     = array(
			'userid'        => $user->ID,
			'traitwareid'   => $twusers[ $t ]['traitwareUserId'],
			'activeaccount' => 1,
			'accountowner'  => $accountowner,
			'recoveryhash'  => '',
			'params'        => '{}',
		);
		$twuserid = traitware_adduser( $user ); // general.php
	}
}

return '';
