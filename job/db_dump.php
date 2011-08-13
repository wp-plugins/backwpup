<?PHP
function db_dump() {
	global $WORKING,$STATIC;
	trigger_error(sprintf(__('%d. try for database dump...','backwpup'),$WORKING['DB_DUMP']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($WORKING['DB_DUMP']['DONETABLE']) or !is_array($WORKING['DB_DUMP']['DONETABLE']))
		$WORKING['DB_DUMP']['DONETABLE']=array();
	
	mysql_update();
	//to backup
	$tabelstobackup=array();
	$result=mysql_query("SHOW TABLES FROM `".$STATIC['WP']['DB_NAME']."`"); //get table status
	if (!$result)
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".$STATIC['WP']['DB_NAME']."`;"),E_USER_ERROR);
	while ($data = mysql_fetch_row($result)) {
		if (!in_array($data[0],$STATIC['JOB']['dbexclude']))
			$tabelstobackup[]=$data[0];
	}	
	$WORKING['STEPTODO']=count($tabelstobackup);
	//Set maintenance
	maintenance_mode(true);

	if (count($tabelstobackup)>0) {
		$result=mysql_query("SHOW TABLE STATUS FROM `".$STATIC['WP']['DB_NAME']."`"); //get table status
		if (!$result)
			trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".$STATIC['WP']['DB_NAME']."`;"),E_USER_ERROR);
		while ($data = mysql_fetch_assoc($result)) {
			$status[$data['Name']]=$data;
		}

		if ($file = fopen($STATIC['TEMPDIR'].$STATIC['WP']['DB_NAME'].'.sql', 'wb')) {
			fwrite($file, "-- ---------------------------------------------------------\n");
			fwrite($file, "-- Dump with BackWPup ver.: ".$STATIC['BACKWPUP']['VERSION']."\n");
			fwrite($file, "-- Plugin for WordPress ".$STATIC['WP']['VERSION']." by Daniel Huesken\n");
			fwrite($file, "-- http://danielhuesken.de/portfolio/backwpup/\n");
			fwrite($file, "-- Blog Name: ".$STATIC['WP']['BLOGNAME']."\n");
			fwrite($file, "-- Blog URL: ".$STATIC['WP']['SITEURL']."\n");
			fwrite($file, "-- Blog ABSPATH: ".$STATIC['WP']['ABSPATH']."\n");
			fwrite($file, "-- Table Prefix: ".$STATIC['WP']['TABLE_PREFIX']."\n");
			fwrite($file, "-- Database Name: ".$STATIC['WP']['DB_NAME']."\n");
			fwrite($file, "-- Dump on: ".date('Y-m-d H:i.s',time()+$STATIC['WP']['TIMEDIFF'])."\n");
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
			foreach($tabelstobackup as $table) {
				if (in_array($table, $WORKING['DB_DUMP']['DONETABLE']))
					continue;
				trigger_error(sprintf(__('Dump database table "%s"','backwpup'),$table),E_USER_NOTICE);
				need_free_memory(($status[$table]['Data_length']+$status[$table]['Index_length'])*1.3); //get more memory if needed
				_db_dump_table($table,$status[$table],$file);
				$WORKING['DB_DUMP']['DONETABLE'][]=$table;
				$WORKING['STEPDONE']=count($WORKING['DB_DUMP']['DONETABLE']);
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
			trigger_error(__('Database dump done!','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('Can not create database dump!','backwpup'),E_USER_ERROR);
		}
	} else {
		trigger_error(__('No tables to dump','backwpup'),E_USER_WARNING);
	}

	//add database file to backupfiles
	if (is_readable($STATIC['TEMPDIR'].$STATIC['WP']['DB_NAME'].'.sql')) {
		$filestat=stat($STATIC['TEMPDIR'].$STATIC['WP']['DB_NAME'].'.sql');
		trigger_error(sprintf(__('Add database dump "%1$s" with %2$s to backup file list','backwpup'),$STATIC['WP']['DB_NAME'].'.sql',formatbytes($filestat['size'])),E_USER_NOTICE);
		$WORKING['ALLFILESIZE']+=$filestat['size'];
		add_file(array(array('FILE'=>$STATIC['TEMPDIR'].$STATIC['WP']['DB_NAME'].'.sql','OUTFILE'=>$STATIC['WP']['DB_NAME'].'.sql','SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode'])));
	}
	//Back from maintenance
	maintenance_mode(false);
	$WORKING['STEPSDONE'][]='DB_DUMP'; //set done
}


function _db_dump_table($table,$status,$file) {
	global $WORKING,$STATIC;
	// create dump
	fwrite($file, "\n--\n-- Table structure for table $table\n--\n\n");
	fwrite($file, "DROP TABLE IF EXISTS `".$table."`;\n");
	fwrite($file, "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
	fwrite($file, "/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;\n");
	//Dump the table structure
	$result=mysql_query("SHOW CREATE TABLE `".$table."`");
	if (!$result) {
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
		return false;
	}
	$tablestruc=mysql_fetch_assoc($result);
	fwrite($file, $tablestruc['Create Table'].";\n");
	fwrite($file, "/*!40101 SET character_set_client = @saved_cs_client */;\n");

	//take data of table
	$result=mysql_query("SELECT * FROM `".$table."`");
	if (!$result) {
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SELECT * FROM `".$table."`"),E_USER_ERROR);
		return false;
	}
	//get key information
	$i = 0;
	$keys = array();
	while ($i < mysql_num_fields($result)) {
		$meta = mysql_fetch_field($result, $i);
		$keymeta[$i]=$meta;
		$keys[] = "`".$meta->name."`";
		$i++;
	}
	
	//build key string
	$keystring='';
	if (!$STATIC['JOB']['dbshortinsert'])
		$keystring=" (".implode(", ",$keys).")";
		
	fwrite($file, "\n--\n-- Dumping data for table $table\n--\n\n");

	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n");
	$querystring='';
	while ($data = mysql_fetch_array($result, MYSQL_NUM)) {
		$values = array();
		foreach($data as $key => $value) {
			if(is_null($value) or !isset($value)) // Make Value NULL to string NULL
				$value = "NULL";
			elseif($keymeta[$key]->numeric and $keymeta[$key]->type!='timestamp' and !$keymeta[$key]->blob)//is value numeric no esc
				$value = empty($value) ? 0 : $value;
			else
				$value = "'".mysql_real_escape_string($value)."'";
			$values[] = $value;
		}
		if (empty($querystring))
			$querystring="INSERT IGNORE INTO `".$table."`".$keystring." VALUES\n";
		if (strlen($querystring)<50000) { //write dump on more than 50000 chars.
			$querystring.="(".implode(", ",$values)."),\n";
		} else { 
			$querystring.="(".implode(", ",$values).");\n";
			fwrite($file, $querystring);
			$querystring='';
		}	
	}
	if (!empty($querystring)) //dump rest
		fwrite($file, substr($querystring,0,-2).";\n");
	
	if ($status['Engine']=='MyISAM')
		fwrite($file, "/*!40000 ALTER TABLE ".$table." ENABLE KEYS */;\n");
	mysql_free_result($result);
}
?>