<?php
/**
 * Defines admin panel behavior.
 */

defined( 'ABSPATH' ) || die( 'No Access' );

/**
 * Class Traitware_Admin
 */
class Traitware_Admin {
	/**
	 * Adds actions and filters.
	 * @return void 
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return; }
		if ( ! session_id() ) {
			session_start(); }
        // Register our hook for handling ajax requests.
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		// Register our hook for the admin menu.
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		// Register our hook for the media_buttons action to provide shortcode helpers.
		add_action( 'media_buttons', array( __CLASS__, 'add_media_buttons' ), 15 );
		// Register our hook for the bulk_actions-screenid action (screenid = users).
		// This hook allows us to add our own actions to the bulk dropdown.
		add_filter( 'bulk_actions-users', array( __CLASS__, 'register_bulk_actions' ) );
		// Register our hook for add_meta_boxes which allows us to draw "Limit Page Access" (ie: edit page/post).
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_protected_page_roles_meta_box' ) );
		// Register our hook for add_meta_boxes which allows us to draw "Self Registration" settings.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_form_meta_boxes' ) );
		// Register our hook for save_post which allows us to save the "Limit Page Access" settings (ie: edit page/post).
		add_action( 'save_post', array( __CLASS__, 'save_protected_page_roles' ), 10, 2 );
		// Register our hook for save_post which allows us to save the "Self Registration Page" settings (ie: edit page).
		add_action( 'save_post', array( __CLASS__, 'save_form_page' ), 10, 2 );
		// Register the shutdown hook.
		add_action( 'shutdown', array( __CLASS__, 'admin_shutdown' ) );
	}

    /**
     * Register ajax related actions.
     */
	public static function admin_init() {
		// frontend ajax router.
		add_action( 'wp_ajax_nopriv_traitware_ajaxpollscan', array( __CLASS__, 'ajax_pollScan' ) ); // for wplogin only
		add_action( 'wp_ajax_nopriv_traitware_ajaxrecovery', array( __CLASS__, 'ajax_recovery' ) );
		add_action( 'wp_ajax_nopriv_traitware_ajaxscrubrecovery', array( __CLASS__, 'ajax_scrubRecovery' ) );
		add_action( 'wp_ajax_nopriv_traitware_ajaxbackground', array( __CLASS__, 'ajax_background' ) );
		add_action( 'wp_ajax_nopriv_traitware_ajaxforms', array( __CLASS__, 'ajax_forms' ) );
		add_action( 'wp_ajax_nopriv_traitware_ajaxreporterror', array( __CLASS__, 'ajax_reportError' ) );

		if ( is_user_logged_in() ) {
			add_action( 'wp_ajax_traitware_ajaxbackground', array( __CLASS__, 'ajax_background' ) );
			add_action( 'wp_ajax_traitware_ajaxforms', array( __CLASS__, 'ajax_forms' ) );
			add_action( 'wp_ajax_traitware_ajaxreporterror', array( __CLASS__, 'ajax_reportError' ) );
		}

		$traitware_vars = traitware_getJsVars();
		$version = traitware_get_var( 'version' );
		// QR is used in the admin sometimes.
		wp_enqueue_script( 'jquery' );
		wp_register_script( 'traitware.js', plugins_url( 'traitware-login-manager/res/traitware.js' ), array( 'jquery' ), $version );
		wp_localize_script( 'traitware.js', 'traitware_vars', $traitware_vars );
		wp_enqueue_script( 'traitware.js' );
		wp_register_style( 'traitware.css', plugins_url( 'traitware-login-manager/res/traitware.css' ), array(), $version );
		wp_enqueue_style( 'traitware.css' );

		if ( ! traitware_isadmin() ) {
			return; }

		// admin ajax router.
		add_action( 'wp_ajax_traitware_ajaxnewaccount', array( __CLASS__, 'ajax_newAccount' ) ); // wp_ajax_nopriv not used here
		add_action( 'wp_ajax_traitware_ajaxpollscan', array( __CLASS__, 'ajax_pollScan' ) );

		// installation notices.
		add_action( 'admin_notices', array( __CLASS__, 'admin_activatenotice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_review_notice' ) );

		if ( isset( $_GET['traitware-review-notice-dismiss'] ) ) {
			$user_id = get_current_user_id();
			if ( update_user_meta( $user_id, 'traitware_review_notice_dismissed', true ) ) {
				return;
			}
        }
	}

