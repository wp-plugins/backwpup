<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e('BackWPup Job Settings', 'backwpup'); ?></h2>

<form method="post" action="">
<input type="hidden" name="action" value="saveeditjob" />
<input type="hidden" name="jobid" value="<?PHP echo $jobid;?>" />
<?php 
wp_nonce_field('edit-job'); 
$jobs[$jobid]=backwpup_check_job_vars($jobs[$jobid]);
$todo=explode('+',$jobs[$jobid]['type']);
?>

<div id="poststuff" class="metabox-holder has-right-sidebar"> 
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortables">
			
			<div id="jobtype" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Job Type','backwpup'); ?></span></h3>
				<div class="inside">
					<?php
					foreach (backwpup_backup_types() as $type) {
						echo "<input class=\"jobtype-select checkbox\" id=\"jobtype-select-".$type."\" type=\"checkbox\"".checked(true,in_array($type,$todo),false)." name=\"type[]\" value=\"".$type."\"/> ".backwpup_backup_types($type);
					}
					?>
					

				</div>
				<div id="major-publishing-actions"> 
					<div id="delete-action"> 
					<a class="submitdelete deletion" style="color:red" href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=delete&jobid='.$jobid, 'delete-job_'.$jobid); ?>" onclick="if ( confirm('<?PHP echo esc_js(__("You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.","backwpup")); ?>') ) { return true;}return false;"><?php _e('Delete', 'backwpup'); ?></a>
					</div> 
					<div id="publishing-action"> 
						<input type="submit" name="submit" class="button-primary right" accesskey="s" value="<?php _e('Save Changes', 'backwpup'); ?>" />
					</div>
					<div class="clear"></div> 
				</div>
			</div>
		
			<div id="jobschedule" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Job Schedule','backwpup'); ?></span></h3>
				<div class="inside">
					<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['activated'],true); ?> name="activated" /> <?PHP _e('Activate scheduling', 'backwpup'); ?><br />
					<?php 
					_e('Run Every:', 'backwpup');
					echo '<select name="scheduleintervalteimes">';
					for ($i=1;$i<=100;$i++) {
						echo '<option value="'.$i.'"'.selected($i,$jobs[$jobid]['scheduleintervalteimes'],false).'>'.$i.'</option>';
					}
					echo '</select>';
					echo '<select name="scheduleintervaltype">';
					echo '<option value="60"'.selected('60',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Min(s)','backwpup').'</option>';
					echo '<option value="3600"'.selected('3600',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Hour(s)','backwpup').'</option>';
					echo '<option value="86400"'.selected('86400',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Day(s)','backwpup').'</option>';
					echo '</select><br />';

					_e('Start Date/Time:', 'backwpup');echo "<br />";
										
					echo '<select name="scheduleday">';
					for ($i=1;$i<=31;$i++) {
						echo '<option value="'.$i.'"'.selected($i,date_i18n('j',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
					}
					echo '</select>.';
					$month=array('1'=>__('January'),'2'=>__('February'),'3'=>__('March'),'4'=>__('April'),'5'=>__('May'),'6'=>__('June'),'7'=>__('July'),'8'=>__('August'),'9'=>__('September'),'10'=>__('October'),'11'=>__('November'),'12'=>__('December'));
					echo '<select name="schedulemonth">';
					for ($i=1;$i<=12;$i++) {
						echo '<option value="'.$i.'"'.selected($i,date_i18n('n',$jobs[$jobid]['scheduletime']),false).'>'.esc_html($month[$i]).'</option>';
					}
					echo '</select>.';
					echo '<select name="scheduleyear">';
					for ($i=date_i18n('Y')-1;$i<=date_i18n('Y')+3;$i++) {
						echo '<option value="'.$i.'"'.selected($i,date_i18n('Y',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
					}
					echo '</select><br />';

					echo '<select name="schedulehour">';
					for ($i=0;$i<=23;$i++) {
						echo '<option value="'.$i.'"'.selected($i,date_i18n('G',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
					}
					echo '</select>:';
					echo '<select name="scheduleminute">';
					for ($i=0;$i<=59;$i++) {
						$minute=$i;
						if (strlen($minute)<2)
							$minute='0'.$minute;
						echo '<option value="'.$i.'"'.selected($minute,date_i18n('i',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
					}
					echo '</select>';

					?>
				</div>
			</div>		

			<div id="fileformart" class="postbox" <?PHP if (!in_array("FILE",$todo) and !in_array("DB",$todo) and !in_array("WPEXP",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Backup File Format','backwpup'); ?></span></h3>
				<div class="inside">
				<?PHP
				if (function_exists('gzopen') or class_exists('ZipArchive'))
					echo '<input class="radio" type="radio"'.checked('.zip',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".zip" />'.__('ZIP (.zip)','backwpup').'<br />';
				else
					echo '<input class="radio" type="radio"'.checked('.zip',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".zip" disabled="disabled" />'.__('ZIP (.zip)','backwpup').'<br />';
				echo '<input class="radio" type="radio"'.checked('.tar',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".tar" />'.__('TAR (.tar)','backwpup').'<br />';
				if (function_exists('gzopen'))
					echo '<input class="radio" type="radio"'.checked('.tar.gz',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".tar.gz" />'.__('TAR GZIP (.tar.gz)','backwpup').'<br />';
				else
					echo '<input class="radio" type="radio"'.checked('.tar.gz',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".tar.gz" disabled="disabled" />'.__('TAR GZIP (.tar.gz)','backwpup').'<br />';
				if (function_exists('bzopen'))
					echo '<input class="radio" type="radio"'.checked('.tar.bz2',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".tar.bz2" />'.__('TAR BZIP2 (.tar.bz2)','backwpup').'<br />';
				else
					echo '<input class="radio" type="radio"'.checked('.tar.bz2',$jobs[$jobid]['fileformart'],false).' name="fileformart" value=".tar.bz2" disabled="disabled" />'.__('TAR BZIP2 (.tar.bz2)','backwpup').'<br />';
				?>
				</div>
			</div>				

			<div id="logmail" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Send log','backwpup'); ?></span></h3>
				<div class="inside">
					<?PHP _e('E-Mail-Adress:','backwpup'); ?>
					<input name="mailaddresslog" id="mailaddresslog" type="text" value="<?PHP echo $jobs[$jobid]['mailaddresslog'];?>" class="large-text" /><br />
					<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['mailerroronly'],true); ?> name="mailerroronly" /> <?PHP _e('Send only E-Mail on errors.','backwpup'); ?>
				</div>
			</div>	
			
			
		</div>
	</div>
	<div class="has-sidebar" >
		<div id="post-body-content" class="has-sidebar-content">
		
			<div id="titlediv"> 
				<div id="titlewrap">
					<label class="hide-if-no-js" style="visibility:hidden" id="title-prompt-text" for="title"><?PHP _e('Enter Job name here','backwpup'); ?></label>
					<input type="text" name="name" size="30" tabindex="1" value="<?PHP echo $jobs[$jobid]['name'];?>" id="title" autocomplete="off" /> 
				</div> 
			</div> 
		
			<div id="databasejobs" class="postbox" <?PHP if (!in_array("CHECK",$todo) and !in_array("DB",$todo) and !in_array("OPTIMIZE",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Database Jobs','backwpup'); ?></span></h3>
				<div class="inside">
				
					<b><?PHP _e('Database Tabels to Exclude:','backwpup'); ?></b>
					<div id="dbexclude-pop" style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:50%; margin:5px 0px 5px 40px; overflow:auto; padding:0.5em 0.5em;"> 
					<?php
					$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
					foreach ($tables as $table) {
						if ($wpdb->backwpup_logs<>$table) {
							echo '	<input class="checkbox" type="checkbox"'.checked(in_array($table,(array)$jobs[$jobid]['dbexclude']),true,false).' name="dbexclude[]" value="'.$table.'"/> '.$table.'<br />';
						}
					}
					?>
					</div><br />
					<span id="dbshortinsert" <?PHP if (!in_array("DB",$todo)) echo 'style="display:none;"';?>><input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['dbshortinsert'],true,true);?> name="dbshortinsert" value="1"/> <?php _e('Use short INSERTs instat of full (with keys)','backwpup');?><br /></span>
					<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['maintenance'],true,true);?> name="maintenance" value="1"/> <?php _e('Set Blog Maintenance Mode on Database Operations','backwpup');?><br />				
				</div>
			</div>		
		
			<div id="filebackup" class="postbox" <?PHP if (!in_array("FILE",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('File Backup','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('Blog Folders to Backup:','backwpup'); ?></b>
					<div style="width:50%; margin:5px 0px 5px 40px; overflow:auto; padding:0.5em 0.5em;">
						<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backuproot'],true,true);?> name="backuproot" value="1"/> <?php _e('Blog root and WP Files','backwpup');?><br />
						<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupcontent'],true,true);?> name="backupcontent" value="1"/> <?php _e('Blog Content','backwpup');?><br />
						<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupplugins'],true,true);?> name="backupplugins" value="1"/> <?php _e('Blog Plugins','backwpup');?>
					</div><br />
					<b><?PHP _e('Include Folders to Backup:','backwpup'); ?></b><br />
					<?PHP _e('Example:','backwpup'); ?> <?PHP echo str_replace('\\','/',ABSPATH); ?>,...<br />
					<input name="dirinclude" id="dirinclude" type="text" value="<?PHP echo $jobs[$jobid]['dirinclude'];?>" class="large-text" /><br />
					<br />
					<b><?PHP _e('Exclude Files/Folders from Backup:','backwpup'); ?></b><br />
					<?PHP _e('Example:','backwpup'); ?> /logs/,.log,.tmp,/temp/,....<br />
					<input name="fileexclude" id="fileexclude" type="text" value="<?PHP echo $jobs[$jobid]['fileexclude'];?>" class="large-text" /><br />
				</div>
			</div>
			
			<div id="todir" class="postbox" <?PHP if (!in_array("FILE",$todo) and !in_array("DB",$todo) and !in_array("WPEXP",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Backup to Directory','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('Full Path to Folder for Backup Files:','backwpup'); ?></b><br />
					<input name="backupdir" id="backupdir" type="text" value="<?PHP echo $jobs[$jobid]['backupdir'];?>" class="large-text" /><br />
					<?PHP _e('Max. Backup Files in Folder:','backwpup'); ?> <input name="maxbackups" id="maxbackups" type="text" size="3" value="<?PHP echo $jobs[$jobid]['maxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span>
					</div>
			</div>			

			<div id="toftp" class="postbox" <?PHP if (!in_array("FILE",$todo) and !in_array("DB",$todo) and !in_array("WPEXP",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Backup to FTP Server','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('Hostname:','backwpup'); ?></b><br />
					<input name="ftphost" type="text" value="<?PHP echo $jobs[$jobid]['ftphost'];?>" class="large-text" /><br />
					<b><?PHP _e('Username:','backwpup'); ?></b><br />
					<input name="ftpuser" type="text" value="<?PHP echo $jobs[$jobid]['ftpuser'];?>" class="user large-text" /><br />
					<b><?PHP _e('Password:','backwpup'); ?></b><br />
					<input name="ftppass" type="password" value="<?PHP echo base64_decode($jobs[$jobid]['ftppass']);?>" class="password large-text" /><br />
					<b><?PHP _e('Directory on Server:','backwpup'); ?></b><br />
					<input name="ftpdir" type="text" value="<?PHP echo $jobs[$jobid]['ftpdir'];?>" class="large-text" /><br />
					<?PHP if (!is_numeric($jobs[$jobid]['ftpmaxbackups'])) $jobs[$jobid]['ftpmaxbackups']=0; ?>
					<?PHP _e('Max. Backup Files in FTP Folder:','backwpup'); ?> <input name="ftpmaxbackups" type="text" size="3" value="<?PHP echo $jobs[$jobid]['ftpmaxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span><br />
				</div>
			</div>	

			<div id="toamazon" class="postbox" <?PHP if (!in_array("FILE",$todo) and !in_array("DB",$todo) and !in_array("WPEXP",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Backup to Amazon S3','backwpup'); ?></span></h3>
				<div class="inside">
					<?PHP if (!(extension_loaded('curl') or @dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll'))) {
						echo "<b>".__('curl Support required','backwpup')."</b>";
					} else { ?>
						<b><?PHP _e('Access Key ID:','backwpup'); ?></b><br />
						<input id="awsAccessKey" name="awsAccessKey" type="text" value="<?PHP echo $jobs[$jobid]['awsAccessKey'];?>" class="large-text" /><br />
						<b><?PHP _e('Secret Access Key:','backwpup'); ?></b><br />
						<input id="awsSecretKey" name="awsSecretKey" type="text" value="<?PHP echo $jobs[$jobid]['awsSecretKey'];?>" class="large-text" /><br />
						<b><?PHP _e('Bucket:','backwpup'); ?></b><br />
						<input id="awsBucketselected" name="awsBucketselected" type="hidden" value="<?PHP echo $jobs[$jobid]['awsBucket'];?>" />
						<?PHP if (!empty($jobs[$jobid]['awsAccessKey']) and !empty($jobs[$jobid]['awsSecretKey'])) backwpup_get_aws_buckets(array('awsAccessKey'=>$jobs[$jobid]['awsAccessKey'],'awsSecretKey'=>$jobs[$jobid]['awsSecretKey'],'selected'=>$jobs[$jobid]['awsBucket'])); ?>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?PHP _e('Create Bucket:','backwpup'); ?><input name="newawsBucket" type="text" value="" class="text" /> <select name="awsRegion" title="<?php _e('Bucket Region', 'backwpup'); ?>"><option value=""><?php _e('US', 'backwpup'); ?></option><option value="EU"><?php _e('EU', 'backwpup'); ?></option></select><br />
						<b><?PHP _e('Directory in Bucket:','backwpup'); ?></b><br />
						<input name="awsdir" type="text" value="<?PHP echo $jobs[$jobid]['awsdir'];?>" class="large-text" /><br />
						<?PHP if (!is_numeric($jobs[$jobid]['awsmaxbackups'])) $jobs[$jobid]['awsmaxbackups']=0; ?>
						<?PHP _e('Max. Backup Files inn Bucket Folder:','backwpup'); ?><input name="awsmaxbackups" type="text" size="3" value="<?PHP echo $jobs[$jobid]['awsmaxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span><br />
						<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['awsSSL'],true); ?> name="awsSSL" /> <?PHP _e('Use SSL connection.','backwpup'); ?><br />
					<?PHP } ?>
				</div>
			</div>	

			<div id="tomail" class="postbox" <?PHP if (!in_array("FILE",$todo) and !in_array("DB",$todo) and !in_array("WPEXP",$todo)) echo 'style="display:none;"';?>>
				<h3 class="hndle"><span><?PHP _e('Backup to E-Mail','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('E-Mail-Adress:','backwpup'); ?></b><br />
					<input name="mailaddress" id="mailaddress" type="text" value="<?PHP echo $jobs[$jobid]['mailaddress'];?>" class="large-text" /><br />
					<?PHP if (!is_numeric($jobs[$jobid]['mailefilesize'])) $jobs[$jobid]['mailefilesize']=0; ?>
					<?PHP echo __('Max. File Size for sending Backups with mail:','backwpup').'<input name="mailefilesize" type="text" value="'.$jobs[$jobid]['mailefilesize'].'" class="small-text" />MB<br />';?>
				</div>
			</div>				
		</div>
	</div>
</div>

</form>
</div>