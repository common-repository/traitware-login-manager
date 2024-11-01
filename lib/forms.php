<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

function traitware_forms_auth_cookie_expiration( $seconds, $userid, $remember ) {
    return 2 * 7 * 24 * 60 * 60; // two weeks
}

/**
 * Responsible for handling various form-based AJAX actions.
 */
function traitware_forms() {
	$form_action = isset( $_GET['form_action'] ) ? trim( sanitize_key( $_GET['form_action'] ) ) : '';
	$form_id     = isset( $_GET['form_id'] ) ? max( 0, (int) sanitize_key( $_GET['form_id'] ) ) : 0;

	if ( ( ! isset( $_REQUEST['_twnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_twnonce'] ), 'traitware_ajaxforms' ) ) && $form_action !== 'approve' ) {
		echo wp_json_encode(
			array(
				'error' => 'Invalid form request.',
			)
		);
		wp_die();
	}

	$form_post = get_post( $form_id );

	if ( is_null( $form_post ) || ! is_object( $form_post ) || ! isset( $form_post->post_type ) || 'traitware_form' !== $form_post->post_type ) {
		echo wp_json_encode(
			array(
				'error' => 'The form that you have specified is invalid. Please try again shortly.',
			)
		);
		wp_die();
	}

	$valid_actions = array( 'loggedin', 'logout', 'signup', 'optin', 'approve' );

	if ( ! in_array( $form_action, $valid_actions ) ) {
		echo wp_json_encode(
			array(
				'error' => 'The form action that you have specified is invalid. Please try again shortly.',
			)
		);
		wp_die();
	}

	// Load the base options for each tab.
	$self_registration_page = traitware_getSelfRegistrationPageById( $form_id );
	$optin_page             = traitware_getOptInPageById( $form_id );
	$login_page             = traitware_getLoginPageById( $form_id );

	// Are we currently logged in?
	$is_logged_in = is_user_logged_in();

	if ( 'logout' === $form_action ) {
		if ( 'yes' !== $login_page && 'yes' !== $optin_page && 'yes' !== $self_registration_page ) {
			die(
				wp_json_encode(
					array(
						'error' => 'The form you have specified is not a login or opt-in form.',
					)
				)
			);
		}

		wp_logout();

		echo wp_json_encode(
			array(
				'error' => null,
			)
		);
		wp_die();
	}

	if ( 'loggedin' === $form_action ) {
		if ( 'yes' !== $login_page ) {
			echo wp_json_encode(
				array(
					'error' => 'The form you have specified is not a login form.',
				)
			);
			wp_die();
		}

		$login_redirect = trim( traitware_getLoginRedirectById( $form_id ) );

		if ( ! $is_logged_in ) {
			echo wp_json_encode(
				array(
					'error' => 'You are not currently logged in.',
				)
			);
			wp_die();
		}

		echo wp_json_encode(
			array(
				'error'        => null,
				'redirect_url' => $login_redirect,
			)
		);
		wp_die();
	}

	if ( 'signup' === $form_action ) {
		if ( 'yes' !== $self_registration_page ) {
			wp_send_json(
				array(
					'error' => 'The form you have specified is not a signup form.',
				)
			);
		}

		$logged_in_message = traitware_getSelfRegistrationLoggedinById( $form_id );
		if ( empty( $logged_in_message ) ) {
			$logged_in_message = traitware_getSelfRegistrationDefaultLoggedin();
		}

		$already_exists_message = traitware_getSelfRegistrationExistingById( $form_id );
		if ( empty( $already_exists_message ) ) {
			$already_exists_message = traitware_getSelfRegistrationDefaultExisting();
		}

		if ( $is_logged_in ) {
			wp_send_json(
				array(
					'error' => esc_html( $logged_in_message ),
				)
			);
		}

		$uses_username = 'yes' === traitware_getSelfRegistrationUsernameById( $form_id );

		if ( $uses_username ) {
			if ( ! isset( $_REQUEST['username'] ) ) {
				wp_send_json(
					array(
						'error' => 'You must enter a username.',
					)
				);
			}

			$username = sanitize_user( $_REQUEST['username'] );

			if ( ! validate_username( $username ) ) {
				wp_send_json(
					array(
						'error' => 'The username you entered is invalid.',
					)
				);
			}

			if ( username_exists( $username ) !== false ) {
				wp_send_json(
					array(
						'error' => esc_html( $already_exists_message ),
					)
				);
			}
		}

		if ( ! isset( $_REQUEST['email'] ) ) {
			wp_send_json(
				array(
					'error' => 'You must enter an email address.',
				)
			);
		}

		$email = sanitize_email( $_REQUEST['email'] );

		if ( ! is_email( $email ) ) {
			wp_send_json(
				array(
					'error' => 'The email address you entered is invalid.',
				)
			);
		}

		if ( email_exists( $email ) !== false ) {
			wp_send_json(
				array(
					'error' => esc_html( $already_exists_message ),
				)
			);
		}

		$userdata = array(
			'user_login' => $uses_username ? $username : traitware_generate_username( $email ),
			'user_email' => $email,
			'user_pass'  => wp_generate_password(),
			'role'       => traitware_getSelfRegistrationRoleById( $form_id ),
		);

		$userid = wp_insert_user( $userdata );

		if ( is_wp_error( $userid ) ) {
			wp_send_json(
				array(
					'error' => 'An error occurred while creating your account. Please try again later.',
				)
			);
		}

		$user = get_user_by( 'id', $userid );
		if ( ! $user ) {
			wp_send_json(
				array(
					'error' => 'An error occurred while creating your account. Please try again later.',
				)
			);
		}

		if ( ! Traitware_API::create_new_scrub_user_forms( $user, $userid ) ) {
			wp_send_json(
				array(
					'error' => 'An error occurred while creating your account. Please try again later.',
				)
			);
		}

		$notification = traitware_getSelfRegistrationApprovalById( $form_id );
		if ( 'yes' === $notification ) {
			traitware_create_self_registration_approval( $form_id, $user );
		} elseif ( 'notification' === $notification ) {
			traitware_send_self_registration_notification( $user );
		}

        // set cookie expitation to two weeks for TraitWare logins only. Leave normal logins alone
        add_filter( 'auth_cookie_expiration', 'traitware_forms_auth_cookie_expiration', 99, 3 );

		wp_set_current_user( $userid, $user->user_login );
		wp_set_auth_cookie( $userid );
		do_action( 'wp_login', $user->user_login, $user );

        // remove the above cookie filter after login
        remove_filter( 'auth_cookie_expiration', 'traitware_forms_auth_cookie_expiration', 99 );

		wp_send_json_success();
	}

	if ( 'optin' === $form_action ) {
		if ( 'yes' !== $optin_page ) {
			wp_send_json(
				array(
					'error' => 'The form you have specified is not an opt-in form.',
				)
			);
		}

		$user = null;
		if ( $is_logged_in ) {
			$user = wp_get_current_user();
		} else {

			if ( ! isset( $_REQUEST['username'] ) ) {
				wp_send_json(
					array(
						'error' => 'You must enter your username or email.',
					)
				);
			}

			if ( ! isset( $_REQUEST['password'] ) ) {
				wp_send_json(
					array(
						'error' => 'You must enter your password.',
					)
				);
			}

			$password = $_REQUEST['password'];

			$usingEmail = false;
			$user       = false;
			if ( is_email( $_REQUEST['username'] ) ) {
				$email      = sanitize_email( $_REQUEST['username'] );
				$user       = get_user_by( 'email', $email );
				$usingEmail = true;
			} elseif ( validate_username( $_REQUEST['username'] ) ) {
				$username = sanitize_user( $_REQUEST['username'] );
				$user     = get_user_by( 'login', $username );
			} else {
				wp_send_json(
					array(
						'error' => 'You did not enter a valid username or email.',
					)
				);
			}

			if ( ! $user ) {
				if ( $usingEmail ) {
					wp_send_json(
						array(
							'error' => 'A user does not exist with the specified email.',
						)
					);
				} else {
					wp_send_json(
						array(
							'error' => 'A user does not exist with the specified username.',
						)
					);
				}
			}

			if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
				wp_send_json(
					array(
						'error' => 'Invalid credentials.',
					)
				);
			}
		}

		if ( traitware_is_tw_user( $user ) ) {
			$existing = traitware_getOptInExistingById( $form_id );
			if ( $existing === '' ) {
				$existing = traitware_getOptInDefaultExisting();
			}
			wp_send_json(
				array(
					'error' => esc_html( $existing ),
				)
			);
		}

		if ( ! Traitware_API::create_new_scrub_user_forms( $user, $user->ID ) ) {
			wp_send_json(
				array(
					'error' => 'An error occurred while opting-in your account. Please try again later.',
				)
			);
		}

		$notification = traitware_getOptInNotificationById( $form_id );
		if ( 'yes' === $notification ) {
			traitware_send_optin_notification( $user );
		}

		if ( ! $is_logged_in ) {
            // set cookie expitation to two weeks for TraitWare logins only. Leave normal logins alone
            add_filter( 'auth_cookie_expiration', 'traitware_forms_auth_cookie_expiration', 99, 3 );

			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID );
			do_action( 'wp_login', $user->user_login, $user );

            // remove the above cookie filter after login
            remove_filter( 'auth_cookie_expiration', 'traitware_forms_auth_cookie_expiration', 99 );
		}

		wp_send_json_success();
	}

	if ( 'approve' === $form_action ) {
		if ( ! isset( $_GET['user_id'] ) || ! isset( $_GET['hash'] ) ) {
			wp_die( 'Forbidden' );
		}

		$user_id = sanitize_key( $_GET['user_id'] );
		$hash    = $_GET['hash'];

		wp_die( traitware_approve_self_registration_approval( $form_id, get_user_by( 'id', $user_id ), $hash ) );
	}
}

traitware_forms();
