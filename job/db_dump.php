<?PHP
function backwpup_job_db_dump() {
	global $backwpupjobrun,$wpdb,$wp_version;
	trigger_error(sprintf(__('%d. try for database dump...','backwpup'),$backwpupjobrun['WORKING']['DB_DUMP']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE']) or !is_array($backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE']))
		$backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE']=array();

	//to backup
	$tablestobackup=array();
	$tables = $wpdb->get_col("SHOW TABLES FROM `".DB_NAME."`"); //get table status
	if (mysql_error())
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".DB_NAME."`;"),E_USER_ERROR);
	foreach ($tables as $table) {
		if (!in_array($table,$backwpupjobrun['STATIC']['JOB']['dbexclude']))
			$tablestobackup[]=$table;
	}
	$backwpupjobrun['WORKING']['STEPTODO']=count($tablestobackup);

	$datevars=array('%d','%D','%l','%N','%S','%w','%z','%W','%F','%m','%M','%n','%t','%L','%o','%Y','%a','%A','%B','%g','%G','%h','%H','%i','%s','%u','%e','%I','%O','%P','%T','%Z','%c','%U');
	$datevalues=array(date_i18n('d'),date_i18n('D'),date_i18n('l'),date_i18n('N'),date_i18n('S'),date_i18n('w'),date_i18n('z'),date_i18n('W'),date_i18n('F'),date_i18n('m'),date_i18n('M'),date_i18n('n'),date_i18n('t'),date_i18n('L'),date_i18n('o'),date_i18n('Y'),date_i18n('a'),date_i18n('A'),date_i18n('B'),date_i18n('g'),date_i18n('G'),date_i18n('h'),date_i18n('H'),date_i18n('i'),date_i18n('s'),date_i18n('u'),date_i18n('e'),date_i18n('I'),date_i18n('O'),date_i18n('P'),date_i18n('T'),date_i18n('Z'),date_i18n('c'),date_i18n('U'));
	$backwpupjobrun['STATIC']['JOB']['dbdumpfile']=str_replace($datevars,$datevalues,$backwpupjobrun['STATIC']['JOB']['dbdumpfile']);

	//check compression
	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz' and !function_exists('gzopen'))
		$backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']='';
	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2' and !function_exists('bzopen'))
		$backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']='';
	//add file ending
	$backwpupjobrun['STATIC']['JOB']['dbdumpfile'].='.sql';
	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz' or $backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		$backwpupjobrun['STATIC']['JOB']['dbdumpfile'].='.'.$backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression'];
	
	//Set maintenance
	backwpup_job_maintenance_mode(true);

	if (count($tablestobackup)==0) { //Check tables to dump
		trigger_error(__('No tables to dump','backwpup'),E_USER_WARNING);
		maintenance_mode(false);
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DB_DUMP'; //set done
		return;
	}

	$tablesstatus=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`"); //get table status
	if (mysql_error())
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".DB_NAME."`;"),E_USER_ERROR);
	foreach ($tablesstatus as $tablestatus) {
		$status[$tablestatus->Name]=$tablestatus;
	}

	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
		$file = gzopen($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile'], 'wb9');
	elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		$file = bzopen($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile'], 'w');
	else
		$file = fopen($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile'], 'wb');

	if (!$file) {
		trigger_error(sprintf(__('Can not create database dump file! "%s"','backwpup'),$backwpupjobrun['STATIC']['JOB']['dbdumpfile']),E_USER_ERROR);
		maintenance_mode(false);
		return;
	}

	$dbdumpheader= "-- ---------------------------------------------------------\n";
	$dbdumpheader.= "-- Dump with BackWPup ver.: ".BACKWPUP_VERSION."\n";
	$dbdumpheader.= "-- Plugin for WordPress ".$wp_version." by Daniel Huesken\n";
	$dbdumpheader.= "-- http://danielhuesken.de/portfolio/backwpup/\n";
	$dbdumpheader.= "-- Blog Name: ".get_bloginfo('name')."\n";
	if (defined('WP_SITEURL'))
        $dbdumpheader.= "-- Blog URL: ".trailingslashit(WP_SITEURL)."\n";
    else
        $dbdumpheader.= "-- Blog URL: ".trailingslashit(get_option('siteurl'))."\n";
	$dbdumpheader.= "-- Blog ABSPATH: ".trailingslashit(str_replace('\\','/',ABSPATH))."\n";
	$dbdumpheader.= "-- Table Prefix: ".$wpdb->prefix."\n";
	$dbdumpheader.= "-- Database Name: ".DB_NAME."\n";
	$dbdumpheader.= "-- Dump on: ".date_i18n('Y-m-d H:i.s')."\n";
	$dbdumpheader.= "-- ---------------------------------------------------------\n\n";
	//for better import with mysql client
	$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
	$dbdumpheader.= "/*!40101 SET NAMES '".mysql_client_encoding()."' */;\n";
	$dbdumpheader.= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n";
	$dbdumpheader.= "/*!40103 SET TIME_ZONE='".$wpdb->get_var("SELECT @@time_zone")."' */;\n";
	$dbdumpheader.= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
	$dbdumpheader.= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
	$dbdumpheader.= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
	$dbdumpheader.= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";
	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $dbdumpheader);
	elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $dbdumpheader);
	else
		fwrite($file, $dbdumpheader);
	//make table dumps
	foreach($tablestobackup as $table) {
		if (in_array($table, $backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE']))
			continue;
		trigger_error(sprintf(__('Dump database table "%s"','backwpup'),$table),E_USER_NOTICE);
		backwpup_job_need_free_memory(($status[$table]->Data_length+$status[$table]->Index_length)*1.5); //get more memory if needed
		_backwpup_job_db_dump_table($table,$status[$table],$file);
		$backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE'][]=$table;
		$backwpupjobrun['WORKING']['STEPDONE']=count($backwpupjobrun['WORKING']['DB_DUMP']['DONETABLE']);
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

	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz') {
		gzwrite($file, $dbdumpfooter);
		gzclose($file);
	} elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2') {
		bzwrite($file, $dbdumpfooter);
		bzclose($file);
	} else {
		fwrite($file, $dbdumpfooter);
		fclose($file);
	}

	trigger_error(__('Database dump done!','backwpup'),E_USER_NOTICE);

	//add database file to backupfiles
	if (is_readable($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile'])) {
		$filestat=stat($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile']);
		trigger_error(sprintf(__('Add database dump "%1$s" with %2$s to backup file list','backwpup'),$backwpupjobrun['STATIC']['JOB']['dbdumpfile'],backwpup_formatBytes($filestat['size'])),E_USER_NOTICE);
		$backwpupjobrun['WORKING']['ALLFILESIZE']+=$filestat['size'];
		backwpup_job_add_file(array(array('FILE'=>$backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['dbdumpfile'],'OUTFILE'=>$backwpupjobrun['STATIC']['JOB']['dbdumpfile'],'SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode'],'FOLDER'=>'/')));
		backwpup_job_add_folder('/');
	}
	//Back from maintenance
	backwpup_job_maintenance_mode(false);
	$backwpupjobrun['WORKING']['STEPSDONE'][]='DB_DUMP'; //set done
}


function _backwpup_job_db_dump_table($table,$status,$file) {
	global $backwpupjobrun,$wpdb;
	// create dump
	$tablecreate="\n--\n-- Table structure for table $table\n--\n\n";
	$tablecreate.="DROP TABLE IF EXISTS `".$table."`;\n";
	$tablecreate.="/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
	$tablecreate.="/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;\n";
	//Dump the table structure
	$tablestruc=$wpdb->get_row("SHOW CREATE TABLE `".$table."`",'ARRAY_A');
	if (mysql_error()) {
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
		return false;
	}
	$tablecreate.=$tablestruc['Create Table'].";\n";
	$tablecreate.="/*!40101 SET character_set_client = @saved_cs_client */;\n";

	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tablecreate);
	elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tablecreate);
	else
		fwrite($file, $tablecreate);

	//take data of table
	$datas=$wpdb->get_results("SELECT * FROM `".$table."`",'ARRAY_N');
	if (mysql_error()) {
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SELECT * FROM `".$table."`"),E_USER_ERROR);
		return false;
	}
	//get key information
	$keys=$wpdb->get_col_info('name',-1);

	//build key string
	$keystring='';
	if (!$backwpupjobrun['STATIC']['JOB']['dbshortinsert'])
		$keystring=" (`".implode("`, `",$keys)."`)";
	//colem infos
	for ($i=0;$i<count($keys);$i++) {
		$colinfo[$i]['numeric']=$wpdb->get_col_info('numeric',$i);
		$colinfo[$i]['type']=$wpdb->get_col_info('type',$i);
		$colinfo[$i]['blob']=$wpdb->get_col_info('blob',$i);
	}
		
	$tabledata="\n--\n-- Dumping data for table $table\n--\n\n";

	if ($status->Engine=='MyISAM')
		$tabledata.="/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n";

	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tabledata);
	elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tabledata);
	else
		fwrite($file, $tabledata);
	$tabledata='';
	
	$querystring='';
	foreach ($datas as $data) {
		$values = array();
		foreach($data as $key => $value) {
			if(is_null($value) or !isset($value)) // Make Value NULL to string NULL
				$value = "NULL";
			elseif($colinfo[$key]['numeric']==1 and $colinfo[$key]['type']!='timestamp' and $colinfo[$key]['blob']!=1)//is value numeric no esc
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
			if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
				gzwrite($file, $querystring);
			elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
				bzwrite($file, $querystring);
			else
				fwrite($file, $querystring);
			$querystring='';
		}
	}
	if (!empty($querystring)) //dump rest
		$tabledata=substr($querystring,0,-2).";\n";

	if ($status->Engine=='MyISAM')
		$tabledata.="/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;\n";

	if ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='gz')
		gzwrite($file, $tabledata);
	elseif ($backwpupjobrun['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
		bzwrite($file, $tabledata);
	else
		fwrite($file, $tabledata);

	$wpdb->flush();
}
?>