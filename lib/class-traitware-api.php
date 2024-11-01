<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

/**
 * Class Traitware_API
 */
class Traitware_API {
    /**
     * init method.
     */
	public static function init() {
	}

	/**
	 * Get all the users from the TraitWare API.
	 *
	 * @return array|null
	 */
	public static function get_users() {
		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
			),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/users',
			$args
		);

		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );

		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null;
		}

		return $body;
	}

	/**
	 * Get a list of the scrub users from the TraitWare API.
	 *
	 * @param $updatedSince
	 * @param $skip_offset
	 * @param $page_limit
	 * @return array|mixed|null|object
	 */
	public static function get_scrub_users( $updatedSince, $skip_offset, $page_limit ) {
		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
			),
		);

		/*
		 * updatedSince: Joi.date().optional(),
		 * skip: Joi.number().optional(),
		 * limit: Joi.number().optional(),
		 */
		if ( is_null( $updatedSince ) || false === $updatedSince || '' === trim( $updatedSince ) ) {
			$updatedSince = '01-01-2000';
		}

		$skip_offset = intval($skip_offset );
		$page_limit  = intval( $page_limit );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_scrubUsers' ) . '?updatedSince=' . rawurlencode( $updatedSince ) .
				'&skip=' . rawurlencode( $skip_offset ) . '&limit=' . rawurlencode( $page_limit ),
			$args
		);

		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );

		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null;
		}

		return $body;
	}

	/**
	 * Create a new user with the TraitWare API. This will also create the user in the TraitWare table.
	 *
	 * @param $user - WordPress user object
	 * @param $wpid - WordPress user ID
     * @param $cookies
	 * @return array|null - Will return either the traitware user ID (on success) or null on failure.
	 */
	public static function create_new_user( $user, $wpid, $cookies ) {
		$args = self::common_args( $cookies );

		// outgoing data.
		$data = array();
		if ( ! is_null( $user->user_firstname ) ) {
			$data['firstName'] = $user->user_firstname;
		} else {
			$data['firstName'] = '';
		}

		if ( ! is_null( $user->user_lastname ) ) {
			$data['lastName'] = $user->user_lastname;
		} else {
			$data['lastName'] = '';
		}
		$data['emailAddress']      = $user->user_email;
		$data['mobilePhone']       = '';
		$data['isAccountOwner']    = false;
		$data['userName']          = $user->user_login;
		$data['wordpressUserMeta'] = traitware_getUserMeta( intval( $wpid ) );

		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/users',
			$args
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );
		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null;
		}

		if ( ! isset( $body['traitwareUserId'] ) ) {
			return null;
		}

		if ( empty( $body['traitwareUserId'] ) ) {
			return null;
		}

		$user = array(
			'userid'        => intval( $wpid ),
			'traitwareid'   => $body['traitwareUserId'],
			'activeaccount' => 1,
			'accountowner'  => 0,
			'recoveryhash'  => '',
			'usertype'      => 'dashboard',
			'params'        => '{}',
		);

		$twuserid = traitware_adduser( $user ); // general.php.

		return array(
			'twuserid'    => $twuserid,
			'traitwareid' => $body['traitwareUserId'],
		);
	}

	public static function create_bulk_scrub_users( $users, $cookies ) {
		$args = self::common_args( $cookies );

		$request_data = array();

		foreach ( $users as $user ) {
			// outgoing data.
			$data = array();
			if ( ! is_null( $user->user_firstname ) ) {
				$data['firstName'] = $user->user_firstname;
			} else {
				$data['firstName'] = '';
			}

			if ( ! is_null( $user->user_lastname ) ) {
				$data['lastName'] = $user->user_lastname;
			} else {
				$data['lastName'] = '';
			}
			$data['emailAddress']      = $user->user_email;
			$data['mobilePhone']       = '';
			$data['isAccountOwner']    = false;
			$data['userName']          = $user->user_login;
			$data['wordpressUserMeta'] = traitware_getUserMeta( $user->ID );

			$request_data[] = $data;
		}

		$args['method']  = 'POST';
		$args['body']    = wp_json_encode( $request_data );
		$args['timeout'] = 30;

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/bulkScrubUsers',
			$args
		);

		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );
		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null; }

		if ( ! is_array( $body ) ) {
			return null;
		}

		$success_list = array();

		foreach ( $body as $success_single ) {
			$success_list[ $success_single['emailAddress'] ] = $success_single['traitwareUserId'];
		}

		$success_users = array();
		foreach ( $users as $user ) {
			if ( ! isset( $success_list[ $user->user_email ] ) ) {
				continue;
			}

			$user = array(
				'userid'        => intval( $user->ID ),
				'traitwareid'   => $success_list[ $user->user_email ],
				'activeaccount' => 1,
				'accountowner'  => 0,
				'recoveryhash'  => '',
				'usertype'      => 'scrub',
				'params'        => '{}',
			);

			$success_users[] = $user;
		}

		traitware_create_or_update_traitwareusers( $success_users ); // general.php.

		return array();
	}
	/**
	 * Create a new user with the TraitWare API. This will also create the user in the TraitWare table.
	 *
	 * @param $user - WordPress user object
	 * @param $wpid - WordPress user ID
     * @param $cookies
	 * @return array|null - Will return either the traitware user ID (on success) or null on failure.
	 */
	public static function create_new_scrub_user( $user, $wpid, $cookies ) {
		$args = self::common_args( $cookies );

		// outgoing data.
		$data = array();
		if ( ! is_null( $user->user_firstname ) ) {
			$data['firstName'] = $user->user_firstname;
		} else {
			$data['firstName'] = '';
		}

		if ( ! is_null( $user->user_lastname ) ) {
			$data['lastName'] = $user->user_lastname;
		} else {
			$data['lastName'] = '';
		}
		$data['emailAddress']      = $user->user_email;
		$data['mobilePhone']       = '';
		$data['isAccountOwner']    = false;
		$data['userName']          = $user->user_login;
		$data['wordpressUserMeta'] = traitware_getUserMeta( (int) $wpid );

		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/scrubUsers',
			$args
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );
		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null;
		}

		if ( ! isset( $body['traitwareUserId'] ) ) {
			return null;
		}

		if ( strlen( $body['traitwareUserId'] ) == 0 ) {
			return null; }

		$user = array(
			'userid'        => (int) $wpid,
			'traitwareid'   => $body['traitwareUserId'],
			'activeaccount' => 1,
			'accountowner'  => 0,
			'recoveryhash'  => '',
			'usertype'      => 'scrub',
			'params'        => '{}',
		);

		$twuserid = traitware_adduser( $user ); // general.php

		return array(
			'twuserid'    => $twuserid,
			'traitwareid' => $body['traitwareUserId'],
		);
	}

	/**
	 * Create a new user with the TraitWare API. This will also create the user in the TraitWare table.
	 *
	 * @param $user - WordPress user object
	 * @param $wpid - WordPress user ID
	 * @return array|null - Will return either the traitware user ID (on success) or null on failure.
	 */
	public static function create_new_scrub_user_forms( $user, $wpid ) {
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		// outgoing data.
		$data = array();
		if ( ! is_null( $user->user_firstname ) ) {
			$data['firstName'] = $user->user_firstname;
		} else {
			$data['firstName'] = '';
		}

		if ( ! is_null( $user->user_lastname ) ) {
			$data['lastName'] = $user->user_lastname;
		} else {
			$data['lastName'] = '';
		}

		$data['emailAddress']      = $user->user_email;
		$data['mobilePhone']       = '';
		$data['isAccountOwner']    = false;
		$data['userName']          = $user->user_login;
		$data['wordpressUserMeta'] = traitware_getUserMeta( intval( $wpid ) );

		$args['body'] = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/scrubUsers',
			$args
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( $response['body'], true );
		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return null;
		}

		if ( ! isset( $body['traitwareUserId'] ) ) {
			return null;
		}

		if ( empty( $body['traitwareUserId'] ) ) {
			return null;
		}

		$user = array(
			'userid'        => intval( $wpid ),
			'traitwareid'   => $body['traitwareUserId'],
			'activeaccount' => 1,
			'accountowner'  => 0,
			'recoveryhash'  => '',
			'usertype'      => 'scrub',
			'params'        => '{}',
		);

		$twuserid = traitware_adduser( $user ); // general.php.

		return array(
			'twuserid'    => $twuserid,
			'traitwareid' => $body['traitwareUserId'],
		);
	}

	/**
	 * Delete a dashboard user from the TraitWare API and also remove the user from the TraitWare table.
	 *
	 * @param $wpid
	 * @param $traitwareid
	 * @return bool
	 */
	public static function delete_user( $wpid, $traitwareid, $cookies ) {
		$args = self::common_args( $cookies );

		$args['method'] = 'DELETE';

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/users/' . $traitwareid,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		traitware_deluser( intval( $wpid ), false ); // general.php.
		return true;
	}

	/**
	 * Delete a user from the TraitWare API and also remove the user from the TraitWare table.
	 *
	 * @param $wpid
	 * @param $traitwareid
     * @param $cookies
	 * @return bool
	 */
	public static function delete_scrub_user( $wpid, $traitwareid, $cookies ) {
		$args = self::common_args( $cookies );

		$args['method'] = 'DELETE';

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/scrubUsers/' . $traitwareid,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		traitware_deluser( intval( $wpid ), true ); // general.php.
		return true;
	}

	/**
	 * Delete a user from the TraitWare API and also remove the user from the TraitWare table (use
	 *
	 * @param $wpid
	 * @param $traitwareid
     * @param $isScrub
	 * @return bool
	 */
	public static function delete_sync_user( $wpid, $traitwareid, $isScrub ) {
		$args = array(
			'method'  => 'DELETE',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
			),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/usersSync/' . $traitwareid,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		traitware_deluser( intval( $wpid ), $isScrub ); // general.php.
		return true;
	}

	/**
	 * Update a user with the TraitWare API and update the TraitWare users table.
	 *
	 * @param $user
	 * @param $traitwareid
	 * @param $isAccountOwner
     * @param $cookies
	 * @return bool
	 */
	public static function update_user( $user, $traitwareid, $isAccountOwner, $cookies ) {
		global $wpdb;

		$current_usertype = traitware_getUserType( $user->ID );

		if ( $current_usertype !== 'dashboard' ) {
			$delete_result = self::delete_scrub_user( $user->ID, $traitwareid, $cookies );

			if ( ! $delete_result ) {
				return false;
			}

			$create_result = self::create_new_user( $user, $user->ID, $cookies );

			if ( ! $create_result ) {
				return false;
			}

			$traitwareid = $create_result['traitwareid'];
		}

		$args = self::common_args( $cookies );

		$data                      = array();
		$data['firstName']         = $user->user_firstname;
		$data['lastName']          = $user->user_lastname;
		$data['emailAddress']      = $user->user_email;
		$data['mobilePhone']       = '';
		$data['isAccountOwner']    = $isAccountOwner;
		$data['userName']          = $user->user_login;
		$data['wordpressUserMeta'] = traitware_getUserMeta( intval( $user->ID ) ); // refresh this.

		$args['method'] = 'PUT';
		$args['body']   = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/users/' . $traitwareid,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		// update accountowner in traitwareusers table
		$data = array(
			'accountowner' => ( $isAccountOwner ? 1 : 0 ),
			'usertype'     => 'dashboard',
		);

		$wpdb->update(
			$wpdb->prefix . 'traitwareusers',
			$data,
			array( 'userid' => intval( $user->ID ) ),
			array( '%d', '%s' )
		);

		return true;
	}

	/**
	 * Update a user with the TraitWare API and update the TraitWare users table.
	 *
	 * @param $user
	 * @param $traitwareid
	 * @param $isAccountOwner
     * @param $cookies
	 * @return bool
	 */
	public static function updateScrubUser( $user, $traitwareid, $isAccountOwner, $cookies ) {
		global $wpdb;

		$current_usertype = traitware_getUserType( $user->ID );

		if ( 'scrub' !== $current_usertype ) {
			$update_result = self::update_user( $user, $traitwareid, false, $cookies );

			if ( ! $update_result ) {
				return false;
			}

			$delete_result = self::delete_user( $user->ID, $traitwareid, $cookies );

			if ( ! $delete_result ) {
				return false;
			}

			$create_result = self::create_new_scrub_user( $user, $user->ID, $cookies );

			if ( ! $create_result ) {
				return false;
			}
		}

		$args = self::common_args( $cookies );

		$data                      = array();
		$data['firstName']         = $user->user_firstname;
		$data['lastName']          = $user->user_lastname;
		$data['emailAddress']      = $user->user_email;
		$data['mobilePhone']       = '';
		$data['isAccountOwner']    = $isAccountOwner;
		$data['userName']          = $user->user_login;
		$data['wordpressUserMeta'] = traitware_getUserMeta( intval( $user->ID ) ); // refresh this.

		$args['method'] = 'PUT';
		$args['body']   = wp_json_encode( $data );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ) . '/scrubUsers/' . $traitwareid,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		// update accountowner in traitwareusers table.
		$data = array(
			'accountowner' => ( $isAccountOwner ? 1 : 0 ),
			'usertype'     => 'scrub',
		);

		$wpdb->update(
			$wpdb->prefix . 'traitwareusers',
			$data,
			array( 'userid' => intval( $user->ID ) ),
			array( '%d', '%s' )
		);

		return true;
	}

	/**
	 * Deactivate the TraitWare installation via the API.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		// API 1E.
		$args     = array(
			'method'  => 'DELETE',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
			),
		);
		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_removeSites' ) . get_option( 'traitware_client_id' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== $response['response']['code'] && 204 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get list of sites attached to TraitWare account.
	 *
	 * @param $cookies
	 * @return array|mixed|null|object
	 */
	public static function get_sites( $cookies ) {
		// get a list of all sites from this account.
		$args = self::common_args( $cookies );

		$args['method'] = 'GET';

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== $response['response']['code'] ) {
			return null;
		}

		$body = json_decode( $response['body'], true );
		return $body;
	}

	/**
	 * Create a WordPress account with the TraitWare API.
	 *
	 * @param $user
	 * @param $phone
	 * @return bool
	 */
	public static function create_account( $user, $phone ) {
		$emailparts = explode( '@', $user->user_email );

		// accountName will be taken from the email address.
		$account_name = ucfirst( $emailparts[0] );
		if ( substr( $account_name, -1 ) == 's' ) {
			$account_name = $account_name . "'"; // Travis' and not Travis's.
		} else {
			$account_name = $account_name . "'s";
		}

		// API 1D.
		$data = array(
			'accountName'  => $account_name . ' Account',
			'firstName'    => $user->user_firstname,
			'lastName'     => $user->user_lastname,
			'emailAddress' => $user->user_email,
			'mobilePhone'  => $phone,
		);

		// send.
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_accounts' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Update multiple users with the API.
	 *
	 * @param $changedUsers
	 * @return bool
	 */
	public static function update_users( $changedUsers ) {
		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ),
			array(
				'headers' => array(
					'Content-type'  => 'application/json',
					'Accept'        => 'application/json',
					'client_id'     => get_option( 'traitware_client_id' ),
					'client_secret' => get_option( 'traitware_client_secret' ),
				),
				'method'  => 'PUT',
				'body'    => wp_json_encode(
					array(
						'wordpressUserMeta' => wp_json_encode( traitware_getAllUserMeta() ),
						'redirectUri'       => get_site_url(),
						'users'             => $changedUsers,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Using $redirect_uri, attempt to get login state initializer.
	 *
	 * @param $redirect_uri
	 * @return array|null
	 */
	public static function console_login( $redirect_uri ) {
	    $redirect_uri_escaped = esc_url_raw( wp_unslash( $redirect_uri . '&format=json' ) );
		$res = wp_remote_request(
            $redirect_uri_escaped,
			array()
		);

		if ( is_wp_error( $res ) || ! isset( $res['response'] ) || ! isset( $res['response']['code'] ) ) {
			return null; }

		if ( 200 !== $res['response']['code'] ) {
			return null;
		}
		$body = json_decode( $res['body'], true );
		if ( is_null( $body ) ) {
			return null;
		}

		$traitwareUserId = '';
		if ( isset( $body['traitwareUserId'] ) ) {
			$traitwareUserId = $body['traitwareUserId'];
		}
		$crumb = '';
		if ( isset( $body['crumb'] ) ) {
			$crumb = $body['crumb'];
		}
		$sidname = '';
		$sid     = '';
		$match   = 'sid_';
		foreach ( $res['cookies'] as $cookie ) {
			if ( substr( $cookie->name, 0, strlen( $match ) ) === $match ) {
				$sidname = $cookie->name;
				$sid     = $cookie->value;
			}
		}

		return array(
			'traitwareUserId' => $traitwareUserId,
			'crumb'           => $crumb,
			'sid'             => $sid,
			'sidname'         => $sidname,
		);
	}

	/**
	 * Create WordPress site with the API. This also creates the local TraitWare user and login.
	 *
     * @param $user
     * @param $cookies
	 * @return array - Will return associative array with twuserid OR an error.
	 */
	public static function create_site( $user, $cookies ) {
		$userData = array(
			'firstName'         => $user->user_firstname,
			'lastName'          => $user->user_lastname,
			'emailAddress'      => $user->user_email,
			'mobilePhone'       => '',
			'isAccountOwner'    => true,
			'userName'          => $user->user_login,
			'wordpressUserMeta' => traitware_getUserMeta( intval( $user->ID ) ),
		);

		$body = array(
			'siteName'          => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'wordpressUserMeta' => wp_json_encode( traitware_getAllUserMeta() ),
			'users'             => array( $userData ),
			'redirectUri'       => get_site_url(),
			'returnUri'         => get_site_url(),
		);

		$args = self::common_args( $cookies );

		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $body );

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Connection to TraitWare failed (4)' );
		}

		$body = json_decode( $response['body'], true );
		if ( is_null( $body ) ) {
			return array( 'error' => 'Connection to TraitWare failed (0)' );
		}

		if ( 200 !== $response['response']['code'] ) {
			return array( 'error' => 'Connection to TraitWare failed (1)' );
		}

		if (
			! isset( $body['users'][0]['emailAddress'] ) ||
			! isset( $body['users'][0]['traitwareUserId'] ) ||
			! isset( $body['client_id'] ) ||
			! isset( $body['client_secret'] )
		) {
			return array( 'error' => 'Connection to TraitWare failed (2)' );
		}

		if (
			$body['users'][0]['emailAddress'] != $user->user_email ||
			empty( $body['users'][0]['traitwareUserId'] ) ||
			empty( $body['client_id'] ) ||
			empty( $body['client_secret'] )
		) {
			return array( 'error' => 'Connection to TraitWare failed (3)' ); }

		$twuser = array(
			'userid'        => $user->ID,
			'traitwareid'   => $body['users'][0]['traitwareUserId'],
			'activeaccount' => 1,
			'accountowner'  => 1,
			'recoveryhash'  => '',
			'usertype'      => 'dashboard',
			'params'        => '{}',
		);

		$twuserid = traitware_adduser( $twuser ); // general.php.

		// the user scanned so add to traitwarelogins table.
		traitware_addlogin( $twuserid );

		update_option( 'traitware_active', '1' );
		update_option( 'traitware_client_id', $body['client_id'] );
		update_option( 'traitware_client_secret', $body['client_secret'] );

		return array(
			'twuserid' => $twuserid,
		);
	}

	/**
	 * Create the common arguments (auth cookies) used for various API calls.
	 *
	 * @param $cookies
	 * @return array
	 */
	private static function common_args($cookies ) {
		return array(
			'headers' => array(
				'Content-type' => 'application/json',
				'Accept'       => 'application/json',
				'X-CSRF-Token' => $cookies['crumb'],
			),
			'cookies' => array(
				'crumb'             => $cookies['crumb'],
				$cookies['sidname'] => $cookies['sid'],
			),
		);
	}

	/**
	 * Resend the activation email to a TraitWare user.
	 *
	 * @param $traitwareid
	 * @param $cookies
	 * @return bool
	 */
	public static function resend_email( $traitwareid, $cookies ) {
		// common headers for each API call
		$args = self::common_args( $cookies );

		$args['method'] = 'PUT';

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . str_replace( '{traitwareUserId}', $traitwareid, traitware_get_var( 'api_activationCode' ) ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Send a TraitWare recovery email.
	 *
	 * @param $twuser
	 * @param $email
	 * @param $path
	 * @param $recoveryHash
	 * @return array - Will contain a key "error" if there is an error, otherwise an empty array.
	 */
	public static function recovery( $twuser, $email, $path, $recoveryHash ) {
		global $wpdb;

		$data = array(
			'emailAddress' => $email,
			'recoveryLink' => $path . '?recovery=' . $recoveryHash,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_recoverAccount' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => 'Email failed to send: ' . $response->get_error_code(),
			);
		}

		$httpcode = $response['response']['code'];

		if ( 204 !== $httpcode ) {
			return array(
				'error' => 'Email failed to send: ' . $httpcode,
			);
		}

		// save to db to check later
		$wpdb->update(
			$wpdb->prefix . 'traitwareusers',
			array( 'recoveryhash' => $recoveryHash . ' ' . time() ),
			array( 'id' => $twuser->id ),
			array( '%s' )
		);

		return array();
	}

	/**
	 * Send a TraitWare recovery email for Scrub users.
	 *
	 * @param $twuser
	 * @param $email
	 * @param $path
	 * @param $recoveryHash
	 * @return array - Will contain a key "error" if there is an error, otherwise an empty array.
	 */
	public static function recovery_scrub( $twuser, $email, $path, $recoveryHash ) {
		global $wpdb;

		if ( 1 === intval( get_option( 'traitware_disablecustomloginrecovery' ) ) ) {
			return array(
				'error' => 'User is not allowed to use recovery',
			);
		}

		$data = array(
			'emailAddress' => $email,
			'recoveryLink' => $path . '?recovery=' . $recoveryHash,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_recoverScrubAccount' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => 'Email failed to send: ' . $response->get_error_code(),
			);
		}

		$httpcode = $response['response']['code'];

		if ( 204 !== $httpcode ) {
			return array(
				'error' => 'Email failed to send: ' . $httpcode,
			);
		}

		// save to db to check later
		$wpdb->update(
			$wpdb->prefix . 'traitwareusers',
			array( 'recoveryhash' => $recoveryHash . ' ' . time() ),
			array( 'id' => $twuser->id ),
			array( '%s' )
		);

		return array();
	}

	/**
	 * Grant a token for logging in
	 *
	 * @param $redirect_uri
	 * @return bool|string - Returns true on success, otherwise returns the error string
	 */
	public static function token( $redirect_uri ) {
		$parts = wp_parse_url( $redirect_uri );
		parse_str( $parts['query'], $uridata );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'client_id'     => get_option( 'traitware_client_id' ),
					'client_secret' => get_option( 'traitware_client_secret' ),
					'code'          => trim( $uridata['code'] ),
					'grant_type'    => 'token',
				)
			),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_token' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return 'Connection to TraitWare failed (4)';
		}

		$body = json_decode( $response['body'], true );

		if ( is_null( $body ) || 200 !== $response['response']['code'] ) {
			return 'Connection to TraitWare failed (1)';
		}

		if ( ! isset( $body['access_token'] ) || ! isset( $body['expires_in'] ) ) {
			return 'Connection to TraitWare failed (2)';
		}

		if ( isset( $body['state'] ) && $body['state'] !== $uridata['state'] ) {
		    return 'Connection to TraitWare failed (state)';
		}

		if ( empty( $body['access_token'] ) ) {
			return 'Connection to TraitWare failed (3)';
		}

		return true;
	}

	/**
	 * Update WordPress Site User Metadata
	 *
	 * @param $wpuserid
	 * @return bool
	 */
	public static function update_site_user( $wpuserid ) {
		// after each login we need to update the traitware server with usermeta info and the current domain for direct login.
		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_sites' ) . '/' . get_option( 'traitware_client_id' ),
			array(
				'headers' => array(
					'Content-type'  => 'application/json',
					'Accept'        => 'application/json',
					'client_id'     => get_option( 'traitware_client_id' ),
					'client_secret' => get_option( 'traitware_client_secret' ),
				),
				'method'  => 'PUT',
				'body'    => wp_json_encode(
					array(
						'wordpressUserMeta' => wp_json_encode( traitware_getUserMeta( intval( $wpuserid ) ) ),
						'redirectUri'       => get_site_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	public static function report_error( $error ) {

		global $wp;
		$wp_url = home_url( add_query_arg( $_GET, $wp->request ) );
		$raw_url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$user_id = get_current_user_id();
		$current_screen = get_current_screen();
		$current_filter = current_filter();
		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins' );
		$current_theme = wp_get_theme();

		$data = array(
			'raw_url' => $raw_url,
			'current_screen'    => $current_screen,
			'current_filter'    => $current_filter,
			'all_plugins'       => $all_plugins,
			'active_plugins'    => $active_plugins,
			'current_theme'     => $current_theme,
			'tw_version'        => traitware_get_var( 'version' ),
			'request_data'         => $_REQUEST,
			'trace'             => debug_backtrace()
		);

		$user_data = $user_id ? get_userdata( $user_id ) : null;
		$user_login = $user_data ? $user_data->user_login : null;

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'client_id'     => get_option( 'traitware_client_id' ),
				'client_secret' => get_option( 'traitware_client_secret' ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'body'          => wp_json_encode(
				array(
					'client_id' => get_option( 'traitware_client_id' ),
					'site'      => get_site_url(),
					'username'  => $user_login,
					'url'       => $wp_url,
					'error'     => $error,
					'data'      => wp_json_encode( $data )
				)
			),
		);

		$response = wp_remote_request(
			traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_errorReport' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 204 !== $response['response']['code'] ) {
			return false;
		}

		return true;
	}
}
Traitware_API::init();
