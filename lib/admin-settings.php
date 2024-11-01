<?php
/**
 * Admin settings code.
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( isset( $_REQUEST['submit'] ) ) {
	// Check nonce.
	if ( ! isset( $_REQUEST['traitware_nonce'] ) ) { // Missing nonce.
		$error_text = 'nonce field is missing. Settings NOT saved.';
	} elseif ( ! wp_verify_nonce( sanitize_key( $_REQUEST['traitware_nonce'] ), 'traitware' ) ) { // Invalid nonce.
		$error_text = 'Invalid nonce specified. Settings NOT saved.';
	} else {

		$traitware_disablewplogin = 0;
		if ( isset( $_REQUEST['traitware_disablewplogin'] ) ) {
			$traitware_disablewplogin = intval( sanitize_key( $_REQUEST['traitware_disablewplogin'] ) );
		}
		update_option( 'traitware_disablewplogin', $traitware_disablewplogin );

		$traitware_enableonlywplogin = 0;
		if ( isset( $_REQUEST['traitware_enableonlywplogin'] ) ) {
			$traitware_enableonlywplogin = intval( sanitize_key( $_REQUEST['traitware_enableonlywplogin'] ) );
		}
		update_option( 'traitware_enableonlywplogin', $traitware_enableonlywplogin );

		$traitware_alternatelogin = '';
		if ( isset( $_REQUEST['traitware_alternatelogin'] ) ) {
			$traitware_alternatelogin = esc_url_raw( wp_unslash( $_REQUEST['traitware_alternatelogin'] ) );
		}
		update_option( 'traitware_alternatelogin', $traitware_alternatelogin );

		$traitware_limitaccesspts = array();
		if ( isset( $_REQUEST['traitware_limitaccesspts'] ) && is_array( $_REQUEST['traitware_limitaccesspts'] ) ) {
			$traitware_limitaccesspts_raw = wp_unslash( $_REQUEST['traitware_limitaccesspts'] );
			foreach ( $traitware_limitaccesspts_raw as $pt ) {
			    $traitware_limitaccesspts[] = sanitize_text_field( $pt );
            }
		}
		update_option( 'traitware_limitaccesspts', $traitware_limitaccesspts );

		$traitware_protectedpageselector = '';
		if ( isset( $_REQUEST['traitware_protectedpageselector'] ) ) {
			$traitware_protectedpageselector = wp_unslash ( $_REQUEST['traitware_protectedpageselector'] );
		}
		update_option( 'traitware_protectedpageselector', $traitware_protectedpageselector );

		if ( isset( $_REQUEST['traitware_protectedpageaccessdeniedmessagetype'] ) ) {
			$traitware_protectedpageaccessdeniedmessagetype = sanitize_text_field( wp_unslash ( $_REQUEST['traitware_protectedpageaccessdeniedmessagetype'] ) );
			if ( 'text' === $traitware_protectedpageaccessdeniedmessagetype ) {
				update_option( 'traitware_protectedpageaccessdeniedmessagetype', $traitware_protectedpageaccessdeniedmessagetype );
				if ( isset( $_REQUEST['traitware_protectedpageaccessdeniedmessagetext'] ) &&
					! empty( $_REQUEST['traitware_protectedpageaccessdeniedmessagetext'] ) ) {
					update_option( 'traitware_protectedpageaccessdeniedmessagetext', sanitize_textarea_field( $_REQUEST['traitware_protectedpageaccessdeniedmessagetext'] ) );
				} else {
					update_option( 'traitware_protectedpageaccessdeniedmessagetext', 'Access Denied' );
				}
			} elseif ( 'html' === $traitware_protectedpageaccessdeniedmessagetype ) {
				global $__traitware_post;
				if ( isset( $__traitware_post['traitware_protectedpageaccessdeniedmessagehtml'] ) &&
					! empty( $__traitware_post['traitware_protectedpageaccessdeniedmessagehtml'] ) ) {
					update_option( 'traitware_protectedpageaccessdeniedmessagetype', $traitware_protectedpageaccessdeniedmessagetype );
					update_option( 'traitware_protectedpageaccessdeniedmessagehtml', base64_encode( $__traitware_post['traitware_protectedpageaccessdeniedmessagehtml'] ) );
				} else {
					$error_text = 'You must enter the <b>Access Denied Message</b> HTML';
				}
			} elseif ( 'post' === $traitware_protectedpageaccessdeniedmessagetype ) {
				if ( isset( $_REQUEST['traitware_protectedpageaccessdeniedmessagepost'] ) &&
					! empty( $_REQUEST['traitware_protectedpageaccessdeniedmessagepost'] ) ) {
					if ( get_post( intval( $_REQUEST['traitware_protectedpageaccessdeniedmessagepost'] ) ) === null ) {
						$error_text = 'Invalid post specified for the <b>Access Denied Message</b>.';
					} else {
						update_option( 'traitware_protectedpageaccessdeniedmessagetype', $traitware_protectedpageaccessdeniedmessagetype );
						update_option( 'traitware_protectedpageaccessdeniedmessagepost', intval( sanitize_text_field( wp_unslash ( $_REQUEST['traitware_protectedpageaccessdeniedmessagepost'] ) ) ) );
					}
				} else {
					$error_text = 'You must enter a post ID for the <b>Access Denied Message</b>.';
				}
			}
		}

		$traitware_disablecustomlogin = 0;
		if ( isset( $_REQUEST['traitware_disablecustomlogin'] ) ) {
			$traitware_disablecustomlogin = intval( wp_unslash( $_REQUEST['traitware_disablecustomlogin'] ) );
		}
		update_option( 'traitware_disablecustomlogin', $traitware_disablecustomlogin );

		$traitware_disablecustomloginform = 0;
		if ( isset( $_REQUEST['traitware_disablecustomloginform'] ) ) {
			$traitware_disablecustomloginform = intval( wp_unslash( $_REQUEST['traitware_disablecustomloginform'] ) );
		}
		update_option( 'traitware_disablecustomloginform', $traitware_disablecustomloginform );

		$traitware_disablecustomloginrecovery = 0;
		if ( isset( $_REQUEST['traitware_disablecustomloginrecovery'] ) ) {
			$traitware_disablecustomloginrecovery = intval( wp_unslash( $_REQUEST['traitware_disablecustomloginrecovery'] ) );
		}
		update_option( 'traitware_disablecustomloginrecovery', $traitware_disablecustomloginrecovery );

		if ( isset( $_REQUEST['traitware_customloginselector'] ) && ! empty( $_REQUEST['traitware_customloginselector'] ) ) {
			update_option( 'traitware_customloginselector', wp_unslash( $_REQUEST['traitware_customloginselector'] ) );
		} else {
			delete_option( 'traitware_customloginselector' );
		}

		if ( isset( $_REQUEST['traitware_customloginredirect'] ) && ! empty( $_REQUEST['traitware_customloginredirect'] ) ) {
			update_option( 'traitware_customloginredirect', esc_url_raw( wp_unslash( $_REQUEST['traitware_customloginredirect'] ) ) );
		} else {
			delete_option( 'traitware_customloginredirect' );
		}

		$traitware_enableselfregistration = 0;
		if ( isset( $_REQUEST['traitware_enableselfregistration'] ) ) {
			$traitware_enableselfregistration = intval( wp_unslash( $_REQUEST['traitware_enableselfregistration'] ) );
		}
		update_option( 'traitware_enableselfregistration', $traitware_enableselfregistration );

		$message = 'Settings Saved.';
	}
}

if ( isset( $message ) ) {
	?><div class="notice notice-success is-dismissible"><p><?php echo $message; ?></p></div>
	<?php
}
if ( isset( $error_text ) ) {
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo $error_text; ?></p></div>
    <?php
}

echo '
	<div class="wrap">';
    $is_onlywplogin = get_option('traitware_enableonlywplogin');
	 $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'login';
	echo '
	 <h2 class="nav-tab-wrapper traitware-nav">
    		<a href="#" class="nav-tab';
echo 'login' === $active_tab ? ' nav-tab-active' : ''; echo '" data-tab="login">WordPress Dashboard Login</a>
			<a href="#" class="nav-tab';
echo 'custom-login' === $active_tab ? ' nav-tab-active' : ''; echo $is_onlywplogin ? ' disabled' : ''; echo '" data-tab="custom-login">Custom Login</a>
			<a href="#" class="nav-tab';
echo 'restricted-content' === $active_tab ? ' nav-tab-active' : ''; echo $is_onlywplogin ? ' disabled' : ''; echo '" data-tab="restricted-content">Protected/Restricted Content</a>
			<a href="#" class="nav-tab';
echo 'self-registration' === $active_tab ? ' nav-tab-active' : ''; echo $is_onlywplogin ? ' disabled' : ''; echo '" data-tab="self-registration">Self-Registration</a>
			<a href="#" class="nav-tab';
echo 'misc-settings' === $active_tab ? ' nav-tab-active' : ''; echo '" data-tab="misc-settings">Bulk User Activate</a>
			<a href="#" class="nav-tab';
echo 'documentation' === $active_tab ? ' nav-tab-active' : ''; echo '" data-tab="documentation">Documentation</a>
	</h2>
	<form id="traitware-settings-form" method="post" action="admin.php?page=traitware-settings&tab='; echo esc_attr( $active_tab ) . '">
        <table class="form-table traitware-form-table traitware-form-table-login';
echo 'login' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>
                <tr>
                    <th><label for="traitware_disablewplogin">Disable Standard WP Login</label></th>
                    <td>
                        <input name="traitware_disablewplogin" type="checkbox" id="traitware_disablewplogin" value="1" ';
						checked( 1, get_option( 'traitware_disablewplogin' ) );
						echo ' />
                        Checking this box will remove the standard username and password fields from the WordPress login screen. This will increase the overall security of the site by enforcing logins with QR codes only. NOTE: these can easily be recovered, and this setting can be turned off.
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_enableonlywplogin">Enable Only For WP Login</label></th>
                    <td>
                        <input name="traitware_enableonlywplogin" type="checkbox" id="traitware_enableonlywplogin" value="1" ';
						checked( 1, get_option( 'traitware_enableonlywplogin' ) );
						echo ' />
                        Checking this box will enable TraitWare only on the WordPress login screen. Other features will be disabled, such as Protected Pages, Protected Content, Custom Form Protection, and TraitWare Forms.
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_alternatelogin">Alternate Login Page URL</label></th>
                    <td>
                        <input name="traitware_alternatelogin" type="text" id="traitware_alternatelogin" value="' . esc_attr( get_option( 'traitware_alternatelogin', '' ) ) . '" class="regular-text" /><br>
                        If your site uses an alternate URL for your admin login (something other than <i>/wp-login.php</i> or <i>/wp-admin</i>) <b>and the TraitWare QR Code is not already displaying there</b>
                        then please enter it in the box above. For example, if your login page is <i>http://domain.com/supersecretlogin</i> then enter <i>supersecretlogin</i> above.
                    </td>
                </tr>
            </tbody>
		</table>
        <table class="form-table traitware-form-table traitware-form-table-custom-login';
echo 'custom-login' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>
                <tr>
                    <th><label for="traitware_disablecustomlogin">Disable Custom Login Protection</label></th>
                    <td>
                        <input name="traitware_disablecustomlogin" type="checkbox" id="traitware_disablecustomlogin" value="1"';
		checked( 1, get_option( 'traitware_disablecustomlogin' ) );
		echo ' /> If you have a Custom Login page, TraitWare will attempt to recognize that page’s url and protect it by default. Select this option to disable this feature.				
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_disablecustomloginform">Disable Custom Login Form</label></th>
                    <td>
                        <input name="traitware_disablecustomloginform" type="checkbox" id="traitware_disablecustomloginform" value="1"';
		checked( 1, get_option( 'traitware_disablecustomloginform' ) );
		echo ' />If you are using a Custom Login form, Checking this box will remove the standard username and password fields from the WordPress login screen. This will increase the overall security of the site by enforcing logins with QR codes only. NOTE: these can easily be recovered, and this setting can be turned off.
			
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_disablecustomloginformrecovery">Disable Custom Login Recovery</label></th>
                    <td>
                        <input name="traitware_disablecustomloginrecovery" type="checkbox" id="traitware_disablecustomloginrecovery" value="1"';
		checked( 1, get_option( 'traitware_disablecustomloginrecovery' ) );
		echo ' /> Selecting this option disables site users from being able to recover their accounts on custom log in pages.				
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_customloginselector">Custom Login Selector</label></th>
                    <td>
                        <input name="traitware_customloginselector" type="text" id="traitware_customloginselector" value="' . esc_attr( get_option( 'traitware_customloginselector', '' ) ) . '" class="regular-text" /><br>
                        The selector for custom login forms to be protected by TraitWare. Some examples are <i>#custom_login_form</i>, <i>form.advanced-login</i>, and <i>body.content .custom_form</i>. If nothing is entered, TraitWare will attempt to find common login forms automatically.
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_customloginselector">Custom Login Redirect</label></th>
                    <td>
                        <input name="traitware_customloginredirect" type="text" id="traitware_customloginredirect" value="' . esc_attr( get_option( 'traitware_customloginredirect', '' ) ) . '" class="regular-text" /><br>
                        You can force TraitWare to redirect successful custom login attempts to any URL by setting this option. For example, to redirect to a page on your site with the URL <i>https://domain.com/log-in</i>, you would enter <i>log-in</i>. You can even redirect to different domains entirely, such as <i>http://www.someotherdomain.com</i>.
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="form-table traitware-form-table traitware-form-table-restricted-content';
echo 'restricted-content' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>
                <tr>
            <tr>
            <th><label for="traitware_limitaccesspts">Limit Access Post Types</label></th>
                    <td>
                    Selecting the various Post Types below will allow them to be protected with TraitWare. By default “Posts” and “Pages” are protected. Select any of the custom options below:<br>
                        ';
// load the current "Limit Page Access" post types value.
$current_limitaccesspts = get_option( 'traitware_limitaccesspts' );

// load the current "Access Denied Message" values.
$current_protectedpageaccessdeniedmessagetype = get_option( 'traitware_protectedpageaccessdeniedmessagetype' );
if ( $current_protectedpageaccessdeniedmessagetype === false || $current_protectedpageaccessdeniedmessagetype === null || empty( $current_protectedpageaccessdeniedmessagetype ) ) {
	$current_protectedpageaccessdeniedmessagetype = 'text';
}

$current_protectedpageaccessdeniedmessagetext = get_option( 'traitware_protectedpageaccessdeniedmessagetext' );
$current_protectedpageaccessdeniedmessagehtml = esc_textarea( base64_decode( get_option( 'traitware_protectedpageaccessdeniedmessagehtml' ) ) );
$current_protectedpageaccessdeniedmessagepost = get_option( 'traitware_protectedpageaccessdeniedmessagepost' );

// make sure we use a default value if there is not an array stored here.
if ( ! is_array( $current_limitaccesspts ) ) {
	$current_limitaccesspts = array( 'post', 'page' );
}

$post_types = get_post_types( array( 'public' => true ), 'objects' );
foreach ($post_types as $pt ) {
	if ( 'traitware_form' === $pt->name ) {
		continue; }
	$checked_value = esc_attr ( in_array( $pt->name, $current_limitaccesspts, true ) ? 'checked="checked"' : '' );
	echo '
    <label><input name="traitware_limitaccesspts[]" type="checkbox" ' . esc_attr( $checked_value ) . ' value="' . esc_attr( $pt->name ) . '" /> ' . esc_html( $pt->label ) . '</label><br>
	';
}
echo '
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_protectedpageselector">Advanced: Protected Page CSS Selector</label></th>
                    <td>
                        <input name="traitware_protectedpageselector" type="text" id="traitware_protectedpageselector" value="' . esc_attr( get_option( 'traitware_protectedpageselector', '' ) ) . '" class="regular-text" /><br>
                        The selector for the DOM element to be protected by TraitWare for page level protection. For example, if your page\'s content selector is <i>#my_page_content</i>, then enter <i>#my_page_content</i>. If nothing is entered, TraitWare will use the default selector <i>#primary</i>.
                    </td>
                </tr>
                <tr>
                    <th><label for="traitware_protectedpageaccessdeniedmessagetype">Protected Pages/Restricted Content Message</label></th>
                    <td>
                    The message that appears to logged-in users who do not have access to restricted content. If nothing is entered, the default plain text message Access Denied will be used by TraitWare.<br />
                        <label>
                            <input type="radio" name="traitware_protectedpageaccessdeniedmessagetype" value="text"' . ( 'text' === $current_protectedpageaccessdeniedmessagetype ? ' checked' : '' ) . ' />
                            Plain Text
                        </label><br />
                        <input name="traitware_protectedpageaccessdeniedmessagetext" type="text" id="traitware_protectedpageaccessdeniedmessagetext" value="' . esc_attr( $current_protectedpageaccessdeniedmessagetext ) . '" class="regular-text"' . ( $current_protectedpageaccessdeniedmessagetype !== 'text' ? ' disabled' : '' ) . ' /><br>
                        <label>
                            <input type="radio" name="traitware_protectedpageaccessdeniedmessagetype" value="post"' . ( 'post' === $current_protectedpageaccessdeniedmessagetype ? ' checked' : '' ) . ' />
                            Post ID
                        </label><br />
                        <input type="text" name="traitware_protectedpageaccessdeniedmessagepost" id="traitware_protectedpageaccessdeniedmessagepost" value="' . esc_attr( $current_protectedpageaccessdeniedmessagepost ) . '" class="regular-text"' . ( $current_protectedpageaccessdeniedmessagetype !== 'post' ? ' disabled' : '' ) . ' /><br />
                        <label>
                            <input type="radio" name="traitware_protectedpageaccessdeniedmessagetype" value="html"' . ( 'html' === $current_protectedpageaccessdeniedmessagetype ? ' checked' : '' ) . ' />
                            HTML
                        </label><br />
                        <textarea name="traitware_protectedpageaccessdeniedmessagehtml" id="traitware_protectedpageaccessdeniedmessagehtml" rows="7" cols="50" class="regular-text"' . ( 'html' !== $current_protectedpageaccessdeniedmessagetype ? ' disabled' : '' ) . '>' . wp_kses_post( $current_protectedpageaccessdeniedmessagehtml ) . '</textarea><br />
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="form-table traitware-form-table traitware-form-table-self-registration';
echo 'self-registration' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>
                <tr>
                    <th><label for="traitware_enableselfregistration">Enable Self-Registration</label></th>
                    <td>
                        <input name="traitware_enableselfregistration" type="checkbox" id="traitware_enableselfregistration" value="1"';
		checked( 1, get_option( 'traitware_enableselfregistration' ) );
		echo ' /> By default, TraitWare self-registration is disabled. Checking this box will allow users to register themselves with TraitWare. To create self-registration options, navigate to TraitWare Forms.
                    </td>
                </tr>
                <tr>
                    <th>
                        <a href="' . esc_url( admin_url( 'post-new.php?post_type=traitware_form' ) ) . '" class="button button-secondary">Add New Form</a>
                    </th>
                </tr>
            </tbody>
        </table>
        <table class="form-table traitware-form-table traitware-form-table-misc-settings';
echo 'misc-settings' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>';
if ( traitware_isAccountOwner() ) {
	echo '
                <tr>
                    <th><label for="traitware_syncallusers">Sync all WP users with TraitWare</label></th>
                    <td>
                        <a href="#" class="button traitware_bulksync_btn">Sync Users</a><br>
                        Select ‘Sync Users’ button above to sync existing WP users with TraitWare. All users will then be added to TraitWare User Management, and can be managed with TraitWare.
                    </td>
                </tr>';
}
		echo '
            </tbody>
        </table>
        <table class="form-table traitware-form-table traitware-form-table-documentation';
echo 'documentation' === $active_tab ? ' active' : ''; echo '">
            <thead>
              <tr>
                 <th>General Settings</th>
              </tr>
            </thead>
            <tbody>
            <tr>
                    <td>
                        <p for="documentation"><b>Scanning the QR Code to Login</b></p><br />
                        <a href="https://imgur.com/pQpbs6v"><img src="https://i.imgur.com/pQpbs6v.gif" title="source: imgur.com" /></a><br />
                    </td>
                </tr>
            
                <tr>
                    <td>
                        <p for="documentation"><b>How To For General Users</b></p><br />
                        <iframe src="https://player.vimeo.com/video/270453260" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>General Part 2: User Management</b></p><br />
                    <iframe src="https://player.vimeo.com/video/276937968" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>General Part 3: Account Management</b></p><br />
                <iframe src="https://player.vimeo.com/video/276949779" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>General Part 4: Updating Users</b></p><br />
                <iframe src="https://player.vimeo.com/video/268860567" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>Settings Part 1: WordPress Standard Login</b></p><br />
                <iframe src="https://player.vimeo.com/video/295429053" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>Settings Part 2: Custom Login</b></p><br />
                <iframe src="https://player.vimeo.com/video/295673907" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>Settings Part 3: Protected Resource</b></p><br />
                <iframe src="https://player.vimeo.com/video/295675978" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>Settings Part 4: Self-Registration</b></p><br />
                <iframe src="https://player.vimeo.com/video/295677804" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
                <tr>
                <td>
                <p for="documentation"><b>Settings Part 5: Bulk User Sync</b></p><br />
                <iframe src="https://player.vimeo.com/video/295684057" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe><br />
                    </td>
                </tr>
            </tbody>
        </table>';
	wp_nonce_field( 'traitware', 'traitware_nonce' );
		echo '
        <p>
            <input name="submit" type="submit" value="Save Settings" class="button button-primary';
echo ( 'documentation' === $active_tab ? ' traitware-hidden-input' : '' ); echo '" />
        </p>
    </form>
</div>
<div id="scanmodal" class="traitwaremodal">
    <div id="scanmodalbox" class="traitwaremodalbox">
        <div class="traitwareboxheader">
            <span id="scanmodalboxclose" class="traitwareboxclose">&times;</span>
            <p align="center">Scan with TraitWare app to confirm change</p>
        </div>
        <img id="traitware-modal-qrcode"/>
    </div>
</div>
';

$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_consoleAuthorization' ) . '?state=' ) );
$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

echo '
<script type="text/javascript">
jQuery("input[name=traitware_protectedpageaccessdeniedmessagetype]").on("change", function(e) {
    if ( "text" === this.value ) {
        jQuery("#traitware_protectedpageaccessdeniedmessagetext").prop("disabled", false);
        jQuery("#traitware_protectedpageaccessdeniedmessagepost").prop("disabled", true);
        jQuery("#traitware_protectedpageaccessdeniedmessagehtml").prop("disabled", true);

    } else if ( "html" === this.value ) {
        jQuery("#traitware_protectedpageaccessdeniedmessagetext").prop("disabled", true);
        jQuery("#traitware_protectedpageaccessdeniedmessagepost").prop("disabled", true);
        jQuery("#traitware_protectedpageaccessdeniedmessagehtml").prop("disabled", false);
    } else {
        jQuery("#traitware_protectedpageaccessdeniedmessagetext").prop("disabled", true);
        jQuery("#traitware_protectedpageaccessdeniedmessagepost").prop("disabled", false);
        jQuery("#traitware_protectedpageaccessdeniedmessagehtml").prop("disabled", true);
    }
});

var traitware_site_url = ' . $traitware_site_url . ';
var traitware_pollscan_action = "userlist";
var traitware_qrclassname = "#traitware-modal-qrcode";
var traitware_qrcode_url = ' . $traitware_qrcode_url . ';
var traitware_auth_url = ' . $traitware_auth_url . ';
var traitware_poll_url = ' . $traitware_poll_url . ';
var traitware_nonce = ' . wp_json_encode( wp_create_nonce('traitware_ajax') ) . ';
var traitware_qrcode_id = 0;

function traitware_useraction( action, data, extra ) {
    extra = typeof extra === "undefined" ? [] : extra;

    traitware_data = {
        "userlistaction":action,
        "userlistdata":data,
        "userlistextradata":extra
    }
    jQuery("#scanmodal").show();
    traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
    traitware_qrcode_id = window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
    traitware_qrcode();
}

var data = {};
jQuery(document).ready(function() {
    jQuery("#scanmodalboxclose").click( function() {
        window.clearInterval(traitware_pollscan_id);
        window.clearInterval(traitware_qrcode_id);
        jQuery("#scanmodal").hide();
    });
});

jQuery(".traitware-nav .nav-tab").on("click", function(e) {
    e.preventDefault();
    
    if (jQuery(this).hasClass("disabled")) {
        return false;
    }
    
    var tab = jQuery(this).data("tab");
    var form = jQuery("#traitware-settings-form");
    var submit = form.find("input[name=\'submit\']");
    var form_action = "admin.php?page=traitware-settings&tab=" + tab;
    form.attr("action", form_action);
    
    jQuery(".traitware-nav .nav-tab").removeClass("nav-tab-active");
    jQuery(this).addClass("nav-tab-active");
    jQuery(".traitware-form-table").removeClass("active");
    
    if (tab === "login") {
        jQuery(".traitware-form-table-login").addClass("active");
        submit.removeClass("traitware-hidden-input");
    } else if (tab === "custom-login") {
        jQuery(".traitware-form-table-custom-login").addClass("active");
        submit.removeClass("traitware-hidden-input");
    } else if (tab === "restricted-content") {
        jQuery(".traitware-form-table-restricted-content").addClass("active");
        submit.removeClass("traitware-hidden-input");
    } else if (tab === "self-registration") {
        jQuery(".traitware-form-table-self-registration").addClass("active");
        submit.removeClass("traitware-hidden-input");
    } else if (tab === "misc-settings") {
        jQuery(".traitware-form-table-misc-settings").addClass("active");
        submit.removeClass("traitware-hidden-input");
    } else {
        jQuery(".traitware-form-table-documentation").addClass("active");
        submit.addClass("traitware-hidden-input");
    }
    
    if (typeof (history.pushState) !== "undefined") {
        var obj = { Title: document.title, Url: (form_action) };
        history.pushState(obj, obj.Title, obj.Url);
    }
});


</script>
';
