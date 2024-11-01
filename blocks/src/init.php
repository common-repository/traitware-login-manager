<?php
/**
 * Blocks Initializer
 *
 * Enqueue CSS/JS of all the blocks.
 *
 * @since   1.0.0
 * @package CGB
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Gutenberg block assets for both frontend + backend.
 *
 * `wp-blocks`: includes block type registration and related functions.
 *
 * @since 1.0.0
 */
function traitware_blocks_assets() {
	if ( ! traitware_is_active() ) {
		return;
	}

	$is_onlywplogin = get_option('traitware_enableonlywplogin');
	if ($is_onlywplogin) {
		return;
	}

	$traitware_vars = traitware_getJsVars();
	$rnd            = (string) wp_rand( 100000, 999999 );
	wp_enqueue_script( 'jquery' );
	wp_register_script( 'traitware.js', plugins_url( 'traitware-login-manager/res/traitware.js?r=' . $rnd ), array( 'jquery' ), false, false );
	wp_localize_script( 'traitware.js', 'traitware_vars', $traitware_vars );
	wp_enqueue_script( 'traitware.js', '', array(), false, true );
	wp_register_style( 'traitware.css', plugins_url( 'traitware-login-manager/res/traitware.css?r=' . $rnd ) );
	wp_enqueue_style( 'traitware.css' );
	// Styles.
	wp_enqueue_style(
		'traitware-block-style-css', // Handle.
		plugins_url( 'dist/blocks.style.build.css', dirname( __FILE__ ) ), // Block style CSS.
		array( 'wp-blocks' ) // Dependency to include the CSS after it.
		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.style.build.css' ) // Version: filemtime — Gets file modification time.
	);
} // End function traitware_cgb_block_assets().

// Hook: Frontend assets.
add_action( 'enqueue_block_assets', 'traitware_blocks_assets' );

/**
 * Enqueue Gutenberg block assets for backend editor.
 *
 * `wp-blocks`: includes block type registration and related functions.
 * `wp-element`: includes the WordPress Element abstraction for describing the structure of your blocks.
 * `wp-i18n`: To internationalize the block's text.
 *
 * @since 1.0.0
 */
function traitware_editor_assets() {

	$is_onlywplogin = get_option('traitware_enableonlywplogin');
	if ($is_onlywplogin) {
		return;
	}

	global $wp_roles;

	$rnd       = (string) wp_rand( 100000, 999999 );
	$all_roles = $wp_roles->roles;
	$roles     = array();
	foreach ( $all_roles as $role => $details ) {
		$name           = translate_user_role( $details['name'] );
		$roles[ $role ] = $name;
	}

	wp_register_script(
		'traitware-block-js', // Handle.
		plugins_url( '/dist/blocks.build.js?r=' . $rnd, dirname( __FILE__ ) ), // Block.build.js: We register the block here. Built with Webpack.
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ), // Dependencies, defined above.
		false,
		true // Enqueue the script in the footer.
	);

	$forms      = array();
	$form_pages = traitware_getFormPages();
	foreach ( $form_pages as $form ) {
		$forms[] = array(
			'id'    => $form->ID,
			'title' => esc_attr( $form->post_title ),
		);
	}

	$obj = array(
		'roles' => $roles,
		'forms' => $forms,
	);

	wp_localize_script( 'traitware-block-js', 'traitware_block_obj', $obj );
	wp_enqueue_script( 'traitware-block-js', '', array(), false, false );

	wp_enqueue_style(
		'traitware-editor-css', // Handle.
		plugins_url( 'dist/blocks.editor.build.css' . $rnd, dirname( __FILE__ ) ), // Block editor CSS.
		array( 'wp-edit-blocks' ) // Dependency to include the CSS after it.
	);
}

// Hook: Editor assets.
add_action( 'enqueue_block_editor_assets', 'traitware_editor_assets' );

if ( function_exists( 'register_block_type' ) ) {
	add_action( 'init', 'traitware_register_blocks' );
	/**
	 * Registers TraitWare blocks.
	 */
	function traitware_register_blocks() {
		register_block_type(
			'traitware/traitware-protected-content-block',
			array(
				'editor_script'   => 'traitware-block-js',
				'editor_style'    => 'traitware-block-css',
				'render_callback' => 'traitware_protected_page_block_render_content',
			)
		);

		$form_pages     = traitware_getFormPages();
		$form_attribute = empty( $form_pages ) ? array(
			'type' => 'number',
		) : array(
			'type'    => 'number',
			'default' => $form_pages[0]->ID,
		);

		register_block_type(
			'traitware/traitware-form-block',
			array(
				'editor_script'   => 'traitware-block-js',
				'editor_style'    => 'traitware-block-css',
				'render_callback' => 'traitware_form_block_render_content',
				'attributes'      => array(
					'form' => $form_attribute,
				),
			)
		);
	}
}

/**
 * Render function for the protected page block.
 *
 * @param array  $attributes
 * @param string $content
 * @return string
 */
function traitware_protected_page_block_render_content( $attributes, $content = '' ) {
	$is_onlywplogin = get_option('traitware_enableonlywplogin');
	if ($is_onlywplogin) {
		return "";
	}

	$roles = isset( $attributes['roles'] ) ? json_decode( $attributes['roles'] ) : array();

	if ( is_user_logged_in() ) {
		if ( ! Traitware_Frontend::has_access_role( $roles ) ) {
			$protected_html = Traitware_Frontend::get_protected_html( $roles );
			return $protected_html;
		}
		return str_replace( '{{traitware_role}}', '', $content );
	}
	if ( Traitware_Frontend::$shortcode_qr_count > 0 ) {
		return '';
	}
	Traitware_Frontend::$shortcode_qr_count++;

	$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
	$traitware_redirecturl = wp_json_encode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
	$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_authorization' ) . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=' ) );
	$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

	// If user is not logged in, display a QR code to login.
	$login_form_stub         = '<div class=\'traitware_protected_container\'><div class=\'traitware_forms_authbox\'><div class=\'traitware_forms_inner_authbox\'>';
	$login_form_stub        .= '<div class=\'traitware_protected_titletext\'>Login with TraitWare</div><div class=\'traitware_protected_welcometext\'>Scan the QR below with your mobile device to access content</div>';
	$login_form_stub        .= '<img class=\'traitware-protected-qrcode\' /><div class=\'traitware-scan-response\'></div>';
	$login_form_stub        .= '</div></div></div>';
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
  		var html = "<div class=\'traitware-login-box\'><p>Login with TraitWare</p>";
					html += "<button type=\'button\' class=\'traitware-login-button\'>Click to Login with TraitWare</button>";
					html += "<img class=\'traitware-qrcode traitware-qrcode-with-button\' /><div class=\'traitware-scan-response\'></div>";
					html += "</div>";
					traitware_enable_polling();
					jQuery("#login #loginform").prepend( html );
					jQuery("#user_login").val( "" );
					jQuery("#loginform .submit").append( "<input type=\'hidden\' name=\'recovery\' value=\'\' />" );
					traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
					window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
					traitware_qrcode();
				});
			</script>

		';
	return $login_form_stub . $traitware_login_payload;
}

/**
 * Render function for forms.
 *
 * @param array  $attributes
 * @param string $content
 * @return string
 */
function traitware_form_block_render_content( $attributes, $content = '' ) {
	$is_onlywplogin = get_option('traitware_enableonlywplogin');
	if ($is_onlywplogin) {
		return "";
	}

	if ( ! isset( $attributes['form'] ) ) {
		return '';
	}

	$form = max( 0, (int) $attributes['form'] );
	return traitware_form_html( $form );
}
