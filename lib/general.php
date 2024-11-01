<?php
/**
 * General functionality.
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

// user must be a full administrator to make any backend changes
function traitware_isadmin() {
	$user = wp_get_current_user();
	if ( in_array( 'administrator', $user->roles, true ) ) {
		return true; }
	return false;
}

function traitware_isAllowedToChangeStuff() {
	$user = wp_get_current_user();
	if ( ! $user || ! $user->ID ) {
		return false;
	}
	$details = traitware_getUserDetails( $user->ID );
	if ( in_array( 'administrator', $user->roles ) && $details->usertype === 'dashboard' ) {
		return true; }
	return false;
}

function traitware_isbackendlogin() {
	if ( $GLOBALS['pagenow'] === 'wp-login.php' ) {
		return true; }
	if ( $_SERVER['PHP_SELF'] == '/wp-login.php' ) {
		return true; }
	// if (in_array(str_replace(array('\\','/'), DIRECTORY_SEPARATOR, ABSPATH) . 'wp-login.php', get_included_files())) { return true; } // does not work in do_action('init') so don't use for now
	// Many plugins will change the url for the login page by including wp-login.php under a different url and then 404'ing /wp-login.php or /wp-admin
	$traitware_alternatelogin = trim( get_option( 'traitware_alternatelogin', '' ), '/\\' ); // from the tw settings page
	if ( strlen( $traitware_alternatelogin ) > 0 ) {
		$ruri = explode( '?', trim( $_SERVER['REQUEST_URI'], '/\\' ) );
		if ( $ruri[0] == $traitware_alternatelogin ) {
			return true; }
	}

	// Compatibility fix for "All In One WP Security & Firewall"
	// https://wordpress.org/plugins/all-in-one-wp-security-and-firewall/
	// This plugin recreates wp-login.php entirely so we need to check for it.
	global $aio_wp_security;
	if ( isset( $aio_wp_security ) ) {
		if ( $aio_wp_security->configs->get_value( 'aiowps_enable_rename_login_page' ) == '1' ) {
			$login      = home_url( $aio_wp_security->configs->get_value( 'aiowps_login_page_slug' ), 'relative' );
			$parsed_url = wp_parse_url( $_SERVER['REQUEST_URI'] );
			if ( untrailingslashit( $parsed_url['path'] ) === $login ) {
				return true; }
		}
	}

	return false;
}

// create or update the schema (occurs when plugin activates or plugin version changes)
function traitware_createdbtables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// grab the charset collate
	$charset_collate = $wpdb->get_charset_collate();

	// the users table
	$userstable = $wpdb->prefix . 'traitwareusers';
	dbDelta(
		'
        CREATE TABLE ' . $userstable . ' (
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            userid int(10) UNSIGNED NOT NULL,
            traitwareid varchar(100) NOT NULL,
            activeaccount tinyint(3) UNSIGNED NOT NULL,
            accountowner tinyint(3) UNSIGNED NOT NULL,
            usertype varchar(32) NULL DEFAULT NULL,
            recoveryhash tinytext NOT NULL,
            params text NOT NULL,
            UNIQUE KEY id (id),
            UNIQUE KEY traitwareid (traitwareid)
        ) ' . $charset_collate . ';
    '
	);

	// column type check
	$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$userstable}` WHERE `Field` = 'traitwareid'", OBJECT );

	// change the column type if it is not varchar
	if ( ! empty( $columns ) && strpos( strtolower( $columns[0]->Type ), 'tinytext' ) !== false ) {
		$wpdb->query( "ALTER TABLE `{$userstable}` MODIFY `traitwareid` VARCHAR(100) NOT NULL" );
	}

	// index check
	$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$userstable}` WHERE `Key_name` = 'traitwareid'" );

	// add the unique index if it doesn't exist
	if ( empty( $indexes ) ) {
		$wpdb->query( "ALTER TABLE `{$userstable}` ADD UNIQUE INDEX `traitwareid` (`traitwareid`)" );
	}

	// added each time a user actually scans a qr code
	$loginstable = $wpdb->prefix . 'traitwarelogins';
	dbDelta(
		'
        CREATE TABLE ' . $loginstable . ' (
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            twuserid int(10) UNSIGNED NOT NULL,
            logintime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY id (id)
        ) ' . $charset_collate . ';
    '
	);

	$approvalstable = $wpdb->prefix . 'traitwareapprovals';
	dbDelta(
		'
        CREATE TABLE ' . $approvalstable . ' (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `form_id` int(10) UNSIGNED NOT NULL,
        `user_id` int(10) UNSIGNED NOT NULL,
        `approval_hash` varchar(255) NOT NULL,
        `datetime` datetime,
        UNIQUE KEY id (`id`),
        UNIQUE KEY form_user (`form_id`, `user_id`)
        ) ' . $charset_collate . ';
    '
	);
}

function traitware_adduser( $user ) {

	global $wpdb;
	$db_users  = $wpdb->prefix . 'traitwareusers';
	$db_logins = $wpdb->prefix . 'traitwarelogins';

	// remove all existing for that wp user
	$results = $wpdb->get_results( 'SELECT id FROM ' . $db_users . ' WHERE userid = ' . (int) $user['userid'], OBJECT );
	for ( $n = 0;$n < count( $results );$n++ ) {
		$wpdb->delete(
			$db_logins,
			array( 'twuserid' => $results[ $n ]->id )
		);
		$wpdb->delete(
			$db_users,
			array( 'id' => $results[ $n ]->id )
		);
	}

	// add to table
	$data   = array(
		'userid'        => (int) $user['userid'],
		'traitwareid'   => $user['traitwareid'],
		'activeaccount' => (int) $user['activeaccount'],
		'accountowner'  => (int) $user['accountowner'],
		'recoveryhash'  => $user['recoveryhash'],
		'usertype'      => $user['usertype'],
		'params'        => $user['params'],
	);
	$format = array(
		'%d',
		'%s',
		'%d',
		'%d',
		'%s',
		'%s',
		'%s',
	);
	$wpdb->insert(
		$db_users,
		$data,
		$format
	);
	return $wpdb->insert_id;
}

function traitware_addusers( $users ) {

	global $wpdb;
	$db_users = $wpdb->prefix . 'traitwareusers';

	$values        = array();
	$place_holders = array();

	$query = "INSERT INTO $db_users (userid, traitwareid, activeaccount, accountowner, recoveryhash, params, usertype) VALUES ";

	foreach ( $users as $user ) {
		array_push(
			$values,
			(int) $user['userid'],
			$user['traitwareid'],
			(int) $user['activeaccount'],
			(int) $user['accountowner'],
			$user['recoveryhash'],
			$user['params'],
			$user['usertype']
		);

		$place_holders[] = '(%d, %s, %d, %d, %s, %s, %s)';
	}

	$query .= implode( ', ', $place_holders );
	$result = $wpdb->query( $wpdb->prepare( $query, $values ) );

	return true;
}

function traitware_addlogin( $twuserid ) {
	global $wpdb;
	$db_users  = $wpdb->prefix . 'traitwareusers';
	$db_logins = $wpdb->prefix . 'traitwarelogins';

	$sql = 'SELECT `id` FROM ' . $db_users . ' WHERE `id` = %d';
	$stmt = $wpdb->prepare( $sql, array( intval( $twuserid ) ) );
	$results = $wpdb->get_results( $stmt, OBJECT );
	if ( count( $results ) == 0 ) {
		return; }

	// add to traitwarelogins table
	$data   = array(
		'twuserid' => (int) $twuserid,
	);
	$format = array(
		'%d',
	);
	$wpdb->insert(
		$db_logins,
		$data,
		$format
	);
}

/**
 * Return a list of scrub users that are duplicated.
 *
 * @return object[]
 */
function traitware_get_dupe_scrub_users() {
	global $wpdb;
	$db_users = $wpdb->prefix . 'traitwareusers';

	$query = "
    SELECT `t`.`traitwareid` as `traitwareid`, `t`.`userid` as `userid` FROM `$db_users` `u`
LEFT JOIN `$db_users` `t` ON `t`.`userid` = `u`.`userid` AND `t`.`usertype` = 'scrub'
WHERE `u`.`usertype` = 'dashboard' AND `t`.`id` IS NOT NULL
    ";

	$results = $wpdb->get_results( $query, OBJECT );
	return $results;
}

function traitware_deluser( $wpid, $isScrub ) {
	global $wpdb;
	$usertype_part = $isScrub ?
		"usertype = 'scrub'" : "usertype != 'scrub'";

	$results = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT
			id
		FROM
			' . $wpdb->prefix . 'traitwareusers
		WHERE
			userid = %d AND ' . $usertype_part,
			(int) $wpid
		),
		OBJECT
	);
	if ( count( $results ) == 0 ) {
		return 0; }
	$twuserid = $results[0]->id;

	// remove logins
	$wpdb->delete(
		$wpdb->prefix . 'traitwarelogins',
		array( 'twuserid' => $twuserid )
	);
	// remove user
	$wpdb->delete(
		$wpdb->prefix . 'traitwareusers',
		array( 'id' => $twuserid )
	);
}

function traitware_get_wpids_by_emails( $emails ) {
	global $wpdb;
	$in_statement = traitware_sql_in_statement( $emails );

	if ( $in_statement === '' ) {
		return array();
	}

	$users_table = $wpdb->prefix . 'users';

	return $wpdb->get_results( "SELECT `ID`, `user_email` FROM `$users_table` WHERE `user_email` {$in_statement}", OBJECT );
}

function traitware_get_wpids_by_traitwareids( $traitwareids ) {
	global $wpdb;
	$in_statement = traitware_sql_in_statement( $traitwareids );

	if ( $in_statement === '' ) {
		return array();
	}

	$users_table = $wpdb->prefix . 'traitwareusers';

	return $wpdb->get_results( "SELECT `userid` as `ID`, `traitwareid` as `traitwareid` FROM `$users_table` WHERE `traitwareid` {$in_statement}", OBJECT );
}

