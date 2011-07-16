<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backup_create() {
	global $WORKING,$STATIC;
	if ($WORKING['ALLFILESIZE']==0)
		return;
	$filelist=get_filelist(); //get file list
	$WORKING['STEPTODO']=count($filelist);
	if (empty($WORKING['STEPDONE']))
		$WORKING['STEPDONE']=0;
	
	if (strtolower($STATIC['JOB']['fileformart'])==".zip") { //Zip files
		if (class_exists('ZipArchive')) {  //use php zip lib
			trigger_error($WORKING['BACKUP_CREATE']['STEP_TRY'].'. '.__('Try to create backup zip file...','backwpup'),E_USER_NOTICE);
			$zip = new ZipArchive();
			if ($res=$zip->open($STATIC['JOB']['backupdir'].$STATIC['backupfile'],ZIPARCHIVE::CREATE) === TRUE) {
				for ($i=$WORKING['STEPDONE'];$i<$WORKING['STEPTODO'];$i++) {
					if (!$zip->addFile($filelist[$i]['FILE'], $filelist[$i]['OUTFILE'])) 
						trigger_error(__('Can not add File to ZIP file:','backwpup').' '.$filelist[$i]['OUTFILE'],E_USER_ERROR);
					$WORKING['STEPDONE']++;
					update_working_file();
				}
				if ($zip->status>0)
					$ziperror=$zip->status;
					if ($zip->status==4)
						$ziperror=__('(4) ER_SEEK','backwpup');
					if ($zip->status==5)
						$ziperror=__('(5) ER_READ','backwpup');
					if ($zip->status==9)
						$ziperror=__('(9) ER_NOENT','backwpup');
					if ($zip->status==10)
						$ziperror=__('(10) ER_EXISTS','backwpup');
					if ($zip->status==11)
						$ziperror=__('(11) ER_OPEN','backwpup');
					if ($zip->status==14)
						$ziperror=__('(14) ER_MEMORY','backwpup');
					if ($zip->status==18)
						$ziperror=__('(18) ER_INVAL','backwpup');
					if ($zip->status==19)
						$ziperror=__('(19) ER_NOZIP','backwpup');
					if ($zip->status==21)
						$ziperror=__('(21) ER_INCONS','backwpup');
					trigger_error(__('Zip Status:','backwpup').' '.$zip->status ,E_USER_ERROR);
				$res2=$zip->close();
				trigger_error(__('Backup zip file create done!','backwpup'),E_USER_NOTICE);
				$WORKING['STEPSDONE'][]='BACKUP_CREATE'; //set done
			} else {
				trigger_error(__('Can not create backup zip file:','backwpup').' '.$res,E_USER_ERROR);
			}
		} else { //use PclZip
			define('PCLZIP_TEMPORARY_DIR', $STATIC['TEMPDIR']);
			require_once($STATIC['WP']['ABSPATH'].'wp-admin/includes/class-pclzip.php');
			//Create Zip File
			if (is_array($filelist[0])) {
				trigger_error($WORKING['BACKUP_CREATE']['STEP_TRY'].'. '.__('Try to create backup zip (PclZip) file...','backwpup'),E_USER_NOTICE);
				$zipbackupfile = new PclZip($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
				need_free_memory(2097152); //free memory for file list
				for ($i=$WORKING['STEPDONE'];$i<$WORKING['STEPTODO'];$i++) {
					$files[$i][79001]=$filelist[$i]['FILE'];
					$files[$i][79003]=$filelist[$i]['OUTFILE'];
				}
				need_free_memory(11534336); //11MB free memory for zip
				if (0==$zipbackupfile->create($files,PCLZIP_CB_POST_ADD, '_pclzipPostAddCallBack',PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
					trigger_error(__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true),E_USER_ERROR);
				} else {
					$WORKING['STEPDONE']=count($filelist);
					unset($files);
					trigger_error(__('Backup Zip file create done!','backwpup'),E_USER_NOTICE);
				}
			}
		}
	} elseif (strtolower($STATIC['JOB']['fileformart'])==".tar.gz" or strtolower($STATIC['JOB']['fileformart'])==".tar.bz2" or strtolower($STATIC['JOB']['fileformart'])==".tar") { //tar files
		
		if (strtolower($STATIC['JOB']['fileformart'])=='.tar.gz') {
			$tarbackup=gzopen($STATIC['JOB']['backupdir'].$STATIC['backupfile'],'w9');
		} elseif (strtolower($STATIC['JOB']['fileformart'])=='.tar.bz2') {
			$tarbackup=bzopen($STATIC['JOB']['backupdir'].$STATIC['backupfile'],'w');
		} else {
			$tarbackup=fopen($STATIC['JOB']['backupdir'].$STATIC['backupfile'],'w');
		}

		if (!$tarbackup) {
			trigger_error(__('Can not create tar backup file','backwpup'),E_USER_ERROR);
			return;
		} else {
			trigger_error($WORKING['BACKUP_CREATE']['STEP_TRY'].'. '.__('Try to create backup archive file...','backwpup'),E_USER_NOTICE);
		}

		for ($index=$WORKING['STEPDONE'];$index<$WORKING['STEPTODO'];$index++) {
			need_free_memory(2097152); //2MB free memory for tar
			$files=$filelist[$index];
			//check file readable
			if (!is_readable($files['FILE']) or empty($files['FILE'])) {
				trigger_error(__('File not readable:','backwpup').' '.$files['FILE'],E_USER_WARNING);
				$WORKING['STEPDONE']++;
				continue;
			}

			//split filename larger than 100 chars
			if (strlen($files['OUTFILE'])<=100) {
				$filename=$files['OUTFILE'];
				$filenameprefix="";
			} else {
				$filenameofset=strlen($files['OUTFILE'])-100;
				$dividor=strpos($files['OUTFILE'],'/',$filenameofset);
				$filename=substr($files['OUTFILE'],$dividor+1);
				$filenameprefix=substr($files['OUTFILE'],0,$dividor);
				if (strlen($filename)>100)
					trigger_error(__('File name to long to save corectly in tar backup archive:','backwpup').' '.$files['OUTFILE'],E_USER_WARNING);
				if (strlen($filenameprefix)>155)
					trigger_error(__('File path to long to save corectly in tar backup archive:','backwpup').' '.$files['OUTFILE'],E_USER_WARNING);
			}
			//Set file user/group name if linux
			$fileowner="Unknown";
			$filegroup="Unknown";
			if (function_exists('posix_getpwuid')) {
				$info=posix_getpwuid($files['UID']);
				$fileowner=$info['name'];
				$info=posix_getgrgid($files['GID']);
				$filegroup=$info['name'];
			}
			
			// Generate the TAR header for this file
			$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
					  $filename,  									//name of file  100
					  sprintf("%07o", $files['MODE']), 				//file mode  8
					  sprintf("%07o", $files['UID']),				//owner user ID  8
					  sprintf("%07o", $files['GID']),				//owner group ID  8
					  sprintf("%011o", $files['SIZE']),				//length of file in bytes  12
					  sprintf("%011o", $files['MTIME']),			//modify time of file  12
					  "        ",									//checksum for header  8
					  0,											//type of file  0 or null = File, 5=Dir
					  "",											//name of linked file  100
					  "ustar",										//USTAR indicator  6
					  "00",											//USTAR version  2
					  $fileowner,									//owner user name 32
					  $filegroup,									//owner group name 32
					  "",											//device major number 8
					  "",											//device minor number 8
					  $filenameprefix,								//prefix for file name 155
					  "");											//fill block 512K

			// Computes the unsigned Checksum of a file's header
			$checksum = 0;
			for ($i = 0; $i < 512; $i++)
				$checksum += ord(substr($header, $i, 1));
			$checksum = pack("a8", sprintf("%07o", $checksum));

			$header = substr_replace($header, $checksum, 148, 8);

			if (strtolower($STATIC['JOB']['fileformart'])=='.tar.gz') {
				gzwrite($tarbackup, $header);
			} elseif (strtolower($STATIC['JOB']['fileformart'])=='.tar.bz2') {
				bzwrite($tarbackup, $header);
			} else {
				fwrite($tarbackup, $header);
			}

			// read/write files in 512K Blocks
			$fd=fopen($files['FILE'],'rb');
			while(!feof($fd)) {
				$filedata=fread($fd,512);
				if (strlen($filedata)>0) {
					if (strtolower($STATIC['JOB']['fileformart'])=='.tar.gz') {
						gzwrite($tarbackup,pack("a512", $filedata));
					} elseif (strtolower($STATIC['JOB']['fileformart'])=='.tar.bz2') {
						bzwrite($tarbackup,pack("a512", $filedata));
					} else {
						fwrite($tarbackup,pack("a512", $filedata));
					}
				}
			}
			fclose($fd);
			$WORKING['STEPDONE']++;
			update_working_file();
		}

		if (strtolower($STATIC['JOB']['fileformart'])=='.tar.gz') {
			gzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			gzclose($tarbackup);
		} elseif (strtolower($STATIC['JOB']['fileformart'])=='.tar.bz2') {
			bzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			bzclose($tarbackup);
		} else {
			fwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			fclose($tarbackup);
		}
		trigger_error(__('Backup Archive file create done!','backwpup'),E_USER_NOTICE);
	}
	$WORKING['STEPSDONE'][]='BACKUP_CREATE'; //set done
	if ($filesize=filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']))
		trigger_error(sprintf(__('Backup archive file size is %1s','backwpup'),formatBytes($filesize)),E_USER_NOTICE);	
}


function _pclzipPostAddCallBack($p_event, &$p_header) {
	global $WORKING,$STATIC;
	if ($p_header['status'] != 'ok') {
		trigger_error(str_replace('%d',$p_header['status'],__('PCL ZIP Error %d on file:','backwpup')).' '.$p_header['filename'],E_USER_ERROR);
	} 
	$WORKING['STEPDONE']++;
	update_working_file();
}
?>