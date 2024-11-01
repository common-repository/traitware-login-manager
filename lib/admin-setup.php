<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

if ( ! traitware_isadmin() ) {
	die( 'No Access' );
}
if ( get_option( 'traitware_useraccount' ) ) {
	die( 'No Access' );
}

$the_current_user = wp_get_current_user();
if ( ! ( $the_current_user instanceof WP_User ) ) {
	die();
}

$traitware_site_url = wp_json_encode( wp_unslash( get_site_url() ) );
$traitware_qrcode_url = wp_json_encode( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'qrcode.php?&str=' ) );
$traitware_auth_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_consoleAuthorization' ) . '?state=' ) );
$traitware_poll_url = wp_json_encode( wp_unslash( traitware_get_var( 'apiurl' ) . traitware_get_var( 'api_loginAttempts' ) ) );
$new_account_nonce = wp_create_nonce('traitware_new_account');

// inline the following CSS because users only ever see it once.
?>
<style>
		a {
			cursor: pointer;
		}

		#traitware-setup-logo {
			width: 231px;
			height: 50px;
			position: relative;
			margin: 40px 40px 0 40px;
		}

		.footer {
			position: absolute;
			bottom: 0;
		}

		#wrapper {
			width: 100%;
			height: 100%;
			position: relative;
		}

		.container {
			margin: 0 auto;
			margin-top: 20px;
			display: flex;
			justify-content: center;
		}

		#header {
			height: 50px;
			padding: 5px 0px 5px 0px;
			width: 100%;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

        .header-title {
			width: 50%;
			display: flex;
		}
		
		.header-title h1 {
            font-size: 23px;
            margin: .67em 0;
            font-weight: 400;
		}

		#support {
			width: 50%;
			display: flex;
			align-items: center;
			justify-content: flex-end;
			margin-right: 20px;
		}

		#support p,
		#support a,
		#support span {
			font-size: 16px;
			margin: 0;
			display: inline;
			font-weight: 100;
			padding-right: 10px;
		}

		/* constraining height/width for white traitware logo */

		.logo img {
			height: 40px;
			width: 198px;
			margin-top: 4px;
			margin-left: 20px;
		}

		/* traitware cards */

		.traitware-card {
			display: flex;
			flex-direction: column;
			flex-wrap: nowrap;
			background: white;
			width: 100%;
			max-width: 650px;
			border: 1px solid #D5D5D5;
			/* box-shadow: 0px 2px 4px rgba(0, 0, 0, .34); */
			/* transition: .2s box-shadow cubic-bezier(0.445, 0.05, 0.55, 0.95); */
		}

		.traitware-card:hover {
			display: flex;
			flex-direction: column;
			flex-wrap: nowrap;
			background: white;
			width: 100%;
			max-width: 650px;
			border: 1px solid #D5D5D5;
			/* box-shadow: 0px 10px 16px rgba(0, 0, 0, .20); */
			/* transition: .2s box-shadow cubic-bezier(0.445, 0.05, 0.55, 0.95); */
		}

		.card-header {
			background: #208FD0;
			padding: 20px 20px 10px 20px;
			border-bottom: 2px solid #076FB5;
		}

		.card-header h3 {
			font-weight: 400;
			font-size: 24px;
			color: white;
			margin: 0;
			padding-bottom: 10px;
			letter-spacing: .5px;
			text-shadow: 1px 1px 20px rgb(0, 0, 0, 0.1);
		}

		.card-content {
			padding: 10px 30px 30px 30px;
		}

		.card-content h4 {
			text-align: center;
			font-size: 24px;
			font-weight: 400;
			margin-bottom: 10px;
		}

		.card-content p {
			/* font styles */
			text-align: center;
			font-weight: 100;
			font-size: 16px;
			color: rgb(118, 118, 118);
			/* spacing */
			margin: 0;
		}

		.continue-button {
			display: flex;
			margin-top: 20px;
			margin-bottom: 50px;
			justify-content: center;
		}

		.traitware-button {
			margin: 0 auto;
			width: auto;
			border-radius: 30px 30px 30px 30px;
			border: none;
			padding: 20px 30px 18px 30px;
			background-color: #259AD6;
			font-size: 10px;
			font-weight: 600;
			font-style: normal;
			text-decoration: none;
			text-transform: uppercase;
			letter-spacing: 2.2px;
			color: white;
			transition: .3s background-color cubic-bezier(0.445, 0.05, 0.55, 0.95);
			cursor: pointer;
		}

		.traitware-button:hover {
			margin: 0 auto;
			width: auto;
			border-radius: 30px 30px 30px 30px;
			border: none;
			padding: 20px 30px 18px 30px;
			background-color: hsl(200, 71%, 43%);
			font-size: 10px;
			font-weight: 600;
			font-style: normal;
			text-decoration: none;
			text-transform: uppercase;
			letter-spacing: 2.2px;
			color: white;
			transition: .3s background-color cubic-bezier(0.445, 0.05, 0.55, 0.95);
		}

		.traitware-button:focus {
			outline: none;
		}

		.traitware-customer {
			margin: 0;
			padding: 15px 30px;
		}
		.traitware-customer h3 {
			text-align: center;
			font-size: 21px;
			color: rgb(50,50,50);
		}
		.traitware-customer span {
			color: #717171;
			margin-right: 10px;
			font-size: 16px;
		}

		/* Modal styling for the JS modal */

		.modal {
			display: none;
			/* Hidden by default */
			position: absolute !important;
			/* Stay in place */
			z-index: 1;
			/* Sit on top */
			left: 0;
			top: 0;
			width: 100%;
			/* Full width */
			min-height: 1000px;
			margin-top: 20px;
			overflow: auto;
			/* Enable scroll if needed */
			background-color: #F1F1F1;
			/* background-color: rgba(0,0,0,0.4); Black w/ opacity */
			backdrop-filter: blur(10px);
			padding-bottom: 50px;
			margin-bottom: 60px;
		}

		/* Modal Content/Box */

		/* Styling specifically for QR Modal */

		#addSiteModal .modal-content {
			margin: 0 auto;
			max-width: 350px;
			width: fit-content;
			display: flex;
			flex-direction: column;
			background-color: #fefefe;
			/* box-shadow: 0px 10px 26px rgba(0, 0, 0, .18); */
			border: 1px solid #D5D5D5;
		}

		/* Styling specifically for onboarding */

		#textLinkModal .modal-content {
			margin: 0 auto;
			max-width: 400px;
			width: fit-content;
			display: flex;
			flex-direction: column;
			background-color: #fefefe;
			/* box-shadow: 0px 10px 26px rgba(0, 0, 0, .18); */
			border: 1px solid #D5D5D5;
		}

		#textLinkModal .modal-content .modal-header .modal-title {
			line-height: 28px;
		}

		#text-buttons {
			padding-top: 20px;
		}

		#skipButton {
			background-color: #44B2F3;
			transition: background-color .3s cubic-bezier(0.445, 0.05, 0.55, 0.95);
		}
		#skipButton:hover {
			background-color: #35A4E6;
			transition: background-color .3s cubic-bezier(0.445, 0.05, 0.55, 0.95);
		}

		#successModal .modal-content {
			margin: 0 auto;
			max-width: 480px;
			width: fit-content;
			display: flex;
			flex-direction: column;
			background-color: #fefefe;
			/* box-shadow: 0px 10px 26px rgba(0, 0, 0, .18); */
			border: 1px solid #D5D5D5;
		}

		#successModal .modal-content .modal-footer {
			margin-bottom: 34px;
		}

		#successModal .modal-content .modal-header {
			margin-bottom: 15px;
		}

		#successModal .modal-content .modal-body h3, #successModal .modal-content .modal-body p {
			text-align: center;
		}

		#error {
			color: red;
			font-size: 11px;
			clear: both;
			display: block;
			padding-top: 5px;
			margin: 0;
		}

		.modal-title {
			font-weight: 300;
			font-size: large;
			color: #FFF;
			margin: 0;
		}

		.modal-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			background: #208FD0;
			border-bottom: 2px solid #076FB5;
			padding: 20px;
		}

		.modal-body {
			padding: 20px;
		}

		.modal-body img {
			margin: 0 auto;
			width: 252px;
		}

		.modal-body h3 {
			line-height: 23px;
			margin-bottom: 0px;
		}

		.modal-body p {
			font-weight: 100;
			margin-top: 0px;
		}

		.modal-footer {
			margin-bottom: 60px;
			border: none;
			display: flex;
			justify-content: center;
		}

		.modal-footer input {
			clear: both;
			/* justify-self: center; */
			width: 228px;
			border-radius: 3px;
			padding: 8px 4px;
		}

		/* The Close Button */

		.close {
			background: transparent;
			border: none;
			color: #FFF;
			font-size: 28px;
			font-weight: 200;
		}

		.close:hover,
		.close:focus {
			color: #E2E1E2;
			text-decoration: none;
			cursor: pointer;
		}

		.closeThree {
			background: transparent;
			border: none;
			color: #FFF;
			font-size: 28px;
			font-weight: 200;
		}

		.closeThree:hover,
		.closeThree:focus {
			color: #E2E1E2;
			text-decoration: none;
			cursor: pointer;
		}

		.closeFour {
			background: transparent;
			border: none;
			color: #FFF;
			font-size: 28px;
			font-weight: 200;
		}

		.closeFour:hover,
		.closeFour:focus {
			color: #E2E1E2;
			text-decoration: none;
			cursor: pointer;
		}

		/* Class to "show" hidden modal for use with Jquery */

		.showMe {
			display: block;
		}

		/* Loader */

		.loader {
			display: none;
			/* Hidden by default */
			position: absolute !important;
			/* Stay in place */
			z-index: 1;
			/* Sit on top */
			left: 0;
			top: 0;
			width: 100%;
			/* Full width */
			height: 100%;
			margin-top: 20px;
			overflow: auto;
			/* Enable scroll if needed */
			/* background-color: rgba(0,0,0,0.4); Black w/ opacity */
			padding-bottom: 50px;
			margin-bottom: 60px;
			background: rgba(239, 239, 239,.8) url('<?php echo esc_url( plugins_url( 'traitware-login-manager/assets/loader.gif' ) ); ?>') 50% 50% no-repeat;
			background-size: 270px 150px;
		}

		.traitware-form-table-account-setup {
			position: relative;
		}

		#wpbody-content .loading .loader {
			overflow: hidden;
		}

		#wpbody-content .loading .modal {
			display: block;
		}

		.user-steps-image {
			display: flex;
		}
	</style>
