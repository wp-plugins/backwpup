<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

global $wpdb,$screen_layout_columns;
if (!empty($_REQUEST['jobid']))
	check_admin_referer('edit-job');

//may be needed to ensure that a special box is always available
add_meta_box('backwpup_jobedit_save', __('Job Type','backwpup'), array('BackWPup_editjob_metaboxes','save'), get_current_screen()->id, 'side', 'high');
add_meta_box('backwpup_jobedit_schedule', __('Job Schedule','backwpup'), array('BackWPup_editjob_metaboxes','schedule'), get_current_screen()->id, 'side', 'core');

//generate jobid if not exists
if (empty($_REQUEST['jobid'])) {
	$_REQUEST['jobid']=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC LIMIT 1",0,0);
	$_REQUEST['jobid']++;
}
$main='job_'.(int)$_REQUEST['jobid'];
//backwpup_get_option($main,'');
//set extra vars
$dests=explode(',',strtoupper(BACKWPUP_DESTS));
?>
<div class="wrap">
<?php 
screen_icon(); 
echo "<h2>".esc_html( __('BackWPup Job Settings', 'backwpup'))."&nbsp;<a href=\"".wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob', 'edit-job')."\" class=\"add-new-h2\">".esc_html__('Add New','backwpup')."</a></h2>";
?>

<?php if (isset($backwpup_message) and !empty($backwpup_message)) : ?>
<div id="message" class="updated"><p><?php echo $backwpup_message; ?></p></div>
<?php endif; ?>

