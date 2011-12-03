<?PHP
if (!defined('ABSPATH'))
	die();
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( __('BackWPup Working', 'backwpup') ); ?></h2>

<?php if (isset($backwpup_message) and !empty($backwpup_message)) : ?>
	<div id="message" class="updated"><p><?php echo $backwpup_message; ?></p></div>
<?php endif;
	$backupdata=backwpup_get_option('working','data');
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
		if ($backupdata['WORKING']['WARNING']>0)
			$stylewarning="";
		echo "<span id=\"warningsid\"".$stylewarning.">".__('Warnings:','backwpup')." <span id=\"warnings\">".$backupdata['WORKING']['WARNING']."</span></span><br/>";
		$styleerror=" style=\"display:none;\"";
		if ($backupdata['WORKING']['ERROR']>0)
			$styleerror="";
		echo "<span id=\"errorid\"".$styleerror.">".__('Errors:','backwpup')." <span id=\"errors\">".$backupdata['WORKING']['ERROR']."</span></span>";
		echo "<div>";
		echo "<div class=\"clear\"></div>";
		echo "<div class=\"progressbar\"><div id=\"progressstep\" style=\"width:".$backupdata['WORKING']['STEPSPERSENT']."%;\">".$backupdata['WORKING']['STEPSPERSENT']."%</div></div>";
		echo "<div class=\"progressbar\"><div id=\"progresssteps\" style=\"width:".$backupdata['WORKING']['STEPPERSENT']."%;\">".$backupdata['WORKING']['STEPPERSENT']."%</div></div>";
	} elseif (!empty($_GET['logfile']) and is_file(trim($_GET['logfile']))) {
		echo '<div id="showlogfile">';
		foreach (backwpup_read_logfile(trim($_GET['logfile'])) as $line)
			echo $line."\n";
		echo "</div>";
		echo "<div class=\"clear\"></div>";
	}
	?>
</div>