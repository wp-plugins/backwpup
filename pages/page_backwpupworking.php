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
<?php endif; 	
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		wp_nonce_field('backwpupworking_ajax_nonce', 'backwpupworkingajaxnonce', false );
		echo "<input type=\"hidden\" name=\"logfile\" id=\"logfile\" value=\"".$_GET['logfile']."\">";
		echo "<input type=\"hidden\" name=\"logpos\" id=\"logpos\" value=\"21\">";		
		echo "<div id=\"showworking\"></div>";
		echo "<div id=\"runniginfos\">";
		echo "<span id=\"warningsid\">".__('Warnings:','backwpup')." <span id=\"warnings\">0</span></span><br/>";
		echo "<span id=\"errorid\">".__('Error:','backwpup')." <span id=\"errors\">0</span></span>";
		echo "<div>";
		echo "<div style=\"border-color:#CEE1EF;border-style:solid;border-width:2px;height:20px;width:800px;\"><div id=\"progressstep\" style=\"height:20px;width:0;\"></div></div>";
		echo "<div style=\"border-color:#CEE1EF;border-style:solid;border-width:2px;height:20px;width:800px;\"><div id=\"progresssteps\" style=\"height:20px;width:0;\"></div></div>";
	} elseif (is_file(trim($_GET['logfile']))) {
		echo '<div id="showlogfile">';
		backwpup_read_logfile(trim($_GET['logfile']));
		echo "</div>";
	}
	?>
</div>