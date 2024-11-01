<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

/**
 * This function releases the background lock prior to calling die().
 *
 * @param $str
 */
function traitware_background_die_and_release_lock( $str ) {
	delete_option( 'traitware_background_lock' );
	die( $str );
}

/**
 * Our background script is used to download users from the traitware console and also process bulksync when necessary
 * this will output a JSON encoded response that looks like this:
 * {
 * 'more_background': <boolean>,
 * 'bulksync_progress': <number|optional> // will only be outputted if the background script is processing a bulksync
 * }
 */
function traitware_background() {
	// we will need to run some queries on the database below.
	global $wpdb;

	// table names for queries below.
	$db_users  = $wpdb->prefix . 'traitwareusers';
	$db_logins = $wpdb->prefix . 'traitwarelogins';

	// if traitware is not active then return false.
	if ( ! traitware_is_active() ) {
		// not yet active, and since we do not have a lock acquired yet just use die().
		die(
			wp_json_encode(
				array(
					'more_background' => false,
				)
			)
		);
	}

	// calculate the current timestamp and UTC date.
	$current_time = time();
	$current_date = traitware_getDateUtc();

	// check if the background lock is already in place.
	$background_lock = get_option( 'traitware_background_lock' );

	// the background lock prevents race conditions.
	if ( ! is_null( $background_lock ) && false !== $background_lock && '' !== $background_lock ) {
		$background_lock = intval( $background_lock );

		// delete the lock if it has expired, otherwise use die() to return more_background = true.
		if ( $current_time >= ( $background_lock + traitware_get_var( 'backgroundTimeout' ) ) ) {
			delete_option( 'traitware_background_lock' );
		} else {
			die(
				wp_json_encode(
					array(
						'more_background' => true,
					)
				)
			);
		}
	}

	// finally try to acquire the lock.
	$acquired_lock = add_option( 'traitware_background_lock', $current_time );

	// if we can't acquired a lock, return more_background = true.
	if ( ! $acquired_lock ) {
		die(
			wp_json_encode(
				array(
					'more_background' => true,
				)
			)
		);
	}

	// at this point, we have acquired our lock so we will use traitware_background_die_and_release_lock() instead of die().
	// the following options contain information about the current download runtime.
	$download_last     = get_option( 'traitware_download_last' );
	$download_lastdate = get_option( 'traitware_download_lastdate' );
	$download_date     = get_option( 'traitware_download_date' );
	$download_running  = get_option( 'traitware_download_running' );
	$download_since    = get_option( 'traitware_download_since' );
	$download_progress = get_option( 'traitware_download_progress' );

	$scrub_create_role = get_option( 'traitware_scrub_create_role', '' );

	if ( false === $scrub_create_role || is_null( $scrub_create_role ) || empty( trim( $scrub_create_role ) ) ) {
		$scrub_create_role = null;
	}

	// the following options contain information about the current bulksync runtime.
	$bulksync_queued    = get_option( 'traitware_bulksync_queued' ); // 'yes' or 'no'.
	$bulksync_running   = get_option( 'traitware_bulksync_running' ); // 'yes' or 'no'.
	$bulksync_role      = get_option( 'traitware_bulksync_role' ); // if bulksync is queued/running, this will contain the role that should be used.
	$bulksync_keeprole  = get_option( 'traitware_bulksync_keeprole' ); // 'yes' or 'no' (should a user keep their role if it is not the default).
	$bulksync_timestamp = get_option( 'traitware_bulksync_timestamp' ); // integer which represents the unix timestamp that bulksync started running.
	$bulksync_progress  = get_option( 'traitware_bulksync_progress' ); // integer which represents the last wpid synced with traitware.
	$bulksync_cookies   = get_option( 'traitware_bulksync_cookies' ); // array of cookies for bulksync authentication.
	$bulksync_userid    = get_option( 'traitware_bulksync_userid' ); // the wpid of the user that started the bulksync operation.
	$bulksync_total     = get_option( 'traitware_bulksync_total' ); // the approximate total number of wpids that will be processed in this bulksync (used to calculate progress).

	// variable used to store currently running background task (will be null, 'download', or 'bulksync').
	$background_task = null;

	// this will be true if there has never been a download performed.
	$has_never_download = ( false === $download_last || is_null( $download_last ) || empty( $download_last ) );

	$download_enabled = traitware_get_var( 'downloadEnabled' );

	if ( $download_enabled ) {
		// the following if/else statements are used to determine the current background task.
		if ( 'yes' === $download_running ) {
			$background_task = 'download';
		} else if ( 'yes' === $bulksync_running ) {
			$background_task = 'bulksync';
		} else if ( $has_never_download ) {
			// since we have never downloaded before, this takes priority.
			// we will use these variables below, and since we are resetting the options in the database we persist to memory first.
			$background_task   = 'download';
			$download_since    = '';
			$download_progress = '0';
			$download_date     = $current_date;

			// now persist the state of the download to the options table.
			update_option( 'traitware_download_running', 'yes' );
			update_option( 'traitware_download_since', '' );
			update_option( 'traitware_download_date', $current_date );
			update_option( 'traitware_download_progress', '0' );
		} elseif ( $download_last <= ( $current_time - traitware_get_var( 'downloadFrequency' ) ) ) {
			// since our last download was long enough ago to exceed the downloadFrequency, it is time to run a download.
			// we will use these variables below, and since we are resetting the options in the database we persist to memory first.
			$background_task   = 'download';
			$download_since    = $download_lastdate;
			$download_progress = '0';
			$download_date     = $current_date;

			// now persist the state of the download to the options table.
			update_option( 'traitware_download_running', 'yes' );
			update_option( 'traitware_download_since', $download_lastdate );
			update_option( 'traitware_download_date', $current_date );
			update_option( 'traitware_download_progress', '0' );
		} elseif ( 'yes' === $bulksync_queued && ! $has_never_download ) {
			// since there is a bulksync queued, and none of the above if statements took priority, it is safe to run a bulksync.
			// we will use these variables below, and since we are resetting the options in the database we persist to memory first.
			$background_task    = 'bulksync';
			$bulksync_progress  = '0';
			$bulksync_timestamp = $current_time;

			// now persist the state of the bulksync to the options table.
			update_option( 'traitware_bulksync_running', 'yes' );
			update_option( 'traitware_bulksync_timestamp', $current_time );
			update_option( 'traitware_bulksync_queued', 'no' );
			update_option( 'traitware_bulksync_progress', '0' );
		}
	} else {
		// the following if/else statements are used to determine the current background task.
		if ( 'yes' === $bulksync_running ) {
			$background_task = 'bulksync';
		} elseif ( 'yes' === $bulksync_queued ) {
			// we will use these variables below, and since we are resetting the options in the database we persist to memory first.
			$background_task    = 'bulksync';
			$bulksync_progress  = '0';
			$bulksync_timestamp = $current_time;

			// now persist the state of the bulksync to the options table.
			update_option( 'traitware_bulksync_running', 'yes' );
			update_option( 'traitware_bulksync_timestamp', $current_time );
			update_option( 'traitware_bulksync_queued', 'no' );
			update_option( 'traitware_bulksync_progress', '0' );
		}
	}

	// if there is no background task, then simply respond accordingly with more_background = false.
	// notice we are now using traitware_background_die_and_release_lock() instead of die() since we need to cleanup the lock.
	if ( is_null( $background_task ) ) {
		traitware_background_die_and_release_lock(
			wp_json_encode(
				array(
					'more_background' => false,
				)
			)
		);
	}

	// if the background task is set to download.
	if ( 'download' === $background_task ) {
		// the download progress represents the current page number for downloading from the console.
		// page 0 is always the dashboard users master list.
		// page 1 and up will always be a list of scrub users.
		$download_progress = intval( $download_progress );

		// our page size for scrub users is defined as a global variable.
		$page_size = traitware_get_var( 'scrubPageLimit' );

		// determine if we are fetching the dashboard users master list or one of the scrub user list pages.
		if ( 0 === $download_progress ) {
			// grab the dashboard users master list from the API.
			$users = Traitware_API::get_users();

			// if for some reason the request timed out or failed, fail out but indicate that we should try again.
			if ( is_null( $users ) || false === $users ) {
				traitware_background_die_and_release_lock(
					wp_json_encode(
						array(
							'more_background' => true,
						)
					)
				);
			}

			// this array will contain a list of traitwareids from the API request response.
			$traitwareids = array();

			// this array will contain a list of email addresses from the API request response.
			$emails = array();

			// loop through each user and add to the arrays defined above.
			foreach ( $users as $user_single ) {
				if ( ! in_array( $user_single['traitwareUserId'], $traitwareids ) ) {
					$traitwareids[] = $user_single['traitwareUserId'];
				}

				if ( ! in_array( $user_single['emailAddress'], $emails ) ) {
					$emails[] = $user_single['emailAddress'];
				}
			}

			// delete the dashboard users that are not in this list
			traitware_del_dash_users_by_traitwareids_not( $traitwareids );

			// get all the wpid/traitware id combos using the traitwareids
			$wpids_by_traitwareids = traitware_get_wpids_by_traitwareids( $traitwareids );

			// get all the wpid/email address combos using the email address
			$wpids_by_emails = traitware_get_wpids_by_emails( $emails );

			// the following array will be used to construct the final list of local users to persist to the database
			$traitwareusers = array();

			// loop through the traitwareid_email_map and compose our traitwareuser
			foreach ( $users as $user_single ) {
				// we need to calculate the user and update where necessary
				$wp_user = traitware_create_or_update_wp_user(
					$user_single,
					$wpids_by_traitwareids,
					$wpids_by_emails,
					null
				);

				if ( ! traitware_is_wp_user( $wp_user ) ) {
					continue;
				}

				// create a $traitware user and append it to our payload
				$traitwareusers[] = array(
					'userid'        => intval( $wp_user->ID ),
					'traitwareid'   => $user_single['traitwareUserId'],
					'activeaccount' => 1,
					'accountowner'  => $user_single['isAccountOwner'] ? 1 : 0,
					'recoveryhash'  => '',
					'usertype'      => 'dashboard',
					'params'        => '{}',
				);
			}

			// persist our final payload
			traitware_create_or_update_traitwareusers( $traitwareusers );

			// update the progress to 1 instead of 0
			update_option( 'traitware_download_progress', '1' );

			// respond with more_background = true to indicate that we need to keep going
			traitware_background_die_and_release_lock(
				wp_json_encode(
					array(
						'more_background' => true,
					)
				)
			);
		} else {
			// grab a page of scrub users from the API (subtract 1 from the page number and multiple by the page size for the offset)
			$scrub_users = Traitware_API::get_scrub_users( $download_since, ( ( $download_progress - 1 ) * $page_size ), $page_size );

			// if for some reason the request timed out or failed, fail out but indicate that we should try again
			if ( false === $scrub_users || null === $scrub_users ) {
				traitware_background_die_and_release_lock(
					wp_json_encode(
						array(
							'more_background' => true,
						)
					)
				);
			}

			// if we have reached the end of our list, finish things up
			if ( empty( $scrub_users ) ) {
				// grab our list of duplicate scrub users to be deleted
				// a user is a duplicate scrub if they also represent a dashboard user
				$dupes = traitware_get_dupe_scrub_users();

				foreach ( $dupes as $dupe ) {
					Traitware_API::delete_sync_user( $dupe->userid, $dupe->traitwareid, true );
				}

				// store the new state of the download process (reset to running='no', etc)
				update_option( 'traitware_download_running', 'no' );
				update_option( 'traitware_download_since', '' );
				update_option( 'traitware_download_date', '' );
				update_option( 'traitware_download_last', $current_time );
				update_option( 'traitware_download_lastdate', $download_date );
				update_option( 'traitware_download_progress', '0' );

				// respond with more_background based on if there is a bulksync queued to be ran after this download.
				traitware_background_die_and_release_lock(
					wp_json_encode(
						array(
							'more_background' => ( 'yes' === $bulksync_queued ),
						)
					)
				);
			}

			// since the response was not empty, we should go through and process it.
			// collect all the traitwareids to be deleted into an array.
			$delete_traitwareids = array();

			// collect all the emails of active accounts into an array.
			$emails = array();

			// collect all the traitwareids to be created/updated into an array.
			$traitwareids = array();

			// go through each scrub user and determine if it should be added to our delete array.
			foreach ( $scrub_users as $scrub_user ) {
				if ( ! $scrub_user['isActive'] && ! in_array( $scrub_user['traitwareUserId'], $delete_traitwareids ) ) {
					$delete_traitwareids[] = $scrub_user['traitwareUserId'];
				}

				if ( $scrub_user['isActive'] ) {
					if ( ! in_array( $scrub_user['emailAddress'], $emails ) ) {
						$emails[] = $scrub_user['emailAddress'];
					}

					if ( ! in_array( $scrub_user['traitwareUserId'], $traitwareids ) ) {
						$traitwareids[] = $scrub_user['traitwareUserId'];
					}
				}
			}

			// delete any scrub users that match the traitwareids in the list.
			traitware_del_scrubs_users_by_traitwareids( $delete_traitwareids );

			// get all the wpid/traitware id combos using the traitwareids.
			$wpids_by_traitwareids = traitware_get_wpids_by_traitwareids( $traitwareids );

			// get all the wpid/email address combos using the email address.
			$wpids_by_emails = traitware_get_wpids_by_emails( $emails );

			// create a list of active traitwareusers.
			$traitwareusers = array();

			// loop through the scrub users and compose our final payload.
			foreach ( $scrub_users as $scrub_user ) {
				if ( ! $scrub_user['isActive'] ) {
					continue;
				}

				$wp_user = traitware_create_or_update_wp_user(
					$scrub_user,
					$wpids_by_traitwareids,
					$wpids_by_emails,
					$scrub_create_role
				);

				if ( ! traitware_is_wp_user( $wp_user ) ) {
					continue;
				}

				// create a $traitware user and append it to our payload.
				$traitwareusers[] = array(
					'userid'        => intval( $wp_user->ID ),
					'traitwareid'   => $scrub_user['traitwareUserId'],
					'activeaccount' => 1,
					'accountowner'  => 0,
					'recoveryhash'  => '',
					'usertype'      => 'scrub',
					'params'        => '{}',
				);
			}

			// create or update the active traitwareusers.
			traitware_create_or_update_traitwareusers( $traitwareusers );

			// calculate the new progress page.
			$new_progress = $download_progress + 1;

			// update the new progress value.
			update_option( 'traitware_download_progress', $new_progress );

			// respond with more_background = true.
			traitware_background_die_and_release_lock(
				wp_json_encode(
					array(
						'more_background' => true,
					)
				)
			);
		}
	}

	// if we are processing a bulksync.
	if ( 'bulksync' === $background_task ) {
		// bulksync_timestamp represents when the bulksync was started so we can check the cookies for timeout.
		$bulksync_timestamp = intval( $bulksync_timestamp );

		if ( $current_time >= ( $bulksync_timestamp + traitware_get_var( 'bulksyncCookiesTimeout' ) ) ) {
			// store the new state of the bulksync process (reset to running='no', etc)
			update_option( 'traitware_bulksync_running', 'no' );
			update_option( 'traitware_bulksync_timestamp', '0' );
			update_option( 'traitware_bulksync_progress', '0' );
			update_option( 'traitware_bulksync_cookies', '' );
			update_option( 'traitware_bulksync_total', '0' );

			// respond with more_background=false and bulksync_progress=100
			traitware_background_die_and_release_lock(
				wp_json_encode(
					array(
						'more_background'   => false,
						'bulksync_progress' => 100,
					)
				)
			);
		}

		// bulksync_progress represents the last wpid that has been processed.
		$bulksync_progress = intval( $bulksync_progress );

		// the total will be the approximate number of users to be processed in the operation (for progress calculations).
		$bulksync_total = intval( $bulksync_total );

		// using $bulksync_progress as our offset, grab a page of the wpids that we need to bulksync next.
		$wpids = traitware_get_wpids_for_bulksync( $bulksync_progress );

		// this array will contain our final composed users.
		$bulkusers = array();

		// get the default role.
		$default_role = get_option( 'default_role' );

		// loop through the wpids and get the userdata for the API call below.
		foreach ( $wpids as $wpid_single ) {
			$bulkuser = get_userdata( $wpid_single->ID );

			if ( ! ( $bulkuser instanceof WP_User ) ) {
				continue;
			}

			// does the user have a non-default role.
			$has_non_default = false;

			// does the user already have the bulk role.
			$has_bulk_role = false;

			// loop used to calculate the variables above.
			foreach ( $bulkuser->roles as $single_role ) {
				if ( $single_role !== $default_role ) {
					$has_non_default = true;
				}

				if ( $single_role === $bulksync_role ) {
					$has_bulk_role = true;
				}
			}

			if ( ! $has_bulk_role && ( 'yes' !== $bulksync_keeprole || ! $has_non_default ) ) {
				$bulkuser->set_role( $bulksync_role );
			}

			$bulkusers[] = $bulkuser;
		}

		// check if there are no more users to bulksync
		if ( empty( $bulkusers ) ) {
			// store the new state of the bulksync process (reset to running='no', etc)
			update_option( 'traitware_bulksync_running', 'no' );
			update_option( 'traitware_bulksync_progress', '0' );
			update_option( 'traitware_bulksync_cookies', '' );
			update_option( 'traitware_bulksync_total', '0' );

			// respond with more_background=false and bulksync_progress=100
			traitware_background_die_and_release_lock(
				wp_json_encode(
					array(
						'more_background'   => false,
						'bulksync_progress' => 100,
					)
				)
			);
		}

		// call the create bulk scrub users endpoint using our payload.
		$bulkresponse = Traitware_API::create_bulk_scrub_users( $bulkusers, $bulksync_cookies );

		// if for some reason the request failed, we should try again.
		if ( is_null( $bulkresponse ) ) {
			// calculate the frontend progress for the response.
			$progress_value = 0;

			if ( 0 !== $bulksync_total ) {
				$progress_value = 100 - round( ( traitware_get_remaining_wpids_for_bulksync( $bulksync_progress ) / $bulksync_total ) * 100 );
			}

			// since something timed out, just return more_background=true and try again.
			traitware_background_die_and_release_lock(
				wp_json_encode(
					array(
						'more_background'   => true,
						'bulksync_progress' => $progress_value,
					)
				)
			);
		}

		// set the new traitware_bulksync_progress value to the last wpid that was processed in this batch.
		$last_idx = count( $bulkusers ) - 1;
		$last_id  = $bulkusers[ $last_idx ]->ID;
		update_option( 'traitware_bulksync_progress', intval( $last_id ) );

		// calculate the frontend progress value (0-100).
		$progress_value = 0;

		if ( 0 !== $bulksync_total ) {
			$progress_value = 100 - round( ( traitware_get_remaining_wpids_for_bulksync( $bulksync_progress ) / $bulksync_total ) * 100 );
		}

		// we don't want them to be redirected yet since there is probably a few more to go.
		if ( $progress_value > 99 ) {
			$progress_value = 99;
		}

		// respond with more_background=true and our frontend progress value for bulksync_progress.
		traitware_background_die_and_release_lock(
			wp_json_encode(
				array(
					'more_background'   => true,
					'bulksync_progress' => $progress_value,
				)
			)
		);
	}

	// there must be nothing to do if we got all the way here.
	traitware_background_die_and_release_lock(
		wp_json_encode(
			array(
				'more_background' => false,
			)
		)
	);
}

traitware_background();
