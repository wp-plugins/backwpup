<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}


/**
 * Class for BackWPup Tools Page
 */
class BackWPup_Page_Tools {

	public static function load() {
		global $backwpup_message;
		if (isset($_POST['dbrestoretool']) && $_POST['dbrestoretool']==__('Put DB restore tool to blog root...', 'backwpup')) {
			check_admin_referer('backwpup-tools');
			if(copy('http://api.backwpup.com/download/backwpup_db_restore.zip',ABSPATH.'backwpup_db_restore.zip')) {
				//unzip
				if (class_exists('ZipArchive',true)) {
					$zip = new ZipArchive;
					if ($zip->open(ABSPATH.'backwpup_db_restore.zip') === TRUE) {
						$zip->extractTo(ABSPATH);
						$zip->close();
						unlink(ABSPATH.'backwpup_db_restore.zip');
					}
				} else { //PCL zip
					require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
					$zip = new PclZip(ABSPATH.'backwpup_db_restore.zip');
					$zip->extract(PCLZIP_OPT_PATH,ABSPATH);
					unset($zip);
					unlink(ABSPATH.'backwpup_db_restore.zip');
				}
				$backwpup_message=__('Files for restore transferred!', 'backwpup');
			}
		}

		if (isset($_POST['dbrestoretooldel']) && $_POST['dbrestoretooldel']==__('Delete restore tool from blog root...', 'backwpup')) {
			check_admin_referer('backwpup-tools');
			if (file_exists(ABSPATH.'backwpup_db_restore.zip'))
				unlink(ABSPATH.'backwpup_db_restore.zip');
			if (file_exists(ABSPATH.'backwpup_db_restore.php'))
				unlink(ABSPATH.'backwpup_db_restore.php');
			if (file_exists(ABSPATH.'.backwpup_restore'))
				unlink(ABSPATH.'.backwpup_restore');
			$backwpup_message=__('Files for restore deleted!', 'backwpup');
		}

		if (isset($_POST['executionsave']) && $_POST['executionsave']==__('Save to config!', 'backwpup')) {
			check_admin_referer('backwpup-tools');
			$times=backwpup_get_option('temp','exectime');
			if ($times['lasttime']<=current_time('timestamp')-5) {
				$exectime=$times['lasttime']-$times['starttime'];
				backwpup_update_option('cfg','jobrunmaxexectime',$exectime);
				$backwpup_message=sprintf(__('Max. execution time saved with %d sec.', 'backwpup'),$exectime);
			}
		}

		if (isset($_POST['executiontime']) && $_POST['executiontime']==__('Start time test...', 'backwpup')) {
			check_admin_referer('backwpup-tools');
			//try to disable safe mode
			@ini_set('safe_mode', '0');
			// Now user abort
			@ini_set('ignore_user_abort', '0');
			ignore_user_abort(true);
			@set_time_limit(1800);
			ob_start();
			wp_redirect(backwpup_admin_url('admin.php') . '?page=backwpuptools');
			echo ' ';
			while ( @ob_end_flush() );
			flush();
			$times['starttime']=current_time('timestamp');
			$times['lasttime']=current_time('timestamp');
			backwpup_update_option('temp','exectime',$times);
			$count=0;
			while ($count<1800) {
				sleep(1);
				if (backwpup_get_option('working','exectimestop')) {
					backwpup_delete_option('working','exectimestop');
					backwpup_delete_option('temp','exectime');
					die();
				}
				$times['lasttime']=current_time('timestamp');
				backwpup_update_option('temp','exectime',$times);
				$count++;
			}
		}

		if (isset($_POST['executionstop']) && $_POST['executionstop']==__('Terminate time test!', 'backwpup')) {
			check_admin_referer('backwpup-tools');
			backwpup_update_option('working','exectimestop',true);
			$backwpup_message=__('Execution Time test terminated!', 'backwpup');
		}

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab( array(
				'id'      => 'overview',
				'title'   => __('Overview'),
				'content'	=>
				'<p>' . '</p>'
			) );

		//add css for Admin Section
		wp_enqueue_style('backwpup_tools',plugins_url('',dirname(__FILE__)).'/css/tools.css','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),'screen');
		//add java for Admin Section
		//wp_enqueue_script('backwpup_tools',plugins_url('',dirname(__FILE__)).'/js/tools.js','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),true);


	}

	public static function page() {
		global $wpdb;
		?>
		<div class="wrap">
			<?php
			screen_icon();
			echo "<h2>".esc_html( __('BackWPup Tools', 'backwpup'))."</h2>";
			if (isset($backwpup_message) && !empty($backwpup_message))
				echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
			?>
			<form id="posts-filter" enctype="multipart/form-data" action="<?php echo backwpup_admin_url('admin.php').'?page=backwpuptools'; ?>" method="post">
				<?php wp_nonce_field('backwpup-tools'); ?>
				<h3><?php _e('Database restore','backwpup'); ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('DB Restore','backwpup'); ?></th>
						<td>
							<?php
							if (!file_exists(ABSPATH.'backwpup_db_restore.php') && is_writeable(ABSPATH)) {
								_e('Download manually DB restore tool: <a href="http://api.backwpup.com/download/backwpup_db_restore.zip">http://api.backwpup.com/download/backwpup_db_restore.zip</a>','backwpup');
								echo '<br />';
								echo '<input type="submit" name="dbrestoretool" class="button-primary" value="'.__('Put DB restore tool to blog root...', 'backwpup').'" /><br />';
							}
							elseif(is_writeable(ABSPATH)) {
								echo '<input type="submit" name="dbrestoretooldel" class="button-primary" value="'.__('Delete restore tool from blog root...', 'backwpup').'" /><br />';
								echo sprintf(__('Make a DB restore:  <a href="%1$s/backwpup_db_restore.php">%1$s/backwpup_db_restore.php</a>', 'backwpup'),get_bloginfo('url')).' <br />';
							}
							?>
						</td>
					</tr>
				</table>

				<h3><?php _e('Import Jobs settings','backwpup'); ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="importfile"><?php _e('Select file to import:', 'backwpup'); ?></label></th>
						<td><input name="importfile" type="file" id="importfile" class="regular-text code" />
							<input type="submit" name="upload" class="button-primary" value="<?php _e('Upload', 'backwpup'); ?>" />
						</td>
					</tr>
					<tr valign="top">
						<?php
						if (isset($_POST['upload']) && is_uploaded_file($_FILES['importfile']['tmp_name']) && $_POST['upload']==__('Upload', 'backwpup')) {
							echo "<th scope=\"row\"><label for=\"maxlogs\">".__('Select jobs to import','backwpup')."</label></th><td>";
							$import=file_get_contents($_FILES['importfile']['tmp_name']);
							$jobids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value ASC");
							foreach ( unserialize($import) as $jobid => $jobvalue ) {
								echo "<select name=\"importtype[".$jobid."]\" title=\"".__('Import Type', 'backwpup')."\"><option value=\"not\">".__('No Import', 'backwpup')."</option>";
								if (in_array($jobid,$jobids))
									echo "<option value=\"over\">".__('Overwrite', 'backwpup')."</option><option value=\"append\">".__('Append', 'backwpup')."</option>";
								else
									echo "<option value=\"over\">".__('Import', 'backwpup')."</option>";
								echo "</select>";
								echo '&nbsp;<span class="description">'.$jobid.". ".$jobvalue['name'].'</span><br />';
							}
							echo "<input type=\"hidden\" name=\"importfile\" value=\"".urlencode($import)."\" />";
							echo "<input type=\"submit\" name=\"import\" class=\"button-primary\" value=\"".__('Import', 'backwpup')."\" />";
						}
						if (isset($_POST['import']) && $_POST['import']==__('Import', 'backwpup') && !empty($_POST['importfile'])) {
							echo "<th scope=\"row\"><label for=\"maxlogs\">".__('Import','backwpup')."</label></th><td>";
							$import=maybe_unserialize(trim(urldecode($_POST['importfile'])));
							foreach ( $_POST['importtype'] as $id => $type ) {
								if ($type=='over')
									$import[$id]['jobid']=$id;
								if ($type=='append') {
									$import[$id]['jobid']=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC LIMIT 1",0,0);
									$import[$id]['jobid']++;
								}
								if ($type=='not' || empty($type))
									continue;
								$import[$id]['activetype']='';
								unset($import[$id]['cronnextrun']);
								unset($import[$id]['starttime']);
								unset($import[$id]['logfile']);
								unset($import[$id]['lastlogfile']);
								unset($import[$id]['lastrun']);
								unset($import[$id]['lastruntime']);
								unset($import[$id]['lastbackupdownloadurl']);
								foreach ($import[$id] as $jobname => $jobvalue)
									backwpup_update_option('job_'.$import[$id]['jobid'],$jobname,$jobvalue);
							}
							_e('Jobs imported!', 'backwpup');
						}
						echo '</td>';
						?>
					</tr>
				</table>

				<h3><?php _e('Test max. script execution time','backwpup'); ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Test result:','backwpup'); ?></th>
						<td>
							<?php
							$times=backwpup_get_option('temp','exectime');
							if (empty($times['starttime']) || empty($times['lasttime'])) {
								_e('No result');
								echo "<br /><input type=\"submit\" name=\"executiontime\" class=\"button-primary\" value=\"".__('Start time test...', 'backwpup')."\" />";
							}
							elseif ($times['lasttime']<=current_time('timestamp')-5) {
								$exectime=$times['lasttime']-$times['starttime'];
								echo '<span>'.sprintf(__('%d sec.','backwpup'),$exectime).' </span><br />';
								echo "<input type=\"submit\" name=\"executiontime\" class=\"button\" value=\"".__('Start time test...', 'backwpup')."\" />";
								echo "<input type=\"submit\" name=\"executionsave\" class=\"button-primary\" value=\"".__('Save to config!', 'backwpup')."\" />";
							}
							else {
								$exectime=$times['lasttime']-$times['starttime'];
								echo '<span>'.sprintf(__('%d sec.','backwpup'),$exectime).' </span> <blink><strong>'.__('In progress').'</strong></blink><br />';
								echo "<input type=\"submit\" name=\"executionstop\" class=\"button\" value=\"".__('Terminate time test!', 'backwpup')."\" />";
							}
							?>
						</td>
					</tr>
				</table>


			</form>
		</div>
		<?php
	}
}