function traitware_generate_username( $email ) {
	$emailparts = explode( '@', $email );
	$namebase   = substr( $emailparts[0], 0, 55 );

	if ( ! username_exists( $namebase ) ) {
		return $namebase;
	}

	for ( $d = 1; $d < 100; $d++ ) {
		if ( ! username_exists( $namebase . $d ) ) {
			return $namebase . $d;
		}
	}

	return null;
}

function traitware_create_or_update_traitwareusers( $traitwareusers ) {
	global $wpdb;

	$db_users = $wpdb->prefix . 'traitwareusers';

	if ( empty( $traitwareusers ) ) {
		return;
	}

	$insert_query = "INSERT INTO `{$db_users}` (`userid`, `traitwareid`, `activeaccount`, `accountowner`, `recoveryhash`, `params`, `usertype`) VALUES ";
	$is_first     = true;

	foreach ( $traitwareusers as $traitwareuser ) {
		if ( ! $is_first ) {
			$insert_query .= ',';
		}

		$insert_query .= "('" . esc_sql( $traitwareuser['userid'] ) . "', '" . esc_sql( $traitwareuser['traitwareid'] ) . "', ";
		$insert_query .= "'" . esc_sql( $traitwareuser['activeaccount'] ) . "', '" . esc_sql( $traitwareuser['accountowner'] ) . "', ";
		$insert_query .= "'" . esc_sql( $traitwareuser['recoveryhash'] ) . "', '" . esc_sql( $traitwareuser['params'] ) . "', ";
		$insert_query .= "'" . esc_sql( $traitwareuser['usertype'] ) . "')";

		$is_first = false;
	}

	$insert_query .= ' ON DUPLICATE KEY UPDATE `userid` = VALUES(`userid`), `activeaccount` = VALUES(`activeaccount`), ';
	$insert_query .= '`accountowner` = VALUES(`accountowner`), `usertype` = VALUES(`usertype`)';

	$wpdb->query( $insert_query );
}

function traitware_sql_in_statement( $items ) {
	$in_statement = '';
	$is_first     = true;

	foreach ( $items as $item_single ) {
		if ( $item_single === false || $item_single === null ) {
			continue;
		}

		if ( ! $is_first ) {
			$in_statement .= ',';
		} else {
			$in_statement .= 'IN(';
		}

		$in_statement .= "'" . esc_sql( $item_single ) . "'";
		$is_first      = false;
	}

	if ( ! $is_first ) {
		$in_statement .= ')';
	}

	return $in_statement;
}

function traitware_del_dash_users_by_traitwareids_not( $traitwareids ) {
	global $wpdb;

	// table names for queries below
	$db_users  = $wpdb->prefix . 'traitwareusers';
	$db_logins = $wpdb->prefix . 'traitwarelogins';

	$and_statement_users  = '';
	$and_statement_logins = '';

	$in_statement = traitware_sql_in_statement( $traitwareids );

	if ( $in_statement !== '' ) {
		$and_statement_users  = " AND `traitwareid` NOT {$in_statement}";
		$and_statement_logins = " AND `u`.`traitwareid` NOT {$in_statement}";
	}

	$user_query = "DELETE FROM $db_users WHERE `usertype` = 'dashboard'{$and_statement_users}";
	$wpdb->query( $user_query );

	$login_query = "DELETE `l` FROM `$db_logins` `l` LEFT JOIN `$db_users` `u` ON `u`.`id` = `l`.`twuserid` WHERE `u`.`usertype` = 'dashboard'{$and_statement_logins}";
	$wpdb->query( $login_query );
}

function traitware_create_or_update_wp_user( $user_single, $wpids_by_traitwareids, $wpids_by_emails, $create_role ) {
	$wp_user = null;

	// try to find the existing user by the traitwareusers relationship
	foreach ( $wpids_by_traitwareids as $wpid_info ) {
		if ( strtolower( $wpid_info->traitwareid ) !== strtolower( $user_single['traitwareUserId'] ) ) {
			continue;
		}

		$wp_user = get_user_by( 'id', (int) $wpid_info->ID );
		break;
	}

	// if we still have not loaded a valid wp user. we need to search by email
	if ( ! traitware_is_wp_user( $wp_user ) ) {
		// try to find the existing user by the email address relationship
		foreach ( $wpids_by_emails as $wpid_info ) {
			if ( strtolower( $wpid_info->user_email ) !== strtolower( $user_single['emailAddress'] ) ) {
				continue;
			}

			$wp_user = get_user_by( 'id', (int) $wpid_info->ID );
			break;
		}
	}

	if ( ! traitware_is_wp_user( $wp_user ) ) {
		$role = null;

		if ( $create_role !== null && $create_role !== false && trim( $create_role ) !== '' ) {
			$role = trim( $create_role );
		}

		if ( $user_single['isAccountOwner'] ) {
			$role = 'administrator';
		}

		$wp_user = traitware_create_wp_user(
			$user_single['emailAddress'],
			$user_single['firstName'],
			$user_single['lastName'],
			$role
		);
	} else {
		traitware_update_wp_user(
			$wp_user,
			$user_single['emailAddress'],
			$user_single['firstName'],
			$user_single['lastName']
		);
	}

	return $wp_user;
}

function traitware_get_wpids_for_bulksync( $offset ) {
	global $wpdb;
	$offset = (int) $offset;

	if ( $offset < 0 ) {
		$offset = 0;
	}

	$users_table = $wpdb->prefix . 'users';
	$db_users    = $wpdb->prefix . 'traitwareusers';

	$page_size = traitware_get_var( 'bulksyncPageLimit' );

	return $wpdb->get_results(
		"SELECT `u`.`ID` FROM `{$users_table}` `u` LEFT JOIN `{$db_users}` `t` ON `t`.`userid` = `u`.`ID` " .
		"WHERE `t`.`id` IS NULL AND `u`.`ID` > {$offset} ORDER BY `u`.`ID` ASC LIMIT {$page_size}",
		OBJECT
	);
}

function traitware_get_remaining_wpids_for_bulksync( $offset ) {
	global $wpdb;
	$offset = (int) $offset;

	if ( $offset < 0 ) {
		$offset = 0;
	}

	$users_table = $wpdb->prefix . 'users';
	$db_users    = $wpdb->prefix . 'traitwareusers';

	$remaining = $wpdb->get_results(
		"SELECT COUNT(`u`.`ID`) as `count` FROM `{$users_table}` `u` LEFT JOIN `{$db_users}` `t` ON `t`.`userid` = `u`.`ID` " .
		"WHERE `t`.`id` IS NULL AND `u`.`ID` > {$offset} ORDER BY `u`.`ID` ASC",
		OBJECT
	);

	return (int) $remaining[0]->count;
}

function traitware_del_scrubs_users_by_traitwareids( $traitwareids ) {
	global $wpdb;

	// table names for queries below
	$db_users  = $wpdb->prefix . 'traitwareusers';
	$db_logins = $wpdb->prefix . 'traitwarelogins';

	$in_statement = traitware_sql_in_statement( $traitwareids );

	$and_statement_users  = " AND `traitwareid` {$in_statement}";
	$and_statement_logins = " AND `u`.`traitwareid` {$in_statement}";

	if ( $in_statement === '' ) {
		return;
	}

	$user_query = "DELETE FROM $db_users WHERE `usertype` = 'scrub'{$and_statement_users}";
	$wpdb->query( $user_query );

	$login_query = "DELETE `l` FROM `$db_logins` `l` LEFT JOIN `$db_users` `u` ON `u`.`id` = `l`.`twuserid` WHERE " .
		"`u`.`usertype` = 'scrub'{$and_statement_logins}";
	$wpdb->query( $login_query );
}

// load the self registration page by page ID from the database
function traitware_getSelfRegistrationPageById( $page_id ) {
	// load the existing value for this post
	$self_registration_page = get_post_meta( $page_id, '_traitware_self_registration_page', true );

	if ( $self_registration_page !== false && $self_registration_page !== null && trim( $self_registration_page ) !== '' ) {
		return $self_registration_page;
	}

	return 'no';
}

// load the self registration username by page ID from the database
function traitware_getSelfRegistrationUsernameById( $page_id ) {
	// load the existing value for this post
	$self_registration_username = get_post_meta( $page_id, '_traitware_self_registration_username', true );

	if ( $self_registration_username !== false && $self_registration_username !== null && trim( $self_registration_username ) !== '' ) {
		return $self_registration_username;
	}

	return 'no';
}

// load the self registration pagelink by page ID from the database
function traitware_getSelfRegistrationPagelinkById( $page_id ) {
	// load the existing value for this post
	$self_registration_pagelink = get_post_meta( $page_id, '_traitware_self_registration_pagelink', true );

	if ( $self_registration_pagelink !== false && $self_registration_pagelink !== null && trim( $self_registration_pagelink ) !== '' ) {
		return max( 0, (int) $self_registration_pagelink );
	}

	return 0;
}

// load the self registration role by page ID from the database
function traitware_getSelfRegistrationRoleById( $page_id ) {
	// load the existing value for this post
	$self_registration_role = get_post_meta( $page_id, '_traitware_self_registration_role', true );

	if ( $self_registration_role !== false && $self_registration_role !== null && trim( $self_registration_role ) !== '' ) {
		return $self_registration_role;
	}

	return traitware_getDefaultRole();
}

// load the self registration approval by page ID from the database
function traitware_getSelfRegistrationApprovalById( $page_id ) {
	// load the existing value for this post
	$self_registration_approval = get_post_meta( $page_id, '_traitware_self_registration_approval', true );

	if ( $self_registration_approval !== false && $self_registration_approval !== null && trim( $self_registration_approval ) !== '' ) {
		return $self_registration_approval;
	}

	return 'no';
}

// load the self registration approval role by page ID from the database
function traitware_getSelfRegistrationApprovalRoleById( $page_id ) {
	// load the existing value for this post
	$self_registration_approval_role = get_post_meta( $page_id, '_traitware_self_registration_approval_role', true );

	if ( $self_registration_approval_role !== false && $self_registration_approval_role !== null && trim( $self_registration_approval_role ) !== '' ) {
		return $self_registration_approval_role;
	}

	return traitware_getDefaultRole();
}

