<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}


function db_dump() {
	trigger_error(__('Run Database Dump to file...','backwpup'),E_USER_NOTICE);
	//Set maintenance
	maintenance_mode(true);

	if (sizeof($_SESSION['JOB']['dbtables'])>0) {
		$result=mysql_query("SHOW TABLE STATUS FROM `".$_SESSION['WP']['DB_NAME']."`"); //get table status
		if (!$result)
			trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".$_SESSION['WP']['DB_NAME']."`;"),E_USER_ERROR);

		while ($data = mysql_fetch_assoc($result)) {
			$status[$data['Name']]=$data;
		}

		if ($file = fopen($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql', 'wb')) {
			fwrite($file, "-- ---------------------------------------------------------\n");
			fwrite($file, "-- Dump with BackWPup ver.: ".$_SESSION['BACKWPUP']['VERSION']."\n");
			fwrite($file, "-- Plugin for WordPress by Daniel Huesken\n");
			fwrite($file, "-- http://danielhuesken.de/portfolio/backwpup/\n");
			fwrite($file, "-- Blog Name: ".$_SESSION['WP']['BLOGNAME']."\n");
			fwrite($file, "-- Blog URL: ".$_SESSION['WP']['SITEURL']."\n");
			fwrite($file, "-- Blog ABSPATH: ".$_SESSION['WP']['ABSPATH']."\n");
			fwrite($file, "-- Table Prefix: ".$_SESSION['WP']['TABLE_PREFIX']."\n");
			fwrite($file, "-- Database Name: ".$_SESSION['WP']['DB_NAME']."\n");
			fwrite($file, "-- Dump on: ".date('Y-m-d H:i.s')."\n");
			fwrite($file, "-- ---------------------------------------------------------\n\n");
			//for better import with mysql client
			fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
			fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
			fwrite($file, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
			fwrite($file, "/*!40101 SET NAMES '".mysql_client_encoding()."' */;\n");
			fwrite($file, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
			fwrite($file, "/*!40103 SET TIME_ZONE='".mysql_result(mysql_query("SELECT @@time_zone"),0)."' */;\n");
			fwrite($file, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
			fwrite($file, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
			fwrite($file, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
			fwrite($file, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
			//make table dumps
			foreach($_SESSION['JOB']['dbtables'] as $table) {
				trigger_error(__('Dump Database table: ','backwpup').' '.$table,E_USER_NOTICE);
				need_free_memory(($status[$table]['Data_length']+$status[$table]['Index_length'])*1.3); //get more memory if needed
				_db_dump_table($table,$status[$table],$file);
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
			trigger_error(__('Database Dump done!','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('Can not create Database Dump file','backwpup'),E_USER_ERROR);
		}
	} else {
		trigger_error(__('No Tables to Dump','backwpup'),E_USER_WARNING);
	}

	//add database file to backupfiles
	if (is_readable($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql')) {
		$filestat=stat($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql');
		trigger_error(__('Add Database Dump to Backup list:','backwpup').' '.$_SESSION['WP']['DB_NAME'].'.sql '.formatbytes($filestat['size']),E_USER_NOTICE);
		$_SESSION['WORKING']['ALLFILESIZE']+=$filestat['size'];
		$_SESSION['WORKING']['FILELIST'][]=array('FILE'=>$_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql','OUTFILE'=>$_SESSION['WP']['DB_NAME'].'.sql','SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode']);
		$_SESSION['WORKING']['DB_DUMP']['DONE']=true; //set done
	}
	//Back from maintenance
	maintenance_mode(false);
}


function _db_dump_table($table,$status,$file) {
	// create dump
	fwrite($file, "\n");
	fwrite($file, "--\n");
	fwrite($file, "-- Table structure for table $table\n");
	fwrite($file, "--\n\n");
	fwrite($file, "DROP TABLE IF EXISTS `" . $table .  "`;\n");
	fwrite($file, "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
	fwrite($file, "/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;\n");
	//Dump the table structure
	$result=mysql_query("SHOW CREATE TABLE `".$table."`");
	if (!$result) {
		trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
		return false;
	}
	$tablestruc=mysql_fetch_assoc($result);
	fwrite($file, $tablestruc['Create Table'].";\n");
	fwrite($file, "/*!40101 SET character_set_client = @saved_cs_client */;\n");

	//take data of table
	$result=mysql_query("SELECT * FROM `".$table."`");
	if (!$result) {
		trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "SELECT * FROM `".$table."`"),E_USER_ERROR);
		return false;
	}

	fwrite($file, "--\n");
	fwrite($file, "-- Dumping data for table $table\n");
	fwrite($file, "--\n\n");
	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n");


	while ($data = mysql_fetch_assoc($result)) {
		$keys = array();
		$values = array();
		foreach($data as $key => $value) {
			if (!$_SESSION['JOB']['dbshortinsert'])
				$keys[] = "`".str_replace("Д", "ДД", $key)."`"; // Add key to key list
			if($value === NULL) // Make Value NULL to string NULL
				$value = "NULL";
			elseif($value === "" or $value === false) // if empty or false Value make  "" as Value
				$value = "''";
			elseif(!is_numeric($value)) //is value not numeric esc
				$value = "'".mysql_real_escape_string($value)."'";
			$values[] = $value;
		}
		// make data dump
		if ($_SESSION['JOB']['dbshortinsert'])
			fwrite($file, "INSERT INTO `".$table."` VALUES ( ".implode(", ",$values)." );\n");
		else
			fwrite($file, "INSERT INTO `".$table."` ( ".implode(", ",$keys)." )\n\tVALUES ( ".implode(", ",$values)." );\n");

	}
	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE ".$table." ENABLE KEYS */;\n");
}
?>