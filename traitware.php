<?php
/**
 * TraitWare
 *
 * @package TraitWare
 *
 * @wordpress-plugin
 * Plugin Name: Secure Login with TraitWare
 * Description: With TraitWare you can have the convenience and security to log into the WordPress admin using only your phone.
 * Version: 1.8.5
 * Author: TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! isset( $__traitware_post ) ) {
	/**
	 * Global variable used to track the original POST values before WordPress sanitizes them.
	 */
	$__traitware_post = $_POST;
}

if ( ! isset( $__traitware_files ) ) {
	/**
	 * Global variable used to track the original FILES values before WordPress sanitizes them.
	 */
	$__traitware_files = $_FILES;
}

require_once 'vars.php';

require_once traitware_get_var( 'libpath' ) . 'general.php';

register_activation_hook( __FILE__, 'traitware_activate_hook' );

require_once traitware_get_var( 'libpath' ) . 'class-traitware-api.php';

require_once traitware_get_var( 'libpath' ) . 'class-traitware-frontend.php';

if ( is_admin() ) {
	include_once traitware_get_var( 'libpath' ) . 'class-traitware-admin.php';
}

require_once traitware_get_var( 'blockspath' ) . 'plugin.php';
