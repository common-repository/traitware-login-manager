<?php
/**
 * Uninstall
 *
 * @package TraitWare
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}

function traitware_uninstall() {
	global $wpdb;

	// contact TraitWare to do some housekeeping.
	if ( ! empty( get_option( 'traitware_client_id' ) ) ) {
		include __DIR__ . '/vars.php';
		include_once traitware_get_var( 'libpath' ) . 'class-traitware-api.php';

		Traitware_API::deactivate();
	}

	// clean out all the globals.
	delete_option( 'traitware_active' );
	delete_option( 'traitware_client_id' );
	delete_option( 'traitware_client_secret' );
	delete_option( 'traitware_disablewplogin' );
	delete_option( 'traitware_enableonlywplogin' );
	delete_option( 'traitware_alternatelogin' );
	delete_option( 'traitware_version' );
	delete_option( 'traitware_limitaccesspts' );
	delete_option( 'traitware_protectedpageselector' );
	delete_option( 'traitware_protectedpageaccessdeniedmessagetype' );
	delete_option( 'traitware_protectedpageaccessdeniedmessagetext' );
	delete_option( 'traitware_protectedpageaccessdeniedmessagehtml' );
	delete_option( 'traitware_protectedpageaccessdeniedmessagepost' );
	delete_option( 'traitware_customloginredirect' );
	delete_option( 'traitware_disablecustomlogin' );
	delete_option( 'traitware_disablecustomloginform' );
	delete_option( 'traitware_disablecustomloginrecovery' );
	delete_option( 'traitware_enableselfregistration' );
	delete_option( 'traitware_review_notice_start_time' );

	delete_metadata(
		'user',
		0,
		'traitware_scrubrecoverythrottlecounter',
		'',
		true
	);

	delete_metadata(
		'user',
		0,
		'traitware_scrubrecoverythrottletimeout',
		'',
		true
	);

	delete_metadata(
		'user',
		0,
		'traitware_scrubrecoverythrottlelast',
		'',
		true
	);

	delete_metadata(
		'user',
		0,
		'traitware_review_notice_dismissed',
		'',
		true
	);

	// clean out all the tables.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'traitwareusers' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'traitwarelogins' );

	// at this point, nothing of Traitware should be in the filesystem or database.
}

traitware_uninstall();
