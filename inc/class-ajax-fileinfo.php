<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
/**
 * @property mixed classobj
 */
class BackWPup_Ajax_Fileinfo {

	static private $classobj = NULL;
	private $countfolder=0;
	private $countfiles=0;
	private $countfilesize=0;
	private $jobmain=0;

	/**
	 * Handler for the action 'init'. Instantiates this class.
	 *
	 * @access  public
	 * @return  object $classobj
	 */
	public static function get_object() {
		if ( NULL === self :: $classobj )
			self :: $classobj = new self;
		return self :: $classobj;
	}

	/**
	 *
	 * helper functions for detecting file size
	 *
	 * @param string $folder
	 * @param int $levels
	 * @param array $excludes
	 * @param array $excludedirs
	 * @param bool $nothumbs
	 */
	protected function calc_file_size_file_list_folder( $folder = '', $levels = 100, $excludes=array(),$excludedirs=array(),$nothumbs=false) {
		$this->countfolder++;
		if ( !empty($folder) && $levels && $dir = @opendir( $folder )) {
			while (($file = readdir( $dir ) ) !== false ) {
				if ( in_array($file, array('.', '..') ) )
					continue;
				foreach ($excludes as $exclusion) { //exclude dirs and files
					if (false !== stripos($folder.$file,$exclusion) && !empty($exclusion) && $exclusion!='/')
						continue 2;
				}
				if ($nothumbs && strpos($folder,BackWPup_File::get_upload_dir()) !== false &&  preg_match("/\-[0-9]{2,4}x[0-9]{2,4}\.(jpg|png|gif)$/i",$file))
					continue;
				if ( @is_dir( $folder.$file )) {
					if (!in_array(trailingslashit($folder.$file),$excludedirs))
						$this->calc_file_size_file_list_folder( trailingslashit($folder.$file), $levels - 1, $excludes,$excludedirs,$nothumbs);
				} elseif ((@is_file( $folder.$file ) || @is_executable($folder.$file)) && @is_readable($folder.$file)) {
					$this->countfiles++;
					$this->countfilesize=$this->countfilesize+filesize($folder.$file);
				}
			}
			@closedir( $dir );
		}
	}

	//helper functions for detecting file size
	protected function calc_file_size() {
		//Exclude Files
		$backwpup_exclude=explode(',',trim(backwpup_get_option($this->jobmain,'fileexclude')));
		$backwpup_exclude=array_unique($backwpup_exclude);

		//File list for blog folders
		if (backwpup_get_option($this->jobmain,'backuproot'))
			$this->calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',ABSPATH)),100,$backwpup_exclude,array_merge(backwpup_get_option($this->jobmain,'backuprootexcludedirs'),BackWPup_File::get_exclude_wp_dirs(ABSPATH)));
		if (backwpup_get_option($this->jobmain,'backupcontent'))
			$this->calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),100,$backwpup_exclude,array_merge(backwpup_get_option($this->jobmain,'backupcontentexcludedirs'),BackWPup_File::get_exclude_wp_dirs(WP_CONTENT_DIR)));
		if (backwpup_get_option($this->jobmain,'backupplugins'))
			$this->calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),100,$backwpup_exclude,array_merge(backwpup_get_option($this->jobmain,'backuppluginsexcludedirs'),BackWPup_File::get_exclude_wp_dirs(WP_PLUGIN_DIR)));
		if (backwpup_get_option($this->jobmain,'backupthemes'))
			$this->calc_file_size_file_list_folder(trailingslashit(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)).'themes'),100,$backwpup_exclude,array_merge(backwpup_get_option($this->jobmain,'backupthemesexcludedirs',BackWPup_File::get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR).'themes'))));
		if (backwpup_get_option($this->jobmain,'backupuploads'))
			$this->calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',BackWPup_File::get_upload_dir())),100,$backwpup_exclude,array_merge(backwpup_get_option($this->jobmain,'backupuploadsexcludedirs'),BackWPup_File::get_exclude_wp_dirs(BackWPup_File::get_upload_dir())),backwpup_get_option($this->jobmain,'backupexcludethumbs'));

		//include dirs
		if (backwpup_get_option($this->jobmain,'dirinclude')) {
			$dirinclude=explode(',',backwpup_get_option($this->jobmain,'dirinclude'));
			$dirinclude=array_unique($dirinclude);
			//Crate file list for includes
			foreach($dirinclude as $dirincludevalue) {
				if (is_dir($dirincludevalue))
					$this->calc_file_size_file_list_folder(trailingslashit($dirincludevalue),100,$backwpup_exclude);
			}
		}

		//add extra files if selected
		if (backwpup_get_option($this->jobmain,'backupspecialfiles')) {
			if ( file_exists( ABSPATH . 'wp-config.php') && !backwpup_get_option($this->jobmain,'backuproot')) {
				$this->countfilesize=$this->countfilesize+filesize(ABSPATH . 'wp-config.php');
				$this->countfiles++;
			} elseif ( file_exists( dirname(ABSPATH) . '/wp-config.php' ) && ! file_exists( dirname(ABSPATH) . '/wp-settings.php' ) ) {
				$this->countfilesize=$this->countfilesize+filesize(dirname(ABSPATH) . '/wp-config.php');
				$this->countfiles++;
			}
			if ( file_exists( ABSPATH . '.htaccess') && !backwpup_get_option($this->jobmain,'backuproot')) {
				$this->countfilesize=$this->countfilesize+filesize(ABSPATH . '.htaccess');
				$this->countfiles++;
			}
			if ( file_exists( ABSPATH . '.htpasswd') && !backwpup_get_option($this->jobmain,'backuproot')) {
				$this->countfilesize=$this->countfilesize+filesize(ABSPATH . '.htpasswd');
				$this->countfiles++;
			}
			if ( file_exists( ABSPATH . 'robots.txt') && !backwpup_get_option($this->jobmain,'backuproot')) {
				$this->countfilesize=$this->countfilesize+filesize(ABSPATH . 'robots.txt');
				$this->countfiles++;
			}
			if ( file_exists( ABSPATH . 'favicon.ico') && !backwpup_get_option($this->jobmain,'backuproot')) {
				$this->countfilesize=$this->countfilesize+filesize(ABSPATH . 'favicon.ico');
				$this->countfiles++;
			}
		}
	}

	/**
	 * ajax show file info in div for jobs
	 */
	public function __construct() {
		check_ajax_referer('backwpup_ajax_nonce');
		$mode=filter_input(INPUT_POST,'mode',FILTER_SANITIZE_STRING);
		$jobid=filter_input(INPUT_POST,'jobid',FILTER_SANITIZE_NUMBER_INT);
		$this->jobmain='job_'.$jobid;
		$this->calc_file_size();
		echo sprintf(__("Files Size: %s","backwpup"),size_format($this->countfilesize),2)."<br />";
		if ( 'excerpt' == $mode ) {
			echo sprintf(__("Folder count: %d","backwpup"),$this->countfolder)."<br />";
			echo sprintf(__("Files count: %d","backwpup"),$this->countfiles)."<br />";
		}
		set_transient('backwpup_file_info_'.$jobid,array('size'=>$this->countfilesize,'files'=>$this->countfiles,'folder'=>$this->countfolder),60*15);
		die();
	}
}