<script>

	var traitware_site_url = <?php echo $traitware_site_url ?>;
	var traitware_pollscan_action = 'newsite';
	var traitware_qrclassname = '.traitware-qrcode';
	var traitware_qrcode_url = <?php echo $traitware_qrcode_url ?>;
	var traitware_auth_url = <?php echo $traitware_auth_url ?>;
	var traitware_poll_url = <?php echo $traitware_poll_url ?>;
    var traitware_nonce = <?php echo wp_json_encode( wp_create_nonce('traitware_ajax') ) ?>;

	jQuery(function() {
		jQuery( ".traitware-setup-form button" ).click( submitNewAccount );
		jQuery( ".traitware-setup-form input" ).on("keyup", function (e) {
			if ( 13 === e.keyCode ) { submitNewAccount(); } // enter will submit.
		});
		traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
		window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr.
		traitware_qrcode();
	});

	function traitware_disableForm( val ) {
		jQuery( ".traitware-setup-form input" ).prop( "disabled", val );
		if (val) {
			jQuery( ".traitware-setup-form button" ).addClass( "disabled" );
		} else {
			jQuery( ".traitware-setup-form button" ).removeClass( "disabled" );
			jQuery( ".traitware-setup-form button" ).html("Resend Signup Email");
		}
	}
	
	function validateEmail(email) {
		var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
		return re.test(email.toLowerCase());
	}

	function submitNewAccount() {
		var data = {};
		data["action"] = "traitware_ajaxnewaccount";
		data['phone'] = document.getElementById('phoneNumber').value;
		data['_twnonce'] = <?php echo wp_json_encode( $new_account_nonce ) ?>;

		traitware_disableForm(true); // no double-clicks.

		var called_url = <?php echo wp_json_encode( wp_unslash( get_site_url() . '/wp-admin/admin-ajax.php' ) ); ?>;
		
		jQuery.ajax({
			method: "POST",
			url: called_url,
			dataType: "json",
			data: data,
			beforeSend: function(){
			    // Handle the beforeSend event.
                jQuery(".loader").css("display", "block");
			   },
			complete: function(){
			    // Handle the complete event.
                jQuery(".loader").css("display", "none");
			}
		}).done( function( data ) {
			if ("" !== data.error) {
				jQuery( ".traitware-setup-form-response" ).html( data.error );
				jQuery( ".traitware-setup-form-response" ).show();
			} else {
				jQuery('#successModal').show();
			}
			traitware_disableForm(false);
		}).fail(function() { // connection error.
			jQuery( ".traitware-setup-form-response" ).html( "Error submitting request" );
			jQuery( ".traitware-setup-form-response" ).show();
			traitware_disableForm(false);
		});
	}

	function validatePhone() {
			var phoneNumber = document.getElementById('phoneNumber').value;
			var phoneRGEX = /^[(]{0,1}[0-9]{3}[)]{0,1}[-\s\.]{0,1}[0-9]{3}[-\s\.]{0,1}[0-9]{4}$/;
			var phoneResult = phoneRGEX.test(phoneNumber);
			if(false === phoneResult) {
			    return false;
			}
			return true;
		}

	// TODO: Finish this function -- for sending SMS to client through AJAX.

	function sendTextToMobile() {

		var data = {};
		data["action"] = "traitware_ajaxnewaccount";
        data['_twnonce'] = <?php echo wp_json_encode( $new_account_nonce ) ?>;
		
		traitware_disableForm(true); // no double-clicks

		var called_url = <?php echo wp_json_encode( wp_unslash( get_site_url() . '/wp-admin/admin-ajax.php' ) ); ?>;
		
		jQuery.ajax({
			method: "POST",
			url: called_url,
			dataType: "json",
			data: data
		}).done( function( data ) {
			if (data.error != "") {
				jQuery( ".traitware-setup-form-response" ).html( data.error );
				jQuery( ".traitware-setup-form-response" ).show();
			} else {
				jQuery( ".traitware-setup-form-response" ).html("<div style='margin-bottom:15px;font-weight:bold;'>Email sent.</div>Install the TraitWare app on your phone. Activate the app with the email just sent to you, then come back here to scan the QR code.");
				jQuery( ".traitware-setup-form-response" ).show();
				jQuery( "#traitware-existing-customers" ).hide();
			}
			traitware_disableForm(false);
		}).fail(function() { // connection error?
			jQuery( ".traitware-setup-form-response" ).html("Error submitting request");
			jQuery( ".traitware-setup-form-response" ).show();
			traitware_disableForm(false);
		});
	}
