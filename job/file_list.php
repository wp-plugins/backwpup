<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}


function file_list() {
	//Make filelist
	trigger_error(__($_SESSION['WORKING']['FILE_LIST']['STEP_TRY'].'. '.'Try for make a list of files to Backup ....','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=2;
	
	if ($_SESSION['WORKING']['STEPDONE']==0) {
		//Check free memory for file list
		need_free_memory(2097152); //2MB free memory for filelist
		//empty filelist
		$_SESSION['WORKING']['TEMPFILELIST']=array();
		//exlude of job
		$_SESSION['WORKING']['FILEEXCLUDES']=explode(',',trim($_SESSION['JOB']['fileexclude']));
		$_SESSION['WORKING']['FILEEXCLUDES']=array_unique($_SESSION['WORKING']['FILEEXCLUDES']);

		//File list for blog folders
		if ($_SESSION['JOB']['backuproot'])
			_file_list($_SESSION['WP']['ABSPATH'],100,array_merge($_SESSION['JOB']['backuprootexcludedirs'],_get_exclude_dirs($_SESSION['WP']['ABSPATH'])));
		if ($_SESSION['JOB']['backupcontent'])
			_file_list($_SESSION['WP']['WP_CONTENT_DIR'],100,array_merge($_SESSION['JOB']['backupcontentexcludedirs'],_get_exclude_dirs($_SESSION['WP']['WP_CONTENT_DIR'])));
		if ($_SESSION['JOB']['backupplugins'])
			_file_list($_SESSION['WP']['WP_PLUGIN_DIR'],100,array_merge($_SESSION['JOB']['backuppluginsexcludedirs'],_get_exclude_dirs($_SESSION['WP']['WP_PLUGIN_DIR'])));
		if ($_SESSION['JOB']['backupthemes'])
			_file_list($_SESSION['WP']['WP_THEMES_DIR'],100,array_merge($_SESSION['JOB']['backupthemesexcludedirs'],_get_exclude_dirs($_SESSION['WP']['WP_THEMES_DIR'])));
		if ($_SESSION['JOB']['backupuploads'])
			_file_list($_SESSION['WP']['WP_UPLOAD_DIR'],100,array_merge($_SESSION['JOB']['backupuploadsexcludedirs'],_get_exclude_dirs($_SESSION['WP']['WP_UPLOAD_DIR'])));

		//include dirs
		if (!empty($_SESSION['JOB']['dirinclude'])) {
			$dirinclude=explode(',',$_SESSION['JOB']['dirinclude']);
			$dirinclude=array_unique($dirinclude);
			//Crate file list for includes
			foreach($dirinclude as $dirincludevalue) {
				if (is_dir($dirincludevalue))
					_file_list($dirincludevalue,100);
			}
		}
		$_SESSION['WORKING']['TEMPFILELIST']=array_unique($_SESSION['WORKING']['TEMPFILELIST']); //all files only one time in list
		sort($_SESSION['WORKING']['TEMPFILELIST']);
		$_SESSION['WORKING']['FILELISTDONE']=0; //Set number of dine files
		$_SESSION['WORKING']['STEPDONE']=1; //Step done
		update_working_file();
	} 
	if ($_SESSION['WORKING']['STEPDONE']==1) {
		//Check abs path
		if ($_SESSION['WP']['ABSPATH']=='/' or $_SESSION['WP']['ABSPATH']=='')
			$removepath='';
		else
			$removepath=$_SESSION['WP']['ABSPATH'];
		//make file list
		for ($i=$_SESSION['WORKING']['FILELISTDONE']; $i<count($_SESSION['WORKING']['TEMPFILELIST']); $i++) {
			$filestat=stat($_SESSION['WORKING']['TEMPFILELIST'][$i]);
			$_SESSION['WORKING']['ALLFILESIZE']+=$filestat['size'];
			$_SESSION['WORKING']['FILELIST'][]=array('FILE'=>$_SESSION['WORKING']['TEMPFILELIST'][$i],'OUTFILE'=>str_replace($removepath,'',$_SESSION['WORKING']['TEMPFILELIST'][$i]),'SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode']);
			$_SESSION['WORKING']['FILELISTDONE']++;
		}
		
		$_SESSION['WORKING']['STEPDONE']=2;
		$_SESSION['WORKING']['STEPSDONE'][]='FILE_LIST'; //set done
		unset($_SESSION['WORKING']['FILELISTDONE']); //clean up
		unset($_SESSION['WORKING']['TEMPFILELIST']);
		
		if (!is_array($_SESSION['WORKING']['FILELIST'][0])) {
			trigger_error(__('No files to Backup','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(__('Files to Backup:','backwpup').' '.count($_SESSION['WORKING']['FILELIST']),E_USER_NOTICE);
			trigger_error(__('Size of all Files:','backwpup').' '.formatBytes($_SESSION['WORKING']['ALLFILESIZE']),E_USER_NOTICE);
		}
	}
}

function _file_list( $folder = '', $levels = 100, $excludedirs=array()) {
	if( empty($folder) )
		return false;
	if( ! $levels )
		return false;
	$folder=rtrim($folder,'/').'/';
	if ( $dir = @opendir( $folder ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..','.svn') ) )
				continue;
			foreach ($_SESSION['WORKING']['FILEEXCLUDES'] as $exclusion) { //exclude dirs and files
				$exclusion=trim($exclusion);
				if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
					continue 2;
			}
			if (in_array(rtrim($folder.$file,'/').'/',$excludedirs) and is_dir( $folder.$file ))
				continue;
			if ( !is_readable($folder.$file)) {
				trigger_error(__('File or folder is not readable:','backwpup').' '.$folder.$file,E_USER_WARNING);
			} elseif ( is_link($folder.$file) ) {
				trigger_error(__('Links not followed:','backwpup').' '.$folder.$file,E_USER_WARNING);
			} elseif ( is_dir( $folder.$file )) {
				_file_list( rtrim($folder.$file,'/'), $levels - 1,$excludedirs);
			} elseif ( is_file( $folder.$file ) or is_executable($folder.$file)) { //add file to filelist
				$_SESSION['WORKING']['TEMPFILELIST'][]=$folder.$file;
			} else {
				trigger_error(__('Is not a file or directory:','backwpup').' '.$folder.$file,E_USER_WARNING);
			}
			
		}
		@closedir( $dir );
	}
}

function _get_exclude_dirs($folder) {
	$excludedir=array();
	$excludedir[]=get_working_dir(); //exclude working dir
	$excludedir[]=$_SESSION['CFG']['dirlogs'];
	if (false !== strpos($_SESSION['WP']['ABSPATH'],$folder) and $_SESSION['WP']['ABSPATH']!=$folder)
		$excludedir[]=$_SESSION['WP']['ABSPATH'];
	if (false !== strpos($_SESSION['WP']['WP_CONTENT_DIR'],$folder) and $_SESSION['WP']['WP_CONTENT_DIR']!=$folder)
		$excludedir[]=$_SESSION['WP']['WP_CONTENT_DIR'];
	if (false !== strpos($_SESSION['WP']['WP_PLUGIN_DIR'],$folder) and $_SESSION['WP']['WP_PLUGIN_DIR']!=$folder)
		$excludedir[]=$_SESSION['WP']['WP_PLUGIN_DIR'];
	if (false !== strpos($_SESSION['WP']['WP_THEMES_DIR'],$folder) and $_SESSION['WP']['WP_THEMES_DIR']!=$folder)
		$excludedir[]=$_SESSION['WP']['WP_THEMES_DIR'];
	if (false !== strpos($_SESSION['WP']['WP_UPLOAD_DIR'],$folder) and $_SESSION['WP']['WP_UPLOAD_DIR']!=$folder)
		$excludedir[]=$_SESSION['WP']['WP_UPLOAD_DIR'];
	//Exclude Backup dirs
	foreach((array)get_option('backwpup_jobs') as $jobsvalue) {
		if (!empty($jobsvalue['backupdir']) and $jobsvalue['backupdir']!='/')
			$excludedir[]=$jobsvalue['backupdir'];
	}
	return $excludedir;
}

?>