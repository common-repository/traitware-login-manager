<?php
/**
 * Pollscan Userlist WpUser
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

$email      = trim( $incoming['userlistdata'][0] );
$emailparts = explode( '@', $email );

$inputerr = '';

// wp core function - validate email
if ( ! is_email( $email ) ) {
	$inputerr = 'Invalid email address[1]'; }

if ( strlen( $inputerr ) === 0 ) {
	// check dns of the email domain. No MX -> invalid for sure
	if ( ! checkdnsrr( $emailparts[1], 'MX' ) ) {
		$inputerr = 'Invalid email provider'; }
}
if ( strlen( $inputerr ) === 0 ) {
	// check db for email
	if ( email_exists( $email ) !== false ) {
		$inputerr = 'Email already in use'; }
}

// username
$username = trim( $incoming['userlistdata'][1] );
$username = sanitize_user( $username );
if ( strlen( $username ) === 0 ) {
	$username = traitware_generate_username( $email ); }
if ( $username === null || username_exists( $username ) !== false ) {
	$inputerr = 'Username already in use';
}

// role
$role     = $incoming['userlistdata'][2]; // string for the role slug
$allroles = get_editable_roles(); // big list of all rolls in the system
if ( ! isset( $allroles[ $role ] ) ) {
	$inputerr = 'Invalid role (' . $role . ')'; }

// usertype
$usertype = $incoming['userlistdata'][3]; // string for the usertype

$isAccountOwner = false;

// if the user is an account owner they can not be a scrub
if ( $role === 'administrator' && $usertype === 'owner' ) {
	$isAccountOwner = true;
	$usertype       = 'dashboard';
}

$currentUserOwner = traitware_isAccountOwner();

if ( ! $currentUserOwner ) {
	$isAccountOwner = false;
}

if ( ! in_array( $usertype, array( 'scrub', 'dashboard' ) ) ) {
	$usertype = 'dashboard';
}

if ( strlen( $inputerr ) > 0 ) {
	$_SESSION['pollscan_userlist_message'] = $inputerr;
} else {

	$userid = wp_insert_user(
		array(
			'user_login' => $username,
			'user_pass'  => wp_generate_password( 20, false ),
			'user_email' => $email,
			'role'       => $role,
		)
	);

	if ( is_wp_error( $userid ) ) {
		$_SESSION['pollscan_userlist_message'] = $userid->get_error_message();
	} else {
		$user = get_user_by( 'id', $userid );

		$error = '';

		$create_result = null;

		if ( $usertype === 'dashboard' ) {
			$create_result = Traitware_API::create_new_user( $user, $userid, $cookies );
		} else {
			$create_result = Traitware_API::create_new_scrub_user( $user, $userid, $cookies );
		}

		if ( ! $create_result ) {
			$error = 'Error contacting TraitWare (2)';
		}

		if ( $isAccountOwner && ! Traitware_API::update_user( $user, $create_result['traitwareid'], $isAccountOwner, $cookies ) ) {
			$error = 'Error contacting TraitWare (3)';
		}

		if ( strlen( $error ) == 0 ) {
			$_SESSION['pollscan_userlist_message']       = 'An activation email was sent to ' . esc_html( $email );
			$_SESSION['pollscan_userlist_openmodaltext'] = 'An activation email was sent to <b>' . esc_html( $email ) . '</b>';
			$_SESSION['pollscan_userlist_openmodaldur']  = '4';
		} else {
			$_SESSION['pollscan_userlist_message'] = $error;
		}
	}
}