</script>
<!-- The main page -->
<div class="wrap">
<?php $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'account-setup'; ?>
	 <h2 class="nav-tab-wrapper traitware-nav">					
		<a href="#" class="nav-tab<?php echo 'account-setup' === $active_tab ? ' nav-tab-active' : ''; ?>" data-tab="account-setup">Account Setup</a>
		<a href="#" class="nav-tab<?php echo 'how-to' === $active_tab ? ' nav-tab-active' : ''; ?>" data-tab="how-to">How To</a>
	</h2>
	<div class="form-table traitware-form-table traitware-form-table-account-setup<?php echo 'account-setup' === $active_tab ? ' active' : ''; ?>">
		<div class="container">
			<div class="traitware-card">
				<div class="card-header">
					<h3>Set Up Your Account</h3>
				</div>
				<div class="card-content">
					<div class="traitware-customer">
						<h3>New TraitWare Account Owners Start Here</h3>
						<p>If you have <strong>not</strong> already registered a WordPress Account with TraitWare <strong>Confirm your email</strong> for the currently logged in account and complete New Account Admin setup.</p>
					</div>
					<div class="continue-button">
						<form id="sendActivation">
						<!-- <button type="button"class="btn btn-secondary"data-dismiss="modal">Close</button> -->
						<!-- TODO: Make this button submit an autopopulated user email field -->
						<button type="button" class="traitware-button" id="textLinkButton">
							<!-- TODO: needs to take this email to text screen for submission -->
							<span>CONFIRM EMAIL TO <?php echo esc_html( $the_current_user->user_email ); ?></span>
						</button>
					</form>
					</div>
					<hr>
					<div class="traitware-customer">
						<h3>Existing TraitWare Account Owners Start Here</h3>
						<p>If you have already set up the TraitWare Plugin on another WordPress site, select <strong>ADD SITE TO EXISTING TRAITWARE WORDPRESS ACCOUNT</strong> to reveal the QR to scan with your TraitWare mobile app.</p>
					</div>
					<div class="continue-button">
						<button class="traitware-button" id="addUserButton">
							ADD SITE TO EXISTING TRAITWARE WORDPRESS ACCOUNT
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modals -->
		<div id="addSiteModal" class="modal">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Add TraitWare to site</h5>
					<button type="button" class="close" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<p>
						<strong>Scan the QR</strong> below with your TraitWare mobile app to finish adding TraitWare to your site.
					</p>
				</div>
				<div class="modal-footer">
					<!-- <button type="button"class="btn btn-secondary"data-dismiss="modal">Close</button> -->
					<img width="320" height="320" class="traitware-qrcode" alt="">
				</div>
			</div>
		</div>

		<div id="textLinkModal" class="modal">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Optional: Send TraitWare Notification Via Text</h5>
				</div>
				<div class="modal-body">
					<p>
						If you are in the US and would like us to send a text notification to your phone, enter your number in the box
						below and select <b>SEND TEXT</b>. Otherwise, select <b>SKIP</b>.

					</p>
					<p>
						<strong>
							*Please note that your phone number will not be used for anything other than to send you requested texts*
						</strong>
					</p>
				</div>
				<div class="modal-footer">
					<form id="sendTextLink">
						<p id="error">&nbsp;</p>
						<input type="tel" id="phoneNumber" class="traitware-text-input" placeholder="Enter mobile number">
						<div id="text-buttons">
							<button type="button" class="traitware-button" id="openSuccessModal">
									<span>
										SEND TEXT
									</span>
							</button>
							<button type="button" class="traitware-button" id="skipButton">
									<span>
										SKIP
									</span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div id="successModal" class="modal">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Success!</h5>

				</div>
				<div class="modal-body">
					<h3><strong>Step 1: Select Blue Registration Button via Email on your phone</strong></h3>
					<p>
						Install TraitWare by selecting the <strong>Register</strong> button in your mobile email.
					</p>
					<h3><strong>Step 2: Complete TraitWare Mobile App Setup</strong></h3>
					<p>
						Once installation is complete, open the TraitWare App and complete the registration by selecting the authentication method to secure your TraitWare profile.
					</p>
					<h3><strong>Step 3: Scan the WordPress QR with TraitWare</strong></h3>
					<p>
						Select <strong>Scan QR Code</strong> in the TraitWare Mobile App and select <strong>CONTINUE TO QR</strong> to finish Account Setup by scanning to add the site.
					</p>
				</div>
				<div class="modal-footer">
					<div class="continue-button">
						<button class="traitware-button" id="addUserFromSuccess">
							CONTINUE TO QR
						</button>
					</div>
				</div>

			</div>
		</div>
		<!-- loader that sits over page in modal whenever AJAX STARTS and end whenever AJAX ENDS: see jQuery func below -->
		<div class="loader"></div>
	</div>
	<div class="form-table traitware-form-table traitware-form-table-how-to<?php echo 'how-to' === $active_tab ? ' active' : ''; ?>">
		<h3>Account Setup</h3>
		<p for="documentation"><b>Scanning the QR Code to Login</b></p><br />
		<a href="https://imgur.com/pQpbs6v"><img src="https://i.imgur.com/pQpbs6v.gif" title="source: imgur.com" /></a><br />

		<p for="documentation"><b>Secure Login with TraitWare Part 1A: New TraitWare Accounts</b></p><br />
		<iframe src="https://player.vimeo.com/video/273603836" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>

		<p for="documentation"><b>Secure Login with TraitWare Part 1B: Mobile App Registration</b></p><br />
		<iframe src="https://player.vimeo.com/video/273985226" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>

		<p for="documentation"><b>Secure Login with TraitWare Part 1C: Finishing Account Setup</b></p><br />
		<iframe src="https://player.vimeo.com/video/273940396" width="480" height="268" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	</div>

    <script type="text/javascript">

        // jquery for all user onboarding modals
        function modal(modalId, btnId, closeButtonClass) {

            // When the user clicks the button, open the modal
            jQuery(btnId).click( function() {
                jQuery(modalId).show();
            });

            // When the user clicks on <span> (x), close the modal
            jQuery(closeButtonClass).click(function() {
                jQuery(modalId).hide();
            });

            // When the user clicks anywhere outside of the modal, close it
            window.onclick = function(event) {

                if (event.target === modalId) {
                    jQuery(modalId).hide();
                }
            }
        }
        // calling addUserModal for pre-existing Secure Login with TraitWare users

        modal('#addSiteModal', '#addUserButton', ".close");

        // calling newUserModal for onboarding

        // TODO: make work -- DISMISSING newUserModal & calling textLinkModal for onboarding

        modal('#textLinkModal', '#textLinkButton', ".closeThree");


        // JQuery for User Activation email form handling -- Makes user able to move on to optional text form without resetting page via post.
        jQuery( "#openSuccessModal" ).click(function( event ) {
            event.preventDefault();

            if (false === validatePhone()) {
                var error = jQuery('#error');
                error.text("Please enter a valid 10-digit phone number");
                jQuery('#phoneNumber').on("focus", function () {
                    jQuery(this).val("");
                    error.html('<span>&nbsp;</span>');
                });
                jQuery('#firstModal').hide();
                jQuery('#secondModal').hide();
                return validatePhone();
            } else {
                submitNewAccount();
                jQuery("#textLinkModal").hide();
            }
        });

        jQuery( "#skipButton" ).click(function( event ) {
            event.preventDefault();
            submitNewAccount();
            jQuery("#textLinkModal").hide();
        });

        jQuery( "#addUserFromSuccess" ).click(function( event ) {
            traitware_enable_polling();
            jQuery("#successModal").hide();
            jQuery("#addSiteModal").show();
        });

        jQuery( "#addUserButton" ).click(function( event ) {
            traitware_enable_polling();
        });

        // starts loader when ajax is fired, ends when ajax loading is over.

        jQuery(".traitware-nav .nav-tab").on("click", function(e) {
            e.preventDefault();

            var tab = jQuery(this).data("tab");
            var form_action = "admin.php?page=traitware-setup&tab=" + tab;

            jQuery(".traitware-nav .nav-tab").removeClass("nav-tab-active");
            jQuery(this).addClass("nav-tab-active");
            jQuery(".traitware-form-table").removeClass("active");

            if ("account-setup" === tab) {
                jQuery(".traitware-form-table-account-setup").addClass("active");
            } else {
                jQuery(".traitware-form-table-how-to").addClass("active");
            }

            if ("undefined" !== typeof (history.pushState)) {
                var obj = { Title: document.title, Url: (form_action) };
                history.pushState(obj, obj.Title, obj.Url);
            }
        });
    </script>