// load the opt-in by page ID from the database
function traitware_getOptInPageById( $page_id ) {
	// load the existing value for this post
	$optin_page = get_post_meta( $page_id, '_traitware_optin_page', true );

	if ( $optin_page !== false && $optin_page !== null && trim( $optin_page ) !== '' ) {
		return $optin_page;
	}

	return 'no';
}

// load the opt-in notification by page ID from the database
function traitware_getOptInNotificationById( $page_id ) {
	// load the existing value for this post
	$optin_notification = get_post_meta( $page_id, '_traitware_optin_notification', true );

	if ( $optin_notification !== false && $optin_notification !== null && trim( $optin_notification ) !== '' ) {
		return $optin_notification;
	}

	return 'no';
}

// load the self registration instructions by page ID from the database
function traitware_getSelfRegistrationInstructionsById( $page_id ) {
	// load the existing value for this post
	$self_registration_instructions = get_post_meta( $page_id, '_traitware_self_registration_instructions', true );

	if ( $self_registration_instructions !== false && $self_registration_instructions !== null && trim( $self_registration_instructions ) !== '' ) {
		return $self_registration_instructions;
	}

	return '';
}

// load the self registration loggedin text by page ID from the database
function traitware_getSelfRegistrationLoggedinById( $page_id ) {
	// load the existing value for this post
	$self_registration_loggedin = get_post_meta( $page_id, '_traitware_self_registration_loggedin', true );

	if ( $self_registration_loggedin !== false && $self_registration_loggedin !== null && trim( $self_registration_loggedin ) !== '' ) {
		return $self_registration_loggedin;
	}

	return '';
}

// load the self registration existing text by page ID from the database
function traitware_getSelfRegistrationExistingById( $page_id ) {
	// load the existing value for this post
	$self_registration_existing = get_post_meta( $page_id, '_traitware_self_registration_existing', true );

	if ( $self_registration_existing !== false && $self_registration_existing !== null && trim( $self_registration_existing ) !== '' ) {
		return $self_registration_existing;
	}

	return '';
}

// load the self registration linktext by page ID from the database
function traitware_getSelfRegistrationLinktextById( $page_id ) {
	// load the existing value for this post
	$self_registration_linktext = get_post_meta( $page_id, '_traitware_self_registration_linktext', true );

	if ( $self_registration_linktext !== false && $self_registration_linktext !== null && trim( $self_registration_linktext ) !== '' ) {
		return $self_registration_linktext;
	}

	return '';
}

// load the self registration success by page ID from the database
function traitware_getSelfRegistrationSuccessById( $page_id ) {
	// load the existing value for this post
	$self_registration_success = get_post_meta( $page_id, '_traitware_self_registration_success', true );

	if ( $self_registration_success !== false && $self_registration_success !== null && trim( $self_registration_success ) !== '' ) {
		return $self_registration_success;
	}

	return '';
}

// load the login page by page ID from the database
function traitware_getLoginPageById( $page_id ) {
	// load the existing value for this post
	$login_page = get_post_meta( $page_id, '_traitware_login_page', true );

	if ( $login_page !== false && $login_page !== null && trim( $login_page ) !== '' ) {
		return $login_page;
	}

	return 'no';
}

// load the form start by page ID from the database
function traitware_getFormStartById( $page_id ) {
	$form_start = get_post_meta( $page_id, '_traitware_form_start', true );

	if ( $form_start !== false && $form_start !== null && trim( $form_start ) !== '' ) {
		return $form_start;
	}

	return '';
}

// load the login pagelink by page ID from the database
function traitware_getLoginPagelinkById( $page_id ) {
	// load the existing value for this post
	$login_pagelink = get_post_meta( $page_id, '_traitware_login_pagelink', true );

	if ( $login_pagelink !== false && $login_pagelink !== null && trim( $login_pagelink ) !== '' ) {
		return max( 0, (int) $login_pagelink );
	}

	return 0;
}

// load the login linktext by page ID from the database
function traitware_getLoginLinktextById( $page_id ) {
	// load the existing value for this post
	$login_linktext = get_post_meta( $page_id, '_traitware_login_linktext', true );

	if ( $login_linktext !== false && $login_linktext !== null && trim( $login_linktext ) !== '' ) {
		return $login_linktext;
	}

	return '';
}

// load the login linktext by page ID from the database
function traitware_getLoginRedirectById( $page_id ) {
	// load the existing value for this post
	$login_redirect = get_post_meta( $page_id, '_traitware_login_redirect', true );

	if ( $login_redirect !== false && $login_redirect !== null && trim( $login_redirect ) !== '' ) {
		return $login_redirect;
	}

	return '';
}

// load the login instructions by page ID from the database
function traitware_getLoginInstructionsById( $page_id ) {
	// load the existing value for this post
	$login_instructions = get_post_meta( $page_id, '_traitware_login_instructions', true );

	if ( $login_instructions !== false && $login_instructions !== null && trim( $login_instructions ) !== '' ) {
		return $login_instructions;
	}

	return '';
}

// load the login loggedin text by page ID from the database
function traitware_getLoginLoggedinById( $page_id ) {
	// load the existing value for this post
	$login_loggedin = get_post_meta( $page_id, '_traitware_login_loggedin', true );

	if ( $login_loggedin !== false && $login_loggedin !== null && trim( $login_loggedin ) !== '' ) {
		return $login_loggedin;
	}

	return '';
}

// load the opt-in pagelink by page ID from the database
function traitware_getOptInPagelinkById( $page_id ) {
	// load the existing value for this post
	$optin_pagelink = get_post_meta( $page_id, '_traitware_optin_pagelink', true );

	if ( $optin_pagelink !== false && $optin_pagelink !== null && trim( $optin_pagelink ) !== '' ) {
		return max( 0, (int) $optin_pagelink );
	}

	return 0;
}

// load the opt-in success by page ID from the database
function traitware_getOptInSuccessById( $page_id ) {
	// load the existing value for this post
	$optin_success = get_post_meta( $page_id, '_traitware_optin_success', true );

	if ( $optin_success !== false && $optin_success !== null && trim( $optin_success ) !== '' ) {
		return $optin_success;
	}

	return '';
}

// load the opt-in instructions by page ID from the database
function traitware_getOptInInstructionsById( $page_id ) {
	// load the existing value for this post
	$optin_instructions = get_post_meta( $page_id, '_traitware_optin_instructions', true );

	if ( $optin_instructions !== false && $optin_instructions !== null && trim( $optin_instructions ) !== '' ) {
		return $optin_instructions;
	}

	return '';
}

// load the opt-in logged in instructions by page ID from the database
function traitware_getOptInLoggedInInstructionsById( $page_id ) {
	// load the existing value for this post
	$optin_instructions = get_post_meta( $page_id, '_traitware_optin_logged_in_instructions', true );

	if ( $optin_instructions !== false && $optin_instructions !== null && trim( $optin_instructions ) !== '' ) {
		return $optin_instructions;
	}

	return '';
}

// load the opt-in linktext by page ID from the database
function traitware_getOptInLinktextById( $page_id ) {
	// load the existing value for this post
	$optin_linktext = get_post_meta( $page_id, '_traitware_optin_linktext', true );

	if ( $optin_linktext !== false && $optin_linktext !== null && trim( $optin_linktext ) !== '' ) {
		return $optin_linktext;
	}

	return '';
}

// load the opt-in existing text by page ID from the database
function traitware_getOptInExistingById( $page_id ) {
	// load the existing value for this post
	$optin_existing = get_post_meta( $page_id, '_traitware_optin_existing', true );

	if ( $optin_existing !== false && $optin_existing !== null && trim( $optin_existing ) !== '' ) {
		return $optin_existing;
	}

	return '';
}

// get the default role for this WordPress site
function traitware_getDefaultRole() {
	$default_role = get_option( 'default_role' );

	if ( $default_role !== false && $default_role !== null && trim( $default_role ) !== '' ) {
		return $default_role;
	}

	return 'subscriber';
}

// get a list of published posts, pages, and viewable CPTs (ordered by post title)
function traitware_getPublishedList() {

}

// get the default instructions for self-registration pages
function traitware_getSelfRegistrationDefaultInstructions() {
	return 'Use the form below to sign up for our site using TraitWare.';
}

// get the default loggedin text for self-registration pages
function traitware_getSelfRegistrationDefaultLoggedin() {
	return 'It appears you are already logged in. The form you are trying to access is for new WordPress users.';
}

// get the default loggedin text for login pages
function traitware_getLoginDefaultLoggedin() {
	return 'You are already logged in!';
}

// get the default existing user text for self-registration pages
function traitware_getSelfRegistrationDefaultExisting() {
	return 'The email address/username you have entered belongs to an existing WordPress account. The form you are trying to access is for new WordPress users.';
}

// get the default linktext for self-registration pages
function traitware_getSelfRegistrationDefaultLinktext() {
	return 'Click here to sign-up using TraitWare!';
}

// get the default linktext for login pages
function traitware_getLoginDefaultLinktext() {
	return 'Click here to Login with TraitWare!';
}

// get the default linktext for opt-in pages
function traitware_getOptInDefaultLinktext() {
	return 'Click here to add TraitWare to an existing WordPress account!';
}

// get the default success text for self-registration pages
function traitware_getSelfRegistrationDefaultSuccess() {
	return 'Congratulations, you are almost done signing up using TraitWare! Check your email for instructions on how to finish the process.';
}

// get the default success text for opt-in pages
function traitware_getOptInDefaultSuccess() {
	return 'Congratulations, you are almost done adding TraitWare! Check your email for instructions on how to finish the process.';
}

// get the default instructions for self-registration pages
function traitware_getOptInDefaultInstructions() {
	return "Use the form below to add TraitWare's Secure Login to your account.";
}