<form name="editjob" id="editjob" method="post" action="<?PHP echo backwpup_admin_url('admin.php').'?page=backwpupeditjob';?>">
<input type="hidden" name="jobid" value="<?PHP echo (int)$_REQUEST['jobid'];?>" />
<?php wp_nonce_field('edit-job'); ?>
<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
<?php wp_nonce_field('backwpupeditjob_ajax_nonce', 'backwpupeditjobajaxnonce', false ); ?>
<div id="poststuff" class="metabox-holder<?php echo 1 != $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
	<div id="side-info-column" class="inner-sidebar">
	<?php
	$side_meta_boxes = do_meta_boxes(get_current_screen()->id, 'side', $main);
	?>
	</div>
	
	<div id="post-body">
		<div id="post-body-content">

			<div id="titlediv">
				<div id="titlewrap">
					<label class="hide-if-no-js" style="visibility:hidden" id="title-prompt-text" for="title"><?PHP _e('Enter Job name here','backwpup'); ?></label>
					<input type="text" name="name" size="30" tabindex="1" value="<?PHP echo backwpup_get_option($main,'name');?>" id="title" autocomplete="off" />
				</div>
			</div>

			<div class="inside">
				<div>
					<?PHP
					if (backwpup_get_option('cfg','jobrunauthkey')) {
						echo '<strong>'. __('External start link:','backwpup').'</strong> ';
						$url=backwpup_jobrun_url('runext',backwpup_get_option($main,'jobid'),false);
						echo '<span>'.$url['url'].'</span><br />';
					}
					echo '<strong>'. __('Commandline start:','backwpup').'</strong> ';
					$abspath='';
					if (WP_PLUGIN_DIR==ABSPATH.'/wp-content/plugins')
						$abspath='-abspath='.str_replace('\\','/',ABSPATH);
					echo "<span>".sprintf('php %1$s -jobid=%2$d %3$s',realpath(dirname(__FILE__).'/../job.php'),backwpup_get_option($main,'jobid'),$abspath)."</span><br />";
					?>
				</div>
			</div>

			<div id="databasejobs" class="stuffbox"<?PHP if (!in_array("OPTIMIZE",backwpup_get_option($main,'type')) and !in_array("DB",backwpup_get_option($main,'type')) and !in_array("CHECK",backwpup_get_option($main,'type'))) echo ' style="display:none;"';?>>
				<h3><label for="dbtables"><?PHP _e('Database Jobs','backwpup'); ?></label></h3>
				<div class="inside">
					<div>
					<b><?PHP _e('Database tables for use:','backwpup'); ?></b>
						<div id="dbtables">
						<?php
						$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
						foreach ($tables as $table) {
							echo '	<input class="checkbox" type="checkbox"'.checked(!in_array($table,backwpup_get_option($main,'dbexclude')),true,false).' name="jobtabs[]" value="'.rawurlencode($table).'"/> '.$table.'<br />';
						}
						?>
						</div>
					</div>
					<span id="dbdump"<?PHP if (!in_array("DB",backwpup_get_option($main,'type'))) echo ' style="display:none;"';?>>
					<strong><?php _e('Filename for Dump:','backwpup');?></strong> <input class="long-text" type="text" name="dbdumpfile" value="<?php echo backwpup_get_option($main,'dbdumpfile');?>"/>.sql
					<br /><strong><?php _e('Copmpression for dump:','backwpup');?></strong>
					<?PHP
					echo ' <input class="radio" type="radio"'.checked('',backwpup_get_option($main,'dbdumpfilecompression'),false).' name="dbdumpfilecompression" value="" />'.__('none','backwpup');
					if (function_exists('gzopen'))
						echo ' <input class="radio" type="radio"'.checked('gz',backwpup_get_option($main,'dbdumpfilecompression'),false).' name="dbdumpfilecompression" value="gz" />'.__('GZip','backwpup');
					else
						echo ' <input class="radio" type="radio"'.checked('gz',backwpup_get_option($main,'dbdumpfilecompression'),false).' name="dbdumpfilecompression" value="gz" disabled="disabled" />'.__('GZip','backwpup');
					if (function_exists('bzopen'))
						echo ' <input class="radio" type="radio"'.checked('bz2',backwpup_get_option($main,'dbdumpfilecompression'),false).' name="dbdumpfilecompression" value="bz2" />'.__('BZip2','backwpup');
					else
						echo ' <input class="radio" type="radio"'.checked('bz2',backwpup_get_option($main,'dbdumpfilecompression'),false).' name="dbdumpfilecompression" value="bz2" disabled="disabled" />'.__('BZip2','backwpup');
					?>
					</span><br />
					<input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'maintenance'),true,true);?> name="maintenance" value="1"/> <?php _e('Set Blog Maintenance Mode on Database Operations','backwpup');?><br />
				</div>
			</div>

			<div id="wpexport" class="stuffbox"<?PHP if (!in_array("WPEXP",backwpup_get_option($main,'type'))) echo ' style="display:none;"';?>>
				<h3><label for="dbtables"><?PHP _e('Wordpress Export','backwpup'); ?></label></h3>
				<div class="inside">
					<strong><?php _e('Filename for Export:','backwpup');?></strong> <input class="long-text" type="text" name="wpexportfile" value="<?php echo backwpup_get_option($main,'wpexportfile');?>"/>.xml
					<br /><strong><?php _e('Copmpression for Export:','backwpup');?></strong>
					<?PHP
					echo ' <input class="radio" type="radio"'.checked('',backwpup_get_option($main,'wpexportfilecompression'),false).' name="wpexportfilecompression" value="" />'.__('none','backwpup');
					if (function_exists('gzopen'))
						echo ' <input class="radio" type="radio"'.checked('gz',backwpup_get_option($main,'wpexportfilecompression'),false).' name="wpexportfilecompression" value="gz" />'.__('GZip','backwpup');
					else
						echo ' <input class="radio" type="radio"'.checked('gz',backwpup_get_option($main,'wpexportfilecompression'),false).' name="wpexportfilecompression" value="gz" disabled="disabled" />'.__('GZip','backwpup');
					if (function_exists('bzopen'))
						echo ' <input class="radio" type="radio"'.checked('bz2',backwpup_get_option($main,'wpexportfilecompression'),false).' name="wpexportfilecompression" value="bz2" />'.__('BZip2','backwpup');
					else
						echo ' <input class="radio" type="radio"'.checked('bz2',backwpup_get_option($main,'wpexportfilecompression'),false).' name="wpexportfilecompression" value="bz2" disabled="disabled" />'.__('BZip2','backwpup');
					?>
				</div>
			</div>


			<div id="filebackup" class="stuffbox"<?PHP if (!in_array("FILE",backwpup_get_option($main,'type'))) echo ' style="display:none;"';?>>
				<h3><label for="filebackup"><?PHP _e('File Backup','backwpup'); ?></label></h3>
				<div class="inside">
					<b><?PHP _e('Blog Folders to Backup:','backwpup'); ?></b><br />&nbsp;<br />
					<div id="filebackup">
						<div style="width:20%; float: left;">
							&nbsp;<b><input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backuproot'),true,true);?> name="backuproot" value="1"/> <?php _e('root','backwpup');?></b><br />
							<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
							<?PHP
							echo '<i>'.__('Exclude:','backwpup').'</i><br />';
							$folder=untrailingslashit(str_replace('\\','/',ABSPATH));
							if ( $dir = @opendir( $folder ) ) {
								while (($file = readdir( $dir ) ) !== false ) {
									if ( !in_array($file, array('.', '..','.svn')) and is_dir($folder.'/'.$file) and !in_array($folder.'/'.$file.'/',backwpup_get_exclude_wp_dirs($folder)))
										echo '<nobr><input class="checkbox" type="checkbox"'.checked(in_array($folder.'/'.$file.'/',backwpup_get_option($main,'backuprootexcludedirs')),true,false).' name="backuprootexcludedirs[]" value="'.$folder.'/'.$file.'/"/> '.$file.'</nobr><br />';
								}
								@closedir( $dir );
							}
							?>
							</div>
						</div>
						<div style="width:20%;float: left;">
							&nbsp;<b><input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupcontent'),true,true);?> name="backupcontent" value="1"/> <?php _e('Content','backwpup');?></b><br />
							<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
							<?PHP
							echo '<i>'.__('Exclude:','backwpup').'</i><br />';
							$folder=untrailingslashit(str_replace('\\','/',WP_CONTENT_DIR));
							if ( $dir = @opendir( $folder ) ) {
								while (($file = readdir( $dir ) ) !== false ) {
									if ( !in_array($file, array('.', '..','.svn')) and is_dir($folder.'/'.$file) and !in_array($folder.'/'.$file.'/',backwpup_get_exclude_wp_dirs($folder)))
										echo '<nobr><input class="checkbox" type="checkbox"'.checked(in_array($folder.'/'.$file.'/',backwpup_get_option($main,'backupcontentexcludedirs')),true,false).' name="backupcontentexcludedirs[]" value="'.$folder.'/'.$file.'/"/> '.$file.'</nobr><br />';
								}
								@closedir( $dir );
							}
							?>
							</div>
						</div>
						<div style="width:20%; float: left;">
							&nbsp;<b><input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupplugins'),true,true);?> name="backupplugins" value="1"/> <?php _e('Plugins','backwpup');?></b><br />
							<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
							<?PHP
							echo '<i>'.__('Exclude:','backwpup').'</i><br />';
							$folder=untrailingslashit(str_replace('\\','/',WP_PLUGIN_DIR));
							if ( $dir = @opendir( $folder ) ) {
								while (($file = readdir( $dir ) ) !== false ) {
									if ( !in_array($file, array('.', '..','.svn')) and is_dir($folder.'/'.$file) and !in_array($folder.'/'.$file.'/',backwpup_get_exclude_wp_dirs($folder)))
										echo '<nobr><input class="checkbox" type="checkbox"'.checked(in_array($folder.'/'.$file.'/',backwpup_get_option($main,'backuppluginsexcludedirs')),true,false).' name="backuppluginsexcludedirs[]" value="'.$folder.'/'.$file.'/"/> '.$file.'</nobr><br />';
								}
								@closedir( $dir );
							}
							?>
							</div>
						</div>
						<div style="width:20%; float: left;">
							&nbsp;<b><input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupthemes'),true,true);?> name="backupthemes" value="1"/> <?php _e('Themes','backwpup');?></b><br />
							<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
							<?PHP
							echo '<i>'.__('Exclude:','backwpup').'</i><br />';
							$folder=untrailingslashit(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes'));
							if ( $dir = @opendir( $folder ) ) {
								while (($file = readdir( $dir ) ) !== false ) {
									if ( !in_array($file, array('.', '..','.svn')) and is_dir($folder.'/'.$file) and !in_array($folder.'/'.$file.'/',backwpup_get_exclude_wp_dirs($folder)))
										echo '<nobr><input class="checkbox" type="checkbox"'.checked(in_array($folder.'/'.$file.'/',backwpup_get_option($main,'backupthemesexcludedirs')),true,false).' name="backupthemesexcludedirs[]" value="'.$folder.'/'.$file.'/"/> '.$file.'</nobr><br />';
								}
								@closedir( $dir );
							}
							?>
							</div>
						</div>
						<div style="width:20%; float: left;">
							&nbsp;<b><input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupuploads'),true,true);?> name="backupuploads" value="1"/> <?php _e('Blog uploads','backwpup');?></b><br />
							<div style="border-color:#CEE1EF; border-style:solid; border-width:2px; height:10em; width:90%; margin:2px; overflow:auto;">
							<?PHP
							echo '<i>'.__('Exclude:','backwpup').'</i><br />';
							$folder=untrailingslashit(backwpup_get_upload_dir());
							if ( $dir = @opendir( $folder ) ) {
								while (($file = readdir( $dir ) ) !== false ) {
									if ( !in_array($file, array('.', '..','.svn')) and is_dir($folder.'/'.$file) and !in_array($folder.'/'.$file,backwpup_get_exclude_wp_dirs($folder)))
										echo '<nobr><input class="checkbox" type="checkbox"'.checked(in_array($folder.'/'.$file.'/',backwpup_get_option($main,'backupuploadsexcludedirs')),true,false).' name="backupuploadsexcludedirs[]" value="'.$folder.'/'.$file.'/"/> '.$file.'</nobr><br />';
								}
								@closedir( $dir );
							}
							?>
							</div>
						</div>
					</div>
					<input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupexcludethumbs'),true,true);?> name="backupexcludethumbs" value="1"/> <?php _e('Don\'t backup thumbnails in blog uploads folder','backwpup');?><br />
					<input class="checkbox" type="checkbox"<?php checked(backwpup_get_option($main,'backupspecialfiles'),true,true);?> name="backupspecialfiles" value="1"/> <?php _e('Backup wp-config.php, robots.txt, .htaccess, .htpasswd and favicon.ico form root if it not selected','backwpup');?>
					<br />&nbsp;<br />
					<b><?PHP _e('Include folders to backup:','backwpup'); ?></b><br />
					<?PHP _e('Example:','backwpup'); ?> <?PHP echo str_replace('\\','/',ABSPATH); ?>,...<br />
					<input name="dirinclude" id="dirinclude" type="text" value="<?PHP echo backwpup_get_option($main,'dirinclude');?>" class="large-text" /><br />
					<br />
					<b><?PHP _e('Exclude files/folders from backup:','backwpup'); ?></b><br />
					<?PHP _e('Example:','backwpup'); ?> /logs/,.log,.tmp,/temp/,....<br />
					<input name="fileexclude" id="fileexclude" type="text" value="<?PHP echo backwpup_get_option($main,'fileexclude');?>" class="large-text" /><br />
				</div>
			</div>

			<?php do_meta_boxes(get_current_screen()->id, 'normal', $main); ?>

			<?php do_meta_boxes(get_current_screen()->id, 'advanced', $main); ?>

		</div>
	</div>
</div>

</form>
</div>

<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function($) {
		postboxes.add_postbox_toggles('<?php echo get_current_screen()->id; ?>');
	});
	//]]>
</script>