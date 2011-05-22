<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( __('BackWPup Working', 'backwpup') ); ?></h2>

<?php if (isset($backwpup_message) and !empty($backwpup_message)) : ?>
	<div id="message" class="updated"><p><?php echo $backwpup_message; ?></p></div>
<?php endif; ?>

	<div id="workinglog">
	<?PHP
	if (!empty($_GET['logfile'])) {
		if (strtolower(substr($_GET['logfile'],-3))==".gz") {
			readgzfile(trim($_GET['logfile']));
		} else {
			readfile(trim($_GET['logfile']));
		}
	}
	?>
	</div>
</div>