// get the default logged-in instructions for self-registration pages
function traitware_getOptInDefaultLoggedInInstructions() {
	return "Do you want to opt-in to TraitWare's Secure Login?";
}

// get the default instructions for login pages
function traitware_getLoginDefaultInstructions() {
	return 'Use the TraitWare app to scan the QR code below to login.';
}

// get the default existing user text for opt-in pages
function traitware_getOptInDefaultExisting() {
	return 'The account you are currently logged into has already setup TraitWare.';
}

// get a list of form pages
function traitware_getFormPages() {
	$args = array(
		'post_type' => 'traitware_form',
	);

	$query = new WP_Query( $args );

	$posts = ( isset( $query->posts ) && is_array( $query->posts ) ) ?
		$query->posts : array();

	return $posts;
}

// get a list of self registration pages
function traitware_getSelfRegistrationPages() {
	$args = array(
		'post_type'  => 'traitware_form',
		'meta_query' => array(
			array(
				'key'     => '_traitware_self_registration_page',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
	);

	$query = new WP_Query( $args );

	$posts = ( isset( $query->posts ) && is_array( $query->posts ) ) ?
		$query->posts : array();

	return $posts;
}

// get a list of login pages
function traitware_getLoginPages() {
	$args = array(
		'post_type'  => 'traitware_form',
		'meta_query' => array(
			array(
				'key'     => '_traitware_login_page',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
	);

	$query = new WP_Query( $args );

	$posts = ( isset( $query->posts ) && is_array( $query->posts ) ) ?
		$query->posts : array();

	return $posts;
}

// get a list of opt-in pages
function traitware_getOptInPages() {
	$args = array(
		'post_type'  => 'traitware_form',
		'meta_query' => array(
			array(
				'key'     => '_traitware_optin_page',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
	);

	$query = new WP_Query( $args );

	$posts = ( isset( $query->posts ) && is_array( $query->posts ) ) ?
		$query->posts : array();

	return $posts;
}

function traitware_isAccountOwner() {
	if ( ! traitware_is_active() ) {
		return false; } // not yet active

	$current_user = wp_get_current_user();
	if ( ! ( $current_user instanceof WP_User ) ) {
		return false; }

	// is this wp account the tw account owner?
	global $wpdb;
	$result = $wpdb->get_results( 'SELECT accountowner FROM ' . $wpdb->prefix . 'traitwareusers WHERE userid = ' . $current_user->ID, OBJECT );
	if ( count( $result ) == 0 ) {
		return false; }
	if ( $result[0]->accountowner != 1 ) {
		return false; }
	return true;
}

function traitware_timeago( $time ) {
	if ( $time == 0 ) {
		return 'Never'; }
	$diff = time() - $time;
	if ( $diff < 60 ) {
		return 'Just now'; } elseif ( $diff < 3600 ) { // it happened X minutes ago
		$out = round( $diff / 60 );
		if ( $out == 1 ) {
			return 'A minute ago'; }
		return $out . ' minutes ago';
		} elseif ( $diff < 3600 * 24 ) { // it happened X hours ago
			$out = round( $diff / 3600 );
			if ( $out == 1 ) {
				return 'An hour ago'; }
			return $out . ' hours ago';
		} elseif ( $diff < 3600 * 24 * 2 ) { // it happened yesterday
			return 'yesterday';
		} else { // falling back on a usual date format as it happened later than yesterday
			if ( strftime( date( 'Y', $time ) == date( 'Y' ) ) ) { // this year
				return date( 'M jS', $time );
			} else {
				return date( 'M jS Y', $time );
			}
		}
}

function traitware_create_wp_user( $email, $firstName, $lastName, $createRole ) {
	$wp_user = null;
	$name    = traitware_generate_username( $email );

	$userdata = array(
		'user_login' => $name,
		'user_pass'  => wp_generate_password(),
		'user_email' => $email,
	);

	if ( $createRole !== null && trim( $createRole ) !== '' ) {
		$userdata['role'] = $createRole;
	}

	if ( $firstName !== null && is_string( $firstName ) && trim( $firstName ) !== '' ) {
		$userdata['first_name'] = $firstName;
	}

	if ( $lastName !== null && is_string( $lastName ) && trim( $lastName ) !== '' ) {
		$userdata['last_name'] = $lastName;
	}

	$userid = wp_insert_user( $userdata );

	if ( is_wp_error( $userid ) ) {
		return null;
	}

	return new WP_User( $userid );
}

function traitware_update_wp_user( $wp_user, $email, $firstName, $lastName ) {
	$userdata = $wp_user->to_array();

	// we might need to update our user
	if ( strtolower( $wp_user->user_email ) !== strtolower( $email ) ) {
		$userdata['user_email'] = $email;
	}

	if ( $firstName !== null && is_string( $firstName ) && trim( $firstName ) !== '' &&
		$wp_user->first_name !== $firstName ) {
		$userdata['first_name'] = $firstName;
	}

	if ( $lastName !== null && is_string( $lastName ) && trim( $lastName ) !== '' &&
		$wp_user->last_name !== $lastName ) {
		$userdata['last_name'] = $lastName;
	}

	// nothing to update
	if ( empty( $userdata ) ) {
		return true;
	}

	// update the user
	$userdata['ID'] = $wp_user->ID;
	$result         = wp_insert_user( $userdata );

	if ( is_wp_error( $result ) ) {
		return false;
	}

	if ( strtolower( $wp_user->user_email ) !== strtolower( $email ) ) {
		$wp_user->user_email = $email;
	}

	if ( $firstName !== null && is_string( $firstName ) && trim( $firstName ) !== '' &&
		$wp_user->first_name !== $firstName ) {
		$wp_user->first_name = $firstName;
	}

	if ( $lastName !== null && is_string( $lastName ) && trim( $lastName ) !== '' &&
		$wp_user->last_name !== $lastName ) {
		$wp_user->last_name = $lastName;
	}

	return true;
}

function traitware_is_wp_user( $wp_user ) {
	if ( $wp_user === false || $wp_user === null || $wp_user === 0 ||
		is_wp_error( $wp_user ) ) {
		return false;
	}

	if ( ! is_object( $wp_user ) || ! ( $wp_user instanceof WP_User ) ) {
		return false;
	}

	return true;
}

function traitware_is_tw_user( $wp_user ) {
	global $wpdb;

	if ( $wp_user === false || $wp_user === null || $wp_user === 0 ||
		is_wp_error( $wp_user ) ) {
		return false;
	}

	if ( ! is_object( $wp_user ) || ! ( $wp_user instanceof WP_User ) ) {
		return false;
	}

	$db_users = $wpdb->prefix . 'traitwareusers';

	return count(
		$wpdb->get_results(
			'SELECT * FROM ' . $db_users . ' WHERE userid = ' . (int) $wp_user->ID,
			OBJECT
		)
	) > 0;
}

function traitware_getUserMeta( $wpid ) {
	$user = get_userdata( $wpid );
	if ( ! ( $user instanceof WP_User ) ) {
		return false; }
	$user = array(
		'user_login'    => $user->user_login,
		'user_nicename' => $user->user_nicename,
		'user_email'    => $user->user_email,
		'user_url'      => $user->user_url,
		'display_name'  => $user->display_name,
		'user_roles'    => $user->roles,
	);

	$usermeta = get_user_meta( $wpid );
	unset( $usermeta['session_tokens'] ); // this is not needed

	return json_encode(
		array(
			'user'     => $user,
			'usermeta' => $usermeta,
		)
	);
}

// get a full site list of all users according to the following:
// All non subscriber users (author,administrator,etc)
// subscriber users IF they have a tw user account
function traitware_getAllUserMeta() {
	$usermeta = array();

	if ( ! traitware_is_active() ) {
		return $usermeta; }// not activated yet so no traitwareusers table

	$allusers = get_users(); // full list of all users in WP_User format
	global $wpdb;
	foreach ( $allusers as $thisuser ) {
		if ( in_array( 'subscriber', $thisuser->roles ) ) {
			$results = $wpdb->get_results( 'SELECT id FROM ' . $wpdb->prefix . 'traitwareusers WHERE userid = ' . (int) $thisuser->ID, OBJECT );
			if ( count( $results ) > 0 ) {
				$usermeta[] = traitware_getUserMeta( $thisuser->ID );
			}
		} else {
			$usermeta[] = traitware_getUserMeta( $thisuser->ID );
		}
	}
	return $usermeta;
}

function traitware_getRoleList() {
	global $wp_roles;

	$roleList = array();

	foreach ( $wp_roles->roles as $roleKey => $roleInfo ) {
		$roleList[ $roleKey ] = array(
			'name'         => $roleInfo['name'],
			'display_name' => translate_user_role( $roleInfo['name'] ),
		);
	}

	return $roleList;
}

function traitware_getUserType( $wpid ) {
	global $wpdb;

	$db_users = $wpdb->prefix . 'traitwareusers';

	$results = $wpdb->get_results( 'SELECT id,traitwareid,usertype FROM ' . $db_users . ' WHERE userid = ' . (int) $wpid, OBJECT );

	if ( $results && count( $results ) > 0 ) {
		$usertype = trim( $results[0]->usertype );
		return $usertype === '' ? 'dashboard' : $usertype;
	}

	return null;
}

function traitware_getUserDetails( $wpid ) {
	global $wpdb;

	$db_users = $wpdb->prefix . 'traitwareusers';

	$results = $wpdb->get_results( 'SELECT id,traitwareid,usertype,accountowner FROM ' . $db_users . ' WHERE userid = ' . (int) $wpid, OBJECT );

	if ( $results && count( $results ) > 0 ) {
		$usertype = trim( $results[0]->usertype );

		$details = (object) array(
			'traitwareid'  => $results[0]->traitwareid,
			'usertype'     => $usertype === '' ? 'dashboard' : $usertype,
			'accountowner' => $results[0]->accountowner !== '0' && $results[0]->accountowner !== 0,
		);

		return $details;
	}

	return null;
}

function traitware_getUserOwner( $wpid ) {
	global $wpdb;

	$db_users = $wpdb->prefix . 'traitwareusers';

	$results = $wpdb->get_results( 'SELECT id,traitwareid,accountowner FROM ' . $db_users . ' WHERE userid = ' . (int) $wpid, OBJECT );

	if ( $results && count( $results ) > 0 ) {
		return $results[0]->accountowner !== '0' && $results[0]->accountowner !== 0;
	}

	return false;
}

// update_user_meta covers changes to role from any screen and all user updates from the user profile, no matter if it's yours or another user.
// changes will trigger update_user_meta sometimes several times so check if it's been added before.
add_action(
	'update_user_meta',
	function( $meta_id, $object_id, $meta_key, $_meta_value ) {
		traitware_addChangedUserId( $object_id );
	},
	10,
	4
);

// delete user
// add_action( 'deleted_user', function( $id, $reassign ) { traitware_updateUCIDs($id); }, 10, 2 );
// This is called any time WP notices the following changes to a wp user WHO IS ALSO A TRAITWARE USER: Role, Meta, New User, Change of wp_users record, delete
// Triggers an api call to update this info to be displayed on the TW console interface.
// accumulates all changes to all users on this page generation before sending a SINGLE call to TW just before PHP exits
function traitware_userConsoleUpdate( $userChangeIDs ) {
	global $wpdb;
	$changedUsers = array();
	foreach ( $userChangeIDs as $id ) {

		// only send users that have a twid
		$res = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					traitwareid
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
				$id
			),
			OBJECT
		);
		if ( count( $res ) == 0 ) {
			continue; }

		$user = get_user_by( 'id', $id );
		if ( ! ( $user instanceof WP_User ) ) {
			continue; }
		$changedUsers[] = array(
			'firstName'       => $user->user_firstname,
			'lastName'        => $user->user_lastname,
			'emailAddress'    => $user->user_email,
			'mobilePhone'     => '',
			'traitwareUserId' => $res[0]->traitwareid,
		);
	}
	if ( count( $changedUsers ) == 0 ) {
		return; }

	Traitware_API::update_users( $changedUsers );
}

// force autoupdate for this plugin
// https://codex.wordpress.org/Configuring_Automatic_Background_Updates
function traitware_autoupdate( $update, $item ) {
	if ( $item->slug == 'traitware-login-manager' ) {
		return true; // force autoupdate
	} else {
		return $update;
	}
}
add_filter( 'auto_update_plugin', 'traitware_autoupdate', 10, 2 );

// traitware activation hook
function traitware_activate( $flush ) {
	 traitware_createdbtables(); // general.php

	if ( $flush ) {
		flush_rewrite_rules(); // refresh permalinks
	}

	update_option( 'traitware_version', traitware_get_var( 'version' ) );
	traitware_store_env_account_credentials();
	traitware_set_admin_notice_start_time();
}

// traitware activation hook (flush rewrite rules)
function traitware_activate_hook() {
	traitware_register_custom_post_types();
	traitware_activate( true );
}

function traitware_plugins_loaded() {
	// check the DB version against our constant
	if ( get_option( 'traitware_version' ) !== traitware_get_var( 'version' ) ) {
		// call our activation hook if the version has changed
		traitware_activate( false );
	}

	// make sure that if we are upgrading from an old version, the "Limit Page Access" post types are defaulted
	$current_limitaccesspts = get_option( 'traitware_limitaccesspts' );

	if ( $current_limitaccesspts === false || $current_limitaccesspts === null ) {
		update_option( 'traitware_limitaccesspts', array( 'post', 'page' ) );
	}

	// make sure that if we are upgrading from an old version, the "Protected Page Selector" is defaulted
	$current_protectedpageselector = get_option( 'traitware_protectedpageselector' );

	if ( $current_protectedpageselector === false || $current_protectedpageselector === null || empty( $current_protectedpageselector ) ) {
		update_option( 'traitware_protectedpageselector', '#primary' );
	}

	// make sure that if we are upgrading from an old version, the "Access Denied Message" is defaulted
	$current_protectedpageaccessdeniedmessagetype = get_option( 'traitware_protectedpageaccessdeniedmessagetype' );

	if ( $current_protectedpageaccessdeniedmessagetype === false || $current_protectedpageaccessdeniedmessagetype === null || empty( $current_protectedpageaccessdeniedmessagetype ) ) {
		update_option( 'traitware_protectedpageaccessdeniedmessagetype', 'text' );
	}

	$current_protectedpageaccessdeniedmessagetext = get_option( 'traitware_protectedpageaccessdeniedmessagetext' );

	if ( $current_protectedpageaccessdeniedmessagetext === false || $current_protectedpageaccessdeniedmessagetext === null || empty( $current_protectedpageaccessdeniedmessagetext ) ) {
		update_option( 'traitware_protectedpageaccessdeniedmessagetext', 'Access Denied' );
	}
	traitware_setup_auth_filters();
}

function traitware_setup_auth_filters() {
	if ( ! traitware_is_active() ) {
		return; }

	if ( traitware_isbackendlogin() ) {
		$recovery = false;
		if ( isset( $_REQUEST['recovery'] ) ) {
			if ( strlen( Traitware_Frontend::validate_recovery( trim( sanitize_key( $_REQUEST['recovery'] ) ) ) ) > 0 ) {
				$recovery = true; } // validate_recovery() returns username if valid
		}

		if ( get_option( 'traitware_disablewplogin' ) != 1 || $recovery ) {
			return;
		}
	} elseif ( get_option( 'traitware_disablecustomloginform' ) != 1 ) {
		return;
	}

	add_filter( 'wp_authenticate_user', 'traitware_authenticate_user_filter', 99, 2 );
	add_filter( 'wp_authenticate_email_password', 'traitware_authenticate_email_password_filter', 99, 3 );
	add_filter( 'wp_authenticate_cookie', 'traitware_authenticate_cookie_filter', 99, 3 );
}

function traitware_authenticate_user_filter( $user, $email ) {
	return new WP_Error();
}

function traitware_authenticate_email_password_filter( $user, $email, $password ) {
	return new WP_Error();
}

function traitware_authenticate_cookie_filter( $user, $username, $password ) {
	return new WP_Error();
}

/**
 * Check if TraitWare has been activated.
 *
 * @return bool
 */
function traitware_is_active() {
	return get_option( 'traitware_active' ) == 1;
}

function traitware_getDateUtc() {
	$old_tz = date_default_timezone_get();

	date_default_timezone_set( 'UTC' );
	$today = date( 'm-d-Y' );
	date_default_timezone_set( $old_tz );

	return $today;
}

function traitware_getJsVars() {
	$is_backendlogin = traitware_isbackendlogin();

	$addrecover = false;
	$login      = '';
	$recovery   = '';
	if ( $is_backendlogin && get_option( 'traitware_disablewplogin' ) == 1 ) {
		$addrecover = true;
		if ( isset( $_REQUEST['recovery'] ) ) {
			$recovery = trim( sanitize_key( $_REQUEST['recovery'] ) );
			$login    = Traitware_Frontend::validate_recovery( $recovery );
			if ( strlen( $login ) == 0 ) {
				$recovery = ''; } // invalid
			else {
				$addrecover = false; } // valid, show wp login form
		}
	} elseif ( ! $is_backendlogin && get_option( 'traitware_disablecustomloginform' ) == 1 ) {
		$addrecover = true;

		if ( get_option( 'traitware_disablecustomloginrecovery' ) == 0 && isset( $_REQUEST['recovery'] ) ) {
			$recovery = trim( sanitize_key( $_REQUEST['recovery'] ) );
			$login    = Traitware_Frontend::validate_scrub_recovery( $recovery );
			if ( strlen( $login ) == 0 ) {
				$recovery = ''; } // invalid
			else {
				$addrecover = false; } // valid, show custom login form
		}
	}

	$default_role  = null;
	$roles_js      = array();
	$bulksync_role = null;

	$forms_js = array();

	if ( is_admin() ) {
		$role_list = traitware_getRoleList();

		foreach ( $role_list as $role_key => $role_single ) {
			$roles_js[ $role_key ] = $role_single['display_name'];
		}

		$bulksync_role = get_option( 'traitware_bulksync_role' );
		$default_role  = get_option( 'default_role' );

		if ( $bulksync_role === false || $bulksync_role === null || trim( $bulksync_role ) === '' ) {
			$bulksync_role = $default_role;
		}

		$formPages = traitware_getFormPages();
		foreach ( $formPages as $page ) {
			$forms_js[ $page->ID ] = esc_html( $page->post_title );
		}
	}

	$traitware_vars = array(
		'site_url'                      => get_site_url(),
		'pollscan_action'               => 'wplogin',
		'qrclassname'                   => '.traitware-qrcode',
		'auth_url'                      => traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_authorization' ) . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=',
		'qrcode_url'                    => get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=',
		'poll_url'                      => traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ),
		'payment_url'                   => '&returnUri=' . rawurlencode( wp_login_url() ),
		'addrecover'                    => $addrecover,
		'login'                         => $login,
		'recovery'                      => $recovery,
		'redirect_style'                => 'url',
		'redirect_url'                  => esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
		'custom_redirect_url'           => get_option( 'traitware_customloginredirect', '' ),
		'disable_custom_login'          => get_option( 'traitware_disablecustomlogin' ),
		'disable_custom_login_recovery' => get_option( 'traitware_disablecustomloginrecovery' ),
		'disable_custom_login_form'     => get_option( 'traitware_disablecustomloginform' ),
		'custom_login_selector'         => get_option( 'traitware_customloginselector' ),
		'is_account_owner'              => traitware_isAccountOwner() ? 'yes' : 'no',
		'is_custom_login'               => ! $is_backendlogin,
		'roles'                         => $roles_js,
		'default_role'                  => $default_role,
		'bulksync_role'                 => $bulksync_role,
		'selfregforms'                  => $forms_js,
		'_twnonce'						=> wp_create_nonce( 'traitware_ajax' ),
	);

	return $traitware_vars;
}

$traitware_userChangeIDs = array();

function traitware_addChangedUserId( $changedUserId ) {
	global $traitware_userChangeIDs;

	if ( ! isset( $traitware_userChangeIDs ) ) {
		$traitware_userChangeIDs = array();
	}

	if ( in_array( $changedUserId, $traitware_userChangeIDs ) ) {
		return;
	}

	$traitware_userChangeIDs[] = $changedUserId;
}

function traitware_getChangedUserIds() {
	global $traitware_userChangeIDs;

	if ( ! isset( $traitware_userChangeIDs ) ) {
		$traitware_userChangeIDs = array();
	}

	return $traitware_userChangeIDs;
}

// add our custom post type for the traitware forms
function traitware_init() {
	traitware_register_custom_post_types();
}

function traitware_register_custom_post_types() {
	$is_onlywplogin = get_option('traitware_enableonlywplogin');
	if ( $is_onlywplogin ) {
		return;
	}

	register_post_type(
		'traitware_form',
		array(
			'labels'       => array(
				'name'               => __( 'TraitWare Forms', 'traitware' ),
				'singular_name'      => __( 'TraitWare Form', 'traitware' ),
				'menu_name'          => __( 'TraitWare Forms', 'traitware' ),
				'name_admin_bar'     => __( 'TraitWare Forms', 'traitware' ),
				'add_new'            => __( 'Add New', 'traitware' ),
				'add_new_item'       => __( 'Add New Form', 'traitware' ),
				'edit_item'          => __( 'Edit Form', 'traitware' ),
				'new_item'           => __( 'New Form', 'traitware' ),
				'view_item'          => __( 'View Form', 'traitware' ),
				'search_items'       => __( 'Search Forms', 'traitware' ),
				'not_found'          => __( 'No forms found', 'traitware' ),
				'not_found_in_trash' => __( 'No forms found in trash', 'traitware' ),
				'all_items'          => __( 'All Forms', 'traitware' ),
			),
			'capabilities' => array(
				'edit_post'            => 'manage_options',
				'read_post'            => 'read',
				'delete_posts'         => 'manage_options',
				'delete_post'         => 'manage_options',
				'edit_posts'           => 'manage_options',
				'edit_others_posts'    => 'manage_options',
				'publish_posts'        => 'manage_options',
				'read_private_posts'   => 'read',
				'create_posts'         => 'manage_options',
				'create_private_posts' => false,
			),
			'supports'     => array( 'title' ),
			'public'       => true,
			'has_archive'  => false,
			'rewrite'      => array(
				'slug' => 'traitware',
			),
			'show_in_menu' => false,
		)
	);
}

// return a list of modes valid for self registration page
function traitware_getSelfRegistrationModes() {
	return array(
		'no'   => 'No',
		'yes'  => 'Yes, allow new users',
		'link' => 'Link to another form',
	);
}

// return a list of modes valid for login page
function traitware_getLoginModes() {
	return array(
		'no'   => 'No',
		'yes'  => 'Yes, allow logging in with TraitWare',
		'link' => 'Link to another form',
	);
}

// return a list of modes valid for opt-in page
function traitware_getOptInModes() {
	return array(
		'no'   => 'No',
		'yes'  => 'Yes, allow existing users to opt-in to TraitWare',
		'link' => 'Link to another form',
	);
}

// return a list of modes valid for start form
function traitware_getFormStartModes() {
	return array(
		'signup' => 'New user sign-up',
		'login'  => 'Login',
		'optin'  => 'Existing user opt-in',
	);
}

// return a list of modes valid for self registration approval
function traitware_getSelfRegistrationApprovalModes() {
	return array(
		'no'           => 'No',
		'yes'          => 'Yes',
		'notification' => 'No, but send admin a notification email',
	);
}

// return a list of modes valid for opt-in notification approval
function traitware_getOptInNotificationModes() {
	return array(
		'no'  => 'No',
		'yes' => 'Yes',
	);
}

// return a list of modes valid for self registration username
function traitware_getSelfRegistrationUsernameModes() {
	return array(
		'no'  => 'No',
		'yes' => 'Yes',
	);
}

// get the link (based on site permalink settings) for form_id
function traitware_getSelfRegistrationFormLink( $form_id ) {
	if ( $form_id <= 0 ) {
		return null;
	}

	return get_permalink( $form_id );
}

// check if an array of posts contains post with $post_id
function traitware_postArrayContainsId( $post_array, $post_id ) {
	$contains = false;

	foreach ( $post_array as $post_single ) {
		if ( ! is_object( $post_single ) || ! isset( $post_single->ID ) || $post_single->ID !== $post_id ) {
			continue;
		}

		$contains = true;
		break;
	}

	return $contains;
}

// generate the traitware form HTML based on the form's post ID
function traitware_form_html( $form_id ) {
	global $post;

	$formPost = $post;

	if ( $post->ID !== $form_id ) {
		$formPost = get_post( $form_id );
	}

	// Generate our form HTML
	$html = '';

	// If this is not a form, just echo empty HTML.
	if ( $formPost->post_type !== 'traitware_form' ) {
		return $html;
	}

	// Load the form start option.
	$form_start = traitware_getFormStartById( $form_id );

	// Are we logged in?
	$is_logged_in = is_user_logged_in();

	// Load all the installed forms.
	$self_registration_pages = traitware_getSelfRegistrationPages();
	$optin_pages             = traitware_getOptInPages();
	$login_pages             = traitware_getLoginPages();

	// Load the pagelink options.
	$self_registration_pagelink = traitware_getSelfRegistrationPagelinkById( $form_id );
	$login_pagelink             = traitware_getLoginPagelinkById( $form_id );
	$optin_pagelink             = traitware_getOptInPagelinkById( $form_id );

	// Set the default hrefs for the form pieces.
	$self_registration_link_href = '#';
	$login_link_href             = '#';
	$optin_link_href             = '#';

	// Load the base options for each tab.
	$self_registration_page = traitware_getSelfRegistrationPageById( $form_id );
	$optin_page             = traitware_getOptInPageById( $form_id );
	$login_page             = traitware_getLoginPageById( $form_id );

	if ( Traitware_Frontend::$protected_page_id || Traitware_Frontend::$shortcode_qr_count ) {
		$login_page = 'no';
	}

	$yes_pages = array();

	if ( $self_registration_page === 'yes' ) {
		$yes_pages[] = 'signup';
	}

	if ( $optin_page === 'yes' ) {
		$yes_pages[] = 'optin';
	}

	if ( $login_page === 'yes' ) {
		$yes_pages[] = 'login';
	}

	if ( ! in_array( $form_start, $yes_pages ) ) {
		if ( empty( $yes_pages ) ) {
			$form_start = '';
		} else {
			$form_start = $yes_pages[0];
		}
	}

	$outer_id = 'traitware_forms_outer_' . uniqid();
	$html    .= '<div id="' . $outer_id . '" class="traitware_forms_outer" data-form-id="' . esc_attr( $form_id ) . '">';

	// Generate the error for forms that have not yet been set up.
	if ( empty( $yes_pages ) ) {
		$html .= '<div class="traitware_forms_notsetup">';
		$html .= '<p>This form does not have any enabled features.</p>';
		$html .= '</div>';
	} else {
		// Calculate $has_self_registration_link
		$has_self_registration_link = $self_registration_page !== 'no';

		if ( $has_self_registration_link && $self_registration_page !== 'yes' ) {
			if ( ! traitware_postArrayContainsId( $self_registration_pages, $self_registration_pagelink ) ) {
				$has_self_registration_link = false;
			}
		}

		// Calculate $self_registration_linktext
		$self_registration_linktext = traitware_getSelfRegistrationLinktextById( $form_id );

		if ( $self_registration_linktext === '' ) {
			$self_registration_linktext = traitware_getSelfRegistrationDefaultLinktext();
		}

		// Calculate $self_registration_link_href
		if ( $has_self_registration_link && $self_registration_page !== 'yes' ) {
			$self_registration_link_href = get_permalink( $self_registration_pagelink );
		}

		// Calculate $self_registration_link_html
		$self_registration_link_html = '';

		if ( $has_self_registration_link ) {
			$self_registration_link_html .= '<p><span><a href="' . esc_url( $self_registration_link_href ) . '" class="traitware_forms_signuplink">';
			$self_registration_link_html .= esc_html( $self_registration_linktext ) . '</a></span></p>';
		}

		// Calculate $has_login_link
		$has_login_link = $login_page !== 'no';

		if ( $has_login_link && $login_page !== 'yes' ) {
			if ( ! traitware_postArrayContainsId( $login_pages, $login_pagelink ) ) {
				$has_login_link = false;
			}
		}

		// Calculate $login_linktext
		$login_linktext = traitware_getLoginLinktextById( $form_id );

		if ( $login_linktext === '' ) {
			$login_linktext = traitware_getLoginDefaultLinktext();
		}

		// Calculate $login_link_href
		if ( $has_login_link && $login_page !== 'yes' ) {
			$login_link_href = get_permalink( $login_pagelink );
		}

		// Calculate $login_link_html
		$login_link_html = '';

		if ( $has_login_link ) {
			$login_link_html .= '<p><span><a href="' . esc_url( $login_link_href ) . '" class="traitware_forms_loginlink">';
			$login_link_html .= esc_html( $login_linktext ) . '</a></span></p>';
		}

		// Calculate $has_optin_link
		$has_optin_link = $optin_page !== 'no';

		if ( $has_optin_link && $optin_page !== 'yes' ) {
			if ( ! traitware_postArrayContainsId( $optin_pages, $optin_pagelink ) ) {
				$has_optin_link = false;
			}
		}

		// Calculate $optin_linktext
		$optin_linktext = traitware_getOptInLinktextById( $form_id );

		if ( $optin_linktext === '' ) {
			$optin_linktext = traitware_getOptInDefaultLinktext();
		}

		// Calculate $optin_link_href
		if ( $has_optin_link && $optin_page !== 'yes' ) {
			$optin_link_href = get_permalink( $optin_pagelink );
		}

		// Calculate $optin_link_html
		$optin_link_html = '';

		if ( $has_optin_link ) {
			$optin_link_html .= '<p><span><a href="' . esc_url( $optin_link_href ) . '" class="traitware_forms_optinlink">';
			$optin_link_html .= esc_html( $optin_linktext ) . '</a></span></p>';
		}

		if ( in_array( 'signup', $yes_pages ) ) {
			$extraClass = $form_start === 'signup' ? 'traitware_forms_active' : '';
			$html      .= '<div class="traitware_forms_signup ' . $extraClass . '" data-form-id="' . esc_attr( $form_id ) . '">';

			if ( $is_logged_in ) {
				$self_registration_welcome_message = traitware_getSelfRegistrationLoggedinById( $form_id );
				if ( $self_registration_welcome_message === '' ) {
					$self_registration_welcome_message = traitware_getSelfRegistrationDefaultLoggedin();
				}
			} else {
				$self_registration_welcome_message = traitware_getSelfRegistrationInstructionsById( $form_id );
				if ( $self_registration_welcome_message === '' ) {
					$self_registration_welcome_message = traitware_getSelfRegistrationDefaultInstructions();
				}
			}

			$self_registration_success_message = traitware_getSelfRegistrationSuccessById( $form_id );
			if ( $self_registration_success_message === '' ) {
				$self_registration_success_message = traitware_getSelfRegistrationDefaultSuccess();
			}

			$self_registration_username = traitware_getSelfRegistrationUsernameById( $form_id );

			$signup_url = add_query_arg(
				array(
					'action'      => 'traitware_ajaxforms',
					'form_id'     => $form_id,
					'form_action' => 'signup',
					'_twnonce' => wp_create_nonce('traitware_ajaxforms')
				),
				admin_url( 'admin-ajax.php' )
			);

			$html .= "\n\t<div class=\"traitware_forms_container\">";
			$html .= "\n\t\t<div class=\"traitware_forms_authbox\">";
			$html .= "\n\t\t\t<div class=\"traitware_forms_inner_authbox\">";

			$html .= "\n\t\t\t\t<div class=\"traitware_forms_titletext\">";
			$html .= "\n\t\t\t\tRegister for TraitWare's Secure Login";
			$html .= "\n\t\t\t\t</div>";
			$html .= "\n\t\t\t\t<div class=\"traitware_forms_welcometext\">";
			if ( $is_logged_in ) {
				$current_user = wp_get_current_user();

				$logged_in_display = trim( $current_user->display_name );

				if ( $logged_in_display === '' ) {
					$logged_in_display = $current_user->user_email;
				}

				$logout_url = add_query_arg(
					array(
						'action'      => 'traitware_ajaxforms',
						'form_id'     => $form_id,
						'form_action' => 'logout',
						'_twnonce' => wp_create_nonce('traitware_ajaxforms')
					),
					admin_url( 'admin-ajax.php' )
				);

				$html .= "\n\t\t\t\tWelcome back, " . esc_html( $logged_in_display ) . '! (<a href="' . esc_url( $logout_url ) . '" class="traitware_forms_logoutlink">Not you?</a>)<br />';
			}
			$html .= "\n\t\t\t\t" . esc_html( $self_registration_welcome_message );
			$html .= "\n\t\t\t\t</div>";
			if ( ! $is_logged_in ) {
				$html .= "\n\t\t\t\t<form class=\"traitware_forms_signup_form\" action=\"" . esc_url( $signup_url ) . '" method="post">';
				$html .= "\n\t\t\t\t\t<div class=\"traitware_forms_inputs\">";

				if ( $self_registration_username === 'yes' ) {
					$html .= '<input placeholder="Enter username" type="text" class="traitware_forms_signup_input traitware_forms_signup_input_username" name="username">';
				}

				$email_class = $self_registration_username === 'yes' ? '' : 'traitware_forms_signup_only_input';
				$html       .= '<input placeholder="Enter email address" type="text" class="traitware_forms_signup_input traitware_forms_signup_input_email ' . $email_class . '" name="email">';

				$html .= '<div class="traitware_forms_button_area">';
				$html .= '<input type="submit" class="traitware_forms_signup_submit_btn" value="Create Account" />';
				$html .= '</div>';

				$html .= "\n\t\t\t\t\t</div>";

				$html .= "\n\t\t\t<div class=\"traitware_forms_errormessage\">";
				$html .= "\n\t\t\t</div>";
				$html .= "\n\t\t\t<div class=\"traitware_forms_successmessage\">";
				$html .= "\n\t\t\t" . esc_html( $self_registration_success_message );
				$html .= "\n\t\t\t</div>";
				$html .= "\n\t\t\t\t</form>";
			}

			$html .= "\n\t\t\t</div>";

			if ( $has_login_link || $has_optin_link ) {
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_bottomarea\">";

				if ( $has_login_link ) {
					$html .= $login_link_html;
				}

				if ( $has_optin_link ) {
					$html .= $optin_link_html;
				}

				$html .= "\n\t\t\t\t</div>";
			}

			$html .= "\n\t</div>";
			$html .= '</div>';
			$html .= '</div>';
		}

		if ( in_array( 'login', $yes_pages ) ) {
			$login_instructions = traitware_getLoginInstructionsById( $form_id );

			if ( $login_instructions === '' ) {
				$login_instructions = traitware_getLoginDefaultInstructions();
			}

			$extraClass = $form_start === 'login' ? 'traitware_forms_active' : '';
			$html      .= '<div class="traitware_forms_login ' . $extraClass . '" data-form-id="' . esc_attr( $form_id ) . '">';
			$html      .= "\n\t<div class=\"traitware_forms_container\">";
			$html      .= "\n\t\t<div class=\"traitware_forms_authbox\">";
			$html      .= "\n\t\t\t<div class=\"traitware_forms_inner_authbox\">";

			if ( ! $is_logged_in ) {
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_titletext\">";
				$html .= "\n\t\t\t\tLogin with TraitWare";
				$html .= "\n\t\t\t\t</div>";
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_welcometext\">";
				$html .= "\n\t\t\t\t" . esc_html( $login_instructions );
				$html .= "\n\t\t\t\t</div>";
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_qrbox\">";
				$html .= "\n\t\t\t\t\t<div class=\"traitware_forms_qrcode\"></div>";
				$html .= "\n\t\t\t\t</div>";
			} else {
				$login_loggedin = traitware_getLoginLoggedInById( $form_id );

				if ( $login_loggedin === '' ) {
					$login_loggedin = traitware_getLoginDefaultLoggedin();
				}

				$current_user = wp_get_current_user();

				$logged_in_display = trim( $current_user->display_name );

				if ( $logged_in_display === '' ) {
					$logged_in_display = $current_user->user_email;
				}

				$logout_url = add_query_arg(
					array(
						'action'      => 'traitware_ajaxforms',
						'form_id'     => $form_id,
						'form_action' => 'logout',
						'_twnonce' => wp_create_nonce('traitware_ajaxforms')
					),
					admin_url( 'admin-ajax.php' )
				);

				$loggedin_url = add_query_arg(
					array(
						'action'      => 'traitware_ajaxforms',
						'form_id'     => $form_id,
						'form_action' => 'loggedin',
						'_twnonce' => wp_create_nonce('traitware_ajaxforms')
					),
					admin_url( 'admin-ajax.php' )
				);

				$html .= "\n\t\t\t\t<div class=\"traitware_forms_titletext\">";
				$html .= "\n\t\t\t\tAlready Logged In";
				$html .= "\n\t\t\t\t</div>";
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_welcometext\">";
				$html .= "\n\t\t\t\t" . esc_html( $login_loggedin ) . '<br />';
				$html .= "\n\t\t\t\tWelcome back, " . esc_html( $logged_in_display ) . '! (<a href="' . esc_url( $logout_url ) . '" class="traitware_forms_logoutlink">Not you?</a>)<br />';
				$html .= "\n\t\t\t\t<a href=\"" . esc_url( $loggedin_url ) . '" class="traitware_forms_loggedinlink">Continue to site.</a>';
				$html .= "\n\t\t\t\t</div>";
			}

			$html .= "\n\t\t\t</div>";

			if ( $has_self_registration_link || $has_optin_link ) {
				$html .= "\n\t\t\t\t<div class=\"traitware_forms_bottomarea\">";

				if ( $has_self_registration_link ) {
					$html .= $self_registration_link_html;
				}

				if ( $has_optin_link ) {
					$html .= $optin_link_html;
				}

				$html .= "\n\t\t\t\t</div>";
			}

			$html .= "\n\t\t</div>";
			$html .= "\n\t</div>";
			$html .= '</div>';

			$login_redirect = traitware_getLoginRedirectById( $form_id );

			if ( trim( $login_redirect ) === '' ) {
				$login_redirect = wp_json_encode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}

			$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
			$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
			$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_authorization' ) . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=' ) );
			$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

			$html .= '
            <script type="text/javascript">
			
				var traitware_site_url = ' . $traitware_site_url . ';
				var traitware_pollscan_action = "wplogin";
				var traitware_qrclassname = ".traitware-forms-qrcode";
                var traitware_redirectstyle = "url";
				var traitware_redirecturl = ' . $login_redirect . ';
				var traitware_auth_url = ' . $traitware_auth_url . ';
				var traitware_qrcode_url = ' . $traitware_qrcode_url . ';
				var traitware_poll_url = ' . $traitware_poll_url . ';
				var traitware_payment_url = "&returnUri=' . rawurlencode( wp_login_url() ) . '";
				var traitware_nonce = ' . wp_json_encode( wp_create_nonce('traitware_ajax') ) . ';

				jQuery(function() {
                    var html = "<img class=\'traitware-forms-qrcode\' /><div class=\'traitware-scan-response\'></div>";
                    jQuery("#' . $outer_id . ' .traitware_forms_qrcode").prepend( html );
                    traitware_enable_polling();
                    traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
                    window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
                    traitware_qrcode();
                });
            </script>
            ';
		}

		if ( in_array( 'optin', $yes_pages ) ) {
			$extraClass = $form_start === 'optin' ? 'traitware_forms_active' : '';
			$html      .= '<div class="traitware_forms_optin ' . $extraClass . '" data-form-id="' . esc_attr( $form_id ) . '">';

			$is_opted_in = false;
			if ( $is_logged_in ) {
				$current_user = wp_get_current_user();
				$is_opted_in  = traitware_is_tw_user( $current_user );
				if ( $is_opted_in ) {
					$optin_instructions = traitware_getOptInExistingById( $form_id );
					if ( $optin_instructions === '' ) {
						$optin_instructions = traitware_getOptInDefaultExisting();
					}
				} else {
					$optin_instructions = traitware_getOptInLoggedInInstructionsById( $form_id );
					if ( $optin_instructions === '' ) {
						$optin_instructions = traitware_getOptInDefaultLoggedInInstructions();
					}
				}
			} else {
				$optin_instructions = traitware_getOptInInstructionsById( $form_id );
				if ( $optin_instructions === '' ) {
					$optin_instructions = traitware_getOptInDefaultInstructions();
				}
			}

			$optin_success_message = traitware_getOptInSuccessById( $form_id );
			if ( $optin_success_message === '' ) {
				$optin_success_message = traitware_getOptInDefaultSuccess();
			}

			$optin_url = add_query_arg(
				array(
					'action'      => 'traitware_ajaxforms',
					'form_id'     => $form_id,
					'form_action' => 'optin',
					'_twnonce' => wp_create_nonce('traitware_ajaxforms')
				),
				admin_url( 'admin-ajax.php' )
			);

			$html .= "\n\t<div class=\"traitware_forms_container\">";
			$html .= "\n\t\t<div class=\"traitware_forms_authbox\">";
			$html .= "\n\t\t\t<div class=\"traitware_forms_inner_authbox\">";

			$html .= "\n\t\t\t\t<div class=\"traitware_forms_titletext\">";
			$html .= "\n\t\t\t\tAdd TraitWare to Account";
			$html .= "\n\t\t\t\t</div>";

			$html .= "\n\t\t\t\t<div class=\"traitware_forms_welcometext\">";

			if ( $is_logged_in ) {
				$current_user      = wp_get_current_user();
				$logged_in_display = trim( $current_user->display_name );

				if ( $logged_in_display === '' ) {
					$logged_in_display = $current_user->user_email;
				}

				$logout_url = add_query_arg(
					array(
						'action'      => 'traitware_ajaxforms',
						'form_id'     => $form_id,
						'form_action' => 'logout',
						'_twnonce' => wp_create_nonce('traitware_ajaxforms')
					),
					admin_url( 'admin-ajax.php' )
				);

				$html .= "\n\t\t\t\tHi, " . esc_html( $logged_in_display ) . '! (<a href="' . esc_url( $logout_url ) . '" class="traitware_forms_logoutlink">Not you?</a>)<br />';
			}

			$html .= "\n\t\t\t\t" . esc_html( $optin_instructions );

			$html .= "\n\t\t\t\t</div>";

			$html .= "\n\t\t\t\t<form class=\"traitware_forms_optin_form\" action=\"" . esc_url( $optin_url ) . '" method="post">';
			$html .= "\n\t\t\t\t\t<div class=\"traitware_forms_inputs\">";

			if ( ! $is_logged_in ) {
				$html .= "\n\t\t\t\t\t\t<input placeholder=\"Enter email/username\" type=\"text\" class=\"traitware_forms_optin_input traitware_forms_optin_input_username\" name=\"username\">";
				$html .= "\n\t\t\t\t\t\t<input placeholder=\"Enter password\" type=\"password\" class=\"traitware_forms_optin_input traitware_forms_optin_input_password\" name=\"password\">";
			} else {
				$html .= "\n\t\t\t\t\t\t<input type=\"hidden\" name=\"logged_in\" value=\"1\" />";
			}

			$html .= "\n\t\t\t\t\t<div class=\"traitware_forms_button_area\">";
			if ( ! $is_opted_in ) {
				$html .= "\n\t\t\t\t\t\t<input type=\"submit\" class=\"traitware_forms_optin_submit_btn\" value=\"Add TraitWare\" />";
			}
			$html .= "\n\t\t\t\t\t</div>";

			$html .= "\n\t\t\t\t</div>";
			$html .= "\n\t\t\t<div class=\"traitware_forms_errormessage\">";
			$html .= "\n\t\t\t</div>";
			$html .= "\n\t\t\t<div class=\"traitware_forms_successmessage\">";
			$html .= "\n\t\t\t" . esc_html( $optin_success_message );
			$html .= "\n\t\t\t</div>";
			$html .= "\n\t\t\t\t</form>";

			$html .= "\n\t\t\t</div>";

			$html .= "\n\t\t\t<div class=\"traitware_forms_bottomarea\">";

			if ( $has_login_link ) {
				$html .= $login_link_html;
			}

			if ( $has_self_registration_link ) {
				$html .= $self_registration_link_html;
			}

			$html .= "\n\t\t\t</div>";

			$html .= "\n\t\t</div>";
			$html .= "\n\t</div>";
		}
	}

	$html .= '</div>';

	return $html;
}

function traitware_create_self_registration_approval( $form_id, $user ) {
	global $wpdb;

	$hash           = wp_hash( wp_generate_password() );
	$approvalstable = $wpdb->prefix . 'traitwareapprovals';

	$wpdb->insert(
		$approvalstable,
		array(
			'form_id'       => $form_id,
			'user_id'       => $user->ID,
			'approval_hash' => $hash,
		)
	);

	$site_url = get_site_url();

	$approval_url = add_query_arg(
		array(
			'action'      => 'traitware_ajaxforms',
			'form_id'     => $form_id,
			'form_action' => 'approve',
			'user_id'     => $user->ID,
			'hash'        => $hash,
		),
		admin_url( 'admin-ajax.php' )
	);

	$message = 'Hello, you have a new TraitWare approval request on the site '
		. esc_html( $site_url )
		. ' for ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email )
		. '). Click this link to approve the user: <a href="' . esc_url( $approval_url ) . '">' . esc_html( $approval_url ) . '</a>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( get_option( 'admin_email' ), 'New TraitWare Approval Request', $message, $headers );
}

function traitware_approve_self_registration_approval( $form_id, $user, $hash ) {
	global $wpdb;

	$approvalstable = $wpdb->prefix . 'traitwareapprovals';
	$results        = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . $approvalstable . ' WHERE form_id = %d AND user_id = %d AND approval_hash = %s LIMIT 1',
			array(
				(int) $form_id,
				(int) $user->ID,
				$hash,
			)
		),
		ARRAY_A
	);

	if ( count( $results ) === 0 ) {
		return 'Invalid approval parameters.';
	}

	if ( $results[0]['datetime'] !== null ) {
		return 'This user has already been approved.';
	}

	$role = traitware_getSelfRegistrationApprovalRoleById( $form_id );
	$u    = new WP_User( $user->ID );
	$u->set_role( $role );

	$wpdb->update(
		$approvalstable,
		array(
			'datetime' => date( 'Y-m-d G:i:s' ),
		),
		array(
			'id' => $results[0]['id'],
		)
	);

	return 'The user has successfully been approved.';
}

