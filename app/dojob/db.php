<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

backwpup_joblog($logtime,__('Run Database Backup...','backwpup'));

function backwpup_dump_table($table,$status,$file) {
	global $wpdb,$logtime;
	$table = str_replace("Д", "ДД", $table); //esc table name

	// create dump
    fwrite($file, "\n");
	fwrite($file, "--\n");
	fwrite($file, "-- Table structure for table $table\n");
    fwrite($file, "--\n\n");
    fwrite($file, "DROP TABLE IF EXISTS `" . $table .  "`;\n");            
	fwrite($file, "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
	fwrite($file, "/*!40101 SET character_set_client = utf8 */;\n");
    //Dump the table structure
    $result=mysql_query("SHOW CREATE TABLE `".$table."`");
	if ($sqlerr=mysql_error($wpdb->dbh)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
		return false;
	}
	$tablestruc=mysql_fetch_assoc($result);
	fwrite($file, $tablestruc['Create Table'].";\n");
	fwrite($file, "/*!40101 SET character_set_client = @saved_cs_client */;\n");
	
    //take data of table
	$result=mysql_query("SELECT * FROM `".$table."`");
	if ($sqlerr=mysql_error($wpdb->dbh)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
		return false;
	}
	
	fwrite($file, "--\n");
	fwrite($file, "-- Dumping data for table $table\n");
	fwrite($file, "--\n\n");
	fwrite($file, "LOCK TABLES `".$table."` WRITE;\n\n");
	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n");
		
	while ($data = mysql_fetch_assoc($result)) {
		$keys = array();
		$values = array();
		foreach($data as $key => $value) {
			$keys[] = "`".str_replace("Д", "ДД", $key)."`"; // Add key to key list
			if($value === NULL) // Make Value NULL to string NULL
				$value = "NULL";
			elseif($value === "" or $value === false) // if empty or false Value make  "" as Value
				$value = '""';
			elseif(!is_numeric($value)) //is value not numeric esc
				$value = "\"".mysql_real_escape_string($value)."\"";
		
			$values[] = $value;
		}
		// make data dump
		fwrite($file, "INSERT INTO `".$table."` ( ".implode(", ",$keys)." )\n\tVALUES ( ".implode(", ",$values)." );\n");
	}
	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE ".$table." ENABLE KEYS */;\n");
	fwrite($file, "UNLOCK TABLES;\n");
}


//Tables to backup		
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');	
$jobs[$jobid]['dbexclude'][]=$wpdb->backwpup_logs; //Exclude log table
if (is_array($jobs[$jobid]['dbexclude'])) {
	foreach($tables as $tablekey => $tablevalue) {
		if (in_array($tablevalue,$jobs[$jobid]['dbexclude']))
			unset($tables[$tablekey]);
	}
	sort($tables);
}

if (sizeof($tables)>0) {
    $result=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`;", ARRAY_A); //get table status
	if ($sqlerr=mysql_error($wpdb->dbh)) 
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
	foreach($result as $statusdata) {
		$status[$statusdata['Name']]=$statusdata;
	}

	if ($file = @fopen(get_temp_dir().'backwpup/'.DB_NAME.'.sql', 'w')) {
		mysql_query("SET NAMES utf8");
		fwrite($file, "-- ---------------------------------------------------------\n");
		fwrite($file, "-- Dump with BackWPup ver.: ".BACKWPUP_VERSION."\n");
		fwrite($file, "-- Plugin for WordPress by Daniel Huesken\n");
		fwrite($file, "-- http://danielhuesken.de/portfolio/backwpup/\n");
		fwrite($file, "-- Blog Name: ".get_option('blogname')."\n");
		if (defined('WP_SITEURL')) 
			fwrite($file, "-- Blog URL: ".trailingslashit(WP_SITEURL)."\n");
		else 
			fwrite($file, "-- Blog URL: ".trailingslashit(get_option('siteurl'))."\n");
		fwrite($file, "-- Blog ABSPATH: ".trailingslashit(ABSPATH)."\n");
		fwrite($file, "-- Database Name: ".DB_NAME."\n");
		fwrite($file, "-- Dump on: ".date('Y-m-d H:i:s')."\n");
		fwrite($file, "-- ---------------------------------------------------------\n\n");
		//for better import with mysql client
		fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
		fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
		fwrite($file, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
		fwrite($file, "/*!40101 SET NAMES utf8 */;\n");
		fwrite($file, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
		fwrite($file, "/*!40103 SET TIME_ZONE='".mysql_result(mysql_query("SELECT @@time_zone"),0)."' */;\n");
		fwrite($file, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
		fwrite($file, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
		fwrite($file, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
		fwrite($file, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
		//make table dumps
		foreach($tables as $table) {
			backwpup_joblog($logtime,__('Database table to Backup: ','backwpup').' '.$table);
			backwpup_needfreememory(($status[$table]['Data_length']+$status[$table]['Index_length'])*1.3); //get more memory if needed
			fwrite($file, backwpup_dump_table($table,$status[$table],$file));
		}
		//for better import with mysql client
		fwrite($file, "\n");
		fwrite($file, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
		fwrite($file, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
		fwrite($file, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
		fwrite($file, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
		fwrite($file, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
		fwrite($file, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
		fwrite($file, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
		fwrite($file, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
		fclose($file);
	} else {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not create Database Backup file','backwpup'));
	}
} else {
	backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('No Tables to Backup','backwpup'));
}


backwpup_joblog($logtime,__('Database backup done!','backwpup'));

if ($jobs[$jobid]['type']=='DB' and is_file(get_temp_dir().'backwpup/'.DB_NAME.'.sql')) {
	backwpup_needfreememory(8388608); //8MB free memory for zip
	backwpup_joblog($logtime,__('Database file size:','backwpup').' '.backwpup_formatBytes(filesize(get_temp_dir().'backwpup/'.DB_NAME.'.sql')));
	backwpup_joblog($logtime,__('Create Zip file from dump...','backwpup'));
	$zipbackupfile = new PclZip($backupfile);
	if (0==$zipbackupfile -> create(get_temp_dir().'backwpup/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_ALL_PATH,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Database Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
		$joberror=true;
	} 
	backwpup_joblog($logtime,__('Zip file created...','backwpup'));
}
//clean vars
unset($tables);
unset($zipbackupfile);
?>