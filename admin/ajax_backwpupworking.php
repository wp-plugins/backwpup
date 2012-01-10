<?PHP
if (!defined('ABSPATH'))
	die();

include_once(dirname(__FILE__).'/func_backwpupworking.php');

function backwpup_get_logfile_ajax() {
    check_ajax_referer('backwpupworking_ajax_nonce');
    if (is_file(trim($_POST['logfile']).'.gz'))
        $_POST['logfile']=trim($_POST['logfile']).'.gz';

    // check given file is a backwpup logfile
    if (substr(trim($_POST['logfile']),-3)!='.gz' and substr(trim($_POST['logfile']),-8)!='.html.gz' and substr(trim($_POST['logfile']),0,13)!='backwpup_log_' and strlen(trim($_POST['logfile']))>40 and strlen(trim($_POST['logfile']))<37)
        die();

    $log='';
    if (is_file(trim($_POST['logfile']))) {
		$backupdata=backwpup_get_option('working','data');
        if (!empty($backupdata)) {
            $warnings=$backupdata['WARNING'];
            $errors=$backupdata['ERROR'];
            $stepspersent=$backupdata['STEPSPERSENT'];
            $steppersent=$backupdata['STEPPERSENT'];
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
    }
    die();
}
//add ajax function
add_action('wp_ajax_backwpup_get_logfile_ajax', 'backwpup_get_logfile_ajax');
?>