function traitware_send_self_registration_notification( $user ) {

	$site_url = get_site_url();

	$message = 'Hello, a new user has signed up through TraitWare on the site '
		. esc_html( $site_url )
		. '.<br /><strong>Name:</strong> ' . esc_html( $user->display_name ) . '<br /><strong>Email: </strong>' . esc_html( $user->user_email );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( get_option( 'admin_email' ), 'New TraitWare Signup', $message, $headers );
}

function traitware_send_optin_notification( $user ) {

	$site_url = get_site_url();

	$message = 'Hello, a new user has opted-in to TraitWare on the site '
		. esc_html( $site_url )
		. '.<br /><strong>Name:</strong> ' . esc_html( $user->display_name ) . '<br /><strong>Email: </strong>' . esc_html( $user->user_email );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( get_option( 'admin_email' ), 'New TraitWare Opt-In', $message, $headers );
}

function traitware_delete_user( $user_id ) {
	$details = traitware_getUserDetails( $user_id );

	if ( $details === null || $details->accountowner ) {
		return;
	}

	Traitware_API::delete_sync_user( $user_id, $details->traitwareid, $details->usertype === 'scrub' );
}

function traitware_store_env_account_credentials() {
    if ( ! traitware_has_env_account_credentials() ) {
        return false;
    }

    $users = TRAITWARE_ENV_SITE_USERS;
    if ( is_string( $users ) ) {
        $users = json_decode( $users );
    }

    if ( ! is_array( $users ) || empty( $users ) ) {
        return false;
    }

    foreach ( $users as $user ) {
        traitware_create_wp_user( $user['emailAddress'], null, null, 'administrator' );
    }

    update_option( 'traitware_client_id', TRAITWARE_ENV_SITE_ID );
    update_option( 'traitware_client_secret', TRAITWARE_ENV_SITE_SECRET );
    update_option( 'traitware_active', '1' );

    return true;
}

function traitware_set_admin_notice_start_time() {
	add_option( 'traitware_review_notice_start_time', strtotime('+2 weeks') );
}

function traitware_has_env_account_credentials() {
    return defined( 'TRAITWARE_ENV_SITE_ID' ) &&
        defined( 'TRAITWARE_ENV_SITE_SECRET' ) &&
        defined( 'TRAITWARE_ENV_SITE_USERS' );
}

// register our activation hook
// register our plugins_loaded hook to enable us to detect version upgrades
add_action( 'plugins_loaded', 'traitware_plugins_loaded', 15 );

// register our delete user hook
add_action( 'delete_user', 'traitware_delete_user' );

// register our init hook
add_action( 'init', 'traitware_init' );
