<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( __('BackWPup Working', 'backwpup') ); ?></h2>
<?php
	$backupdata=backwpup_get_workingdata();
	if (!empty($backupdata)) {
		$backwpup_message.=sprintf(__('Job "%s" is running.','backwpup'),backwpup_get_option($backupdata['JOBMAIN'],'name'));
		$backwpup_message.=" <a class=\"submitdelete\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!','backwpup') . "</a>";
	}
	if (isset($backwpup_message) and !empty($backwpup_message))
		echo '<div id="message" class="updated"><p>'.$backwpup_message.'</p></div>';
	if (!empty($backupdata)) {
		wp_nonce_field('backwpupworking_ajax_nonce', 'backwpupworkingajaxnonce', false );
		$logfilarray=backwpup_read_logfile($backupdata['LOGFILE']);
		echo "<input type=\"hidden\" name=\"logfile\" id=\"logfile\" value=\"".$backupdata['LOGFILE']."\">";
		echo "<input type=\"hidden\" name=\"logpos\" id=\"logpos\" value=\"".count($logfilarray)."\">";
		echo "<div id=\"showworking\">";
		for ($i=0;$i<count($logfilarray);$i++)
			echo $logfilarray[$i]."\n";
		echo "</div>";
		echo "<div id=\"runniginfos\">";
		$stylewarning=" style=\"display:none;\"";
		if ($backupdata['WARNING']>0)
			$stylewarning="";
		echo "<span id=\"warningsid\"".$stylewarning.">".__('Warnings:','backwpup')." <span id=\"warnings\">".$backupdata['WARNING']."</span></span><br/>";
		$styleerror=" style=\"display:none;\"";
		if ($backupdata['ERROR']>0)
			$styleerror="";
		echo "<span id=\"errorid\"".$styleerror.">".__('Errors:','backwpup')." <span id=\"errors\">".$backupdata['ERROR']."</span></span>";
		echo "<div>";
		echo "<div class=\"clear\"></div>";
		echo "<div class=\"progressbar\"><div id=\"progressstep\" style=\"width:".$backupdata['STEPSPERSENT']."%;\">".$backupdata['STEPSPERSENT']."%</div></div>";
		echo "<div class=\"progressbar\"><div id=\"progresssteps\" style=\"width:".$backupdata['STEPPERSENT']."%;\">".$backupdata['STEPPERSENT']."%</div></div>";
	} elseif (!empty($_GET['logfile']) and is_file(trim($_GET['logfile']))) {
		echo '<div id="showlogfile">';
		foreach (backwpup_read_logfile(trim($_GET['logfile'])) as $line)
			echo $line."\n";
		echo "</div>";
		echo "<div class=\"clear\"></div>";
	}
	?>
</div>