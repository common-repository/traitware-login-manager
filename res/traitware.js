var traitware_state = '';
var traitware_loginAttemptUuid = '';
var traitware_loginAttemptSecret = '';
var traitware_pollscan_id = 0;
var traitware_data = '';
var traitware_overlaystate = [];
var traitware_last_focus = null;
var traitware_escapemap = {
  '&': '&amp;',
  '"': '&quot;',
  '\'': '&#039;',
  '<': '&lt;',
  '>': '&gt;'
};

var traitware_disable_custom_login = false;
var traitware_disable_custom_login_recovery = false;
var traitware_disable_custom_login_form = false;
var traitware_custom_login_selector = '';
var traitware_logging_in = false;

function traitware_qrcode() {
  // create a random state
  var ch = '0123456789abcdef';
  traitware_state = '';
  for (var n = 0; n < 64; n++) {
    traitware_state += ch.charAt(Math.floor(Math.random() * ch.length));
  }
  // grab data from tw server to create qr code
  jQuery.ajax({
    url: traitware_auth_url + traitware_state,
    headers: {
      'Accept': 'application/json'
    }
  }).done(function (data) {
    traitware_loginAttemptUuid = data.loginAttemptUuid;
    traitware_loginAttemptSecret = data.loginAttemptSecret;
    jQuery(traitware_qrclassname).attr('src', traitware_qrcode_url + traitware_loginAttemptUuid);
  }).fail(function (jqXHR, textStatus, errorThrown) {
    jQuery(traitware_qrclassname).attr('src', traitware_qrcode_url); // show failure qr
    traitware_report_error('Failed to create QR code.');
  });
}

function traitware_enable_polling() {
  traitware_logging_in = true;
}

function traitware_disable_polling() {
  traitware_logging_in = false;
}

function traitware_pollscan() {
  if (!traitware_logging_in) {
    return;
  }
  // check tw if scanned every 2 seconds
  jQuery.ajax({
    url: traitware_poll_url + traitware_loginAttemptSecret,
    dataType: "json",
    headers: {
      'Content-type': 'application/json',
      'Accept': 'application/json'
    }
  }).done(function (data, textStatus, xhr) {
    if (xhr.status == 204) { return; } // not yet scanned
    if (data.verification.approved) {
      if (data.state != traitware_state) { return; } // state mismatch
      window.clearInterval(traitware_pollscan_id);
      if (data.redirectUri.indexOf('paymentPortal') !== -1) {
        document.location.href = data.redirectUri + traitware_payment_url;
        return;
      }
      traitware_scantrigger(data.redirectUri);
    }
  }).fail(function (jqXHR, textStatus, errorThrown) {
    traitware_report_error('Pollscan error: <b>' + errorThrown + '</b>');
  });
}

function getHomeUrl() {
  if (typeof window['traitware_site_url'] !== 'undefined') {
    return window['traitware_site_url'];
  }

  if (typeof window['traitware_vars'] !== 'undefined' && typeof window['traitware_vars']['site_url'] !== 'undefined') {
    return window['traitware_vars']['site_url'];
  }

  var href = window.location.href;
  if (href.indexOf('wp-login') !== -1) {
    var homeUrl = href.substring(0, href.lastIndexOf('/wp-login'));
  }
  else if (href.indexOf('wp-admin') !== -1) {
    var homeUrl = href.substring(0, href.lastIndexOf('/wp-admin'));
  }
  else {
    if (href.indexOf('/?loggedout=true') !== -1) {
      var cleanUrl = href.substring(0, href.indexOf('/?loggedout=true'));
      var newhref = cleanUrl.replace(/\/$/, "");
      var homeUrl = newhref.substring(0, newhref.lastIndexOf('/'));
    } else {
      var newhref = href.replace(/\/$/, "");
      var homeUrl = newhref.substring(0, newhref.lastIndexOf('/'));
    }
  }
  return homeUrl;
}

function traitware_scantrigger(redirectUri) {
  var args = '';
  if (traitware_data != '') {
    args = JSON.stringify(traitware_data);
  }
  var data = {
    'action': 'traitware_ajaxpollscan',
    'twaction': traitware_pollscan_action,
    'redirecturi': redirectUri,
    'args': traitware_data,
    '_twnonce': traitware_nonce
  };

  jQuery.ajax({
    method: "POST",
    url: getHomeUrl() + '/wp-admin/admin-ajax.php',
    dataType: "json",
    data: data
  }).done(function (data) {
    if (data.error == '') {
      if (typeof window['traitware_redirectstyle'] === 'undefined' || window['traitware_redirectstyle'] !== 'url') {
        document.location.href = data.url;
      } else {
        document.location.href = window['traitware_redirecturl'];
      }
    } else {
      traitware_scantrigger_error(data.error)
    }
  }).fail(function (jqXHR, textStatus, errorThrown) {
    traitware_scantrigger_error('Error: ' + errorThrown)
  });
}

function traitware_scantrigger_error(error) {
  var el = jQuery('#traitware-modal-qrcode'); // users
  if (!el.length) {
    el = jQuery('.traitware-qrcode'); // setup
  }
  if (!el.length) { return; }
  el.replaceWith('<div id="scantriggererror"><div class="dashicons dashicons-warning" style="font-size:40px;margin:0 auto;width:40px;height:60px;"></div><br>We\'re sorry, a problem occurred authenticating you. Please try again later and contact the site administrator.</div>');
  traitware_report_error(error);
}

// populate id="traitwarerecoveryemail" with form to send to tw
function traitwarerecovery() {
  var form = '';
  form += '<p>';
  form += '	TraitWare can restore the default WordPress login. Please enter your email and we\'ll send you a recovery link.<br><br>';
  form += '	<label for="twemail">Email<br />';
  form += '	<input type="text" name="twemail" id="twemail" class="input" value="" size="20" /></label>';
  form += '</p>';
  form += '<input type="submit" id="traitware-submit" class="button button-primary button-large" value="Recover" />'
  form += '<div id="twrecoverresponse" style="color:#f00; font-style: bold; display:none;"></div>';
  jQuery('#traitwarerecoveryemail').html(form);
  jQuery('#traitwarerecoveryemail').show();
}

function isEmail(email) {
  var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  return regex.test(email);
}

