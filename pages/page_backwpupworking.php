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
		$logfilarray=backwpup_read_logfile(trim($_GET['logfile']));
		$runfile=trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
		$infile=unserialize($runfile);
		echo "<input type=\"hidden\" name=\"logfile\" id=\"logfile\" value=\"".trim($_GET['logfile'])."\">";
		echo "<input type=\"hidden\" name=\"logpos\" id=\"logpos\" value=\"".count($logfilarray)."\">";		
		echo "<div id=\"showworking\">";
		for ($i=0;$i<count($logfilarray);$i++)
			echo $logfilarray[$i]."\n";
		echo "</div>";
		echo "<div id=\"runniginfos\">";
		$stylewarning=" style=\"display:none;\"";
		if ($infile['WARNING']>0)
			$stylewarning="";
		echo "<span id=\"warningsid\"".$stylewarning.">".__('Warnings:','backwpup')." <span id=\"warnings\">".$infile['WARNING']."</span></span><br/>";
		$styleerror=" style=\"display:none;\"";
		if ($infile['ERROR']>0)
			$styleerror="";		
		echo "<span id=\"errorid\"".$styleerror.">".__('Error:','backwpup')." <span id=\"errors\">".$infile['ERROR']."</span></span>";
		echo "<div>";
		echo "<div class=\"clear\"></div>";
		echo "<div class=\"progressbar\"><div id=\"progressstep\" style=\"width:".$infile['STEPSPERSENT']."%;\">".$infile['STEPSPERSENT']."%</div></div>";
		echo "<div class=\"progressbar\"><div id=\"progresssteps\" style=\"width:".$infile['STEPPERSENT']."%;\">".$infile['STEPPERSENT']."%</div></div>";
	} elseif (is_file(trim($_GET['logfile']))) {
		echo '<div id="showlogfile">';
		foreach (backwpup_read_logfile(trim($_GET['logfile'])) as $line)
			echo $line."\n";
		echo "</div>";
		echo "<div class=\"clear\"></div>";
	}
	?>
</div>