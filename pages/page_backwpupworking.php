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
	<input type="hidden" name="logfile" value="<?php echo $_GET['logfile']; ?>" />
	<?PHP
	wp_nonce_field('backwpupworking_ajax_nonce', 'backwpupworkingajaxnonce', false );
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) 
		echo '<div id="showworking">';
	else 
		echo '<div id="showlogfile">';
	if (!empty($_GET['logfile'])) {
		backwpup_read_logfile($_GET['logfile']);
	}
	echo "</div>";
	?>
</div>