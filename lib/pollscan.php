<?php
/**
 * Pollscan
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! session_id() ) {
	session_start(); }

function traitware_pollscan_error( $error ) {
	Traitware_API::report_error( $error );
	echo wp_json_encode( array( 'error' => $error ) );
	wp_die();
}

if ( ! isset( $_POST['twaction'] ) ) {
	traitware_pollscan_error( 'Pollscan Error: 1' ); }
$action     = trim( sanitize_key( $_POST['twaction'] ) );
$actionlist = array(
	'wplogin',
	'newsite',
	'userlist',
);
if ( ! in_array( $action, $actionlist ) ) {
	traitware_pollscan_error( 'Pollscan Error: 2' ); }

if ( ! isset( $_POST['redirecturi'] ) ) {
	traitware_pollscan_error( 'Pollscan Error: 3' ); }
$redirectUri = $_POST['redirecturi'];
if ( strlen( $redirectUri ) === 0 ) {
	traitware_pollscan_error( 'Pollscan Error: 4' ); }

if ( isset( $_POST['args'] ) ) {
	if ( is_array( $_POST['args'] ) ) { // already json decoded
		$args = $_POST['args'];
	}
}
$args['redirectUri'] = $redirectUri;

// wplogin uses API 2C and should NOT continue to 1B.2
if ( $action == 'wplogin' ) {
	include 'pollscan_wplogin.php';
	$error = traitware_wplogin( $redirectUri );
	echo wp_json_encode(
		array(
			'url'   => get_dashboard_url(),
			'error' => $error,
		)
	);
	wp_die();
}

// -------------------------- API 1B.2 --------------------------
function traitware_consoleLogin( $redirectUri, $action ) {
	$consoleData = Traitware_API::console_login( $redirectUri );

	if ( ! $consoleData ) {
		return false;
	}

	$traitwareUserId = $consoleData['traitwareUserId'];
	$crumb           = $consoleData['crumb'];
	$sidname         = $consoleData['sidname'];
	$sid             = $consoleData['sid'];

	if ( strlen( $traitwareUserId ) === 0 ) {
		return false; }
	if ( strlen( $crumb ) === 0 ) {
		return false; }
	if ( strlen( $sidname ) === 0 ) {
		return false; }
	if ( strlen( $sid ) === 0 ) {
		return false; }

	// validate that traitwareUserId is the correct value for this wp account
	if ( $action == 'wplogin' ) {
		$current_user = wp_get_current_user();
		if ( ! ( $current_user instanceof WP_User ) ) {
			return false;
		}

		global $wpdb;

		$sql = 'SELECT traitwareid FROM ' . $wpdb->prefix . 'traitwareusers WHERE userid = %d';
		$stmt = $wpdb->prepare( $sql, array( $current_user->ID ) );
		$result = $wpdb->get_results( $stmt, OBJECT );

		if ( count( $result ) === 0 ) {
			return false;
		}

		if ( intval( $result[0]->traitwareid ) !== intval( $traitwareUserId ) ) {
			return false;
		}
	}

	if ( $action == 'userlist' ) {
		$current_user = wp_get_current_user();
		if ( ! ( $current_user instanceof WP_User ) ) {
			return false; }
		global $wpdb;
		$result = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					*
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					traitwareid = %s
				AND
				    userid = %d',
				$traitwareUserId,
				$current_user->ID
			),
			OBJECT
		);
		if ( count( $result ) === 0 ) {
			return false; }
		if ( $result[0]->usertype === 'scrub' || ! traitware_isadmin() ) {
			return false; } // can not be a scrub user
	}

	return array(
		'crumb'   => $crumb,
		'sidname' => $sidname,
		'sid'     => $sid,
	);
}
$cookies = traitware_consoleLogin( $redirectUri, $action );
if ( $cookies === false ) {
	traitware_pollscan_error( 'The user scanning must match the user logged in. Please refresh the page and try again.' ); }

// -------------------------- action router --------------------------
require 'pollscan_' . $action . '.php';

echo wp_json_encode( traitware_pollscanAction( $cookies, $args ) );
wp_die();