function traitwarerecoverysubmit(e) {
  e.preventDefault();

  var email = jQuery('#twemail').val();
  if (email == '') {
    jQuery('#twrecoverresponse').html('Please enter your email.');
    jQuery('#twrecoverresponse').show();
    return;
  }
  if (!isEmail(email)) {
    jQuery('#twrecoverresponse').html('Invalid email address');
    jQuery('#twrecoverresponse').show();
    return;
  }
  var data = {
    'action': traitware_vars.is_custom_login ? 'traitware_ajaxscrubrecovery' : 'traitware_ajaxrecovery',
    'email': email,
    'path': window.location.href,
    '_twnonce': traitware_nonce
  };

  var called_url = getHomeUrl() + '/wp-admin/admin-ajax.php';

  jQuery.ajax({
    method: "POST",
    url: called_url,
    dataType: "json",
    data: data
  }).done(function (data) {
    jQuery('#twrecoverresponse').html(data.msg);
    jQuery('#twrecoverresponse').show();
  }).fail(function (jqXHR, textStatus, errorThrown) { // connection error?
    traitware_report_error('Traitware traitwarerecoverysubmit: <b>' + errorThrown + '</b>');
    jQuery('#twrecoverresponse').html('Could not submit');
    jQuery('#twrecoverresponse').show();
  });
}

// function checks how many overlays are showing
function traitware_showingoverlaycount() {
  var count = 0;

  jQuery.each(traitware_overlaystate, function () {
    var state = this;

    if (state['showing']) {
      count++;
    }
  });

  return count;
}

// function for updating the overlays container
function traitware_updateoverlays() {
  if (traitware_showingoverlaycount() > 0) {
    jQuery('#traitware_overlays').addClass('traitware_overlays_showing');
    return;
  }

  jQuery('#traitware_overlays').removeClass('traitware_overlays_showing');
}

// function for adding or updating an overlay state
function traitware_addorupdateoverlay(key, showing) {
  var update = false;

  jQuery.each(traitware_overlaystate, function (arrayIndex, _) {
    var state = this;

    if (state['key'] !== key) {
      return;
    }

    traitware_overlaystate[arrayIndex]['showing'] = showing;
    update = true;
  });

  if (update) {
    return;
  }

  traitware_overlaystate.push({
    'key': key,
    'showing': showing
  });
}

// function to show an overlay
function traitware_showoverlay(key, element) {
  traitware_last_focus = document.activeElement;
  traitware_addorupdateoverlay(key, true);
  jQuery(element).show();
  traitware_updateoverlays();
}

// function to hide an overlay
function traitware_hideoverlay(key, element) {
  traitware_addorupdateoverlay(key, false);
  jQuery(element).hide();
  traitware_updateoverlays();
  traitware_last_focus.focus();
}

// emulate htmlspecialchars()
function traitware_escapehtml(text) {
  text = text + '';

  var finalText = '';

  for (var i = 0; i < text.length; ++i) {
    if (text[i] in traitware_escapemap)
      finalText += traitware_escapemap[text[i]];
    else
      finalText += text[i];
  }

  return finalText;
}

//this function is used to retrieve the selected text from the text editor
function traitware_editor_getsel() {

  if (window.tinyMCE !== null && window.tinyMCE.activeEditor !== null && !window.tinyMCE.activeEditor.isHidden() &&
    typeof window.tinyMCE.activeEditor.getContent !== 'undefined') {
    return window.tinyMCE.activeEditor.selection.getContent();
  }

  var txtarea = document.getElementById("content");
  var start = txtarea.selectionStart;
  var finish = txtarea.selectionEnd;
  return txtarea.value.substring(start, finish);

}

function traitware_noop() { }

// simple popup overlay error notice
function traitware_errornotice(title_html, message_html, ok_callback) {
  title_html = typeof title_html === 'undefined' ? "Error" : title_html;
  message_html = typeof message_html === 'undefined' ? "An error has occurred, please try again shortly." : message_html;

  ok_callback = typeof ok_callback === 'undefined' ? window['traitware_noop'] : ok_callback;

  jQuery('#traitware_errornotice_overlay .traitware_errornotice_title').html(title_html);
  jQuery('#traitware_errornotice_overlay .traitware_errornotice_message').html(message_html);

  jQuery('#traitware_errornotice_overlay .traitware_errornotice_ok').unbind('click').bind('click', function () {
    traitware_hideoverlay('errornotice', jQuery('#traitware_errornotice_overlay'));
    ok_callback();
    return false;
  });

  traitware_showoverlay('errornotice', jQuery('#traitware_errornotice_overlay'));
}

