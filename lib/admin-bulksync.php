<?php
/**
 * Admin bulksync code.
 *
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );

// They must be an admin to see the bulksync progress.
if ( ! traitware_isadmin() ) {
	die( 'No Access' );
}

// Bulksync is not running if neither queued nor running is set to 'yes'.
$bulksync_pending = get_option( 'traitware_bulksync_queued' ) === 'yes' || get_option( 'traitware_bulksync_running' ) === 'yes';

// Bulksync_progress represents the last synced user ID for bulk sync.
$bulksync_progress = get_option( 'traitware_bulksync_progress' );
$bulksync_progress = (int) $bulksync_progress;

// Calculate the remaining number of wpids to process for this bulksync.
$remaining = traitware_get_remaining_wpids_for_bulksync( $bulksync_progress );
$total     = get_option( 'traitware_bulksync_total' );
$total     = (int) $total;

// Now calculate a frontend progress value [0-100].
$progress_value = 0;

if ( 0 !== $total ) {
	$progress_value = 100 - round( ( $remaining / $total ) * 100 );
}
?>
<p class="traitware_bulksync_done" style="<?php echo( $bulksync_pending ? 'display:none' : '' ); ?>">The bulksync has completed successfully! Please <a href="admin.php?page=traitware-users">click here to view your TraitWare user list.</a></p>
<p class="traitware_bulksync_working" style="<?php echo( ! $bulksync_pending ? 'display:none' : '' ); ?>">Please wait while TraitWare performs a bulk sync with our secure server.<br /><progress max="100" value="<?php echo( esc_attr( $progress_value ) ); ?>"></progress><br /><br /><strong>Please be patient and do not navigate away from this page until the process has finished.</strong></p>
<script type="text/javascript">
	// Setting this global allows the background process to happen more frequently.
	var traitware_bulksync_page = true;
</script>
<script type="text/javascript">
	// If there is not a bulksync pending, then we never really want to hook this.
	var traitware_old_unload = <?php echo( $bulksync_pending ? 'false' : 'true' ); ?>;

	// This function simply disables the page exit confirmation after the bulksync is finished.
	function traitware_disable_confirm_exit() {
		traitware_old_unload = true;
	}

	(function($) {
		$(document).ready(function() {
			// Hook the onbeforeunload function so we can warn the user if they are mid-sync.
			window.onbeforeunload = traitware_confirm_exit;

			// Our function to hook before unload, which returns a message only if the old unload is not enabled.
			// This message is ignored on most browsers to prevent spam.
			function traitware_confirm_exit() {
				if(!traitware_old_unload) {
					return "The bulk sync is still in progress. Are you sure?";
				}
			}
		});
	})(jQuery);
</script>