	public static function admin_activatenotice() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		$page = get_current_screen();
		if ( 'plugins' !== $page->base ) {
			return; 
		}
		if ( traitware_is_active() ) {
			return;
		}
		echo '
			<div class="updated notice is-dismissible">
				<h2 class="dashicons-before dashicons-smartphone">Thank you for installing TraitWare. <a class="button button-primary traitware-admin-notice-button" href="admin.php?page=traitware-setup">Set up your account today!</a></h2>
			</div>
		';
	}

	public static function admin_review_notice() {
		$notice_start_time = get_option( 'traitware_review_notice_start_time' );

		if ( false === $notice_start_time || time() <= $notice_start_time ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, 'traitware_review_notice_dismissed', true ) ) {
		    return;
        }

        $dismiss_url = add_query_arg( 'traitware-review-notice-dismiss', 1, admin_url() );

		?>
		<div class="notice notice-success traitware-review-notice">
			<p>Liking TraitWare? You can review us <a target="_blank" href="https://wordpress.org/support/plugin/traitware-login-manager/reviews/#new-post">here</a> and check out our other products <a target="_blank" href="https://traitware.com/enterprise/">here</a>.</p>
            <button type="button" class="notice-dismiss" data-href="<?php echo $dismiss_url ?>"><span class="screen-reader-text"><?php _e( 'Dismiss' ); ?></span></button>
		</div>
		<?php
	}

	/**
	 * Adds the menu pages.
	 */
	public static function menu() {
		if ( traitware_is_active() ) {
			if ( ! traitware_isadmin() ) {
				return; 
			}
			// menu pages
			add_menu_page( 'TraitWare Settings', 'TraitWare', 'manage_options', 'traitware-settings', array( __CLASS__, 'menu_settings' ), plugins_url( 'traitware-login-manager/res/traitware_icon.png' ) );
			add_submenu_page( 'traitware-settings', 'TraitWare Settings', 'Settings', 'manage_options', 'traitware-settings', array( __CLASS__, 'menu_settings' ) );
			add_submenu_page( 'traitware-settings', 'TraitWare Users', 'User Management', 'manage_options', 'traitware-users', array( __CLASS__, 'menu_users' ) );

			$is_onlywplogin = get_option('traitware_enableonlywplogin');
			if ( ! $is_onlywplogin ) {
				add_submenu_page( 'traitware-settings', 'TraitWare Forms', 'Forms', 'manage_options', 'edit.php?post_type=traitware_form' );
			}

			// nonmenu pages
			add_submenu_page( null, 'TraitWare Welcome', 'TraitWare Welcome', 'manage_options', 'traitware-welcome', array( __CLASS__, 'menu_welcome' ) );
			add_submenu_page( null, 'TraitWare User Logins', 'User Logins', 'manage_options', 'traitware-userlogins', array( __CLASS__, 'menu_userlogins' ) );
			add_submenu_page( null, 'Bulk Sync Progress', 'Bulk Sync Progress', 'manage_options', 'traitware-bulksync', array( __CLASS__, 'menu_bulksync' ) );
		} else {
			if ( ! traitware_isadmin() ) {
				return; } // only install if full admin
			add_menu_page( 'TraitWare', 'TraitWare', 'manage_options', 'traitware-setup', array( __CLASS__, 'menu_setup' ), plugins_url( 'traitware-login-manager/res/traitware_icon.png' ) );
		}
	}

	/**
	 * Called just before php quits. do any cleanup that this admin page gen requires.
	 */
	public static function admin_shutdown() {
		// traitware_getChangedUserIds() returns an array containing wp ids that have changed during this page generation. Only is defined if there have been changes.
		$changedUserIds = traitware_getChangedUserIds();
		if ( ! empty( $changedUserIds ) ) {
			traitware_userConsoleUpdate( $changedUserIds );
		}
	}

	/**
	 * Menu items.
	 */
	public static function menu_setup() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		include_once 'admin-setup.php';
	}

	/**
	 * Menu settings.
	 */
	public static function menu_settings() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		include_once 'admin-settings.php';
	}

	/**
	 * Menu welcome.
	 */
	public static function menu_welcome() {
		if ( ! traitware_isadmin() ) {
			return;
		}
		include_once 'admin-welcome.php';
	}

	/**
	 * Menu users.
	 */
	public static function menu_users() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		include_once 'admin-users.php';
	}

	/**
	 * Menu user logins.
	 */
	public static function menu_userlogins() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		include_once 'class-traitwareloginstable.php';
	}

	/**
	 * Menu bulksync.
	 */
	public static function menu_bulksync() {
		if ( ! traitware_isadmin() ) {
			return; 
		}
		include_once 'admin-bulksync.php';
	}

	/**
	 * New account ajax.
	 */
	public static function ajax_newAccount() {
		include_once 'newaccount.php';
	}

	/**
	 * Pollscan ajax.
	 */
	public static function ajax_pollScan() {
		include_once 'pollscan.php';
	}

	/**
	 * Background ajax.
	 */
	public static function ajax_background() {
		include_once 'background.php';
	}

	/**
	 * Forms ajax.
	 */
	public static function ajax_forms() {
		include_once 'forms.php';
	}

	/**
	 * Recovery ajax.
	 */
	public static function ajax_recovery() {
		include_once 'recovery.php';
	}

	/**
	 * Scrub recovery ajax.
	 */
	public static function ajax_scrubRecovery() {
		include_once 'scrub-recovery.php';
	}

	/**
	 * Report error ajax.
	 */
	public static function ajax_reportError() {
		if ( ! isset( $_REQUEST['error'] ) ) {
			wp_die();
		}

		Traitware_API::report_error( $_REQUEST['error'] );
		wp_die();
    }

	/**
	 * Register bulk actions.
	 * @param $bulk_actions
	 * @return mixed
	 */
	public static function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['traitware_sync_user']       = 'Sync with TraitWare';
		$bulk_actions['traitware_send_activation'] = 'Send TraitWare Activation Email';
		return $bulk_actions;
	}

	/**
	 * Media buttons for the post editor.
	 */
	public static function add_media_buttons() {
		global $wp_roles;
		echo '<a href="#" id="traitware-shortcode-button" class="button">Protect with TraitWare</a> ' .
			'<a href="#" id="traitware-reqroles-button" class="button">TraitWare Required Role(s)</a>' .
			'<a href="#" id="traitware-selfregforms-button" class="button">Add TraitWare Form</a>';
	}

	/**
	 * Add the meta box for post/page role restriction (list of roles in checkbox form)
	 * @param $post
	 */
	public static function traitware_protected_page_roles_html( $post ) {
		global $wp_roles;

		// get a list of all the roles
		$all_roles = $wp_roles->roles;

		// load the existing role selections for this post
		$current_roles = get_post_meta( $post->ID, '_traitware_protected_page_roles_meta_key', true );

		// variable used for HTML to be rendered
		$html = '';

		// loop through each of the roles for HTML construction
		foreach ( $all_roles as $role => $details ) {
			// if the role exists within the current roles, then checked should be true
			$checked = is_array( $current_roles ) && in_array( $role, $current_roles );

			// get the actual role name for display purposes
			$name = translate_user_role( $details['name'] );

			// build the HTML for the label + checkbox
			$html .= "\n<label>";
			$html .= "\n\t<input type='checkbox' name='traitware_protected_page_roles[]' value='" .
				esc_attr( $role ) . "'" . ( $checked ? ' checked' : '' ) . ' /> ' . esc_html( $name );
			$html .= "\n</label><br />";
		}

		$current_form = get_post_meta( $post->ID, '_traitware_protected_page_form_meta_key', true );
		$current_form = max( 0, (int) $current_form );

		$formPages = traitware_getFormPages();

		$html .= "\n<label>(optional) Which form would you like to display under the protected page?<br />";
		$html .= "\n\t<select class=\"traitware_protected_page_form\" name=\"traitware_protected_page_form\">";
		$html .= "\n\t\t<option value=\"0\">Select a form...</option>";
		foreach ( $formPages as $formPage ) {
			$selected_html = $formPage->ID === $current_form ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $formPage->ID ) . '" ' . $selected_html . '>' .
				esc_html( $formPage->post_title ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		$html .= wp_nonce_field('traitware-protected-page-roles', '_twnonce', true, false);

		// echo the HTML and cause it to be rendered!
		echo $html;
	}

	/**
	 * Add the meta box for traitware form settings.
	 * @param $post
	 */
	public static function traitware_form_metabox_html( $post ) {
		$html = '';

		// if self registration has not been enabled then make sure to reflect in the UI.
		$registration_enabled = get_option( 'traitware_enableselfregistration' );

		// generate the HTML for the form option tab links.
		$html .= '<div class="traitware_form_tab_links">';
		$html .= '<a href="#" class="traitware_form_tab_link traitware_form_tab_link_overview traitware_form_tab_link_active" data-tab="overview">Overview</a>&nbsp;|&nbsp;';

		if ( ! $registration_enabled ) {
			$html .= '<a href="#" class="traitware_form_tab_link traitware_form_tab_link_login" data-tab="login">Login Page</a>';
		} else {
			$html .= '<a href="#" class="traitware_form_tab_link traitware_form_tab_link_signup" data-tab="signup">New User Sign-Up Page</a>&nbsp;|&nbsp;';
			$html .= '<a href="#" class="traitware_form_tab_link traitware_form_tab_link_login" data-tab="login">Login Page</a>&nbsp;|&nbsp;';
			$html .= '<a href="#" class="traitware_form_tab_link traitware_form_tab_link_optin" data-tab="optin">Existing User Opt-In Page</a>';
		}

		$html .= '</div>';

		// generate the HTML for the form option tabs.
		$html .= '<div class="traitware_form_tabs">';

		// generate the HTML for the overview tab
		$html .= self::traitware_overview_html( $post, $registration_enabled );

		// generate the HTML for the signup tab.
		$html .= self::traitware_self_registration_tab_html( $post );

		// generate the HTML for the login tab.
		$html .= self::traitware_login_tab_html( $post );

		// generate the HTML for the opt-in tab.
		$html .= self::traitware_optin_tab_html( $post );

		// generate nonce.
		$html .= wp_nonce_field( 'traitware-form', '_twnonce', true, false );

		// end the tabs container.
		$html .= '</div>';

		// echo the HTML and cause it to be rendered!
		echo $html;
	}

	/**
	 * HTML for self registration page field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_form_start_html( $post ) {
		$form_start = traitware_getFormStartById( $post->ID );

		$html = '';

		$modes = traitware_getFormStartModes();

		$html .= "\n<label>Which form would you like to display when the page loads?<br />";
		$html .= "\n\t<select class=\"traitware_form_start\" name=\"traitware_form_start\">";
		$html .= "\n\t\t<option value=\"\">Select default form...</option>";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $form_start ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration page field
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_page_html( $post ) {
		$self_registration_page = traitware_getSelfRegistrationPageById( $post->ID );

		$html = '';

		$modes = traitware_getSelfRegistrationModes();

		$html .= "\n<label>Would you like to enable new users to sign-up using TraitWare?<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_page\" name=\"traitware_self_registration_page\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $self_registration_page ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration page link field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_pagelink_html( $post ) {
		$self_registration_page_link = traitware_getSelfRegistrationPagelinkById( $post->ID );

		$html = '';

		$self_registration_pages = traitware_getSelfRegistrationPages();

		$html .= "\n<label>Which form would you like to link the user to? The form you select must have new user sign-up enabled.<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_pagelink\" name=\"traitware_self_registration_pagelink\">";
		$html .= "\n\t\t<option value=\"0\">Select a form...</option>";
		foreach ( $self_registration_pages as $self_registration_page ) {
			$selected_html = $self_registration_page->ID === $self_registration_page_link ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $self_registration_page->ID ) . '" ' . $selected_html . '>' .
				esc_html( $self_registration_page->post_title ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration username field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_username_html( $post ) {
		$self_registration_username = traitware_getSelfRegistrationUsernameById( $post->ID );

		$html = '';

		$modes = traitware_getSelfRegistrationUsernameModes();

		$html .= "\n<label>Would you like new users to specify a username? If you select no, a username will be generated automatically.<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_username\" name=\"traitware_self_registration_username\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $self_registration_username ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration role field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_role_html( $post ) {
		// load the existing value for this post.
		$self_registration_role = traitware_getSelfRegistrationRoleById( $post->ID );

		$role_list = traitware_getRoleList();

		$html = '';

		$html .= "\n<label>Select a role to be assigned to new users that sign up on this page.<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_role\" name=\"traitware_self_registration_role\">";
		foreach ( $role_list as $role_key => $role_single ) {
			$selected_html = $role_key === $self_registration_role ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $role_key ) . '" ' . $selected_html . '>' .
				esc_html( $role_single['display_name'] ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration approval.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_approval_html( $post ) {
		// load the existing value for this post.
		$self_registration_approval = traitware_getSelfRegistrationApprovalById( $post->ID );

		$modes = traitware_getSelfRegistrationApprovalModes();

		$html = '';

		$html .= "\n<label>Would you like to enable admin approval (via email) for new users?<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_approval\" name=\"traitware_self_registration_approval\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $self_registration_approval ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration approval role.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_approval_role_html( $post ) {
		// load the existing value for this post.
		$self_registration_approval_role = traitware_getSelfRegistrationApprovalRoleById( $post->ID );

		$role_list = traitware_getRoleList();

		$html = '';

		$html .= "\n<label>Select a role to be assigned to new users once they are approved by an admin.<br />";
		$html .= "\n\t<select class=\"traitware_self_registration_approval_role\" name=\"traitware_self_registration_approval_role\">";
		foreach ( $role_list as $role_key => $role_single ) {
			$selected_html = $role_key === $self_registration_approval_role ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $role_key ) . '" ' . $selected_html . '>' .
				esc_html( $role_single['display_name'] ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration instructions field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_instructions_html( $post ) {
		// load the existing value for this post.
		$self_registration_instructions = traitware_getSelfRegistrationInstructionsById( $post->ID );

		$html = '';

		$placeholder = traitware_getSelfRegistrationDefaultInstructions();

		$html .= "\n<label>If you would like to display some text above the new user sign-up form, enter it here. ";
		$html .= "\n\tIf you leave this empty, the default instructions text will be used.<br />";
		$html .= "\n\t<textarea name=\"traitware_self_registration_instructions\" class=\"traitware_self_registration_instructions\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $self_registration_instructions ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration loggedin field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_loggedin_html( $post ) {
		// load the existing value for this post.
		$self_registration_logged_in = traitware_getSelfRegistrationLoggedinById( $post->ID );

		$html = '';

		$placeholder = traitware_getSelfRegistrationDefaultLoggedin();

		$html .= "\n<label>When a user is already logged in, they are not allowed to sign-up (however they may be allowed to opt-in to TraitWare depending on opt-in page settings). Instead, they will be presented with some text explaining the situation. If you want to override the text presented to the user, input your own here.<br />";
		$html .= "\n\t<textarea name=\"traitware_self_registration_loggedin\" class=\"traitware_self_registration_loggedin\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $self_registration_logged_in ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration existing field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_existing_html( $post ) {
		// load the existing value for this post.
		$self_registration_existing = traitware_getSelfRegistrationExistingById( $post->ID );

		$html = '';

		$placeholder = traitware_getSelfRegistrationDefaultExisting();

		$html .= "\n<label>Similar to the above, when a user inputs the email address or username of an existing user, they are not allowed to sign-up (again, they may be allowed to opt-in to TraitWare depending on opt-in page settings). Instead, they will be presented with some text explaining the situation. If you want to override the text presented to the user, input your own here.<br />";
		$html .= "\n\t<textarea name=\"traitware_self_registration_existing\" class=\"traitware_self_registration_existing\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $self_registration_existing ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration linktext field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_linktext_html( $post ) {
		// load the existing value for this post.
		$self_registration_link_text = traitware_getSelfRegistrationLinktextById( $post->ID );

		$html = '';

		$placeholder = traitware_getSelfRegistrationDefaultLinktext();

		$html .= "\n<label>TraitWare special pages allow for simple linking between each form. You may change the text for links that lead new users to the sign-up form by entering the desired text here.<br />";
		$html .= "\n\t<textarea name=\"traitware_self_registration_linktext\" class=\"traitware_self_registration_linktext\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $self_registration_link_text ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for self registration success field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_success_html( $post ) {
		 // load the existing value for this post.
		$self_registration_success = traitware_getSelfRegistrationSuccessById( $post->ID );

		$html = '';

		$placeholder = traitware_getSelfRegistrationDefaultSuccess();

		$html .= "\n<label>When a new user finishes signing up, they are presented with a success message which will instruct them to check their email for device activation instructions. You may change the text in this success message by entering the desired text here.<br />";
		$html .= "\n\t<textarea name=\"traitware_self_registration_success\" class=\"traitware_self_registration_success\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $self_registration_success ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in page field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_html( $post ) {
		 // load the existing value for this post.
		$optin_page = traitware_getOptInPageById( $post->ID );

		$html = '';

		$modes = traitware_getOptInModes();

		$html .= "\n<label>Would you like to enable opt-in TraitWare access for existing users? By opting-in, users will not experience role changes.<br />";
		$html .= "\n\t<select class=\"traitware_optin_page\" name=\"traitware_optin_page\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $optin_page ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in page link field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_pagelink_html( $post ) {
		$optin_page_link = traitware_getOptInPagelinkById( $post->ID );

		$html = '';

		$optin_pages = traitware_getOptInPages();

		$html .= "\n<label>Which form would you like to link the user to? The form you select must have existing user opt-in enabled.<br />";
		$html .= "\n\t<select class=\"traitware_optin_pagelink\" name=\"traitware_optin_pagelink\">";
		$html .= "\n\t\t<option value=\"0\">Select a form...</option>";
		foreach ( $optin_pages as $optin_page ) {
			$selected_html = $optin_page->ID === $optin_page_link ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $optin_page->ID ) . '" ' . $selected_html . '>' .
				esc_html( $optin_page->post_title ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in notification field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_notification_html( $post ) {
		// load the existing value for this post.
		$optin_notification = traitware_getOptInNotificationById( $post->ID );

		$modes = traitware_getOptInNotificationModes();

		$html = '';

		$html .= "\n<label>Would you like to enable admin email notifications for opt-ins?<br />";
		$html .= "\n\t<select class=\"traitware_optin_notification\" name=\"traitware_optin_notification\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $optin_notification ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in instructions field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_instructions_html( $post ) {
		// load the existing value for this post.
		$optin_instructions = traitware_getOptInInstructionsById( $post->ID );

		$html = '';

		$placeholder = traitware_getOptInDefaultInstructions();

		$html .= "\n<label>If you would like to display some text above the opt-in form, enter it here. ";
		$html .= "\n\tIf you leave this empty, the default instructions text will be used.<br />";
		$html .= "\n\t<textarea name=\"traitware_optin_instructions\" class=\"traitware_optin_instructions\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $optin_instructions ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in logged-in instructions field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_logged_in_instructions_html( $post ) {
		// load the existing value for this post.
		$optin_instructions = traitware_getOptInLoggedInInstructionsById( $post->ID );

		$html = '';

		$placeholder = traitware_getOptInDefaultLoggedInInstructions();

		$html .= "\n<label>If you would like to display some text above the opt-in form for logged-in users, enter it here. ";
		$html .= "\n\tIf you leave this empty, the default instructions text will be used.<br />";
		$html .= "\n\t<textarea name=\"traitware_optin_logged_in_instructions\" class=\"traitware_optin_logged_in_instructions\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $optin_instructions ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in linktext field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_linktext_html( $post ) {
		// load the existing value for this post.
		$optin_link_text = traitware_getOptInLinktextById( $post->ID );

		$html = '';

		$placeholder = traitware_getOptInDefaultLinktext();

		$html .= "\n<label>TraitWare special pages allow for simple linking between each form. You may change the text for links that lead existing users to the opt-in form by entering the desired text here.<br />";
		$html .= "\n\t<textarea name=\"traitware_optin_linktext\" class=\"traitware_optin_linktext\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $optin_link_text ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in success field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_success_html( $post ) {
		 // load the existing value for this post.
		$optin_success = traitware_getOptInSuccessById( $post->ID );

		$html = '';

		$placeholder = traitware_getOptInDefaultSuccess();

		$html .= "\n<label>When an existing user finishes opting-in, they are presented with a success message which will instruct them to check their email for device activation instructions. You may change the text in this success message by entering the desired text here.<br />";
		$html .= "\n\t<textarea name=\"traitware_optin_success\" class=\"traitware_optin_success\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $optin_success ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for opt-in existing field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_existing_html( $post ) {
		// load the existing value for this post.
		$optin_existing = traitware_getOptInExistingById( $post->ID );

		$html = '';

		$placeholder = traitware_getOptInDefaultExisting();

		$html .= "\n<label>When a user already has opted-into TraitWare, they will be presented with some text explaining the situation. If you want to override the text presented to the user, input your own here.<br />";
		$html .= "\n\t<textarea name=\"traitware_optin_existing\" class=\"traitware_optin_existing\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $optin_existing ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * Generate the traitware form settings tab for the overview.
	 * @param $post
	 * @param $registration_enabled
	 * @return string
	 */
	public static function traitware_overview_html( $post, $registration_enabled ) {
		$html = '';

		$html .= '<div class="traitware_form_tab traitware_form_tab_overview" data-tab="overview">';

		// simple title for our forms overview
		$html .= '<b>TraitWare Forms Overview</b><br />';

		// generate a description explaining how traitware forms work and how to use them
		$html .= '<p>TraitWare forms can be used to create sign-up (new user self-registration), opt-in (existing user TraitWare registration), and login pages. <a href="admin.php?page=traitware-settings&tab=self-registration" target="_blank">Click here to go to the global settings and enable self-registration.</a> (opens new tab)</p>';

		// generate the form start field HTML
		$html .= self::traitware_form_start_html( $post );

		// close the tab div
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate the HTML for the signup tab for form settings.
	 * @param $post
	 * @return string
	 */
	public static function traitware_self_registration_tab_html( $post ) {
		$html = '';

		$html .= '<div class="traitware_form_tab traitware_form_tab_signup" data-tab="signup" style="display: none">';

		// simple title for our self registration options.
		$html .= '<b>New User Sign-Up</b><br />';

		// get the self registration page field html.
		$html .= self::traitware_self_registration_page_html( $post );

		// get the self registration instructions field html.
		$html .= self::traitware_self_registration_instructions_html( $post );

		// get the self registration username field html.
		$html .= self::traitware_self_registration_username_html( $post );

		// get the self registration loggedin field html.
		$html .= self::traitware_self_registration_loggedin_html( $post );

		// get the self registration existing field html.
		$html .= self::traitware_self_registration_existing_html( $post );

		// get the self registration pagelink field html.
		$html .= self::traitware_self_registration_pagelink_html( $post );

		// get the self registration role field html.
		$html .= self::traitware_self_registration_role_html( $post );

		// get the self registration approval field html.
		$html .= self::traitware_self_registration_approval_html( $post );

		// get the self registration approval role field html.
		$html .= self::traitware_self_registration_approval_role_html( $post );

		// get the self registration success field html.
		$html .= self::traitware_self_registration_success_html( $post );

		// get the self registration linktext field html.
		$html .= self::traitware_self_registration_linktext_html( $post );

		// close the tab div.
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate the HTML for the login tab for form settings.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_tab_html( $post ) {
		$html = '';

		$html .= '<div class="traitware_form_tab traitware_form_tab_login" data-tab="login" style="display: none">';

		// simple title for our self registration options.
		$html .= '<b>Login</b><br />';

		// get the login field html.
		$html .= self::traitware_login_html( $post );

		// get the login pagelink field html.
		$html .= self::traitware_login_pagelink_html( $post );

		// get the login instructions field html.
		$html .= self::traitware_login_instructions_html( $post );

		// get the login loggedin field html.
		$html .= self::traitware_login_loggedin_html( $post );

		// get the login redirect field html.
		$html .= self::traitware_login_redirect_html( $post );

		// get the login linktext field html.
		$html .= self::traitware_login_linktext_html( $post );

		// close the tab div.
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate the HTML for the opt-in tab for form settings.
	 * @param $post
	 * @return string
	 */
	public static function traitware_optin_tab_html( $post ) {
		$html = '';

		$html .= '<div class="traitware_form_tab traitware_form_tab_optin" data-tab="optin" style="display: none">';

		// simple title for our opt-in options.
		$html .= '<b>Existing User Opt-In</b><br />';

		// get the opt-in field html.
		$html .= self::traitware_optin_html( $post );

		// get the opt-in instructions html.
		$html .= self::traitware_optin_instructions_html( $post );

		// get the opt-in logged in instructions html.
		$html .= self::traitware_optin_logged_in_instructions_html( $post );

		// get the opt-in pagelink field html.
		$html .= self::traitware_optin_pagelink_html( $post );

		// get the opt-in notification field html.
		$html .= self::traitware_optin_notification_html( $post );

		// get the opt-in success field html.
		$html .= self::traitware_optin_success_html( $post );

		// get the opt-in existing field html.
		$html .= self::traitware_optin_existing_html( $post );

		// get the opt-in linktext field html.
		$html .= self::traitware_optin_linktext_html( $post );

		// close the tab div.
		$html .= '</div>';

		return $html;
	}

	/**
	 * HTML for login page field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_html( $post ) {
		 // load the existing value for this post.
		$login_page = traitware_getLoginPageById( $post->ID );

		$html = '';

		$modes = traitware_getLoginModes();

		$html .= "\n<label>Would you like to enable logging in with TraitWare?<br />";
		$html .= "\n\t<select class=\"traitware_login_page\" name=\"traitware_login_page\">";
		foreach ( $modes as $mode_single => $mode_display ) {
			$selected_html = $mode_single === $login_page ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $mode_single ) . '" ' . $selected_html . '>' .
				esc_html( $mode_display ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for login page link field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_pagelink_html( $post ) {
		$login_page_link = traitware_getLoginPagelinkById( $post->ID );

		$html = '';

		$loginPages = traitware_getLoginPages();

		$html .= "\n<label>Which form would you like to link the user to? The form you select must have login enabled.<br />";
		$html .= "\n\t<select class=\"traitware_login_pagelink\" name=\"traitware_login_pagelink\">";
		$html .= "\n\t\t<option value=\"0\">Select a form...</option>";
		foreach ( $loginPages as $loginPage ) {
			$selected_html = $loginPage->ID === $login_page_link ? 'selected="selected"' : '';
			$html        .= "\n\t\t<option value=\"" . esc_attr( $loginPage->ID ) . '" ' . $selected_html . '>' .
				esc_html( $loginPage->post_title ) . '</option>';
		}
		$html .= "\n\t</select>";
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for login loggedin field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_loggedin_html( $post ) {
		// load the existing value for this post.
		$login_logged_in = traitware_getLoginLoggedinById( $post->ID );

		$html = '';

		$placeholder = traitware_getLoginDefaultLoggedin();

		$html .= "\n<label>When a user is already logged in, they will be presented with some text explaining the situation. If you want to override the text presented to the user, input your own here.<br />";
		$html .= "\n\t<textarea name=\"traitware_login_loggedin\" class=\"traitware_login_loggedin\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $login_logged_in ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for login instructions field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_instructions_html( $post ) {
		// load the existing value for this post.
		$login_instructions = traitware_getLoginInstructionsById( $post->ID );

		$html = '';

		$placeholder = traitware_getLoginDefaultInstructions();

		$html .= "\n<label>If you would like to display some text above the login form, enter it here. ";
		$html .= "\n\tIf you leave this empty, the default instructions text will be used.<br />";
		$html .= "\n\t<textarea name=\"traitware_login_instructions\" class=\"traitware_login_instructions\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $login_instructions ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for login redirect field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_redirect_html( $post ) {
		// load the existing value for this post.
		$login_redirect = traitware_getLoginRedirectById( $post->ID );

		$html = '';

		$html .= "\n<label>What URL would you like to redirect users to once they have successfully logged in.<br />";
		$html .= "\n\t<input type=\"text\" name=\"traitware_login_redirect\" class=\"traitware_login_redirect traitware_textbox\" value=\"" . esc_attr( $login_redirect ) . '" />';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * HTML for login linktext field.
	 * @param $post
	 * @return string
	 */
	public static function traitware_login_linktext_html( $post ) {
		// load the existing value for this post.
		$login_link_text = traitware_getLoginLinktextById( $post->ID );

		$html = '';

		$placeholder = traitware_getLoginDefaultLinktext();

		$html .= "\n<label>TraitWare special pages allow for simple linking between each form. You may change the text for links that lead to the login form by entering the desired text here.<br />";
		$html .= "\n\t<textarea name=\"traitware_login_linktext\" class=\"traitware_login_linktext\" placeholder=\"" . esc_attr( $placeholder ) . '">' . esc_textarea( $login_link_text ) . '</textarea>';
		$html .= "\n</label>";

		return $html;
	}

	/**
	 * Metabox for the page editor to limit protected page role(s)
	 */
	public static function add_protected_page_roles_meta_box() {
		$current_limitaccesspts = get_option( 'traitware_limitaccesspts' );

		if ( false === $current_limitaccesspts || is_null( $current_limitaccesspts ) || ! is_array( $current_limitaccesspts ) ) {
			$current_limitaccesspts = array( 'post', 'page' );
		}

		foreach ( $current_limitaccesspts as $screen ) {
			add_meta_box(
				'traitware_protected_page_roles',
				'Limit Page Access with TraitWare',
				array( __CLASS__, 'traitware_protected_page_roles_html' ),
				$screen,
				'side'
			);
		}
	}

	/**
	 * Metaboxes for the page editor to add special page settings
	 */
	public static function add_form_meta_boxes() {
		add_meta_box(
			'traitware_form_options',
			'TraitWare Form Settings',
			array( __CLASS__, 'traitware_form_metabox_html' ),
			'traitware_form',
			'normal'
		);
	}

	/**
	 * Handle the save functionality for our protected page roles
	 * @param $post_id
	 * @param $post
	 */
	public static function save_protected_page_roles( $post_id, $post ) {

		$current_limitaccesspts = get_option( 'traitware_limitaccesspts' );

		if ( false === $current_limitaccesspts || is_null( $current_limitaccesspts ) || ! is_array( $current_limitaccesspts ) ) {
			$current_limitaccesspts = array( 'post', 'page' );
		}

		if ( ! in_array( $post->post_type, $current_limitaccesspts ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_twnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_twnonce'] ), 'traitware-protected-page-roles' ) ) {
			return;
		}

		if ( array_key_exists( 'traitware_protected_page_roles', $_POST ) ) {
			$pp_roles = array();
			foreach ( $_POST['traitware_protected_page_roles'] as $pp_role ) {
				$pp_roles[] = sanitize_key( $pp_role );
			}

			update_post_meta(
				$post_id,
				'_traitware_protected_page_roles_meta_key',
				$pp_roles
			);
		} else {
			delete_post_meta( $post_id, '_traitware_protected_page_roles_meta_key' );
		}

		if ( isset( $_POST['traitware_protected_page_form'] ) ) {
			$form_pages = traitware_getFormPages();

			$form_value = max( 0, (int) sanitize_key( $_POST['traitware_protected_page_form'] ) );

			$is_valid_form = false;

			foreach ( $form_pages as $form_page ) {
				if ( $form_page->ID === $form_value ) {
					$is_valid_form = true;
					break;
				}
			}

			if ( ! $is_valid_form ) {
				$form_value = 0;
			}

			update_post_meta(
				$post_id,
				'_traitware_protected_page_form_meta_key',
				$form_value
			);
		}
	}

	/**
	 * Handle the save functionality for our form pages
	 * @param $post_id
	 * @param $post
	 */
	public static function save_form_page( $post_id, $post ) {
		 // make sure we do not save the form if we are not saving a valid traitware form.
		if ( 'traitware_form' !== $post->post_type || ! isset( $_POST['traitware_self_registration_page'] ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_twnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_twnonce'] ), 'traitware-form' ) ) {
			return;
		}

		// First, save the state of the self registration page dropdown.
		$self_registration_page  = 'no';
		$self_registration_modes = traitware_getSelfRegistrationModes();

		if ( isset( $self_registration_modes[ $_POST['traitware_self_registration_page'] ] ) ) {
			$self_registration_page = sanitize_key( $_POST['traitware_self_registration_page'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_page',
			$self_registration_page
		);

		// Save the state of the self registration pagelink.
		$self_registration_pages = traitware_getSelfRegistrationPages();

		$self_registration_pagelink        = 0;
		$posted_self_registration_pagelink = max( 0, (int) sanitize_key( $_POST['traitware_self_registration_pagelink'] ) );

		foreach ( $self_registration_pages as $self_reg_page ) {
			if ( $self_reg_page->ID === $posted_self_registration_pagelink ) {
				$self_registration_pagelink = $posted_self_registration_pagelink;
				break;
			}
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_pagelink',
			$self_registration_pagelink
		);

		// Save self registration instructions.
		update_post_meta(
			$post_id,
			'_traitware_self_registration_instructions',
			trim( sanitize_textarea_field( $_POST['traitware_self_registration_instructions'] ) )
		);

		// Get a role list to validate the admin's inputs below.
		$roleList = traitware_getRoleList();

		// If they are passing a valid role, set the post meta.
		$self_registration_role = '';

		if ( isset( $roleList[ $_POST['traitware_self_registration_role'] ] ) ) {
			$self_registration_role = sanitize_key( $_POST['traitware_self_registration_role'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_role',
			$self_registration_role
		);

		// Save the state of the self registration username dropdown.
		$self_registration_username       = 'no';
		$self_registration_username_modes = traitware_getSelfRegistrationUsernameModes();

		if ( isset( $self_registration_username_modes[ $_POST['traitware_self_registration_username'] ] ) ) {
			$self_registration_username = sanitize_key( $_POST['traitware_self_registration_username'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_username',
			$self_registration_username
		);

		// Save self registration loggedin.
		update_post_meta(
			$post_id,
			'_traitware_self_registration_loggedin',
			trim( sanitize_textarea_field( $_POST['traitware_self_registration_loggedin'] ) )
		);

		// Save self registration existing.
		update_post_meta(
			$post_id,
			'_traitware_self_registration_existing',
			trim( sanitize_textarea_field( $_POST['traitware_self_registration_existing'] ) )
		);

		// Save the state of the self registration approval dropdown.
		$self_registration_approval       = 'no';
		$self_registration_approval_modes = traitware_getSelfRegistrationApprovalModes();

		if ( isset( $self_registration_approval_modes[ $_POST['traitware_self_registration_approval'] ] ) ) {
			$self_registration_approval = sanitize_key( $_POST['traitware_self_registration_approval'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_approval',
			$self_registration_approval
		);

		// If they are passing a valid role, set the post meta.
		$self_registration_approval_role = '';

		if ( isset( $roleList[ $_POST['traitware_self_registration_approval_role'] ] ) ) {
			$self_registration_approval_role = sanitize_key( $_POST['traitware_self_registration_approval_role'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_self_registration_approval_role',
			$self_registration_approval_role
		);

		// Save self registration success.
		update_post_meta(
			$post_id,
			'_traitware_self_registration_success',
			trim( sanitize_textarea_field( $_POST['traitware_self_registration_success'] ) )
		);

		// Save self registration linktext.
		update_post_meta(
			$post_id,
			'_traitware_self_registration_linktext',
			trim( sanitize_text_field( $_POST['traitware_self_registration_linktext'] ) )
		);

		// First, save the state of the login page dropdown.
		$login_page  = 'no';
		$login_modes = traitware_getLoginModes();

		if ( isset( $login_modes[ $_POST['traitware_login_page'] ] ) ) {
			$login_page = sanitize_key( $_POST['traitware_login_page'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_login_page',
			$login_page
		);

		// Save the state of the login pagelink.
		$login_pages = traitware_getLoginPages();

		$login_pagelink        = 0;
		$posted_login_pagelink = max( 0, (int) sanitize_key( $_POST['traitware_login_pagelink'] ) );

		foreach ( $login_pages as $login_pages_page ) {
			if ( $login_pages_page->ID === $posted_login_pagelink ) {
				$login_pagelink = $posted_login_pagelink;
				break;
			}
		}

		update_post_meta(
			$post_id,
			'_traitware_login_pagelink',
			$login_pagelink
		);

		// Save login instructions.
		update_post_meta(
			$post_id,
			'_traitware_login_instr
			uctions',
			trim( sanitize_textarea_field( $_POST['traitware_login_instructions'] ) )
		);

		// Save login linktext.
		update_post_meta(
			$post_id,
			'_traitware_login_linktext',
			trim( sanitize_text_field( $_POST['traitware_login_linktext'] ) )
		);

		// Save login redirect.
		update_post_meta(
			$post_id,
			'_traitware_login_redirect',
			trim( esc_url_raw( $_POST['traitware_login_redirect'] ) )
		);

		// Save login loggedin.
		update_post_meta(
			$post_id,
			'_traitware_login_loggedin',
			trim( sanitize_textarea_field( $_POST['traitware_login_loggedin'] ) )
		);

		// First, save the state of the opt-in page dropdown.
		$optin_page  = 'no';
		$optin_modes = traitware_getOptInModes();

		if ( isset( $optin_modes[ $_POST['traitware_optin_page'] ] ) ) {
			$optin_page = sanitize_key( $_POST['traitware_optin_page'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_optin_page',
			$optin_page
		);

		// Save opt-in linktext.
		update_post_meta(
			$post_id,
			'_traitware_optin_linktext',
			trim( sanitize_text_field( $_POST['traitware_optin_linktext'] ) )
		);

		// Save opt-in instructions.
		update_post_meta(
			$post_id,
			'_traitware_optin_instructions',
			trim( sanitize_textarea_field( $_POST['traitware_optin_instructions'] ) )
		);

		// Save logged-in opt-in instructions.
		update_post_meta(
			$post_id,
			'_traitware_optin_logged_in_instructions',
			trim( sanitize_textarea_field( $_POST['traitware_optin_logged_in_instructions'] ) )
		);

		// Save opt-in existing.
		update_post_meta(
			$post_id,
			'_traitware_optin_existing',
			trim( sanitize_textarea_field( $_POST['traitware_optin_existing'] ) )
		);

		// Save opt-in success.
		update_post_meta(
			$post_id,
			'_traitware_optin_success',
			trim( sanitize_textarea_field( $_POST['traitware_optin_success'] ) )
		);

		// Save the state of the opt-in pagelink.
		$optinPages = traitware_getOptInPages();

		$optin_pagelink        = 0;
		$posted_optin_pagelink = max( 0, (int) sanitize_key( $_POST['traitware_optin_pagelink'] ) );

		foreach ( $optinPages as $optinPage ) {
			if ( $optinPage->ID === $posted_optin_pagelink ) {
				$optin_pagelink = $posted_optin_pagelink;
				break;
			}
		}

		update_post_meta(
			$post_id,
			'_traitware_optin_pagelink',
			$optin_pagelink
		);

		// Save the state of the opt-in notification dropdown.
		$optin_notification       = 'no';
		$optin_notification_modes = traitware_getOptInNotificationModes();

		if ( isset( $optin_notification_modes[ $_POST['traitware_optin_notification'] ] ) ) {
			$optin_notification = sanitize_key( $_POST['traitware_optin_notification'] );
		}

		update_post_meta(
			$post_id,
			'_traitware_optin_notification',
			$optin_notification
		);

		// Save the state of the form start dropdown.
		$form_start = '';

		if ( $_POST['traitware_form_start'] === 'signup' && $self_registration_page === 'yes' ) {
			$form_start = 'signup';
		} elseif ( $_POST['traitware_form_start'] === 'optin' && $optin_page === 'yes' ) {
			$form_start = 'optin';
		} elseif ( $_POST['traitware_form_start'] === 'login' && $login_page === 'yes' ) {
			$form_start = 'login';
		}

		update_post_meta(
			$post_id,
			'_traitware_form_start',
			$form_start
		);
	}
}
Traitware_Admin::init();
