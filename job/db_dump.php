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
	
	$datevars=array('%d','%D','%l','%N','%S','%w','%z','%W','%F','%m','%M','%n','%t','%L','%o','%Y','%a','%A','%B','%g','%G','%h','%H','%i','%s','%u','%e','%I','%O','%P','%T','%Z','%c','%U');
	$datevalues=array(date('d'),date('D'),date('l'),date('N'),date('S'),date('w'),date('z'),date('W'),date('F'),date('m'),date('M'),date('n'),date('t'),date('L'),date('o'),date('Y'),date('a'),date('A'),date('B'),date('g'),date('G'),date('h'),date('H'),date('i'),date('s'),date('u'),date('e'),date('I'),date('O'),date('P'),date('T'),date('Z'),date('c'),date('U'));
	$STATIC['JOB']['dbdumpfile']=str_replace($datevars,$datevalues,$STATIC['JOB']['dbdumpfile']);
	
	//check compression
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz' and !function_exists('gzopen'))
		$STATIC['JOB']['dbdumpfilecompression']='';
	if ($STATIC['JOB']['dbdumpfilecompression']=='bz2' and !function_exists('bzopen'))
		$STATIC['JOB']['dbdumpfilecompression']='';
	//add file ending
	$STATIC['JOB']['dbdumpfile'].='.sql';
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz' or $STATIC['JOB']['dbdumpfilecompression']=='bz2')
		$STATIC['JOB']['dbdumpfile'].='.'.$STATIC['JOB']['dbdumpfilecompression'];
	
	//Set maintenance
	maintenance_mode(true);

	if (count($tabelstobackup)==0) { //Check tables to dump
		trigger_error(__('No tables to dump','backwpup'),E_USER_WARNING);
		maintenance_mode(false);
		$WORKING['STEPSDONE'][]='DB_DUMP'; //set done
		return;
	}
	
	$result=mysql_query("SHOW TABLE STATUS FROM `".$STATIC['WP']['DB_NAME']."`"); //get table status
	if (!$result)
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".$STATIC['WP']['DB_NAME']."`;"),E_USER_ERROR);
	while ($data = mysql_fetch_assoc($result)) {
		$status[$data['Name']]=$data;
	}
	
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
		$file = gzopen($STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile'], 'wb9');
	elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2') 
		$file = bzopen($STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile'], 'wb9');
	else
		$file = fopen($STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile'], 'wb');
		
	if (!$file) {
		trigger_error(sprintf(__('Can not create database dump file! "%s"','backwpup'),$STATIC['JOB']['dbdumpfile']),E_USER_ERROR);
		maintenance_mode(false);
		return;
	}
	
	$dbdumpheader= "-- ---------------------------------------------------------\n";
	$dbdumpheader.= "-- Dump with BackWPup ver.: ".$STATIC['BACKWPUP']['VERSION']."\n";
	$dbdumpheader.= "-- Plugin for WordPress ".$STATIC['WP']['VERSION']." by Daniel Huesken\n";
	$dbdumpheader.= "-- http://danielhuesken.de/portfolio/backwpup/\n";
	$dbdumpheader.= "-- Blog Name: ".$STATIC['WP']['BLOGNAME']."\n";
	$dbdumpheader.= "-- Blog URL: ".$STATIC['WP']['SITEURL']."\n";
	$dbdumpheader.= "-- Blog ABSPATH: ".$STATIC['WP']['ABSPATH']."\n";
	$dbdumpheader.= "-- Table Prefix: ".$STATIC['WP']['TABLE_PREFIX']."\n";
	$dbdumpheader.= "-- Database Name: ".$STATIC['WP']['DB_NAME']."\n";
	$dbdumpheader.= "-- Dump on: ".date('Y-m-d H:i.s',time()+$STATIC['WP']['TIMEDIFF'])."\n";
	$dbdumpheader.= "-- ---------------------------------------------------------\n\n";
	//for better import with mysql client
	$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
	$dbdumpheader.= "/*!40101 SET NAMES '".mysql_client_encoding()."' */;\n";
	$dbdumpheader.= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n";
	$dbdumpheader.= "/*!40103 SET TIME_ZONE='".mysql_result(mysql_query("SELECT @@time_zone"),0)."' */;\n";
	$dbdumpheader.= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
	$dbdumpheader.= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
	$dbdumpheader.= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $dbdumpheader);
	elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $dbdumpheader);
	else 
		fwrite($file, $dbdumpheader);
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
	$dbdumpfooter= "\n";
	$dbdumpfooter.= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n";
	$dbdumpfooter.= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
	$dbdumpfooter.= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
	$dbdumpfooter.= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n";
	$dbdumpfooter.= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
	$dbdumpfooter.= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
	$dbdumpfooter.= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
	$dbdumpfooter.= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";
	
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz') {
		gzwrite($file, $dbdumpfooter);
		gzclose($file);
	} elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2') {
		bzwrite($file, $dbdumpfooter);
		bzclose($file);
	} else {
		fwrite($file, $dbdumpfooter);
		fclose($file);
	}

	trigger_error(__('Database dump done!','backwpup'),E_USER_NOTICE);

	//add database file to backupfiles
	if (is_readable($STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile'])) {
		$filestat=stat($STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile']);
		trigger_error(sprintf(__('Add database dump "%1$s" with %2$s to backup file list','backwpup'),$STATIC['JOB']['dbdumpfile'],formatbytes($filestat['size'])),E_USER_NOTICE);
		$WORKING['ALLFILESIZE']+=$filestat['size'];
		add_file(array(array('FILE'=>$STATIC['TEMPDIR'].$STATIC['JOB']['dbdumpfile'],'OUTFILE'=>$STATIC['JOB']['dbdumpfile'],'SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode'])));
	}
	//Back from maintenance
	maintenance_mode(false);
	$WORKING['STEPSDONE'][]='DB_DUMP'; //set done
}


function _db_dump_table($table,$status,$file) {
	global $STATIC;
	// create dump
	$tablecreate="\n--\n-- Table structure for table $table\n--\n\n";
	$tablecreate.="DROP TABLE IF EXISTS `".$table."`;\n";
	$tablecreate.="/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
	$tablecreate.="/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;\n";
	//Dump the table structure
	$result=mysql_query("SHOW CREATE TABLE `".$table."`");
	if (!$result) {
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
		return false;
	}
	$tablestruc=mysql_fetch_assoc($result);
	$tablecreate.=$tablestruc['Create Table'].";\n";
	$tablecreate.="/*!40101 SET character_set_client = @saved_cs_client */;\n";

	if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tablecreate);
	elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tablecreate);
	else 
		fwrite($file, $tablecreate);	
	
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
		
	$tabledata="\n--\n-- Dumping data for table $table\n--\n\n";

	if ($status['Engine']=='MyISAM')
		$tabledata.="/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n";

	if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tabledata);
	elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tabledata);
	else 
		fwrite($file, $tabledata);
	$tabledata='';
		
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
			$querystring="INSERT INTO `".$table."`".$keystring." VALUES\n";
		if (strlen($querystring)<=50000) { //write dump on more than 50000 chars.
			$querystring.="(".implode(", ",$values)."),\n";
		} else { 
			$querystring.="(".implode(", ",$values).");\n";
			if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
				gzwrite($file, $querystring);
			elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2')
				bzwrite($file, $querystring);
			else 
				fwrite($file, $querystring);
			$querystring='';
		}	
	}
	if (!empty($querystring)) //dump rest
		$tabledata=substr($querystring,0,-2).";\n";
	
	if ($status['Engine']=='MyISAM')
		$tabledata.="/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;\n";
		
	if ($STATIC['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tabledata);
	elseif ($STATIC['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tabledata);
	else 
		fwrite($file, $tabledata);	
		
	mysql_free_result($result);
}
?>