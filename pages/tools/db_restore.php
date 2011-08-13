<?PHP
if (!defined('ABSPATH')) {
	die();
}

global $wpdb;	
ignore_user_abort(true);
@set_time_limit(0); //not time limit for restore.
//Vars
$oldblogabspath="";
$oldblogurl="";
$oldtabelprefix="";
$numquerys="";
if (defined('WP_SITEURL') and WP_SITEURL)
	$blogurl=trailingslashit(WP_SITEURL);
else
	$blogurl=trailingslashit(get_option('siteurl'));
$blogabspath=trailingslashit(ABSPATH);
$sqlquery="";
$file = fopen ($sqlfile, "r");
while (!feof($file)){
	$line = trim(fgets($file));
	
	if (substr($line,0,12)=="-- Blog URL:")
		$oldblogurl=trim(substr($line,13));
	if (substr($line,0,16)=="-- Blog ABSPATH:")
		$oldblogabspath=trim(substr($line,17));
	if (substr($line,0,16)=="-- Table Prefix:") {
		$oldtabelprefix=trim(substr($line,17));
		if ($oldtabelprefix!=$wpdb->prefix and !empty($oldtabelprefix)) {
			echo __('ERROR:','backwpup').' '.sprintf(__('Pleace set <i>$table_prefix  = \'%1$s\';</i> in wp-config.php','backwpup'), $oldtabelprefix)."<br />\n";
			break;
		}
	}	
	if (substr($line,0,2)=="--" or empty($line))
		continue;
	
	$line=str_replace("/*!40000","", $line);
	$line=str_replace("/*!40101","", $line);
	$line=str_replace("/*!40103","", $line);
	$line=str_replace("/*!40014","", $line);
	$line=str_replace("/*!40111","", $line);
	$line=str_replace("*/;",";", trim($line));
	
	if (substr($line,0,9)=="SET NAMES") {
		$chrset=trim(str_replace("'","",substr($line,10,-1)));
		if ((defined('DB_CHARSET') and $chrset!=DB_CHARSET and strtolower(DB_CHARSET)!='utf8') or ($chrset!=mysql_client_encoding())) {
			echo __('ERROR:','backwpup').' '.sprintf(__('Pleace set <i>define(\'DB_CHARSET\', \'%1$s\');</i> in wp-config.php','backwpup'), $chrset)."<br />\n";
			break;
		}
	}
	
	//convert cahrs if can and false encoding
	if (strtolower(DB_CHARSET)=='utf8')
		$line=mb_convert_encoding($line, 'UTF-8');
		
	$sqlquery.=$line;  //build query
	if (substr($sqlquery,-1)==";") { //execute query
		$result=mysql_query($sqlquery);
		if ($sqlerr=mysql_error($wpdb->dbh)) {
			echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlquery)."<br />\n";
		}
		$sqlquery="";
		$numquerys++;
	}
}
fclose($file);
echo sprintf(__('%1$s Database Querys done.','backwpup'),$numquerys).'<br />';
echo __('Make changes for blogurl and ABSPATH in database if needed.','backwpup')."<br />";
if (!empty($oldblogurl) and $oldblogurl!=$blogurl and !is_multisite()) {
	mysql_query("UPDATE `".$wpdb->options."` SET option_value = replace(option_value, '".untrailingslashit($oldblogurl)."', '".untrailingslashit($blogurl)."');");
	if ($sqlerr=mysql_error()) 
		echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "UPDATE `".$wpdb->options."` SET option_value = replace(option_value, '".untrailingslashit($oldblogurl)."', '".untrailingslashit($blogurl)."');")."<br />\n";
	mysql_query("UPDATE `".$wpdb->posts."` SET guid = replace(guid, '".untrailingslashit($oldblogurl)."','".untrailingslashit($blogurl)."');");
	if ($sqlerr=mysql_error())
		echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "UPDATE `".$wpdb->posts."` SET guid = replace(guid, '".untrailingslashit($oldblogurl)."','".untrailingslashit($blogurl)."');")."<br />\n";
	mysql_query("UPDATE `".$wpdb->posts."` SET post_content = replace(post_content, '".untrailingslashit($oldblogurl)."', '".untrailingslashit($blogurl)."');");
	if ($sqlerr=mysql_error())
		echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "UPDATE `".$wpdb->posts."` SET post_content = replace(post_content, '".untrailingslashit($oldblogurl)."', '".untrailingslashit($blogurl)."');")."<br />\n";
}
if (!empty($oldblogabspath) and $oldblogabspath!=$blogabspath and !is_multisite()) {
	mysql_query("UPDATE `".$wpdb->options."` SET option_value = replace(option_value, '".untrailingslashit($oldblogabspath)."', '".untrailingslashit($blogabspath)."');");
	if ($sqlerr=mysql_error())
		echo __('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "UPDATE `".$wpdb->options."` SET option_value = replace(option_value, '".untrailingslashit($oldblogabspath)."', '".untrailingslashit($blogabspath)."');")."<br />\n";
}
echo __('Restore Done. Please delete the SQL file after restoring.','backwpup')."<br />";
?>