function traitware_add_login_form(selector) {
  if (jQuery('.traitware-login-box').length > 0) {
    clearTimeout(window['traitware_scan_custom_logins_timeout']);
    return;
  }

  if (!jQuery(selector).is('form')) {
    selector = jQuery(selector).parents('form').eq(0);
  }

  if (jQuery(selector).length === 0) {
    return;
  }

	/*if (jQuery(selector).attr('src').contains('register') || jQuery(selector).attr('src').contains('signup')) {
		return;
	}*/

  var html = '';
  var new_redirect_style = null;
  var new_redirect_url = null;

  if (traitware_vars.addrecover && !traitware_disable_custom_login_recovery) {
    html = "<div class='traitware-login-box'><p>Login with TraitWare";
    html += " (<a href='javascript:void(0);' id='traitwarerecovery'>trouble?</a>)</p>";
    html += "<button type='button' class='traitware-login-button'>Click to Login with TraitWare</button>";
    html += "<img class='traitware-qrcode traitware-qrcode-with-button' /><div class='traitware-scan-response'></div>";
    html += "<div id='traitwarerecoveryemail'></div>";
    html += "</div>";
    jQuery(selector).html(html);

    jQuery("#traitwarerecoveryemail").hide();
    jQuery("#traitwarerecovery").click(traitwarerecovery);

    jQuery("p#nav").hide();

    jQuery(selector).submit(traitwarerecoverysubmit);
  }
  else if (traitware_vars.addrecover && traitware_disable_custom_login_recovery) {
    html = "<div class='traitware-login-box'><p>Login with TraitWare</p>";
    html += "<button type='button' class='traitware-login-button'>Click to Login with TraitWare</button>";
    html += "<img class='traitware-qrcode traitware-qrcode-with-button' /><div class='traitware-scan-response'></div>";
    html += "</div>";
    jQuery(selector).html(html);
  } else {
    html = "<div class='traitware-login-box'><p>Login with TraitWare</p>";
    html += "<button type='button' class='traitware-login-button'>Click to Login with TraitWare</button>";
    html += "<img class='traitware-qrcode traitware-qrcode-with-button' /><div class='traitware-scan-response'></div>";
    html += "</div>";
    jQuery(selector).prepend(html);

    var usernames = jQuery(selector).find(':input[name="username"], :input[name="user_name"], :input[name="name"], #user_login:input, :input[name="email"], :input[name="email_address"]');
    usernames.val(traitware_vars.login);
    jQuery(selector).append("<input type='hidden' name='recovery' value='" + traitware_vars.recovery + "' />");

    var redirect_selector = jQuery(':input[name="redirect"], :input[name="_wp_http_referer"]');

    if (redirect_selector.length > 0) {
      new_redirect_style = 'url';
      new_redirect_url = redirect_selector.val();
    }
  }

  traitware_site_url = traitware_vars.site_url;
  traitware_pollscan_action = traitware_vars.pollscan_action;
  traitware_qrclassname = traitware_vars.qrclassname;
  traitware_auth_url = traitware_vars.auth_url;
  traitware_qrcode_url = traitware_vars.qrcode_url;
  traitware_poll_url = traitware_vars.poll_url;
  traitware_payment_url = traitware_vars.payment_url;
  traitware_nonce = traitware_vars._twnonce;

  if (new_redirect_style && new_redirect_url) {
    traitware_redirectstyle = new_redirect_style;
    traitware_redirecturl = new_redirect_url;
  } else {
    traitware_redirectstyle = traitware_vars.redirect_style;
    traitware_redirecturl = traitware_vars.redirect_url;
  }

  var custom_redirect_url = jQuery.trim(traitware_vars.custom_redirect_url);

  if (custom_redirect_url !== '') {
    traitware_redirecturl = custom_redirect_url;
  }

  traitware_pollscan_id = window.setInterval(traitware_pollscan, 2000);
  window.setInterval(traitware_qrcode, 300000); // 5 min refresh qr
  traitware_qrcode();

}

function traitware_scan_custom_logins() {

  if (traitware_disable_custom_login) {
    clearTimeout(window['traitware_scan_custom_logins_timeout']);
    return;
  }

  if (jQuery('.traitware-login-box').length > 0) {
    clearTimeout(window['traitware_scan_custom_logins_timeout']);
    return;
  }

  var common_selectors = 'form.woocommerce-form-login, form.wc-auth-login, form#mepr_loginform, form#login, form#loginform';
  var selectors = traitware_custom_login_selector.length > 0 ?
    common_selectors + ', ' + traitware_custom_login_selector : common_selectors;

  var woocommerce_login_form = null;

  try {
    woocommerce_login_form = jQuery(selectors);
  } catch (e) {
    woocommerce_login_form = [];
  }

  if (woocommerce_login_form.length > 0) {
    clearTimeout(window['traitware_scan_custom_logins_timeout']);
    traitware_add_login_form(selectors);
  }
}

function traitware_send_to_editor(embedcode) {
  if (embedcode.indexOf("[") !== 0) {
    embedcode = "<p>" + embedcode + "</p>";
  }

  if (window.tinyMCE !== null && window.tinyMCE.activeEditor !== null && !window.tinyMCE.activeEditor.isHidden()) {
    if (typeof window.tinyMCE.execInstanceCommand !== 'undefined') {
      window.tinyMCE.execInstanceCommand(
        window.tinyMCE.activeEditor.id,
        'mceInsertContent',
        false,
        embedcode);
    }
    else {
      send_to_editor(embedcode);
    }
  }
  else {
    embedcode = embedcode.replace('<p>', '\n').replace('</p>', '\n');
    if (typeof QTags.insertContent === 'function') {
      QTags.insertContent(embedcode);
    }
    else {
      send_to_editor(embedcode);
    }
  }
}

function traitware_process_background() {
  // Timeout set for 5 minutes.
  var timeout = 300000;
  (function ($) {
    $.ajax({
      method: 'GET',
      url: getHomeUrl() + '/wp-admin/admin-ajax.php?action=traitware_ajaxbackground',
      complete: function (xhr) {
        var responseData = null;
        try {
          responseData = $.parseJSON(xhr.responseText);
        } catch (e) {
          traitware_report_error('Background process error: <b>' + e + '</b> Response Text: <b>' + xhr.responseText + '</b>');
        }

        if (responseData === null || typeof responseData === 'undefined' ||
          typeof responseData['more_background'] === 'undefined') {
          // Try again in 15 seconds.
          setTimeout(function () {
            traitware_process_background();
          }, timeout);

          return;
        }

        if (typeof responseData['bulksync_progress'] !== 'undefined') {
          var progressNumber = parseInt(responseData['bulksync_progress'], 10);

          $('.traitware_bulksync_working progress').attr('value', progressNumber);
          var progressValue = parseInt($('.traitware_bulksync_working progress').attr('value'), 100);
          if (progressNumber >= 100 || progressValue >= 100) {
            traitware_disable_confirm_exit();
            $('.traitware_bulksync_working').hide();
            $('.traitware_bulksync_done').show();
          }
        }

        if (responseData['more_background'] || (typeof window['traitware_bulksync_page'] !== 'undefined' && traitware_bulksync_page)) {
          // Try again in 1 second.
          setTimeout(function () {
            traitware_process_background();
          }, 1000);

          return;
        }

        // Try again in 15 seconds by default
        setTimeout(function () {
          traitware_process_background();
        }, timeout);
      }
    });
  })(jQuery);
}

function traitware_report_error(error) {
  var data = {
    'action': 'traitware_ajaxreporterror',
    'error': error
  };

  var called_url = getHomeUrl() + '/wp-admin/admin-ajax.php';

  jQuery.ajax({
    method: 'POST',
    url: called_url,
    dataType: 'json',
    data: data
  }).done(function (data) {
    console.log('Error reported: ', error);
  }).fail(function (jqXHR, textStatus, errorThrown) { // connection error?
    console.log('Failed to report error: ', error);
  });
}

