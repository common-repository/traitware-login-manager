<?php
/**
 * Pollscan Userlist
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
	global $wpdb;

	if ( ! session_id() ) {
		session_start(); }
	$thisuser = get_current_user_id();
	if ( ! traitware_isadmin() ) {
		die( 'No Access' ); }
	$currentUserOwner = traitware_isAccountOwner();

	$parts = wp_parse_url( $incoming['redirectUri'] );
	$query = array();
	wp_parse_str( $parts['query'], $query );
	$currentTraitwareId = $query['traitwareUserId'];

	if ( $incoming['userlistaction'] === 'add' ) {

		for ( $n = 0; $n < count( $incoming['userlistdata'] ); $n++ ) {
			// -------------------------- API 3D --------------------------
			$user = get_userdata( (int) $incoming['userlistdata'][ $n ] );
			if ( ! ( $user instanceof WP_User ) ) {
				return array( 'error' => 'Please log in again (1)' ); }

			// get traitwareUserId from db
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
					traitwareid, usertype, accountowner
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
					(int) $user->ID
				),
				OBJECT
			);
			if ( count( $results ) > 0 ) {
				continue; }

			if ( ! isset( $incoming['userlistextradata'] ) || ! isset( $incoming['userlistextradata'][ $n ] ) ) {
				continue; }

			$extradata = (array) $incoming['userlistextradata'][ $n ];

			$isAccountOwner = $extradata['usertype'] === 'owner';

			// prevent non-admins from becoming account owners
			if ( $isAccountOwner && ( ! in_array( 'administrator', $user->roles ) || ! $currentUserOwner ) ) {
				$isAccountOwner = false;
			}

			$usertype = $extradata['usertype'];

			// if the user is an account owner they can not be a scrub
			if ( $isAccountOwner || ! in_array( $usertype, array( 'scrub', 'dashboard' ) ) ) {
				$usertype = 'dashboard';
			}

			$create_result = null;

			if ( $usertype === 'scrub' ) {
				$create_result = Traitware_API::create_new_scrub_user( $user, $incoming['userlistdata'][ $n ], $cookies );
			} else {
				$create_result = Traitware_API::create_new_user( $user, $incoming['userlistdata'][ $n ], $cookies );
			}

			$twuserid = null;
			if ( $create_result !== null ) {
				$twuserid = $create_result['twuserid'];

				if ( $isAccountOwner && $usertype === 'dashboard' ) {
					Traitware_API::update_user( $user, $create_result['traitwareid'], $isAccountOwner, $cookies );
				}
			}
		}
		if ( count( $incoming['userlistdata'] ) == 1 ) {
			$_SESSION['pollscan_userlist_message'] = 'User Activated';
		} else {
			$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Users Activated';
		}
	}

	if ( $incoming['userlistaction'] == 'usertype' ) {
		for ( $n = 0; $n < count( $incoming['userlistdata'] ); $n++ ) {
			// -------------------------- API 3D --------------------------
			$user = get_userdata( (int) $incoming['userlistdata'][ $n ] );
			if ( ! ( $user instanceof WP_User ) ) {
				continue; }

			// get traitwareUserId from db
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
					traitwareid, usertype, accountowner
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
					(int) $user->ID
				),
				OBJECT
			);
			if ( count( $results ) == 0 ) {
				return array( 'error' => 'Please log in again (8)' ); }

			$traitwareid = $results[0]->traitwareid;

			if ( $traitwareid === $currentTraitwareId ) {
				continue; }

			$ownerCheck = $results[0]->accountowner !== '0' && $results[0]->accountowner !== 0;

			if ( ! $currentUserOwner && $ownerCheck ) {
				continue; }

			if ( ! isset( $incoming['userlistextradata'] ) || ! isset( $incoming['userlistextradata'][ $n ] ) ) {
				continue; }

			$extradata = (array) $incoming['userlistextradata'][ $n ];

			$isAccountOwner = $extradata['usertype'] === 'owner';

			// prevent non-admins from becoming account owners
			if ( $isAccountOwner && ( ! in_array( 'administrator', $user->roles ) || ! $currentUserOwner ) ) {
				$isAccountOwner = false;
			}

			$usertype = $extradata['usertype'];

			// if the user is an account owner they can not be a scrub
			if ( $isAccountOwner || ! in_array( $usertype, array( 'scrub', 'dashboard' ) ) ) {
				$usertype = 'dashboard';
			}

			if ( $usertype === 'scrub' ) {
				Traitware_API::updateScrubUser( $user, $traitwareid, $isAccountOwner, $cookies );
			} else {
				Traitware_API::update_user( $user, $traitwareid, $isAccountOwner, $cookies );
			}
		}
		if ( count( $incoming['userlistdata'] ) == 1 ) {
			$_SESSION['pollscan_userlist_message'] = 'User Updated';
		} else {
			$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Users Updated';
		}
	}

	if ( $incoming['userlistaction'] == 'bulksync' ) {
		if ( ! $currentUserOwner ) {
			return array(
				'error' => '',
				'url'   => 'admin.php?page=traitware-bulksync',
			);
		}

		if ( get_option( 'traitware_bulksync_queued' ) === 'yes' || get_option( 'traitware_bulksync_running' ) === 'yes' ||
			count( $incoming['userlistdata'] ) !== 2 ) {
			return array(
				'error' => '',
				'url'   => 'admin.php?page=traitware-bulksync',
			);
		}

		update_option( 'traitware_bulksync_queued', 'yes' );
		update_option( 'traitware_bulksync_cookies', $cookies );
		update_option( 'traitware_bulksync_role', $incoming['userlistdata'][0] );
		update_option( 'traitware_bulksync_keeprole', $incoming['userlistdata'][1] === 'yes' ? 'yes' : 'no' );
		update_option( 'traitware_bulksync_userid', $thisuser );
		update_option( 'traitware_bulksync_total', traitware_get_remaining_wpids_for_bulksync( 0 ) );

		return array(
			'error' => '',
			'url'   => 'admin.php?page=traitware-bulksync',
		);
	}

	if ( $incoming['userlistaction'] == 'resend' ) { // resend registration email

		for ( $n = 0; $n < count( $incoming['userlistdata'] ); $n++ ) {

			$user = get_userdata( (int) $incoming['userlistdata'][ $n ] );
			if ( ! ( $user instanceof WP_User ) ) {
				return array( 'error' => 'Please log in again (2)' ); }

			// get traitwareUserId from db
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
					traitwareid
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
					(int) $user->ID
				),
				OBJECT
			);
			if ( count( $results ) == 0 ) {
				return array( 'error' => 'Please log in again (3)' ); }
			$traitwareid = $results[0]->traitwareid;

			if ( $results[0]->accountowner && ! $currentUserOwner ) {
				return array( 'error' => 'Please log in again (5)' );
			}

			if ( ! Traitware_API::resend_email( $traitwareid, $cookies ) ) {
				return array( 'error' => 'Please log in again (4)' );
			}
		}
		if ( count( $incoming['userlistdata'] ) == 1 ) {
			$_SESSION['pollscan_userlist_message'] = 'Recovery Email Sent';
		} else {
			$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Recovery Emails Sent';
		}
	}

	if ( $incoming['userlistaction'] == 'del' ) {

		for ( $n = 0; $n < count( $incoming['userlistdata'] ); $n++ ) {
			if ( $thisuser == (int) $incoming['userlistdata'][ $n ] ) {
				continue; }

			$user = get_userdata( (int) $incoming['userlistdata'][ $n ] );
			if ( ! ( $user instanceof WP_User ) ) {
				continue; }

			// get traitwareUserId from db
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
					traitwareid, usertype
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
					(int) $user->ID
				),
				OBJECT
			);
			if ( count( $results ) == 0 ) {
				return array( 'error' => 'Please log in again (5)' ); }
			$traitwareid = $results[0]->traitwareid;
			$usertype    = $results[0]->usertype;

			if ( $traitwareid === $currentTraitwareId ) {
				continue; }
			if ( $results[0]->accountowner && ! $currentUserOwner ) {
				continue; }

			if ( ! in_array( $usertype, array( 'scrub', 'dashboard' ) ) ) {
				$usertype = 'dashboard';
			}

			$delete_result = null;
			if ( $usertype === 'scrub' ) {
				$delete_result = Traitware_API::delete_scrub_user( $incoming['userlistdata'][ $n ], $traitwareid, $cookies );
			} else {
				$delete_result = Traitware_API::delete_user( $incoming['userlistdata'][ $n ], $traitwareid, $cookies );
			}

			if ( ! $delete_result ) {
				return array( 'error' => 'Please log in again (6)' );
			}
		}
		if ( count( $incoming['userlistdata'] ) == 1 ) {
			$_SESSION['pollscan_userlist_message'] = 'User Deactivated';
		} else {
			$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Users Deactivated';
		}
	}

	if ( $incoming['userlistaction'] == 'addowner' || $incoming['userlistaction'] == 'removeowner' ) {

		for ( $n = 0; $n < count( $incoming['userlistdata'] ); $n++ ) {
			if ( $thisuser == (int) $incoming['userlistdata'][ $n ] ) {
				continue; }

			$user = get_userdata( (int) $incoming['userlistdata'][ $n ] );
			if ( ! ( $user instanceof WP_User ) ) {
				continue; } // not in wp users table

			if ( $incoming['userlistaction'] == 'addowner' ) { // need to be a wp admin to be an account owner
				if ( ! in_array( 'administrator', $user->roles ) ) {
					continue; }
			}

			// get traitwareUserId from db
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT
					traitwareid, accountowner, usertype
				FROM
					' . $wpdb->prefix . 'traitwareusers
				WHERE
					userid = %d',
					(int) $user->ID
				),
				OBJECT
			);
			if ( count( $results ) == 0 ) {
				continue; }
			$traitwareid = $results[0]->traitwareid;

			if ( intval( $traitwareid ) === intval( $currentTraitwareId ) ) {
				continue; }
			if ( ! $currentUserOwner ) {
				continue; }

			if ( $incoming['userlistaction'] == 'addowner' ) {
				if ( $results[0]->accountowner == 1 ) {
					continue; } // already owner
			}
			if ( $incoming['userlistaction'] == 'removeowner' ) {
				if ( $results[0]->accountowner == 0 ) {
					continue; } // already not owner
			}

			$isAccountOwner = $incoming['userlistaction'] == 'addowner';
			$usertype       = $results[0]->usertype;
			if ( $isAccountOwner ) {
				$usertype = 'dashboard';
			}

			$update_result = null;

			if ( $usertype === 'scrub' ) {
				$update_result = Traitware_API::updateScrubUser( $user, $traitwareid, $isAccountOwner, $cookies );
			} else {
				$update_result = Traitware_API::update_user( $user, $traitwareid, $isAccountOwner, $cookies );
			}

			if ( ! $update_result ) {
				return array( 'error' => 'Please log in again (7)' );
			}
		}
		if ( $incoming['userlistaction'] == 'addowner' ) {
			if ( count( $incoming['userlistdata'] ) == 1 ) {
				$_SESSION['pollscan_userlist_message'] = 'User Added as Account Owner';
			} else {
				$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Users Added as Account Owners';
			}
		}
		if ( $incoming['userlistaction'] == 'removeowner' ) {
			if ( count( $incoming['userlistdata'] ) == 1 ) {
				$_SESSION['pollscan_userlist_message'] = 'User Removed as Account Owner';
			} else {
				$_SESSION['pollscan_userlist_message'] = count( $incoming['userlistdata'] ) . ' Users Removed as Account Owners';
			}
		}
	}

	// quick user add modal form
	if ( $incoming['userlistaction'] == 'wpuser' ) {
		include_once 'pollscan_userlist_wpuser.php';
	}

	return array(
		'error' => '',
		'url'   => 'admin.php?page=traitware-users',
	);
}
