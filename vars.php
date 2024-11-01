<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! function_exists( 'traitware_get_vars' ) ) {
	/**
	 * Get all TraitWare variables.
	 * @return array
	 */
	function traitware_get_vars() {
		return array(
			'apiurl'                   => 'https://api.traitware.com/',
			'api_consoleAuthorization' => 'customer-api/v1/oauth2/consoleAuthorization', // qr for backend.
			'api_authorization'        => 'customer-api/v1/oauth2/authorization', // qr for wplogin.
			'api_accounts'             => 'wordpress-api/v1/accounts',
			'api_loginAttempts'        => 'customer-api/v1/loginAttempts/',
			'api_sites'                => 'wordpress-api/v1/sites',
			'api_scrubUsers'           => 'wordpress-api/v1/scrubUsers',
			'api_token'                => 'customer-api/v1/oauth2/token',
			'api_removeSites'          => 'wordpress-api/v1/sites/',
			'api_recoverAccount'       => 'wordpress-api/v1/recovery/recoverAccount',
			'api_recoverScrubAccount'  => 'wordpress-api/v1/recovery/recoverScrubUser',
			'api_activationCode'       => 'wordpress-api/v1/users/{traitwareUserId}/activationCode',
			'api_users'                => 'wordpress-api/v1/users',
			'api_errorReport'          => 'wordpress-api/v1/errorReport',
			'version'                  => '1.8.5',
			'libpath'                  => __DIR__ . '/lib/',
			'blockspath'               => __DIR__ . '/blocks/',
			'pluginurl'                => 'wp-content/plugins/traitware-login-manager/',
			'scrubRecoveryLimit'       => 3,
			'scrubRecoveryTimeout'     => 60 * 60 * 24,
			'downloadFrequency'        => 60 * 30, // download scrubUser changes once every half hour.
			'scrubPageLimit'           => 150,
			'bulksyncPageLimit'        => 150,
			'bulksyncCookiesTimeout'   => 60 * 60 * 4, // cookies should be good for 4 hours.
			'backgroundTimeout'        => 60, // how much time should the background task lock for in the event of a slow load.
			'downloadEnabled'          => true,
		);
	}
}

if ( ! function_exists( 'traitware_get_var' ) ) {
	/**
	 * Get a TraitWare variable by key.
	 * @param $key
	 * @return mixed|null
	 */
	function traitware_get_var($key ) {
		$vars = traitware_get_vars();

		if ( ! isset( $vars[ $key ] ) ) {
			return null;
		}

		return $vars[ $key ];
	}
}
