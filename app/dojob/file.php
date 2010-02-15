<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

global $backwpup_exclude, $backwpup_jobs;
$backwpup_jobs=$jobs[$jobid];

	backwpup_joblog($logtime,__('Get files to backup...','backwpup'));		
	// helper function to scan dirs recursive
	function backwpup_list_files( $folder = '', $levels = 100 ) {  
		global $backwpup_exclude ,$backwpup_allfilezise, $backwpup_jobs, $logtime, $backwpup_fielstobackup;
		if( empty($folder) )
			return false;
		if( ! $levels )
			return false;
		if ( $dir = @opendir( $folder ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if ( in_array($file, array('.', '..','.svn') ) )
					continue;
				foreach ($backwpup_exclude as $exclusion) { //exclude dirs and files
					if (false !== stripos($folder.'/'.$file,str_replace('\\','/',$exclusion)))
						continue 2;
				}
				if (!$backwpup_jobs['backuproot'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',ABSPATH)) and false === stripos($folder.'/'.$file,str_replace('\\','/',WP_CONTENT_DIR)) and !is_dir($folder.'/'.$file))
					continue;
				if (!$backwpup_jobs['backupcontent'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',WP_CONTENT_DIR)) and false === stripos($folder.'/'.$file,str_replace('\\','/',WP_PLUGIN_DIR)) and !is_dir($folder.'/'.$file))
					continue;
				if (!$backwpup_jobs['backupplugins'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',WP_PLUGIN_DIR)))
					continue;
				if ( is_dir( $folder . '/' . $file ) ) {
					backwpup_list_files( $folder . '/' . $file, $levels - 1);
				} elseif (is_file( $folder . '/' . $file )) {
					if (is_readable($folder . '/' . $file)) {
						$backwpup_fielstobackup[]=array(PCLZIP_ATT_FILE_NAME=>$folder.'/' .$file,PCLZIP_ATT_FILE_NEW_FULL_NAME=>str_replace(str_replace('\\','/',trailingslashit(ABSPATH)),'',$folder.'/') . $file);
						$filezise=filesize($folder . '/' . $file);
						$backwpup_allfilezise=$backwpup_allfilezise+$filezise;
						backwpup_joblog($logtime,__('Add File to Backup:','backwpup').' '.$folder . '/' . $file.' '.backwpup_formatBytes($filezise));
					} else {
						backwpup_joblog($logtime,__('WARNING:','backwpup').' '.__('Can not read file:','backwpup').' '.$folder . '/' . $file);
					}
				} else {
					backwpup_joblog($logtime,__('WARNING:','backwpup').' '.__('Is not a file or directory:','backwpup').' '.$folder . '/' . $file);
				}
			}
		}
		@closedir( $dir );
	}
	
	//Make filelist
	$backwpup_exclude=array(); $dirinclude=array();
	
	if (!empty($jobs[$jobid]['fileexclude'])) 
		$backwpup_exclude=split(',',$jobs[$jobid]['fileexclude']);
	//Exclude Temp dir
	$backwpup_exclude[]=get_temp_dir().'backwpup';
	//Exclude Backup dirs
	foreach($jobs as $jobsvale) {
		$backwpup_exclude[]=$jobsvale['backupdir'];
	}
	$backwpup_exclude=array_unique($backwpup_exclude);
	
	//include dirs
	if (!empty($jobs[$jobid]['dirinclude'])) 
		$dirinclude=split(',',str_replace('\\','/',$jobs[$jobid]['dirinclude']));
		
	if ($jobs[$jobid]['backuproot']) //Include extra path
		$dirinclude[]=ABSPATH;
	if ($jobs[$jobid]['backupcontent'] and ((strtolower(str_replace('\\','/',substr(WP_CONTENT_DIR,0,strlen(ABSPATH))))!=strtolower(str_replace('\\','/',ABSPATH)) and $jobs[$jobid]['backuproot']) or !$jobs[$jobid]['backuproot']))
		$dirinclude[]=WP_CONTENT_DIR;
	if ($jobs[$jobid]['backupplugins'] and ((strtolower(str_replace('\\','/',substr(WP_PLUGIN_DIR,0,strlen(ABSPATH))))!=strtolower(str_replace('\\','/',ABSPATH)) and $jobs[$jobid]['backuproot']) or !$jobs[$jobid]['backuproot']) and  ((strtolower(str_replace('\\','/',substr(WP_PLUGIN_DIR,0,strlen(WP_CONTENT_DIR))))!=strtolower(str_replace('\\','/',WP_CONTENT_DIR)) and $jobs[$jobid]['backupcontent']) or !$jobs[$jobid]['backupcontent']))
		$dirinclude[]=WP_PLUGIN_DIR;	
	$dirinclude=array_unique($dirinclude);
	//Crate file list
	if (is_array($dirinclude)) {
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue)) 
				backwpup_list_files(untrailingslashit(str_replace('\\','/',$dirincludevalue)));
		}
	}	
?>