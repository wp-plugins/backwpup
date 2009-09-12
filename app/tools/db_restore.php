<?PHP
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
	
ignore_user_abort(true);

if (empty($cfg['maxexecutiontime']))
	$cfg['maxexecutiontime']=300;
set_time_limit($cfg['maxexecutiontime']); //300 is most webserver time limit.
	
//Vars
$oldblogabspath="";
$oldblogurl="";
$blogurl=trailingslashit(get_option('siteurl'));
$blogabspath=trailingslashit(ABSPATH);

$file = fopen ($sqlfile, "r");
while (!feof($file)){
	$line = trim(fgets($file));
	
	if (substr($line,0,12)=="-- Blog URL:")
		$oldblogurl=trim(substr($line,13));
	if (substr($line,0,16)=="-- Blog ABSPATH:")
		$oldblogabspath=trim(substr($line,17));
	if (substr($line,0,2)=="--" or empty($line))
		continue;
	
	$line=str_replace("/*!40000","", $line);
	$line=str_replace("/*!40101","", $line);
	$line=str_replace("/*!40103","", $line);
	$line=str_replace("/*!40014","", $line);
	$line=str_replace("/*!40111","", $line);
	$line=str_replace("*/;",";", $line);
	if (!empty($oldblogurl) and $oldblogurl!=$blogurl)
		$line=str_replace($oldblogurl,$blogurl,$line);
	if (!empty($oldblogabspath) and $oldblogabspath!=$blogabspath)
		$line=str_replace($oldblogabspath,$blogabspath,$line);
	
	$command="";
	
	if (";"==substr($line,-1)) {
		$command=$rest.$line;
		$rest="";
	} else {
		$rest.=$line;
	}
	if (!empty($command)) {
		$result=mysql_query($command);
		if ($sqlerr=mysql_error($wpdb->dbh)) {
			echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $command)."<br />\n";
		}
		$numcommands++;
	}
}
fclose($file);
echo __('Executed Database Querys:','backwpup').' '.$numcommands.'<br />';
echo __('Plugins Deactivated.','backwpup')."<br />";
echo __('Restore Done.','backwpup')."<br />";
update_option("active_plugins","");


?>