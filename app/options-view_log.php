<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup View Log", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>
<br class="clear" /> 
<?PHP
$log=explode("\n",$wpdb->get_var("SELECT log FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime));
set_time_limit
?>
<div style="font-family:monospace;font-size:12px;white-space:nowrap;">
<?PHP
foreach ($log as $line) {
	if (empty($line)) {
		echo "<br />\n";
		continue;
	}
	@set_time_limit(10);
	$style='';
	if (substr($line,21,strlen(__('ERROR:','backwpup')))==__('ERROR:','backwpup')) 
		$style=' style="background-color:red;color:black;"';
	if (substr($line,21,strlen(__('WARNING:','backwpup')))==__('WARNING:','backwpup')) 
		$style=' style="background-color:yellow;color:black;"';
	echo "<span style=\"background-color:gray;color:black;\">".substr($line,0,19).":</span> <span".$style.">".substr($line,21)."</span><br />\n";
}
?>
</div>
</div>