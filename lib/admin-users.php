<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

// page has GET vars of orderby,order,role.
// page has POST of data only coming from a JS form submission due to the requirement of a qr scan for each user change.
require 'class-traitware-userstable.php';

/**
 * URL helper function.
 * @param string $exclude
 * @return string
 */
function traitware_thisurl( $exclude = '' ) {
	$req = 'admin.php?page=' . wp_unslash( $_REQUEST['page'] );
	if ( isset( $_REQUEST['orderby'] ) && 'orderby' !== $exclude ) {
		$req .= '&orderby=' . $_REQUEST['orderby'];
	}

	if ( isset( $_REQUEST['order'] ) && 'order' !== $exclude ) {
		$req .= '&order=' . wp_unslash( $_REQUEST['order'] );
	}

	if ( isset( $_REQUEST['role'] ) && 'role' !== $exclude ) {
		$req .= '&role=' . wp_unslash( $_REQUEST['role'] );
	}

	return esc_url( $req );
}

// feedback from ajax call.
if ( ! session_id() ) {
	session_start(); }
if ( isset( $_SESSION['pollscan_userlist_message'] ) ) {
	if ( ! empty( $_SESSION['pollscan_userlist_message'] ) ) {
		$message = $_SESSION['pollscan_userlist_message'];
		unset( $_SESSION['pollscan_userlist_message'] );
	}
}

if ( isset( $message ) ) {
	?><div class="updated fade"><p><?php echo esc_html( $message ); ?></p></div>
	<?php
}
if ( isset( $error ) ) {
	if ( ! empty( $error ) ) {
	    ?>
		<div class="error fade"><p><?php echo esc_html( $error ); ?></p></div>
        <?php
	}
}

$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_consoleAuthorization' ) . '?state=' ) );
$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );

