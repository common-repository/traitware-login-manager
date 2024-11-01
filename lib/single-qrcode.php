<?php
/**
 * @package TraitWare
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '' );
}

require_once dirname(__FILE__) . '/simple-html-dom.php';

ob_start();
Traitware_Frontend::toggle_single_hook( false );

require Traitware_Frontend::$template;

Traitware_Frontend::toggle_single_hook( true );

$html_only = ob_get_contents();
ob_end_clean();

$protected_page_selector = get_option( 'traitware_protectedpageselector' );
if ( false === $protected_page_selector || is_null( $protected_page_selector ) || empty( $protected_page_selector ) ) {
	$protected_page_selector = '#primary';
}

$traitware_form_under = get_post_meta( $post->ID, '_traitware_protected_page_form_meta_key', true );
$traitware_form_under = max( 0, (int) $traitware_form_under );

$form_pages = traitware_getFormPages();

$traitware_form_under_is_valid = false;

foreach ($form_pages as $form_page ) {
	if ( $form_page->ID === $traitware_form_under ) {
		$traitware_form_under_is_valid = true;
		break;
	}
}

$traitware_form_under_html = '';

if ( $traitware_form_under_is_valid ) {
	$traitware_form_under_html = traitware_form_html( $traitware_form_under );
}

$xml            = traitware_str_get_html( $html_only );
$target_element = $xml->find( $protected_page_selector );
if ( empty( $target_element ) ) {
	echo $xml;
	die( '' );
} else {
	$target_element = $target_element[0];
}

if ( is_user_logged_in() ) {
	$protected_html  = Traitware_Frontend::get_protected_html( Traitware_Frontend::$roles_required_page );
	$protected_html .= $traitware_form_under_html;

	$links = $target_element->find( 'link' );

	foreach ($links as $links_link ) {
		$protected_html .= $links_link->outertext;
	}

	$scripts = $target_element->find( 'script' );
	foreach ( $scripts as $script ) {
		$protected_html .= $script->outertext;
	}

	$target_element->innertext = $protected_html;
} else {
	// If user is not logged in, display a QR code to login.
	$login_form_stub  = '<div class=\'traitware_protected_container\'><div class=\'traitware_forms_authbox\'><div class=\'traitware_forms_inner_authbox\'>';
	$login_form_stub .= '<div class=\'traitware_protected_titletext\'>Login with TraitWare</div><div class=\'traitware_protected_welcometext\'>Scan the QR below with your mobile device to access content</div>';
	$login_form_stub .= '<img class=\'traitware-protected-qrcode\' /><div class=\'traitware-scan-response\'></div>';
	$login_form_stub .= '</div></div></div>';

	$login_form_stub .= $traitware_form_under_html;


	$target_element->innertext = $login_form_stub;

	$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
	$traitware_redirecturl = wp_json_encode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
	$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_authorization' ) . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=' ) );
	$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

	$traitware_login_payload = '
			<script type="text/javascript">
			
				var traitware_site_url = ' . $traitware_site_url . ';
				var traitware_pollscan_action = "wplogin";
				var traitware_qrclassname = ".traitware-protected-qrcode";
				var traitware_redirectstyle = "url";
				var traitware_redirecturl = ' . $traitware_redirecturl . ';
				var traitware_auth_url = ' . $traitware_auth_url . ';
				var traitware_qrcode_url = ' . $traitware_qrcode_url . ';
				var traitware_poll_url = ' . $traitware_poll_url . ';
				var traitware_payment_url = "&returnUri=' . rawurlencode( wp_login_url() ) . '";
				var traitware_nonce = ' . wp_json_encode( wp_create_nonce('traitware_ajax') ) . ';

				jQuery(function() {
                    jQuery("#user_login").val( "" );
                    jQuery("#loginform .submit").append( "<input type=\'hidden\' name=\'recovery\' value=\'\' />" );
                    traitware_enable_polling();
                    traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
                    window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
                    traitware_qrcode();
                });
            </script>
					
		';

	$body             = $xml->find( 'body' )[0];
	$body->innertext .= $traitware_login_payload;
}

echo $xml;
die( '' );
