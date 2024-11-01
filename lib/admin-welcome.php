<?php
/**
 * @package TraitWare
 */

defined( 'ABSPATH' ) || die( 'No Access' );
?>
<script type="text/javascript">
	(function($) {
		$(document).ready(function() {
			document.location = 'admin.php?page=traitware-users';
		});
	})(jQuery);
</script>
<style>
	#traitware-welcome-logo {
		width: 462px;
		height: 100px;
		position:relative;
		margin: 40px 40px 0 40px;
	}
	.traitware-welcome-description {
		width: 462px;
		position:relative;
		margin: 40px 40px 0 40px;
	}
	.traitware-welcome-description p {
		font-size: 15px;
		margin-left:20px;
	}
</style>
<img id="traitware-welcome-logo" src="<?php echo esc_url( wp_unslash( get_site_url() . '/' . traitware_get_var( 'pluginurl' ) . 'res/traitware.png' ) ); ?>" alt="TraitWare" width="462" height="100" />
<div class="traitware-welcome-description">
	<h3>Good to go!</h3>
	<p>Please wait while you are redirected to the <b>TraitWare >> User Management</b> panel.</p>
</div>
