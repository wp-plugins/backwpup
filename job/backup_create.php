<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backup_create() {
	if ($_SESSION['WORKING']['ALLFILESIZE']==0)
		return;
		
	if (strtolower($_SESSION['JOB']['fileformart'])==".zip") { //Zip files
		if (!class_exists('ZipArchive')) {  //use php zip lib
			trigger_error(__('Create Backup Zip file...','backwpup'),E_USER_NOTICE);
			$zip = new ZipArchive;
			if ($res=$zip->open($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],ZIPARCHIVE::CREATE) === TRUE) {
				foreach($_SESSION['WORKING']['FILELIST'] as $files) {
					update_working_file();
					if (!$zip->addFile($files['FILE'], $files['OUTFILE'])) 
						trigger_error(__('Can not add File to ZIP file:','backwpup').' '.$files['OUTFILE'],E_USER_ERROR);
				}
				$zip->close();
				trigger_error(__('Backup Zip file create done!','backwpup'),E_USER_NOTICE);
				$_SESSION['WORKING']['BACKUP_CREATE']['DONE']=true; //set done
			} else {
				trigger_error(__('Can not create Backup ZIP file:','backwpup').' '.$res,E_USER_ERROR);
			}
		} else { //use PclZip
			define('PCLZIP_TEMPORARY_DIR', $_SESSION['STATIC']['TEMPDIR']);
			if (!class_exists('PclZip'))
				require_once(dirname(__FILE__).'/../libs/pclzip.lib.php');
			//Create Zip File
			if (is_array($_SESSION['WORKING']['FILELIST'][0])) {
				$i=0;
				foreach($_SESSION['WORKING']['FILELIST'] as $file) { //geneate file list for PCL zip
					$files[$i][79001]=$file['FILE'];
					$files[$i][79003]=$file['OUTFILE'];
					$i++;
				}
				need_free_memory(10485760); //10MB free memory for zip
				trigger_error(__('Create Backup Zip (PclZip) file...','backwpup'),E_USER_NOTICE);
				$zipbackupfile = new PclZip($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
				if (0==$zipbackupfile -> create($files,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
					trigger_error(__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true),E_USER_ERROR);
				} else {
					trigger_error(__('Backup Zip file create done!','backwpup'),E_USER_NOTICE);
				}
			}
		}
		$_SESSION['WORKING']['BACKUP_CREATE']['DONE']=true; //set done
		if ($filesize=filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']))
			trigger_error(sprintf(__('Backup Archive File size is %1s','backwpup'),formatBytes($filesize)),E_USER_NOTICE);	
	} elseif (strtolower($_SESSION['JOB']['fileformart'])==".tar.gz" or strtolower($_SESSION['JOB']['fileformart'])==".tar.bz2" or strtolower($_SESSION['JOB']['fileformart'])==".tar") { //tar files
		
		if (strtolower($_SESSION['JOB']['fileformart'])=='.tar.gz') {
			$tarbackup=gzopen($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],'w9');
		} elseif (strtolower($_SESSION['JOB']['fileformart'])=='.tar.bz2') {
			$tarbackup=bzopen($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],'w');
		} else {
			$tarbackup=fopen($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],'w');
		}

		if (!$tarbackup) {
			trigger_error(__('Can not create TAR Backup file','backwpup'),E_USER_ERROR);
			return;
		} else {
			trigger_error(__('Create Backup Archive file...','backwpup'),E_USER_NOTICE);
		}

		need_free_memory(1048576); //1MB free memory for tar

		foreach($_SESSION['WORKING']['FILELIST'] as $files) {
			update_working_file();
				
			//check file exists
			if (!is_readable($files['FILE'])) {
				trigger_error(__('File not readable:','backwpup').' '.$files['FILE'],E_USER_WARNING);
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
					trigger_error(__('File Name to Long to save corectly in TAR Backup Archive:','backwpup').' '.$files['OUTFILE'],E_USER_WARNING);
				if (strlen($filenameprefix)>155)
					trigger_error(__('File Path to Long to save corectly in TAR Backup Archive:','backwpup').' '.$files['OUTFILE'],E_USER_WARNING);
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
					  sprintf("%07o", $files['MODE']), 	//file mode  8
					  sprintf("%07o", $files['UID']),	//owner user ID  8
					  sprintf("%07o", $files['GID']),	//owner group ID  8
					  sprintf("%011o", $files['SIZE']),	//length of file in bytes  12
					  sprintf("%011o", $files['MTIME']),	//modify time of file  12
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

			if (strtolower($_SESSION['JOB']['fileformart'])=='.tar.gz') {
				gzwrite($tarbackup, $header);
			} elseif (strtolower($_SESSION['JOB']['fileformart'])=='.tar.bz2') {
				bzwrite($tarbackup, $header);
			} else {
				fwrite($tarbackup, $header);
			}

			// read/write files in 512K Blocks
			$fd=fopen($files['FILE'],'rb');
			while(!feof($fd)) {
				$filedata=fread($fd,512);
				if (strlen($filedata)>0) {
					if (strtolower($_SESSION['JOB']['fileformart'])=='.tar.gz') {
						gzwrite($tarbackup,pack("a512", $filedata));
					} elseif (strtolower($_SESSION['JOB']['fileformart'])=='.tar.bz2') {
						bzwrite($tarbackup,pack("a512", $filedata));
					} else {
						fwrite($tarbackup,pack("a512", $filedata));
					}
				}
			}
			fclose($fd);
		}

		if (strtolower($_SESSION['JOB']['fileformart'])=='.tar.gz') {
			gzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			gzclose($tarbackup);
		} elseif (strtolower($_SESSION['JOB']['fileformart'])=='.tar.bz2') {
			bzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			bzclose($tarbackup);
		} else {
			fwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			fclose($tarbackup);
		}

		trigger_error(__('Backup Archive file create done!','backwpup'),E_USER_NOTICE);
		$_SESSION['WORKING']['BACKUP_CREATE']['DONE']=true; //set done
		if ($filesize=filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']))
			trigger_error(sprintf(__('Backup Archive File size is %1s','backwpup'),formatBytes($filesize)),E_USER_NOTICE);	
	}
}
?>