// on document ready, draw out the base overlay DOM
(function ($) {
  $(document).ready(function () {
    var overlaysHtml = "<div id=\"traitware_overlays\"></div>";
    var overlaysElem = $(overlaysHtml);

    // construct a list of labels + checkboxes for the roles in the system
    var checkboxesHtml = "";

    if (typeof window['traitware_vars'] !== 'undefined' && typeof window['traitware_vars']['roles'] !== 'undefined') {
      $.each(window['traitware_vars']['roles'], function (roleKey, roleText) {
        checkboxesHtml += "<label><input type=\"checkbox\" name=\"traitware_shortcode_roles[]\" value=\"" +
          traitware_escapehtml(roleKey) + "\" /> " + traitware_escapehtml(roleText) + "</label><br />";
      });
    }

    // construct a dropdown for the roles in the system
    var dropdownHtml = "<select class=\"traitware_roles_dropdown\">";

    if (typeof window['traitware_vars'] !== 'undefined' && typeof window['traitware_vars']['roles'] !== 'undefined') {
      $.each(window['traitware_vars']['roles'], function (roleKey, roleText) {
        dropdownHtml += "<option value=\"" + traitware_escapehtml(roleKey) + "\">" + traitware_escapehtml(roleText) + "</option>";
      });
    }

    dropdownHtml += "</select>";

    // construct a dropdown for the TW self-reg forms in the system
    var selfRegFormsHtml = "<select class=\"traitware_selfregforms_dropdown\">";

    if (typeof window['traitware_vars'] !== 'undefined' && typeof window['traitware_vars']['selfregforms'] !== 'undefined') {
      $.each(window['traitware_vars']['selfregforms'], function (formKey, formText) {
        selfRegFormsHtml += "<option value=\"" + traitware_escapehtml(formKey) + "\">" + traitware_escapehtml(formText) + "</option>";
      });
    }

    selfRegFormsHtml += "</select>";

    var shortcodeOverlayHtml = "<div id=\"traitware_shortcode_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title\">" +
      "<p>TraitWare Shortcode Generator</p></div></div><div class=\"traitware_overlay_body\">" +
      "<p>Protect parts of your posts and pages using a shortcode.</p>" +
      "<form id=\"traitware_shortcode_form\"><p>Which roles would you like to be able to access this content?</p>" +
      "<div id=\"traitware_shortcode_checkboxes\">" + checkboxesHtml + "</div><div id=\"traitware_shortcode_buttons\">" +
      "<input id=\"traitware_shortcode_submit\" class=\"button button-primary\" type=\"submit\" value=\"Create Shortcode\" />&nbsp;" +
      "<input type=\"button\" class=\"button traitware_overlay_close\" value=\"Cancel\" /></div></form>" +
      "</div></div>";

    var usertypeOptionsHtml = "<option value=\"scrub\">Site User</option><option value=\"dashboard\">Dashboard User</option>" +
      "<option value=\"owner\">Account Owner</option>";

    window['traitware_getUserTypeOptionsHtml'] = function () {
      return usertypeOptionsHtml;
    };

    var activateOverlayHtml = "<div id=\"traitware_activate_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title\">" +
      "<p>Activate TraitWare User</p></div></div><div class=\"traitware_overlay_body\">" +
      "<form id=\"traitware_activate_form\"><label><select name=\"traitware_activate_type\"></select></label><br />" +
      "<div id=\"traitware_activate_buttons\">" +
      "<input id=\"traitware_activate_submit\" class=\"button button-primary\" type=\"submit\" value=\"Send Activation Email\" />&nbsp;" +
      "<input type=\"button\" class=\"button traitware_overlay_close\" value=\"Cancel\" /></div></form>" +
      "</div></div>";

    var usertypeOverlayHtml = "<div id=\"traitware_usertype_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title\">" +
      "<p>Change TraitWare User Type</p></div></div><div class=\"traitware_overlay_body\">" +
      "<form id=\"traitware_usertype_form\"><label><select name=\"traitware_usertype_type\"></select></label><br />" +
      "<div id=\"traitware_usertype_buttons\">" +
      "<input id=\"traitware_usertype_submit\" class=\"button button-primary\" type=\"submit\" value=\"Save User Type\" />&nbsp;" +
      "<input type=\"button\" class=\"button traitware_overlay_close\" value=\"Cancel\" /></div></form>" +
      "</div></div>";

    var errorOverlayHtml = "<div id=\"traitware_errornotice_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title traitware_errornotice_title\">Error</div></div>" +
      "<p class=\"traitware_overlay_body\"><p class=\"traitware_errornotice_message\">An error has occurred, please try again shortly.</p>" +
      "<div class=\"traitware_errornotice_buttons\"><button class=\"traitware_errornotice_ok button\">OK</button>" +
      "</div></div>";

    var defaultDisplayRole = "";

    if (typeof window['traitware_vars'] !== 'undefined' && typeof window['traitware_vars']['default_role'] !== 'undefined' &&
      typeof window['traitware_vars']['roles'][window['traitware_vars']['default_role']] !== 'undefined') {
      defaultDisplayRole = window['traitware_vars']['roles'][window['traitware_vars']['default_role']];
    }

    var bulksyncOverlayHtml = "<div id=\"traitware_bulksync_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title\">" +
      "<p>TraitWare Bulk User Sync</p></div></div><div class=\"traitware_overlay_body\">" +
      "<p>Synchronizes pre-existing WordPress users with TraitWare. Adds users to the TraitWare <b>User Management</b> table</p>" +
      "<form id=\"traitware_bulksync_form\"><p>Which WordPress user role would you like to be synced with TraitWare in this operation?</p>" +
      "<div id=\"traitware_bulksync_inputs\">" + dropdownHtml + "</div>" +
      "<div id=\"traitware_bulksync_checkboxes\"><label><input type=\"checkbox\" id=\"traitware_bulksync_checkbox\" value=\"1\" />" +
      "Check this box if you would like users to keep their existing role if they are not currently the default role. (default role: " +
      traitware_escapehtml(defaultDisplayRole) + ")</label></div>" +
      "<div id=\"traitware_bulksync_buttons\">" +
      "<input id=\"traitware_bulksync_submit\" class=\"button button-primary\" type=\"submit\" value=\"Continue\" />&nbsp;" +
      "<input type=\"button\" class=\"button traitware_overlay_close\" value=\"Cancel\" /></div></form>" +
      "</div></div>";

    var selfRegFormsOverlayHtml = "<div id=\"traitware_selfregforms_overlay\" class=\"traitware_overlay\">" +
      "<div class=\"traitware_overlay_header\"><div class=\"traitware_overlay_title\">" +
      "<p>TraitWare Form Selector</p></div></div><div class=\"traitware_overlay_body\">" +
      "<form id=\"traitware_selfregforms_form\"><p>Which TraitWare Form would you like to insert into the content?</p>" +
      "<div id=\"traitware_selfregforms_inputs\">" + selfRegFormsHtml + "</div>" +
      "<div id=\"traitware_selfregforms_buttons\">" +
      "<input id=\"traitware_selfregforms_submit\" class=\"button button-primary\" type=\"submit\" value=\"Continue\" />&nbsp;" +
      "<input type=\"button\" class=\"button traitware_overlay_close\" value=\"Cancel\" /></div></form>" +
      "</div></div>";

    overlaysElem.append(shortcodeOverlayHtml);
    overlaysElem.append(activateOverlayHtml);
    overlaysElem.append(usertypeOverlayHtml);
    overlaysElem.append(errorOverlayHtml);
    overlaysElem.append(bulksyncOverlayHtml);
    overlaysElem.append(selfRegFormsOverlayHtml);

    $('body').eq(0).append(overlaysElem);

    $('#traitware-reqroles-button').click(function () {
      traitware_send_to_editor("{{traitware_role}}");

      return false;
    });

    var activateOverlay = $('#traitware_activate_overlay');

    $('.traitware_activate_user_link').click(function () {
      var link = $(this);
      var activateUserId = link.attr('data-user-id');
      var activateAdmin = link.attr('data-user-admin') === '1';

      var isAccountOwner = window['traitware_vars']['is_account_owner'] === 'yes';

      activateOverlay.find(':input[name="traitware_activate_type"]').html(usertypeOptionsHtml);

      if (!activateAdmin || !isAccountOwner) {
        activateOverlay.find(':input[name="traitware_activate_type"]').find('option[value="owner"]').remove();
      }

      activateOverlay.attr('data-user-id', activateUserId);

      traitware_showoverlay('activate', activateOverlay);

      return false;
    });

    var activateCloseButton = activateOverlay.find('.traitware_overlay_close');

    activateCloseButton.bind('click', function () {
      traitware_hideoverlay('activate', activateOverlay);
      return false;
    });

    $('#traitware_activate_form').submit(function () {
      traitware_hideoverlay('activate', activateOverlay);

      var activateUserId = activateOverlay.attr('data-user-id');

      traitware_useraction('add', [activateUserId], [{
        'usertype': activateOverlay.find(':input[name="traitware_activate_type"]').val()
      }]);

      return false;
    });

    var usertypeOverlay = $('#traitware_usertype_overlay');

    $('.traitware_change_usertype_link').click(function () {
      var link = $(this);
      var usertypeUserId = link.attr('data-user-id');
      var usertypeCurrent = link.attr('data-user-type');
      var usertypeAdmin = link.attr('data-user-admin') === '1';

      var isAccountOwner = window['traitware_vars']['is_account_owner'] === 'yes';

      usertypeOverlay.find(':input[name="traitware_usertype_type"]').html(usertypeOptionsHtml);

      if (!usertypeAdmin || !isAccountOwner) {
        usertypeOverlay.find(':input[name="traitware_usertype_type"]').find('option[value="owner"]').remove();
      }

      usertypeOverlay.attr('data-user-id', usertypeUserId);
      usertypeOverlay.find(':input[name="traitware_usertype_type"]').val(usertypeCurrent);

      traitware_showoverlay('usertype', usertypeOverlay);

      return false;
    });

    var usertypeCloseButton = usertypeOverlay.find('.traitware_overlay_close');

    usertypeCloseButton.bind('click', function () {
      traitware_hideoverlay('usertype', usertypeOverlay);
      return false;
    });

    $('#traitware_usertype_form').submit(function () {
      traitware_hideoverlay('usertype', usertypeOverlay);

      var usertypeUserId = usertypeOverlay.attr('data-user-id');

      traitware_useraction('usertype', [usertypeUserId], [{
        'usertype': usertypeOverlay.find(':input[name="traitware_usertype_type"]').val()
      }]);

      return false;
    });

    var shortcodeOverlay = $('#traitware_shortcode_overlay');

    $('#traitware-shortcode-button').click(function () {
      traitware_showoverlay('shortcode', shortcodeOverlay);
      shortcodeCloseButton.focus();

      // uncheck all the role boxes
      $('#traitware_shortcode_checkboxes :input[type="checkbox"]').prop('checked', false);

      return false;
    });

    $('#traitware_shortcode_form').submit(function () {
      var roleList = "";
      var firstRole = true;

      $('#traitware_shortcode_checkboxes :input[type="checkbox"]').each(function () {
        var checkbox = $(this);

        if (!checkbox.is(':checked')) {
          return;
        }

        if (!firstRole) {
          roleList += ",";
        }

        roleList += checkbox.val();
        firstRole = false;
      });

      if (firstRole) {
        traitware_hideoverlay('shortcode', shortcodeOverlay);

        traitware_errornotice("Error", "You must select one or more roles to protect this shortcode with.", function () {
          traitware_showoverlay('shortcode', shortcodeOverlay);
        });

        return false;
      }

      var selectedText = traitware_editor_getsel();
      traitware_send_to_editor("[traitware roles=\"" + roleList + "\"]" + selectedText + "[/traitware]");
      traitware_hideoverlay('shortcode', shortcodeOverlay);
      return false;
    });

    var shortcodeCloseButton = shortcodeOverlay.find('.traitware_overlay_close');

    shortcodeCloseButton.bind('click', function () {
      traitware_hideoverlay('shortcode', shortcodeOverlay);
      return false;
    });

    var bulksyncOverlay = $('#traitware_bulksync_overlay');

    $('body').on('click', '.traitware_bulksync_btn', function () {
      traitware_showoverlay('bulksync', bulksyncOverlay);

      bulksyncOverlay.find('.traitware_roles_dropdown').val(window['traitware_vars']['bulksync_role']);
      return false;
    });

    var bulksyncCloseButton = bulksyncOverlay.find('.traitware_overlay_close');

    bulksyncCloseButton.bind('click', function () {
      traitware_hideoverlay('bulksync', bulksyncOverlay);
      return false;
    });

    $('#traitware_bulksync_submit').click(function () {
      var selectedRole = bulksyncOverlay.find('.traitware_roles_dropdown').val();
      var keepExisting = bulksyncOverlay.find('#traitware_bulksync_checkbox').is(':checked') ? 'yes' : 'no';

      traitware_hideoverlay('bulksync', bulksyncOverlay);
      traitware_useraction('bulksync', [selectedRole, keepExisting], []);
      return false;
    });

    var selfRegFormsOverlay = $('#traitware_selfregforms_overlay');

    $('#traitware-selfregforms-button').click(function () {
      traitware_showoverlay('selfregforms', selfRegFormsOverlay);
      return false;
    });

    var selfRegFormsCloseButton = selfRegFormsOverlay.find('.traitware_overlay_close');

    selfRegFormsCloseButton.bind('click', function () {
      traitware_hideoverlay('selfregforms', selfRegFormsOverlay);
      return false;
    });

    $('#traitware_selfregforms_submit').click(function () {
      var selectedForm = selfRegFormsOverlay.find('.traitware_selfregforms_dropdown').val();

      traitware_hideoverlay('selfregforms', selfRegFormsOverlay);
      traitware_send_to_editor("[traitwareform id=\"" + selectedForm + "\"]");
      return false;
    });

    // traitware special pages admin javascript
    if ($('#traitware_form_options').length > 0) {
      // simple function to generate an option for label
      var getFormStartOption_Label = function () {
        return "<option value=\"\">Select default form...</option>";
      };

      // simple function to generate an option for sign-up
      var getFormStartOption_Signup = function () {
        return "<option value=\"signup\">New user sign-up</option>";
      };

      // simple function to generate an option for login
      var getFormStartOption_Login = function () {
        return "<option value=\"login\">Login</option>";
      };

      // simple function to generate an option for opt-in
      var getFormStartOption_Optin = function () {
        return "<option value=\"optin\">Existing user opt-in</option>";
      };

      // update the UI based on the different dropdown values
      var updateSpecialPagesUI = function () {
        var formStartDropdown = $('.traitware_form_start');
        var formStartValue = formStartDropdown.val();

        var selfRegValue = $('.traitware_self_registration_page').val();
        var loginValue = $('.traitware_login_page').val();
        var optinValue = $('.traitware_optin_page').val();


        // generate the new HTML for the form start dropdown options
        var formStartOptions = "";

        // add the label option
        formStartOptions += getFormStartOption_Label();

        // add the sign-up option if the value is 'yes'
        if (selfRegValue === 'yes') {
          formStartOptions += getFormStartOption_Signup();
        }

        // add the login option if the value is 'yes'
        if (loginValue === 'yes') {
          formStartOptions += getFormStartOption_Login();
        }

        // add the opt-in option if the value is 'yes'
        if (optinValue === 'yes') {
          formStartOptions += getFormStartOption_Optin();
        }

        // reset the options and try to preserve the value
        formStartDropdown.html(formStartOptions);

        if (formStartValue !== '' && formStartDropdown.find('option[value="' + formStartValue + '"]').length > 0) {
          formStartDropdown.val(formStartValue);
        } else {
          formStartDropdown.val('');
        }

        var approvalDropdown = $('.traitware_self_registration_approval');

        if (selfRegValue === 'no') {
          // selfRegValue = no, hide appropriate fields
          $('.traitware_self_registration_instructions').parents('label').hide();
          $('.traitware_self_registration_loggedin').parents('label').hide();
          $('.traitware_self_registration_existing').parents('label').hide();
          $('.traitware_self_registration_success').parents('label').hide();
          $('.traitware_self_registration_linktext').parents('label').hide();
          $('.traitware_self_registration_pagelink').parents('label').hide();
          $('.traitware_self_registration_username').parents('label').hide();
          $('.traitware_self_registration_role').parents('label').hide();
          approvalDropdown.parents('label').hide();
          $('.traitware_self_registration_approval_role').parents('label').hide();
          $('.traitware_form_tab_link.traitware_form_tab_link_signup').
            removeClass('traitware_form_tab_link_on traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_off');
        } else if (selfRegValue === 'yes') {
          // selfRegValue = yes, show/hide appropriate fields
          $('.traitware_self_registration_instructions').parents('label').show();
          $('.traitware_self_registration_loggedin').parents('label').show();
          $('.traitware_self_registration_existing').parents('label').show();
          $('.traitware_self_registration_success').parents('label').show();
          $('.traitware_self_registration_linktext').parents('label').show();
          $('.traitware_self_registration_pagelink').parents('label').hide();
          $('.traitware_self_registration_username').parents('label').show();
          $('.traitware_self_registration_role').parents('label').show();
          approvalDropdown.parents('label').show();

          $('.traitware_form_tab_link.traitware_form_tab_link_signup').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_on');

          var approvalValue = approvalDropdown.val();

          if (approvalValue === 'yes') {
            // approvalValue = yes, show the appropriate fields
            $('.traitware_self_registration_approval_role').parents('label').show();
          } else {
            // approvalValue = no/notification, hide the appropriate fields
            $('.traitware_self_registration_approval_role').parents('label').hide();
          }
        } else {
          // selfRegValue = link, show/hide appropriate fields
          $('.traitware_self_registration_instructions').parents('label').hide();
          $('.traitware_self_registration_loggedin').parents('label').hide();
          $('.traitware_self_registration_existing').parents('label').hide();
          $('.traitware_self_registration_success').parents('label').hide();
          $('.traitware_self_registration_linktext').parents('label').show();
          $('.traitware_self_registration_pagelink').parents('label').show();
          $('.traitware_self_registration_username').parents('label').hide();
          $('.traitware_self_registration_role').parents('label').hide();
          approvalDropdown.parents('label').hide();
          $('.traitware_self_registration_approval_role').parents('label').hide();

          $('.traitware_form_tab_link.traitware_form_tab_link_signup').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_on').
            addClass('traitware_form_tab_link_external');
        }

        if (optinValue === 'no') {
          // optinValue = no, hide appropriate fields
          $('.traitware_optin_instructions').parents('label').hide();
          $('.traitware_optin_logged_in_instructions').parents('label').hide();
          $('.traitware_optin_notification').parents('label').hide();
          $('.traitware_optin_success').parents('label').hide();
          $('.traitware_optin_linktext').parents('label').hide();
          $('.traitware_optin_pagelink').parents('label').hide();

          $('.traitware_form_tab_link.traitware_form_tab_link_optin').
            removeClass('traitware_form_tab_link_on traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_off');
        } else if (optinValue === 'yes') {
          // optinValue = yes, show/hide appropriate fields
          $('.traitware_optin_instructions').parents('label').show();
          $('.traitware_optin_logged_in_instructions').parents('label').show();
          $('.traitware_optin_success').parents('label').show();
          $('.traitware_optin_notification').parents('label').show();
          $('.traitware_optin_linktext').parents('label').show();
          $('.traitware_optin_pagelink').parents('label').hide();

          $('.traitware_form_tab_link.traitware_form_tab_link_optin').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_on');
        } else {
          // optinValue = link, show/hide appropriate fields
          $('.traitware_optin_instructions').parents('label').hide();
          $('.traitware_optin_logged_in_instructions').parents('label').hide();
          $('.traitware_optin_notification').parents('label').hide();
          $('.traitware_optin_success').parents('label').hide();
          $('.traitware_optin_linktext').parents('label').show();
          $('.traitware_optin_pagelink').parents('label').show();

          $('.traitware_form_tab_link.traitware_form_tab_link_optin').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_on').
            addClass('traitware_form_tab_link_external');
        }

        if (loginValue === 'no') {
          // loginValue = no, hide appropriate fields
          $('.traitware_login_instructions').parents('label').hide();
          $('.traitware_login_redirect').parents('label').hide();
          $('.traitware_login_linktext').parents('label').hide();
          $('.traitware_login_pagelink').parents('label').hide();
          $('.traitware_login_loggedin').parents('label').hide();

          $('.traitware_form_tab_link.traitware_form_tab_link_login').
            removeClass('traitware_form_tab_link_on traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_off');
        } else if (loginValue === 'yes') {
          // loginValue = yes, show/hide appropriate fields
          $('.traitware_login_instructions').parents('label').show();
          $('.traitware_login_redirect').parents('label').show();
          $('.traitware_login_linktext').parents('label').show();
          $('.traitware_login_pagelink').parents('label').hide();
          $('.traitware_login_loggedin').parents('label').show();

          $('.traitware_form_tab_link.traitware_form_tab_link_login').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_external').
            addClass('traitware_form_tab_link_on');
        } else {
          // loginValue = link, show/hide appropriate fields
          $('.traitware_login_instructions').parents('label').hide();
          $('.traitware_login_redirect').parents('label').hide();
          $('.traitware_login_linktext').parents('label').show();
          $('.traitware_login_pagelink').parents('label').show();
          $('.traitware_login_loggedin').parents('label').hide();

          $('.traitware_form_tab_link.traitware_form_tab_link_login').
            removeClass('traitware_form_tab_link_off traitware_form_tab_link_on').
            addClass('traitware_form_tab_link_external');
        }
      };

      // when the various dropdowns are changed make sure to update the UI
      var changeSelectors = '.traitware_self_registration_page,.traitware_self_registration_approval,' +
        '.traitware_optin_page,.traitware_login_page';

      $(changeSelectors).change(function () {
        updateSpecialPagesUI();
      });

      // tab link handler
      $('.traitware_form_tab_links .traitware_form_tab_link').click(function () {
        // show the right tab
        var tab = $(this).attr('data-tab');
        $('.traitware_form_tab').hide();
        $('.traitware_form_tab[data-tab="' + tab + '"]').show();

        // set the correct link to active
        $('.traitware_form_tab_link').removeClass('traitware_form_tab_link_active');
        $(this).addClass('traitware_form_tab_link_active');

        return false;
      });

      // fire our UI updates since document ready is before this
      updateSpecialPagesUI();
    }

    $('.traitware_forms_logoutlink').click(function () {
      var link = $(this);

      if (link.hasClass('traitware_forms_logoutlink_loggingout')) {
        return false;
      }

      link.addClass('traitware_forms_logoutlink_loggingout');

      $.ajax({
        method: 'GET',
        url: link.attr('href'),
        complete: function (xhr) {
          var responseData = null;
          try {
            responseData = $.parseJSON(xhr.responseText);
          } catch (e) {
            traitware_report_error('Forms login error: <b>' + e + '</b>');
          }

          if (responseData === null || typeof responseData === 'undefined' ||
            typeof responseData['error'] === 'undefined' || responseData['error'] !== null) {
            link.removeClass('traitware_forms_logoutlink_loggingout');
            return;
          }

          document.location.reload();
        }
      });

      return false;
    });

    $('.traitware_forms_loggedinlink').click(function () {
      var link = $(this);

      if (link.hasClass('traitware_forms_loggedinlink_processing')) {
        return false;
      }

      link.addClass('traitware_forms_loggedinlink_processing');

      $.ajax({
        method: 'GET',
        url: link.attr('href'),
        complete: function (xhr) {
          var responseData = null;
          try {
            responseData = $.parseJSON(xhr.responseText);
          } catch (e) {
            traitware_report_error('Forms logout error: <b>' + e + '</b>');
          }

          if (responseData === null || typeof responseData === 'undefined' ||
            typeof responseData['error'] === 'undefined' || responseData['error'] !== null) {
            link.removeClass('traitware_forms_loggedinlink_processing');
            return;
          }

          if (responseData['redirect_url'] === '') {
            document.location.reload();
          } else {
            document.location = responseData['redirect_url'];
          }
        }
      });

      return false;
    });

    $('.traitware_forms_signuplink').click(function () {
      var link = $(this);
      var href = link.attr('href');
      var outer = link.parents('.traitware_forms_outer').eq(0);

      if (href === '#') {
        outer.find('.traitware_forms_login,.traitware_forms_optin').removeClass('traitware_forms_active');
        outer.find('.traitware_forms_signup').addClass('traitware_forms_active');
        return false;
      }
    });

    $('.traitware_forms_optinlink').click(function () {
      var link = $(this);
      var href = link.attr('href');
      var outer = link.parents('.traitware_forms_outer').eq(0);

      if (href === '#') {
        outer.find('.traitware_forms_login,.traitware_forms_signup').removeClass('traitware_forms_active');
        outer.find('.traitware_forms_optin').addClass('traitware_forms_active');
        return false;
      }
    });

    $('.traitware_forms_loginlink').click(function () {
      var link = $(this);
      var href = link.attr('href');
      var outer = link.parents('.traitware_forms_outer').eq(0);

      if (href === '#') {
        outer.find('.traitware_forms_signup,.traitware_forms_optin').removeClass('traitware_forms_active');
        outer.find('.traitware_forms_login').addClass('traitware_forms_active');
        return false;
      }
    });

    $('.traitware_forms_signup_form').submit(function () {
      var $form = $(this);
      var url = $form.attr('action');
      var $username = $form.find(':input[name="username"]');
      var $email = $form.find(':input[name="email"]');
      var $submit = $form.find(':input[type="submit"]');
      var $successMessage = $form.find('.traitware_forms_successmessage');
      var $errorMessage = $form.find('.traitware_forms_errormessage');
      var formData = {};

      if ($form.hasClass('traitware_forms_signup_processing')) {
        return false;
      }

      $form.addClass('traitware_forms_signup_processing');

      $successMessage.hide();
      $errorMessage.hide();

      if (!$email.length) {
        $errorMessage.text('An error occurred while creating your account. Please try again later.').show();
        return false;
      }

      var email = $.trim($email.val());
      if (!email.length) {
        $errorMessage.text('You must enter an email address.').show();
        $form.removeClass('traitware_forms_signup_processing');
        return false;
      }

      var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      if (!re.test(String(email).toLowerCase())) {
        $errorMessage.text('The email address you entered is invalid.').show();
        $form.removeClass('traitware_forms_signup_processing');
        return false;
      }

      formData.email = email;

      if ($username.length) {
        var username = $.trim($username.val());
        if (!username.length) {
          $errorMessage.text('You must enter a username.').show();
          $form.removeClass('traitware_forms_signup_processing');
          return false;
        }

        formData.username = username;
      }

      $.ajax({
        method: 'POST',
        url: url,
        data: formData,
        complete: function (xhr) {
          var responseData = null;
          try {
            responseData = $.parseJSON(xhr.responseText);
          } catch (e) {
            traitware_report_error('Signup form error: <b>' + e + '</b>');
          }

          if (responseData === null || typeof responseData === 'undefined') {
            $errorMessage.text('An error occurred while creating your account. Please try again later.').show();
            traitware_report_error('Signup form error: An error occurred while creating your account. Please try again later.');
            $form.removeClass('traitware_forms_signup_processing');
            return;
          }

          if (typeof responseData['error'] !== 'undefined' && responseData['error'] !== null) {
            traitware_report_error('Signup form error: <b>' + responseData['error'] + '</b>');
            $errorMessage.text(responseData['error']).show();
            $form.removeClass('traitware_forms_signup_processing');
            return;
          }

          if ($username.length) {
            $username.hide();
          }
          $email.hide();
          $submit.hide();
          $form.removeClass('traitware_forms_signup_processing');
          $successMessage.show();
        }
      });

      return false;
    });

    $('.traitware_forms_optin_form').submit(function () {
      var $form = $(this);
      var url = $form.attr('action');
      var $username = $form.find(':input[name="username"]');
      var $password = $form.find(':input[name="password"]');
      var $loggedIn = $form.find(':input[name="logged_in"]');
      var $submit = $form.find(':input[type="submit"]');
      var $successMessage = $form.find('.traitware_forms_successmessage');
      var $errorMessage = $form.find('.traitware_forms_errormessage');

      if ($form.hasClass('traitware_forms_optin_processing')) {
        return false;
      }

      $form.addClass('traitware_forms_optin_processing');

      $successMessage.hide();
      $errorMessage.hide();

      var formData = {};
      if (!$loggedIn.length) {
        if (!$username.length || !$password.length) {
          $errorMessage.text('An error occurred while opting-in your account. Please try again later.').show();
          return false;
        }

        var username = $.trim($username.val());
        if (!username.length) {
          $errorMessage.text('You must enter your username or email address.').show();
          $form.removeClass('traitware_forms_optin_processing');
          return false;
        }

        var password = $password.val();
        $password.val('');
        if (!password.length) {
          $errorMessage.text('You must enter your password.').show();
          $form.removeClass('traitware_forms_optin_processing');
          return false;
        }

        formData = {
          username: username,
          password: password
        };
      }

      $.ajax({
        method: 'POST',
        url: url,
        data: formData,
        complete: function (xhr) {
          var responseData = null;
          try {
            responseData = $.parseJSON(xhr.responseText);
          } catch (e) {
            traitware_report_error('Optin form error: <b>' + e + '</b>');
          }

          if (responseData === null || typeof responseData === 'undefined') {
            traitware_report_error('Optin form error: An error occurred while opting-in your account. Please try again later.');
            $errorMessage.text('An error occurred while opting-in your account. Please try again later.').show();
            $form.removeClass('traitware_forms_optin_processing');
            return;
          }

          if (typeof responseData['error'] !== 'undefined' && responseData['error'] !== null) {
            traitware_report_error('Optin form error: <b>' + responseData['error'] + '</b>');
            $errorMessage.text(responseData['error']).show();
            $form.removeClass('traitware_forms_optin_processing');
            return;
          }

          $username.hide();
          $password.hide();
          $submit.hide();
          $form.removeClass('traitware_forms_optin_processing');
          $successMessage.show();
        }
      });

      return false;
    });

    $('body').on('click', '.traitware-review-notice .notice-dismiss', function () {
      var dismissButton = $(this);
      var dismissUrl = dismissButton.data('href');
      $.ajax({
        method: 'GET',
        url: dismissUrl,
        complete: function () {
          dismissButton.closest('.traitware-review-notice').remove();
        }
      });
    });

    $('body').on('click', '.traitware-login-button', function () {
      traitware_enable_polling();
      $(this).hide();
      $(this).closest('.traitware-login-box').find(traitware_qrclassname + '.traitware-qrcode-with-button').show();
      return false;
    });

    traitware_disable_custom_login = traitware_vars.disable_custom_login === '1';
    traitware_disable_custom_login_recovery = traitware_vars.disable_custom_login_recovery === '1';
    traitware_disable_custom_login_form = traitware_vars.disable_custom_login_form === '1';
    traitware_custom_login_selector = jQuery.trim(traitware_vars.custom_login_selector);

    traitware_process_background();

    if (!traitware_disable_custom_login) {
      window['traitware_scan_custom_logins_timeout'] = setTimeout(traitware_scan_custom_logins, 3000);
      traitware_scan_custom_logins();
    }
  });
})(jQuery);
