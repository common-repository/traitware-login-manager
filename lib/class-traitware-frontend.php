<?php
/**
 * The frontend related code.
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

/**
 * Class Traitware_Frontend
 */
class Traitware_Frontend {
    public static $single_hooked = true;
    public static $shortcode_qr_count = 0;
    public static $roles_required_page = array();
    public static $template = null;
    public static $protected_page_id = null;

	public static function init() {
		if (is_admin()) { return; }
		if (!session_id()) { session_start(); }

		// res files
		add_action('init', array(__CLASS__, 'wp_register_script'));

		// 'login_head' is inside head tag for all login/register/... but AFTER jquery loads
		add_action('login_head', array(__CLASS__, 'login_form'));

		// direct login: https://codex.wordpress.org/Plugin_API/Action_Reference/wp_loaded
		add_action('wp_loaded', array(__CLASS__, 'wp_loaded'));
        // filter the template_include call so we can prevent unauthorized access and display traitware forms
        add_filter('template_include', array(__CLASS__, 'filter_template_include'), 99999, 1);

        // filter is_active_sidebar when we are on a traitware_form
        add_filter('is_active_sidebar', array(__CLASS__, 'is_active_sidebar_check'), 10, 2);

        // add the shortcode for protected pages
        add_shortcode('traitware', array(__CLASS__, 'protected_shortcode_callback'));

        // add the shortcode for traitware forms
        add_shortcode('traitwareform', array(__CLASS__, 'form_shortcode_callback'));
	}

	public static function is_active_sidebar_check($is_active, $sidebar_check) {
	    global $post;

	    if(!is_object($post) || !isset($post->post_type) ) {
	        return $is_active;
        }

        if($post->post_type === 'traitware_form') {
            return false;
        }

        return $is_active;
    }

	public static function filter_template_include($template) {
	    $is_single = is_single();
	    $is_page = is_page();
		$is_onlywplogin = get_option('traitware_enableonlywplogin');

	    if(!$is_single && !$is_page) {
	        return $template;
        }

        if ($is_onlywplogin) {
        	return $template;
        }

        global $post;

	    if($is_single && $post->post_type === 'traitware_form') {
            if($theme_file = locate_template(array('single-traitware-form.php'))) {
                $template = $theme_file;
            } else {
                $template = plugin_dir_path(__FILE__) . '/single-traitware-form.php';
            }

            return $template;
        }

        if(!self::$single_hooked) {
            return $template;
        }
        $current_limitaccesspts = get_option('traitware_limitaccesspts');
        if($current_limitaccesspts === false || $current_limitaccesspts === null || !is_array($current_limitaccesspts)) {
            $current_limitaccesspts = array('post', 'page');
        }
        if(!in_array($post->post_type, $current_limitaccesspts)) {
            return $template;
        }
        $current_roles = get_post_meta($post->ID, '_traitware_protected_page_roles_meta_key', true);
        if($current_roles === false || $current_roles === null || empty($current_roles)) {
            return $template;
        }
        $has_access_role = self::has_access_role($current_roles);
        if(!$has_access_role) {
            Traitware_Frontend::$roles_required_page = $current_roles;
            Traitware_Frontend::$template = $template;
            Traitware_Frontend::$protected_page_id = $post->ID;
            return dirname(__FILE__) . '/single-qrcode.php';
        }
        return $template;
    }

	public static function wp_register_script() {
		if (!traitware_is_active()) { return; }
		$is_onlywplogin = get_option('traitware_enableonlywplogin');
		if ($is_onlywplogin && !traitware_isbackendlogin()) {
			return;
		}
		//if (!traitware_isbackendlogin()) { return; }
        $traitware_vars = traitware_getJsVars();
		$version = traitware_get_var( 'version' );
		wp_enqueue_script('jquery');
		wp_register_script( 'traitware.js', plugins_url( 'traitware-login-manager/res/traitware.js' ), array( 'jquery' ), $version );
        wp_localize_script( 'traitware.js', 'traitware_vars', $traitware_vars );
		wp_enqueue_script( 'traitware.js', '', array(), false, true );
		wp_register_style( 'traitware.css', plugins_url( 'traitware-login-manager/res/traitware.css' ), array(), $version );
		wp_enqueue_style( 'traitware.css' );
	}

