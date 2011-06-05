<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
// Remove header and footer form logfile
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

//ajax show info div for jobs
function backwpup_working_update() {
	check_ajax_referer('backwpupworking_ajax_nonce');
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		die('-1');
	if (is_file(trim($_POST['logfile']).'.gz'))
		$_POST['logfile']=trim($_POST['logfile']).'.gz';
	$log='';
	if (is_file(trim($_POST['logfile']))) {
		$runfile="";
		if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) 
			$runfile=trim(@file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
		if (!empty($runfile)) {
			$infile=unserialize($runfile);
			$warnings=$infile['WARNING'];
			$errors=$infile['ERROR'];
			$stepspersent=$infile['STEPSPERSENT'];
			$steppersent=$infile['STEPPERSENT'];
		} else {
			$logheader=backwpup_read_logheader(trim($_POST['logfile']));
			$warnings=$logheader['warnings'];
			$errors=$logheader['errors'];
			$stepspersent=100;
			$steppersent=100;
			$log.='<span id="stopworking"></span>';			
		}
		$logfilarray=backwpup_read_logfile(trim($_POST['logfile']));
		for ($i=$_POST['logpos'];$i<count($logfilarray);$i++)
				$log.=$logfilarray[$i];
		echo json_encode(array('logpos'=>count($logfilarray),'LOG'=>$log,'WARNING'=>$warnings,'ERROR'=>$errors,'STEPSPERSENT'=>$stepspersent,'STEPPERSENT'=>$steppersent));
		die();
		
	}
	echo json_encode(array('logpos'=>$_POST['logpos'],'LOG'=>$log,'WARNING'=>0,'ERROR'=>0,'STEPSPERSENT'=>0,'STEPPERSENT'=>0));
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_working_update', 'backwpup_working_update');	
?>