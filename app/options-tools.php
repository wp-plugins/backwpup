<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Tools", "backwpup"); ?></h2>

<?php backwpup_option_submenues(); ?>

<div class="clear"></div> 

<form enctype="multipart/form-data" method="post" action="">
<input type="hidden" name="subpage" value="tools" />
<input type="hidden" name="page" value="BackWPup" />
<?php  wp_nonce_field('backwpup-tools'); ?>

<div id="poststuff" class="metabox-holder has-right-sidebar"> 
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortables">
		
		</div>
	</div>
	<div class="has-sidebar" >
		<div id="post-body-content" class="has-sidebar-content">
				
			<div id="dbrestore" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Database restore','backwpup'); ?></span></h3>
				<div class="inside">
					<?PHP
					if ($_POST['dbrestore']==__('Restore', 'backwpup') and is_file($_POST['sqlfile'])) {
						$sqlfile=$_POST['sqlfile'];
						require('tools/db_restore.php');
					} else {
						if ( $dir = @opendir(ABSPATH)) {
							$sqlfile="";
							while (($file = readdir( $dir ) ) !== false ) {
								if (strtolower(substr($file,-4))==".sql") {
									$sqlfile=$file;
									break;
								}	
							}
							@closedir( $dir );
						}
						if (!empty($sqlfile)) {
							echo __('SQL File to restore:','backwpup').' '.trailingslashit(ABSPATH).$sqlfile."<br />";
							?>
							<input type="hidden" name="sqlfile" id="sqlfile" value="<?PHP echo trailingslashit(ABSPATH).$sqlfile;?>" />
							<input type="submit" name="dbrestore" class="button-primary" value="<?php _e('Restore', 'backwpup'); ?>" />
							<?PHP
						} else {
							echo __('Copy SQL file to blog root folder to use for a restoration.', 'backwpup')."<br />";
						}
					}
					?>
				</div>
			</div>
			
			<div id="import" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Import Jobs settings','backwpup'); ?></span></h3>
				<div class="inside">
					<?php _e('Select File to import:', 'backwpup'); ?> <input name="importfile" type="file" />
					<input type="submit" name="upload" value="<?php _e('Upload', 'backwpup'); ?>" /><br />
					<?PHP
					if (is_uploaded_file($_FILES['importfile']['tmp_name']) and $_POST['upload']==__('Upload', 'backwpup')) {
						_e('Select Jobs to Import:', 'backwpup'); echo "<br />";
						$import=file_get_contents($_FILES['importfile']['tmp_name']);
						$oldjobs=get_option('backwpup_jobs');
						foreach ( unserialize($import) as $jobid => $jobvalue ) {
							echo $jobid.". ".$jobvalue['name']." <select name=\"importtype[".$jobid."]\" title=\"".__('Import Type', 'backwpup')."\"><option value=\"not\">".__('No Import', 'backwpup')."</option>";
							if (is_array($oldjobs[$jobid]))
								echo "<option value=\"over\">".__('Overwrite', 'backwpup')."</option><option value=\"append\">".__('Append', 'backwpup')."</option>"; 
							else
								echo "<option value=\"over\">".__('Import', 'backwpup')."</option>";
							echo "</select><br />";
						}
						echo "<input type=\"hidden\" name=\"importfile\" value=\"".urlencode($import)."\" />";
						echo "<input type=\"submit\" name=\"import\" class=\"button-primary\" value=\"".__('Import', 'backwpup')."\" />";
					}
					if ($_POST['import']==__('Import', 'backwpup') and !empty($_POST['importfile'])) {
						$oldjobs=get_option('backwpup_jobs');
						$import=unserialize(urldecode($_POST['importfile']));
						foreach ( $_POST['importtype'] as $id => $type ) {
							if ($type=='over') {
								unset($oldjobs[$id]);
								$oldjobs[$id]=$import[$id];
								$oldjobs[$id]['activated']=false;
								$oldjobs[$id]['cronnextrun']='';
								$oldjobs[$id]['starttime']='';
								$oldjobs[$id]['logfile']='';
								$oldjobs[$id]['lastlogfile']='';
								$oldjobs[$id]['lastrun']='';
								$oldjobs[$id]['lastruntime']='';
								$oldjobs[$id]['lastbackupdownloadurl']='';								
							} elseif ($type=='append') {
								if (is_array($oldjobs)) { //generate a new id for new job
									foreach ($oldjobs as $jobkey => $jobvalue) {
										if ($jobkey>$heighestid) $heighestid=$jobkey;
									}
									$jobid=$heighestid+1;
								} else {
									$jobid=1;
								}
								$oldjobs[$jobid]=$import[$id];
								$oldjobs[$jobid]['activated']=false;
								$oldjobs[$jobid]['cronnextrun']='';
								$oldjobs[$jobid]['starttime']='';
								$oldjobs[$jobid]['logfile']='';
								$oldjobs[$jobid]['lastlogfile']='';
								$oldjobs[$jobid]['lastrun']='';
								$oldjobs[$jobid]['lastruntime']='';
								$oldjobs[$jobid]['lastbackupdownloadurl']='';
							} 
						}
						update_option('backwpup_jobs',$oldjobs);
						_e('Jobs imported!', 'backwpup');
					}
					?>
				</div>
			</div>
			
		</div>
	</div>
</div>
</form>
</div>