	public static function wp_loaded() {
		if (!traitware_is_active()) { return; }

		// direct login from phone
		if ( isset( $_GET['code'], $_GET['traitwareUserId'], $_GET['emailAddress'], $_GET['clientId'] ) ) {
			include 'pollscan_wplogin.php';
			$error = traitware_wplogin( esc_url_raw( wp_unslash( (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) ) );
			if ( strlen( $error ) === 0 ) {;
				wp_redirect( get_dashboard_url() );
				exit();
			}
		}
	}

	// deals with the recovery process as well. recoveryemail >> spacial email link to this page >> $addrecover = 0
	public static function login_form() {
		if (!traitware_is_active()) { return; }
		if (!traitware_isbackendlogin()) { return; }
		$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
		$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
		$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var('apiurl') . traitware_get_var('api_authorization') . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=' ) );
		$traitware_poll_url =  wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

		echo '
			<script type="text/javascript">

				var traitware_site_url = ' . $traitware_site_url . ';
				var traitware_pollscan_action = "wplogin";
				var traitware_qrclassname = ".traitware-qrcode";
				var traitware_auth_url = ' . $traitware_auth_url . ';
				var traitware_qrcode_url = ' . $traitware_qrcode_url . ';
				var traitware_poll_url = ' . $traitware_poll_url . ';
				var traitware_payment_url = "&returnUri=' . rawurlencode(wp_login_url()) . '";
				var traitware_nonce = ' . wp_json_encode( wp_create_nonce('traitware_ajax') ) . ';
				jQuery(function() {

		';

		$addrecover = false;
		$login = '';
		$recovery = '';
		if (get_option( 'traitware_disablewplogin' ) == 1) {
			$addrecover = true;
			if ( isset( $_REQUEST['recovery'] ) ) {
				$recovery = trim( sanitize_key( $_REQUEST['recovery'] ) );
				$login = self::validate_recovery( $recovery );
				if ( strlen( $login ) === 0 ) { $recovery = ''; } // invalid
				else { $addrecover = false; } // valid, show wp login form
			}
		}
		if ( $addrecover ) { // do not display wp login form
			echo '
					var html = "<div class=\'traitware-login-box\'><p>Login with TraitWare";
					html += " (<a href=\'javascript:void(0);\' id=\'traitwarerecovery\'>trouble?</a>)";
					html += "<button type=\'button\' class=\'traitware-login-button\'>Click to Login with TraitWare</button>";
					html += "<img class=\'traitware-qrcode traitware-qrcode-with-button\' /><div class=\'traitware-scan-response\'></div>";
					html += "<div id=\'traitwarerecoveryemail\'></div>";
					html += "</div>";
					jQuery("#login #loginform").html( html );

					jQuery("#traitwarerecoveryemail").hide();
					jQuery("#traitwarerecovery").click( traitwarerecovery );

					jQuery("p#nav").hide();

					jQuery("#loginform").submit( traitwarerecoverysubmit );
			';
		} else {
			echo '
					var html = "<div class=\'traitware-login-box\'><p>Login with TraitWare</p>";
					html += "<button type=\'button\' class=\'traitware-login-button\'>Click to Login with TraitWare</button>";
					html += "<img class=\'traitware-qrcode traitware-qrcode-with-button\' /><div class=\'traitware-scan-response\'></div>";

					html += "</div>";
					jQuery("#login #loginform").prepend( html );
					jQuery("#user_login").val( ' . wp_json_encode( $login ) . ' );
					jQuery("#loginform .submit").append( "<input type=\'hidden\' name=\'recovery\' value=\'' . esc_attr( $recovery ) . '\' />" );
			';
		}
		echo '
					traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
					window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
					traitware_qrcode();
				});
			</script>
		';
	}

	public static function validate_recovery( $recovery ) { // private
        if ( strlen( $recovery ) === 0 ) { return ''; }
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            'SELECT
				userid,
				recoveryhash
			FROM
				' . $wpdb->prefix . 'traitwareusers
			WHERE
			    accountowner = %d AND
				recoveryhash LIKE %s',
                1, $recovery . ' %'
        ), OBJECT );
        if ( count($results) > 0 ) {
            // recovery link will timeout after 1hr. recoveryhash is stored in 'HASH TIME' format
            $valid = true;
            $recoveryhash = explode(' ', $results[0]->recoveryhash);
            if ( count($recoveryhash) != 2 ) { $valid = false; }
            else {
                if ( ( (int)$recoveryhash[1] + 60*60 ) < time() ) { $valid = false; }
            }
            $user = get_user_by( 'ID', $results[0]->userid );
            if ( !($user instanceof WP_User) ) { $valid = false; }
            if ( $valid ) {
                return $user->user_login;
            }
        }
        return '';
    }

	public static function validate_scrub_recovery( $recovery ) { // private
        if ( strlen( $recovery ) === 0 ) { return ''; }
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            'SELECT
				userid,
				recoveryhash
			FROM
				' . $wpdb->prefix . 'traitwareusers
			WHERE
			    usertype = %s AND
				recoveryhash LIKE %s',
                'scrub', $recovery . ' %'
        ), OBJECT );
        if ( count($results) > 0 ) {
            // recovery link will timeout after 1hr. recoveryhash is stored in 'HASH TIME' format
            $valid = true;
            $recoveryhash = explode(' ', $results[0]->recoveryhash);
            if ( count($recoveryhash) != 2 ) { $valid = false; }
            else {
                if ( ((int)$recoveryhash[1] + 60*60) < time() ) { $valid = false; }
            }
            $user = get_user_by( 'ID', $results[0]->userid );
            if ( !($user instanceof WP_User) ) { $valid = false; }
            if ($valid) {
                return $user->user_login;
            }
        }
        return '';
    }

	public static function toggle_single_hook($hook_status) {
	    self::$single_hooked = $hook_status ? true : false;
    }

	public static function has_access_role($which_roles) {
        $user = wp_get_current_user();
        $has_access_role = false;
        $roles = ($user && is_object($user) && isset($user->roles)) ?
            ((array)$user->roles) : array();
        foreach($which_roles as $check_role) {
            if(!in_array($check_role, $roles)) {
                continue;
            }
            $has_access_role = true;
            break;
        }
        return $has_access_role;
    }

	public static function get_protected_html($roles_required) {
	    $protected_html = "Access Denied";
        $protected_page_access_denied_message_type = get_option('traitware_protectedpageaccessdeniedmessagetype');
        if ($protected_page_access_denied_message_type === false || $protected_page_access_denied_message_type === null || empty($protected_page_access_denied_message_type)) {
            $protected_page_access_denied_message_type = 'text';
        }
        if ($protected_page_access_denied_message_type === 'text') {
            $protected_page_access_denied_message_text = get_option('traitware_protectedpageaccessdeniedmessagetext');
            if ($protected_page_access_denied_message_text === false || $protected_page_access_denied_message_text === null || empty($protected_page_access_denied_message_text)) {
                $protected_page_access_denied_message_text = 'Access Denied';
            }
            $protected_html = esc_html($protected_page_access_denied_message_text);
        } else if ($protected_page_access_denied_message_type === 'html') {
            $protected_page_access_denied_message_html = base64_decode(get_option('traitware_protectedpageaccessdeniedmessagehtml'));
            if ($protected_page_access_denied_message_html === false || $protected_page_access_denied_message_html === null || empty($protected_page_access_denied_message_html)) {
                $protected_page_access_denied_message_text = 'Access Denied';
                $protected_html = $protected_page_access_denied_message_text;
            } else {
                $protected_html = $protected_page_access_denied_message_html;
            }
        } else {
            $protected_page_access_denied_message_post = get_option('traitware_protectedpageaccessdeniedmessagepost');
            if ($protected_page_access_denied_message_post === false || $protected_page_access_denied_message_post === null || empty($protected_page_access_denied_message_post)) {
                $protected_page_access_denied_message_text = 'Access Denied';
                $protected_html = $protected_page_access_denied_message_text;
            } else {
                $access_denied_post = get_post((int)$protected_page_access_denied_message_post);
                // Check if the post exists and is not the same post as the current post being protected (prevent infinite loop)
                if (!$access_denied_post || get_the_ID() == (int)$protected_page_access_denied_message_post) {
                    $protected_page_access_denied_message_text = 'Access Denied';
                    $protected_html = $protected_page_access_denied_message_text;
                } else {
                    $access_denied_post_output = apply_filters('the_content', $access_denied_post->post_content);
                    $protected_html = $access_denied_post_output;
                }
            }
        }

        $display_roles = array();
        global $wp_roles;

        foreach($roles_required as $role_required) {
            // skip invalid roles
            if(!isset($wp_roles->roles[$role_required])) {
                continue;
            }
            $display_roles[] = translate_user_role($wp_roles->roles[$role_required]['name']);
        }
        // TODO: Replace {{traitware_role}} with something else
        $protected_html = str_replace('{{traitware_role}}', implode(', ', $display_roles), $protected_html);
        return $protected_html;
    }

    // protected pages shortcode
	public static function protected_shortcode_callback($atts, $content = "") {

		$is_onlywplogin = get_option('traitware_enableonlywplogin');
		if ($is_onlywplogin) {
			return "";
		}

        $atts = shortcode_atts(array(
            'roles' => ''
        ), $atts, 'traitware');

        $role_list = explode(',', $atts['roles']);
        $clean_roles = array();

        foreach( $role_list as $role_single ) {
            $clean_role = trim($role_single);
            if ( in_array( $clean_role, $clean_roles, true ) ) {
                continue;
            }
            $clean_roles[] = $clean_role;
        }

        if (is_user_logged_in()) {
            if(!self::has_access_role($clean_roles)) {
                $protected_html = self::get_protected_html($clean_roles);
                return $protected_html;
            }
            return str_replace('{{traitware_role}}', '', $content);
        }

        if(self::$shortcode_qr_count > 0) {
            return "";
        }

        self::$shortcode_qr_count++;

		$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
		$traitware_redirecturl = wp_json_encode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
		$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var('apiurl') . traitware_get_var('api_authorization') . '?client_id=' . get_option( 'traitware_client_id' ) . '&response_type=code&state=' ) );
		$traitware_poll_url =  wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

        // If user is not logged in, display a QR code to login.
        $login_form_stub = '<div class=\'traitware_protected_container\'><div class=\'traitware_forms_authbox\'><div class=\'traitware_forms_inner_authbox\'>';
        $login_form_stub .= '<div class=\'traitware_protected_titletext\'>Login with TraitWare</div><div class=\'traitware_protected_welcometext\'>Scan the QR below with your mobile device to access content</div>';
        $login_form_stub .= '<img class=\'traitware-protected-qrcode\' /><div class=\'traitware-scan-response\'></div>';
        $login_form_stub .= '</div></div></div>';
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
				var traitware_payment_url = "&returnUri=' . rawurlencode(wp_login_url()) . '";
				var traitware_nonce = ' . wp_json_encode( wp_create_nonce('traitware_ajax') ) . ';
				jQuery(function() {
				var html = "<div class=\'traitware-login-box\'><p>Login with TraitWare</p>";
					html += "<button type=\'button\' class=\'traitware-login-button\'>Click to Login with TraitWare</button>";
					html += "<img class=\'traitware-qrcode traitware-qrcode-with-button\' /><div class=\'traitware-scan-response\'></div>";
					html += "</div>";
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

    // traitwareform shortcode callback to generate the HTML
	public static function form_shortcode_callback($atts, $content = "") {
		$is_onlywplogin = get_option('traitware_enableonlywplogin');
		if ($is_onlywplogin) {
			return "";
		}

        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts, 'traitwareform');

        $form_id = max(0, (int) sanitize_key( $atts['id'] ));

        return traitware_form_html($form_id);
    }
}
Traitware_Frontend::init();
