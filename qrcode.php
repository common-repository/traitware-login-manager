<?php
/**
 * @package TraitWare
 */

/**
 * Displays a QR code failure.
 */
function traitware_qrcodefailure() {
	header( 'Content-Type: image/png' );
	readfile( 'res/qrcodefailure.png' ); // 222x222 "Unable to create QR Code".
	die();
}

if ( ! isset( $_REQUEST['str'] ) ) {
	traitware_qrcodefailure();
}

$str = trim( $_REQUEST['str'] ); // form of "f9475cad-e609-4aab-afb8-51b205e35035".

// validate to make this useless to leechers.
if ( ! preg_match( '#^[a-z0-9/-]+$#', $str ) ) {
	traitware_qrcodefailure();
}
if ( 36 !== strlen( $str ) ) {
	traitware_qrcodefailure();
}

// create and display GR Code.
define( 'ABSPATH', 'unused' );
require 'lib/phpqrcode.php';
QRcode::png( $str, false, QR_ECLEVEL_H, 6, 0 ); // 222x222.
