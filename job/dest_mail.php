<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_mail() {
	if (empty($_SESSION['JOB']['mailaddress'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_MAIL'; //set done
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_MAIL']['STEP_TRY'].'. '.__('Try to sending backup file with mail...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;
	//Create PHP Mailer
	require_once($_SESSION['WP']['ABSPATH'].$_SESSION['WP']['WPINC'].'/class-phpmailer.php');
	$phpmailer = new PHPMailer();
	//Setting den methode
	if ($_SESSION['CFG']['mailmethod']=="SMTP") {
		require_once($_SESSION['WP']['ABSPATH'].$_SESSION['WP']['WPINC'].'/class-smtp.php');
		$smtpport=25;
		$smtphost=$_SESSION['CFG']['mailhost'];
		if (false !== strpos($_SESSION['CFG']['mailhost'],':')) //look for port
			list($smtphost,$smtpport)=explode(':',$_SESSION['CFG']['mailhost'],2);
		$phpmailer->Host=$smtphost;
		$phpmailer->Port=$smtpport;
		$phpmailer->SMTPSecure=$_SESSION['CFG']['mailsecure'];
		$phpmailer->Username=$_SESSION['CFG']['mailuser'];
		$phpmailer->Password=base64_decode($_SESSION['CFG']['mailpass']);
		if (!empty($_SESSION['CFG']['mailuser']) and !empty($_SESSION['CFG']['mailpass']))
			$phpmailer->SMTPAuth=true;
		$phpmailer->IsSMTP();
		trigger_error(__('Send mail with SMTP','backwpup'),E_USER_NOTICE);
	} elseif ($_SESSION['CFG']['mailmethod']=="Sendmail") {
		$phpmailer->Sendmail=$_SESSION['CFG']['mailsendmail'];
		$phpmailer->IsSendmail();
		trigger_error(__('Send mail with Sendmail','backwpup'),E_USER_NOTICE);
	} else {
		$phpmailer->IsMail();
		trigger_error(__('Send mail with PHP mail','backwpup'),E_USER_NOTICE);
	}

	trigger_error(__('Creating mail','backwpup'),E_USER_NOTICE);
	$phpmailer->From     = $_SESSION['CFG']['mailsndemail'];
	$phpmailer->FromName = $_SESSION['CFG']['mailsndname'];
	$phpmailer->AddAddress($_SESSION['JOB']['mailaddress']);
	$phpmailer->Subject  =  __('BackWPup File from','backwpup').' '.date('Y-m-d H:i',$_SESSION['JOB']['starttime']).': '.$_SESSION['JOB']['name'];
	$phpmailer->IsHTML(false);
	$phpmailer->Body  =  __('Backup File:','backwpup').$_SESSION['STATIC']['backupfile'];

	//check file Size
	if (!empty($_SESSION['JOB']['mailefilesize'])) {
		$maxfilezise=abs($_SESSION['JOB']['mailefilesize']*1024*1024);
		if (filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])>$maxfilezise) {
			trigger_error(__('Backup Archive too big for sending by mail','backwpup'),E_USER_ERROR);
			$_SESSION['WORKING']['STEPDONE']=1;
			$_SESSION['WORKING']['STEPSDONE'][]='DEST_MAIL'; //set done
			return;
		}
	}

	trigger_error(__('Adding Attachment to mail','backwpup'),E_USER_NOTICE);
	need_free_memory(filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])*5);
	$phpmailer->AddAttachment($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);

	trigger_error(__('Send mail....','backwpup'),E_USER_NOTICE);
	if (false == $phpmailer->Send()) {
		trigger_error(__('Can not send mail:','backwpup').' '.$phpmailer->ErrorInfo,E_USER_ERROR);
	} else {
		trigger_error(__('Mail send!!!','backwpup'),E_USER_NOTICE);
	}
	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_MAIL'; //set done
}
?>