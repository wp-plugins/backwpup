<?PHP
function backwpup_read_logfile($logfile) {
	if (is_file($logfile) and strtolower(substr($logfile,-3))=='.gz')
		$logfiledata=gzfile($logfile);
	elseif (is_file($logfile.'.gz'))
		$logfiledata=gzfile($logfile.'.gz');
	elseif (is_file($logfile))
		$logfiledata=file($logfile);	
	else
		return false;
	$lines=array();
	$start=false;
	foreach ($logfiledata as $line){
		$line=trim($line);
		if (strripos($line,'<body')!== false) {  // jop over header
			$start=true;
			continue;
		}
		if ($line!='</body>' and $line!='</html>' and $start) //no Footer
			$lines[]=$line;
	}
	return $lines;
}

function backwpup_get_working_file() {
	if (is_file(trim($_POST['BackWPupJobTemp']).'.running')) {
		if ($runningfile=file_get_contents(trim($_POST['BackWPupJobTemp']).'.running'))
			return unserialize(trim($runningfile));
		else
			return false;
	} else {
		return false;
	}
}

//read log file header
function backwpup_read_logheader($logfile) {
	$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfilesize" => "backupfilesize");
	if (!is_readable($logfile))
		return false;
	//Read file
	if (strtolower(substr($logfile,-3))==".gz") {
		$fp = gzopen( $logfile, 'r' );
		$file_data = gzread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		gzclose( $fp );
	} else {
		$fp = fopen( $logfile, 'r' );
		$file_data = fread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		fclose( $fp );
	}
	//get data form file
	foreach ($headers as $keyword => $field) {
		preg_match('/(<meta name="'.$keyword.'" content="(.*)" \/>)/i',$file_data,$content);
		if (!empty($content))
			$joddata[$field]=$content[2];
		else
			$joddata[$field]='';
	}
	if (empty($joddata['logtime']))
		$joddata['logtime']=filectime($logfile);
	return $joddata;
}

$_POST['logfile']=trim(str_replace(array(':','@','../','//','\\'),'',$_POST['logfile']));
$_POST['BackWPupJobTemp']=trim(str_replace(array(':','@','../','//','\\'),'',$_POST['BackWPupJobTemp']));
if (is_file($_POST['logfile'].'.gz'))
	$_POST['logfile']=$_POST['logfile'].'.gz';

// check given file is a backwpup logfile
if (substr(trim($_POST['logfile']),-3)!='.gz' and substr($_POST['logfile'],-8)!='.html.gz' and substr($_POST['logfile'],0,13)!='backwpup_log_' and strlen($_POST['logfile'])>40 and strlen($_POST['logfile'])<37)
	die();
	
$log='';
if (is_file($_POST['logfile'])) {
	if (is_file($_POST['BackWPupJobTemp'].'.running')) {
		if ($infile=backwpup_get_working_file()) {
			$warnings=$infile['WORKING']['WARNING'];
			$errors=$infile['WORKING']['ERROR'];
			$stepspersent=$infile['STEPSPERSENT'];
			$steppersent=$infile['STEPPERSENT'];
		}
	} else {
		$logheader=backwpup_read_logheader(trim($_POST['logfile']));
		$warnings=$logheader['warnings'];
		$errors=$logheader['errors'];
		$stepspersent=100;
		$steppersent=100;
		$log.='<span id="stopworking"></span>';		
	}
	$logfilarray=backwpup_read_logfile(trim($_POST['logfile']));
	//for ($i=0;$i<count($logfilarray);$i++)
	for ($i=$_POST['logpos'];$i<count($logfilarray);$i++)
			$log.=$logfilarray[$i];
	echo json_encode(array('logpos'=>count($logfilarray),'LOG'=>$log,'WARNING'=>$warnings,'ERROR'=>$errors,'STEPSPERSENT'=>$stepspersent,'STEPPERSENT'=>$steppersent));
}
die();
?>