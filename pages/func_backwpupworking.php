<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//ajax show info div for jobs
function backwpup_working_update() {
	check_ajax_referer('backwpupworking_ajax_nonce');
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		die('-1');
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$runfile=trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
		if (!empty($runfile)) {
			$infile=unserialize($runfile);
			$infile['LOG']='';
			if (is_file($infile['LOGFILE'])) {
				$logfilarray=file($infile['LOGFILE']);
				for ($i=$_POST['logpos'];$i<count($logfilarray);$i++) {
					if (trim($logfilarray[$i])!="</body>" and trim($logfilarray[$i])!="</html>")
						$infile['LOG'].=trim($logfilarray[$i]);
				}
				$infile['logpos']=count($logfilarray);
			}
			echo json_encode($infile);
		}
	} else {
		$log='';
		$logpos='';
		$logheader['warnings']=0;
		$logheader['errors']=0;
		if (is_file(trim($_POST['logfile']))) {
			$logfilarray=file(trim($_POST['logfile']));
			for ($i=$_POST['logpos'];$i<count($logfilarray);$i++) {
				if (trim($logfilarray[$i])!="</body>" and trim($logfilarray[$i])!="</html>")
					$log.=trim($logfilarray[$i]);
			}
			$logpos=count($logfilarray);
			$logheader=backwpup_read_logheader(trim($_POST['logfile']));
		}
		echo json_encode(array('logpos'=>$logpos,'LOG'=>$log.'<span id="stopworking"></span>','WARNING'=>$logheader['warnings'],'ERROR'=>$logheader['errors'],'STEPSPERSENT'=>100,'STEPPERSENT'=>100));
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_working_update', 'backwpup_working_update');	
?>