echo '
	<script>
		var data = {};
		jQuery(document).ready(function() {
			jQuery("#scanmodalboxclose").click( function() {
			    traitware_disable_polling();
				window.clearInterval(traitware_pollscan_id);
				window.clearInterval(traitware_qrcode_id);
				jQuery("#scanmodal").hide();
			});
			
			jQuery("#traitwaretableform").submit(function(e){
				e.preventDefault();
				
				var action = jQuery("#bulk-action-selector-top").val();
				if (action == "-1") {
					action = jQuery("#bulk-action-selector-bottom").val();
				}
				if (action == "-1") { return; }
				
				var data = []; var tmp;
				var additionalData = [];
				jQuery("[name=\'bulkrule[]\']:checkbox:checked").each(function(i){
					tmp = parseInt(jQuery(this).val(), 10);
					if (!isNaN(tmp)) {
					    data.push(tmp);
					    
					    if(action === "add") {
					        additionalData.push({
					            "usertype": "scrub"
					        });
					    }
					}
				});
				if (data.length === 0) { return; }
				
				traitware_useraction( action, data, additionalData );
			});
			
			jQuery("#quickusermodalboxclose").click( function() {
				jQuery(".traitwaremodal").hide();
			});
			
			jQuery("#quickuserroll").change(function() {
			    var role = jQuery("#quickuserroll").val();
			    var usertype = jQuery("#quickusertype").val();
                var isAccountOwner = window["traitware_vars"]["is_account_owner"] === "yes";
                
                jQuery("#quickusertype").html(traitware_getUserTypeOptionsHtml());
                
                if(role !== "administrator" || !isAccountOwner) {
                    jQuery("#quickusertype").find("option[value=\"owner\"]").remove();
                }
			
                var usertypeExists = jQuery("#quickusertype").find("option[value=\"" + usertype + "\"]").length;
                
                if(usertypeExists) {
                    jQuery("#quickusertype").val(usertype);
                }
            });
			
			jQuery("#quickuserform").submit(function(e){
				e.preventDefault();
				var email = jQuery("#quickuseremail").val();
				var username = jQuery("#quickusername").val();
				var role = jQuery("#quickuserroll").val();
				var usertype = jQuery("#quickusertype").val();
				if ( !isEmail(email) ) { return; }
				jQuery("#quickusermodal").hide();
				jQuery("#quickuseremail").val("");
				jQuery("#quickusername").val("");
				traitware_useraction( "wpuser", [email,username,role,usertype] );
			});
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
			traitware_enable_polling();
			traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
			traitware_qrcode_id = window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
			traitware_qrcode();
		}
		function traitware_quickuser() {
			jQuery("#quickusermodal").show();
			jQuery("#quickuseremail").trigger( "focus" );
			jQuery("#quickusertype").html(traitware_getUserTypeOptionsHtml());
			
			// remove the owner option for now
			jQuery("#quickusertype").find("option[value=\"owner\"]").remove();
		}
	</script>
	
	<div id="scanmodal" class="traitwaremodal">
		<div id="scanmodalbox" class="traitwaremodalbox">
			<div class="traitwareboxheader">
				<span id="scanmodalboxclose" class="traitwareboxclose">&times;</span>
				<p align="center">Scan with TraitWare app to confirm change</p>
			</div>
			<img id="traitware-modal-qrcode"/>
		</div>
	</div>
	
	<div id="quickusermodal" class="traitwaremodal">
		<div id="quickusermodalbox" class="traitwaremodalbox">
			<div class="traitwareboxheader">
				<span id="quickusermodalboxclose" class="traitwareboxclose">&times;</span>
				<p>Add a TraitWare User</p>
			</div>
			<form id="quickuserform">
				<div class="quickuserinputbox">
					<label>
                        <strong>Email</strong> (required)
                        <input name="quickuseremail" id="quickuseremail" class="quickuserinput" aria-describedby="new-user-email" value="" type="email" required >
					</label>
				</div>
				<div class="quickuserinputbox">
				    <label>
                        <strong>User Name</strong><br>
                        <input name="quickusername" id="quickusername" class="quickuserinput" aria-describedby="new-user-name" value="" type="text">
					</label>
				</div>
				<div class="quickuserinputbox">
				    <label>
                        <div class="quickuserleft"><strong>Role</strong></div>
    					<div class="quickuserright"><select name="quickuserroll" id="quickuserroll">
';
wp_dropdown_roles( get_option( 'default_role' ) );
echo '
					    </select></div>
					    <div class="clear"></div>
					</label>
				</div>
                <div class="quickuserinputbox">
                    <label>
                        <div class="quickuserleft"><strong>Type</strong></div>
                        <div class="quickuserright"><select name="quickusertype" id="quickusertype"></select></div>
                        <div class="clear"></div>
					</label>
				</div>
				<div class="submit">
					<input name="submit" id="submit" class="button button-primary" value="Add User" type="submit">
				</div>
			</form>
		</div>
	</div>
';

if ( isset( $_SESSION['pollscan_userlist_openmodaltext'] ) ) {
	if ( strlen( $_SESSION['pollscan_userlist_openmodaltext'] ) > 0 ) {
		$openmodaltext = $_SESSION['pollscan_userlist_openmodaltext'];
		echo '
			<div id="quickusermodalopenmodal" class="traitwaremodal" style="display:block;">
				<div id="scanmodalbox" class="traitwaremodalbox" style="height:180px;">
					<div class="traitwareboxheader">
						<span id="scanmodalboxclose" class="traitwareboxclose">&times;</span>
						<p align="center">Notice</p>
					</div>
					<div style="padding:20px;font-size:16px;">' . $openmodaltext . '</div>
				</div>
			</div>
		';
	}
	unset( $_SESSION['pollscan_userlist_openmodaltext'] );
}
if ( isset( $_SESSION['pollscan_userlist_openmodaldur'] ) ) {
	$openmodaldur = (int) $_SESSION['pollscan_userlist_openmodaldur'];
	if ( $openmodaldur > 0 ) {
		$openmodaldur = $openmodaldur * 1000; // ms is expected.
		echo '
			<script>
				function removeOpenModal() {
					jQuery("#quickusermodalopenmodal").hide();
				}
				setTimeout(removeOpenModal, ' . wp_json_encode( $openmodaldur ) . ');
			</script>
		';
	}
	unset( $_SESSION['pollscan_userlist_openmodaldur'] );
}

echo '
	<div class="wrap">

	<h1 class="wp-heading-inline">TraitWare User Management</h1>
';

if ( traitware_isAllowedToChangeStuff() ) {
	echo '
    <a href="javascript:void(0);" onclick="javascript:traitware_quickuser();" class="page-title-action">Add New User</a>
    <a href="#" class="traitware_bulksync_btn page-title-action">Bulk Sync Users</a>
    ';
}

echo '
	<p><b>To make changes to existing users or add a new user, you must be a TraitWare Account Owner or TraitWare Dashboard User.</p></b>
	<hr class="wp-header-end">
';

$users_table = new Traitware_UsersTable();
$users_table->set_vars(
	array(
		'isOwner'        => traitware_isAccountOwner(),
		'canChangeStuff' => traitware_isAllowedToChangeStuff(),
	)
);

global $wpdb;
$twusers  = $wpdb->prefix . 'traitwareusers';
$twlogins = $wpdb->prefix . 'traitwarelogins';

$request_role  = '';
$limit = array( 'orderby' => 'login' );
if ( isset( $_REQUEST['role'] ) ) {
	$request_role = sanitize_key( trim( $_REQUEST['role'] ) );
}
if ( empty( $request_role ) ) {
	$request_role = 'all';
}

if ( 'all' !== $request_role ) {
	$limit = array(
		'role__in' => array( $request_role ),
		'orderby'  => 'login',
	);
}

$users = get_users( $limit );
foreach ( $users as $user ) {

	// tw account.
	$user_status    = 'Inactive';
	$lastlogin = 0;
	$usertype  = '';

	$sql = 'SELECT `id`, `activeaccount`, `accountowner`, `usertype` FROM ' . $twusers . ' WHERE `userid` = %d';
	$stmt = $wpdb->prepare( $sql, array( $user->data->ID ) );
	$res = $wpdb->get_results( $stmt, OBJECT );
	if ( ! empty( $res ) ) {
		$usertype = $res[0]->usertype;

		$usertype = in_array( $usertype, array( 'dashboard', 'scrub' ) ) ? $usertype : 'dashboard';

		if ( 1 === intval( $res[0]->accountowner ) ) {
			$user_status   = 'Account Owner';
			$usertype = 'dashboard';
		} else {
		    if ( 1 === intval( $res[0]->activeaccount ) ) {
				$user_status = 'Active'; }
			}

			// last login.
            $last_login_sql = 'SELECT UNIX_TIMESTAMP(logintime) AS ts FROM ' . $twlogins . ' WHERE `twuserid` = %d ORDER BY `logintime` DESC LIMIT 1';
		    $last_login_stmt = $wpdb->prepare( $last_login_sql, array( $res[0]->id ) );
			$res = $wpdb->get_results( $last_login_stmt, OBJECT );
			if ( ! empty( $res ) ) {
				$lastlogin = (int) $res[0]->ts;
			}
	}

	$name = trim( $user->first_name . ' ' . $user->last_name );
	if ( empty( $name ) ) {
		$name = '-';
	}

	$users_table->data[] = array(
		'ID'        => (int) $user->data->ID,
		'username'  => $user->data->user_login,
		'status'    => $user_status,
		'lastlogin' => $lastlogin,
		'name'      => $name,
		'email'     => $user->data->user_email,
		'role'      => implode( ', ', array_map( 'ucfirst', $user->roles ) ),
		'type'      => $usertype,
	);

}
$users_table->prepare_items();

$wp_roles   = wp_roles();
$role_links = array();
if ( 'all' === $request_role ) {
	$role_links[] = '<b>All User Roles</b>';
} else {
	$role_links[] = '<a href="' . traitware_thisurl( 'role' ) . '&role=all">All User Roles</a>';
}

foreach ( $wp_roles->get_names() as $this_role => $name ) {
	if ( $this_role === $request_role ) {
		$role_links[] = '<b>' . esc_html( translate_user_role( $name ) ) . '</b>';
	} else {
		$role_links[] = '<a href="' . traitware_thisurl( 'role' ) . '&role=' . $this_role . '">' . esc_html( translate_user_role( $name ) ) . '</a>';
	}
}
echo implode( ' | ', $role_links );

echo '<form method="post" id="traitwaretableform" action="' . traitware_thisurl() . '">';
$users_table->display();
echo '</form>';
