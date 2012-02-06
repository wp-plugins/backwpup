<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 *
 */
class BackWPup_Page_Editjob_Metaboxes {
	/**
	 * @static
	 *
	 * @param $main
	 */
	public static function save( $main ) {
		?>
	<div class="submitbox" id="submitjobedit">
		<div id="minor-publishing">
			<div id="minor-publishing-actions">
				<div id="preview-action">
				</div>
				<div class="clear"></div>
			</div>
			<div id="misc-publishing-actions">
				<div class="misc-pub-section misc-pub-section-last">
					<?php
					foreach ( backwpup_job_types() as $type )
					{
						echo "<input class=\"jobtype-select checkbox\" id=\"jobtype-select-" . $type . "\" type=\"checkbox\"" . checked( true, in_array( $type, backwpup_get_option( $main, 'type' ) ), false ) . " name=\"type[]\" value=\"" . $type . "\"/> " . backwpup_job_types( $type );
					}
					if ( ! function_exists( 'curl_init' ) )
						echo '<br /><strong style="color:red;">' . __( 'PHP curl functions not available! Most backup destinations deaktivated!', 'backwpup' ) . '</strong>';
					?>
				</div>
			</div>
		</div>
		<div id="major-publishing-actions">
			<div id="delete-action">
				<a class="submitdelete deletion" href="<?php echo wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=delete&jobs[]=' . backwpup_get_option( $main, 'jobid' ), 'bulk-jobs' ); ?>" onclick="if ( confirm('<?php echo esc_js( __( "You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.", "backwpup" ) ); ?>') ) { return true;}return false;"><?php _e( 'Delete', 'backwpup' ); ?></a>
			</div>
			<div id="publishing-action">
				<?php submit_button( __( 'Save Changes', 'backwpup' ), 'primary', 'save', false, array( 'tabindex'  => '2',
																										'accesskey' => 'p' ) ); ?>
			</div>
			<div class="clear"></div>
		</div>
	</div>
	<?php
	}

	public static function backupfile( $main ) {
		?>
	<b><?php _e( 'Backup type:', 'backwpup' ); ?></b><br />
	<?php
		echo '<input class="radio" type="radio"' . checked( 'sync', backwpup_get_option( $main, 'backuptype' ), false ) . ' name="backuptype" value="sync" />' . __( 'Sync files with destination', 'backwpup' ) . '<br />';
		echo '<input class="radio" type="radio"' . checked( 'archive', backwpup_get_option( $main, 'backuptype' ), false ) . ' name="backuptype" value="archive" />' . __( 'Create backup archive', 'backwpup' ) . '<br />';
		?>
	<div class="nosync"<?php if ( backwpup_get_option( $main, 'backuptype' ) == 'sync' ) echo ' style="display:none;"';?>>
		<b><?php _e( 'File Prefix:', 'backwpup' ); ?></b><br />
		<input name="fileprefix" type="text" value="<?php echo backwpup_get_option( $main, 'fileprefix' );?>" class="large-text" /><br />
		<b><?php _e( 'File Formart:', 'backwpup' ); ?></b><br />
		<?php
		if ( function_exists( 'gzopen' ) || class_exists( 'ZipArchive', true ) )
			echo '<input class="radio" type="radio"' . checked( '.zip', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".zip" />' . __( 'Zip', 'backwpup' ) . '<br />';
		else
			echo '<input class="radio" type="radio"' . checked( '.zip', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".zip" disabled="disabled" />' . __( 'Zip', 'backwpup' ) . '<br />';
		echo '<input class="radio" type="radio"' . checked( '.tar', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".tar" />' . __( 'Tar', 'backwpup' ) . '<br />';
		if ( function_exists( 'gzopen' ) )
			echo '<input class="radio" type="radio"' . checked( '.tar.gz', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".tar.gz" />' . __( 'Tar GZip', 'backwpup' ) . '<br />';
		else
			echo '<input class="radio" type="radio"' . checked( '.tar.gz', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".tar.gz" disabled="disabled" />' . __( 'Tar GZip', 'backwpup' ) . '<br />';
		if ( function_exists( 'bzopen' ) )
			echo '<input class="radio" type="radio"' . checked( '.tar.bz2', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".tar.bz2" />' . __( 'Tar BZip2', 'backwpup' ) . '<br />';
		else
			echo '<input class="radio" type="radio"' . checked( '.tar.bz2', backwpup_get_option( $main, 'fileformart' ), false ) . ' name="fileformart" value=".tar.bz2" disabled="disabled" />' . __( 'Tar BZip2', 'backwpup' ) . '<br />';
		_e( 'Preview:', 'backwpup' );
		echo '<br /><i><span id="backupfileprefix">' . backwpup_get_option( $main, 'fileprefix' ) . '</span>' . date_i18n( 'Y-m-d_H-i-s' ) . '<span id="backupfileformart">' . backwpup_get_option( $main, 'fileformart' ) . '</span></i>';
		?>
	</div>
	<?php
	}

	public static function sendlog( $main ) {
		_e( 'E-Mail-Adress:', 'backwpup' );
		?>
	<input name="mailaddresslog" id="mailaddresslog" type="text" value="<?php echo backwpup_get_option( $main, 'mailaddresslog' );?>" class="large-text" />
	<br />
	<input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'mailerroronly' ), true ); ?> name="mailerroronly" /> <?php _e( 'Only send an e-mail if there are errors.', 'backwpup' ); ?>
	<?php
	}

	public static function schedule( $main ) {
		echo "<br /><strong>" . __( 'Run job with:', 'backwpup' ) . "<br />";
		echo '<input class="radio" type="radio"' . checked( '', backwpup_get_option( $main, 'activetype' ), false ) . ' name="activetype" value="" />' . __( 'Manually', 'backwpup' ) . '<br />';
		echo '<input class="radio" type="radio"' . checked( 'wpcron', backwpup_get_option( $main, 'activetype' ), false ) . ' name="activetype" value="wpcron" />' . __( 'WordPress Cron', 'backwpup' ) . '<br />';
		$disabled = '';
		if ( ! backwpup_get_option( 'cfg', 'apicronservicekey' ) )
			$disabled = ' disabled="disabled"';
		echo '<input class="radio" type="radio"' . checked( 'backwpupapi', backwpup_get_option( $main, 'activetype' ), false ) . ' name="activetype" value="backwpupapi"' . $disabled . ' />' . __( 'BackWPup external cron service', 'backwpup' ) . '<br />';
		$display = '';
		if ( backwpup_get_option( $main, 'activetype' ) == '' )
			$display = ' style="display:none;"';
		echo "</strong><br /><div id=\"schedulecron\"" . $display . ">";
		list($cronstr['minutes'], $cronstr['hours'], $cronstr['mday'], $cronstr['mon'], $cronstr['wday']) = explode( ' ', backwpup_get_option( $main, 'cron' ), 5 );
		if ( strstr( $cronstr['minutes'], '*/' ) )
			$minutes = explode( '/', $cronstr['minutes'] );
		else
			$minutes = explode( ',', $cronstr['minutes'] );
		if ( strstr( $cronstr['hours'], '*/' ) )
			$hours = explode( '/', $cronstr['hours'] );
		else
			$hours = explode( ',', $cronstr['hours'] );
		if ( strstr( $cronstr['mday'], '*/' ) )
			$mday = explode( '/', $cronstr['mday'] );
		else
			$mday = explode( ',', $cronstr['mday'] );
		if ( strstr( $cronstr['mon'], '*/' ) )
			$mon = explode( '/', $cronstr['mon'] );
		else
			$mon = explode( ',', $cronstr['mon'] );
		if ( strstr( $cronstr['wday'], '*/' ) )
			$wday = explode( '/', $cronstr['wday'] );
		else
			$wday = explode( ',', $cronstr['wday'] );
		BackWPup_Ajax_Editjob::cron_text( array( 'cronstamp'=> backwpup_get_option( $main, 'cron' ) ) );
		?>
	<br />

	<?php	 echo '<input class="radio" type="radio"' . checked( "advanced", backwpup_get_option( $main, 'cronselect' ), false ) . ' name="cronselect" value="advanced" />' . __( 'advanced', 'backwpup' ) . '&nbsp;';
		echo '<input class="radio" type="radio"' . checked( "basic", backwpup_get_option( $main, 'cronselect' ), false ) . ' name="cronselect" value="basic" />' . __( 'basic', 'backwpup' );?>
	<br /><br />
	<div id="schedadvanced"<?php if ( backwpup_get_option( $main, 'cronselect' ) != 'advanced' ) echo ' style="display:none;"';?>>
		<div id="cron-min-box">
			<b><?php _e( 'Minutes: ', 'backwpup' ); ?></b><br />
			<?php
			echo '<input class="checkbox" type="checkbox"' . checked( in_array( "*", $minutes, true ), true, false ) . ' name="cronminutes[]" value="*" /> ' . __( 'Any (*)', 'backwpup' ) . '<br />';
			?>
			<div id="cron-min"><?php
				for ( $i = 0; $i < 60; $i = $i + 5 ) {
					echo '<input class="checkbox" type="checkbox"' . checked( in_array( "$i", $minutes, true ), true, false ) . ' name="cronminutes[]" value="' . $i . '" /> ' . $i . '<br />';
				}
				?>
			</div>
		</div>
		<div id="cron-hour-box">
			<b><?php _e( 'Hours:', 'backwpup' ); ?></b><br />
			<?php

			echo '<input class="checkbox" type="checkbox"' . checked( in_array( "*", $hours, true ), true, false ) . ' name="cronhours[]" value="*" /> ' . __( 'Any (*)', 'backwpup' ) . '<br />';
			?>
			<div id="cron-hour"><?php
				for ( $i = 0; $i < 24; $i ++ ) {
					echo '<input class="checkbox" type="checkbox"' . checked( in_array( "$i", $hours, true ), true, false ) . ' name="cronhours[]" value="' . $i . '" /> ' . $i . '<br />';
				}
				?>
			</div>
		</div>
		<div id="cron-day-box">
			<b><?php _e( 'Day of Month:', 'backwpup' ); ?></b><br />
			<input class="checkbox" type="checkbox"<?php checked( in_array( "*", $mday, true ), true, true ); ?> name="cronmday[]" value="*" /> <?php _e( 'Any (*)', 'backwpup' ); ?>
			<br />

			<div id="cron-day">
				<?php
				for ( $i = 1; $i <= 31; $i ++ ) {
					echo '<input class="checkbox" type="checkbox"' . checked( in_array( "$i", $mday, true ), true, false ) . ' name="cronmday[]" value="' . $i . '" /> ' . $i . '<br />';
				}
				?>
			</div>
		</div>
		<div id="cron-month-box">
			<b><?php _e( 'Month:', 'backwpup' ); ?></b><br />
			<?php
			echo '<input class="checkbox" type="checkbox"' . checked( in_array( "*", $mon, true ), true, false ) . ' name="cronmon[]" value="*" /> ' . __( 'Any (*)', 'backwpup' ) . '<br />';
			?>
			<div id="cron-month">
				<?php
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "1", $mon, true ), true, false ) . ' name="cronmon[]" value="1" /> ' . __( 'January', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "2", $mon, true ), true, false ) . ' name="cronmon[]" value="2" /> ' . __( 'February', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "3", $mon, true ), true, false ) . ' name="cronmon[]" value="3" /> ' . __( 'March', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "4", $mon, true ), true, false ) . ' name="cronmon[]" value="4" /> ' . __( 'April', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "5", $mon, true ), true, false ) . ' name="cronmon[]" value="5" /> ' . __( 'May', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "6", $mon, true ), true, false ) . ' name="cronmon[]" value="6" /> ' . __( 'June', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "7", $mon, true ), true, false ) . ' name="cronmon[]" value="7" /> ' . __( 'July', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "8", $mon, true ), true, false ) . ' name="cronmon[]" value="8" /> ' . __( 'Augest', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "9", $mon, true ), true, false ) . ' name="cronmon[]" value="9" /> ' . __( 'September', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "10", $mon, true ), true, false ) . ' name="cronmon[]" value="10" /> ' . __( 'October', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "11", $mon, true ), true, false ) . ' name="cronmon[]" value="11" /> ' . __( 'November', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "12", $mon, true ), true, false ) . ' name="cronmon[]" value="12" /> ' . __( 'December', 'backwpup' ) . '<br />';
				?>
			</div>
		</div>
		<div id="cron-weekday-box">
			<b><?php _e( 'Day of Week:', 'backwpup' ); ?></b><br />
			<?php
			echo '<input class="checkbox" type="checkbox"' . checked( in_array( "*", $wday, true ), true, false ) . ' name="cronwday[]" value="*" /> ' . __( 'Any (*)', 'backwpup' ) . '<br />';
			?>
			<div id="cron-weekday">
				<?php
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "0", $wday, true ), true, false ) . ' name="cronwday[]" value="0" /> ' . __( 'Sunday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "1", $wday, true ), true, false ) . ' name="cronwday[]" value="1" /> ' . __( 'Monday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "2", $wday, true ), true, false ) . ' name="cronwday[]" value="2" /> ' . __( 'Tuesday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "3", $wday, true ), true, false ) . ' name="cronwday[]" value="3" /> ' . __( 'Wednesday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "4", $wday, true ), true, false ) . ' name="cronwday[]" value="4" /> ' . __( 'Thursday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "5", $wday, true ), true, false ) . ' name="cronwday[]" value="5" /> ' . __( 'Friday', 'backwpup' ) . '<br />';
				echo '<input class="checkbox" type="checkbox"' . checked( in_array( "6", $wday, true ), true, false ) . ' name="cronwday[]" value="6" /> ' . __( 'Saturday', 'backwpup' ) . '<br />';
				?>
			</div>
		</div>
		<br class="clear" />
	</div>
	<div id="schedbasic"<?php if ( backwpup_get_option( $main, 'cronselect' ) != 'basic' ) echo ' style="display:none;"';?>>
		<table>
			<tr>
				<th>
					<?php _e( 'Type', 'backwpup' )	 ?>
				</th>
				<th>
				</th>
				<th>
					<?php _e( 'Hour', 'backwpup' )	 ?>
				</th>
				<th>
					<?php _e( 'Minute', 'backwpup' )	 ?>
				</th>
			</tr>
			<tr>
				<td><?php echo '<input class="radio" type="radio"' . checked( true, is_numeric( $mday[0] ), false ) . ' name="cronbtype" value="mon" />' . __( 'monthly', 'backwpup' ); ?></td>
				<td><select name="moncronmday"><?php for ( $i = 1; $i <= 31; $i ++ ) {
					echo '<option ' . selected( in_array( "$i", $mday, true ), true, false ) . '  value="' . $i . '" />' . __( 'on', 'backwpup' ) . ' ' . $i . '.</option>';
				} ?></select></td>
				<td><select name="moncronhours"><?php for ( $i = 0; $i < 24; $i ++ ) {
					echo '<option ' . selected( in_array( "$i", $hours, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
				<td><select name="moncronminutes"><?php for ( $i = 0; $i < 60; $i = $i + 5 ) {
					echo '<option ' . selected( in_array( "$i", $minutes, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
			</tr>
			<tr>
				<td><?php echo '<input class="radio" type="radio"' . checked( true, is_numeric( $wday[0] ), false ) . ' name="cronbtype" value="week" />' . __( 'weekly', 'backwpup' ); ?></td>
				<td><select name="weekcronwday">
					<?php	 echo '<option ' . selected( in_array( "0", $wday, true ), true, false ) . '  value="0" />' . __( 'Sunday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "1", $wday, true ), true, false ) . '  value="1" />' . __( 'Monday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "2", $wday, true ), true, false ) . '  value="2" />' . __( 'Tuesday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "3", $wday, true ), true, false ) . '  value="3" />' . __( 'Wednesday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "4", $wday, true ), true, false ) . '  value="4" />' . __( 'Thursday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "5", $wday, true ), true, false ) . '  value="5" />' . __( 'Friday', 'backwpup' ) . '</option>';
					echo '<option ' . selected( in_array( "6", $wday, true ), true, false ) . '  value="6" />' . __( 'Saturday', 'backwpup' ) . '</option>'; ?>
				</select></td>
				<td><select name="weekcronhours"><?php for ( $i = 0; $i < 24; $i ++ ) {
					echo '<option ' . selected( in_array( "$i", $hours, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
				<td><select name="weekcronminutes"><?php for ( $i = 0; $i < 60; $i = $i + 5 ) {
					echo '<option ' . selected( in_array( "$i", $minutes, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
			</tr>
			<tr>
				<td><?php echo '<input class="radio" type="radio"' . checked( "**", $mday[0] . $wday[0], false ) . ' name="cronbtype" value="day" />' . __( 'daily', 'backwpup' ); ?></td>
				<td></td>
				<td><select name="daycronhours"><?php for ( $i = 0; $i < 24; $i ++ ) {
					echo '<option ' . selected( in_array( "$i", $hours, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
				<td><select name="daycronminutes"><?php for ( $i = 0; $i < 60; $i = $i + 5 ) {
					echo '<option ' . selected( in_array( "$i", $minutes, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
			</tr>
			<tr>
				<td><?php echo '<input class="radio" type="radio"' . checked( "*", $hours[0], false, false ) . ' name="cronbtype" value="hour" />' . __( 'hourly', 'backwpup' ); ?></td>
				<td></td>
				<td></td>
				<td><select name="hourcronminutes"><?php for ( $i = 0; $i < 60; $i = $i + 5 ) {
					echo '<option ' . selected( in_array( "$i", $minutes, true ), true, false ) . '  value="' . $i . '" />' . $i . '</option>';
				} ?></select></td>
			</tr>
		</table>
	</div>
	</div>
	<?php
	}

	public static function destfolder( $main ) {
		?>
	<b><?php _e( 'Full Path to folder for Backup Files:', 'backwpup' ); ?></b><br />
	<input name="backupdir" id="backupdir" type="text" value="<?php echo backwpup_get_option( $main, 'backupdir' );?>" class="large-text" />
	<br />
	<span class="description"><?php _e( 'Your WordPress dir is:', 'backwpup' ); echo ' ' . trailingslashit( str_replace( '\\', '/', ABSPATH ) );?></span>
	<br />
	<span class="nosync"><?php _e( 'Max. backup files in folder:', 'backwpup' ); ?>
		<input name="maxbackups" id="maxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'maxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will deleted first.)', 'backwpup' );?></span><br /></span>
	<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'backupsyncnodelete' ), true ); ?> name="backupsyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
		<br /></span>
	<?php
	}

	public static function destftp( $main ) {
		?>
	<b><?php _e( 'Hostname:', 'backwpup' ); ?></b><br />
	<input name="ftphost" type="text" value="<?php echo backwpup_get_option( $main, 'ftphost' );?>" class="large-text" />
	<br />
	<b><?php _e( 'Port:', 'backwpup' ); ?></b><br />
	<input name="ftphostport" type="text" value="<?php echo backwpup_get_option( $main, 'ftphostport' );?>" class="small-text" />
	<br />
	<b><?php _e( 'Username:', 'backwpup' ); ?></b><br />
	<input name="ftpuser" type="text" value="<?php echo backwpup_get_option( $main, 'ftpuser' );?>" class="user large-text" />
	<br />
	<b><?php _e( 'Password:', 'backwpup' ); ?></b><br />
	<input name="ftppass" type="password" value="<?php echo backwpup_get_option( $main, 'ftppass' );?>" class="password large-text" />
	<br />
	<b><?php _e( 'Folder on Server:', 'backwpup' ); ?></b><br />
	<input name="ftpdir" type="text" value="<?php echo backwpup_get_option( $main, 'ftpdir' );?>" class="large-text" />
	<br />
	<span class="nosync"><?php _e( 'Max. backup files in FTP folder:', 'backwpup' ); ?>
		<input name="ftpmaxbackups" class="small-text" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'ftpmaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
	<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'ftpsyncnodelete' ), true ); ?> name="ftpsyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
		<br /></span>
	<?php _e( 'Timeout for FTP connection:', 'backwpup' ); ?>
	<input name="ftptimeout" type="text" class="small-text" size="3" value="<?php echo backwpup_get_option( $main, 'ftptimeout' );?>" class="small-text" /><?php _e( 'sec.', 'backwpup' ); ?>
	<br />
	<input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'ftpssl' ), true ); ?> name="ftpssl"<?php if ( ! function_exists( 'ftp_ssl_connect' ) ) echo " disabled=\"disabled\""; ?> /> <?php _e( 'Use SSL-FTP Connection.', 'backwpup' ); ?>
	<br />
	<input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'ftppasv' ), true ); ?> name="ftppasv" /> <?php _e( 'Use FTP Passiv mode.', 'backwpup' ); ?>
	<br />
	<?php
	}

	public static function dests3( $main ) {
		?>
	<div class="dests">
		<b><?php _e( 'Access Key ID:', 'backwpup' ); ?></b>
		<input id="awsAccessKey" name="awsAccessKey" type="text" value="<?php echo backwpup_get_option( $main, 'awsAccessKey' );?>" class="large-text" /><br />
		<b><?php _e( 'Secret Access Key:', 'backwpup' ); ?></b><br />
		<input id="awsSecretKey" name="awsSecretKey" type="password" value="<?php echo backwpup_get_option( $main, 'awsSecretKey' );?>" class="large-text" /><br />
		<b><?php _e( 'Bucket:', 'backwpup' ); ?></b><br />
		<input id="awsBucketselected" name="awsBucketselected" type="hidden" value="<?php echo backwpup_get_option( $main, 'awsBucket' );?>" />
		<?php if ( backwpup_get_option( $main, 'awsAccessKey' ) && backwpup_get_option( $main, 'awsSecretKey' ) ) BackWPup_Ajax_Editjob::aws_buckets( array( 'awsAccessKey' => backwpup_get_option( $main, 'awsAccessKey' ),
																																							 'awsSecretKey' => backwpup_get_option( $main, 'awsSecretKey' ),
																																							 'awsselected'  => backwpup_get_option( $main, 'awsBucket' ),
																																							 'awsdisablessl'=> backwpup_get_option( $main, 'awsdisablessl' ) ) ); ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'Create bucket:', 'backwpup' ); ?>
		<input name="newawsBucket" type="text" value="" class="text" />
		<select name="awsRegion" title="<?php _e( 'Bucket Region', 'backwpup' ); ?>">
			<option value="s3.amazonaws.com"><?php _e( 'US-Standard (Northern Virginia & Washington State)', 'backwpup' ); ?></option>
			<option value="s3-us-west-1.amazonaws.com"><?php _e( 'US-West 1 (Northern California)', 'backwpup' ); ?></option>
			<option value="s3-us-west-2.amazonaws.com"><?php _e( 'US-West 2 (Oregon)', 'backwpup' ); ?></option>
			<option value="s3-eu-west-1.amazonaws.com"><?php _e( 'EU (Ireland)', 'backwpup' ); ?></option>
			<option value="s3-ap-southeast-1.amazonaws.com"><?php _e( 'Asia Pacific (Singapore)', 'backwpup' ); ?></option>
			<option value="s3-ap-northeast-1.amazonaws.com"><?php _e( 'Asia Pacific (Japan)', 'backwpup' ); ?></option>
			<option value="s3-sa-east-1.amazonaws.com"><?php _e( 'South America (Sao Paulo)', 'backwpup' ); ?></option>
			<option value="s3-us-gov-west-1.amazonaws.com"><?php _e( 'United States GovCloud', 'backwpup' ); ?></option>
			<option value="s3-fips-us-gov-west-1.amazonaws.com"><?php _e( 'United States GovCloud FIPS 140-2', 'backwpup' ); ?></option>
		</select><br />
		<b><?php _e( 'Folder in bucket:', 'backwpup' ); ?></b><br />
		<input name="awsdir" type="text" value="<?php echo backwpup_get_option( $main, 'awsdir' );?>" class="large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in bucket folder:', 'backwpup' ); ?>
			<input name="awsmaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'awsmaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'awssyncnodelete' ), true ); ?> name="awssyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span>
		<input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'awsrrs' ), true ); ?> name="awsrrs" /> <?php _e( 'Save Files with reduced redundancy!', 'backwpup' ); ?>
		<br />
		<input class="checkbox" value="AES256" type="checkbox" <?php checked( backwpup_get_option( $main, 'awsssencrypt' ), 'AES256' ); ?> name="awsssencrypt" />	<?php _e( 'Save Files Server Side Encrypted!', 'backwpup' ); ?>
		<br />
		<input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'awsdisablessl' ), true ); ?> name="awsdisablessl" />	<?php _e( 'Disable SSL connection on transfer!', 'backwpup' ); ?>
		<br />
	</div>
	<div class="destlinks">
		<a href="http://www.amazon.de/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fgp%2Faws%2Fregistration%2Fregistration-form.html&site-redirect=de&tag=hueskennet-21&linkCode=ur2&camp=1638&creative=6742" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="http://aws-portal.amazon.com/gp/aws/developer/account/index.html?action=access-key" target="_blank"><?php _e( 'Find Keys', 'backwpup' ); ?></a><br />
		<a href="https://console.aws.amazon.com/s3/home" target="_blank"><?php _e( 'Webinterface', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destgstorage( $main ) {
		?>
	<div class="dests">
		<b><?php _e( 'Access Key:', 'backwpup' ); ?></b><br />
		<input id="GStorageAccessKey" name="GStorageAccessKey" type="text" value="<?php echo backwpup_get_option( $main, 'GStorageAccessKey' );?>" class="large-text" /><br />
		<b><?php _e( 'Secret:', 'backwpup' ); ?></b><br />
		<input id="GStorageSecret" name="GStorageSecret" type="password" value="<?php echo backwpup_get_option( $main, 'GStorageSecret' );?>" class="large-text" /><br />
		<b><?php _e( 'Bucket:', 'backwpup' ); ?></b><br />
		<input id="GStorageselected" name="GStorageselected" type="hidden" value="<?php echo backwpup_get_option( $main, 'GStorageBucket' );?>" />
		<?php if ( backwpup_get_option( $main, 'GStorageAccessKey' ) && backwpup_get_option( $main, 'GStorageSecret' ) ) BackWPup_Ajax_Editjob::gstorage_buckets( array( 'GStorageAccessKey'=> backwpup_get_option( $main, 'GStorageAccessKey' ),
																																										 'GStorageSecret'   => backwpup_get_option( $main, 'GStorageSecret' ),
																																										 'GStorageselected' => backwpup_get_option( $main, 'GStorageBucket' ) ) ); ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'Create bucket:', 'backwpup' ); echo ' '; _e( 'Please create it in Webinterface!', 'backwpup' );?>
		<br />
		<b><?php _e( 'Folder in bucket:', 'backwpup' ); ?></b><br />
		<input name="GStoragedir" type="text" value="<?php echo backwpup_get_option( $main, 'GStoragedir' );?>" class="large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in bucket folder:', 'backwpup' ); ?>
			<input name="GStoragemaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'GStoragemaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'GStoragesyncnodelete' ), true ); ?> name="GStoragesyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span>
	</div>
	<div class="destlinks">
		<a href="http://code.google.com/apis/storage/docs/signup.html" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="https://code.google.com/apis/console/" target="_blank"><?php _e( 'Find Keys', 'backwpup' ); ?></a><br />
		<a href="https://sandbox.google.com/storage/" target="_blank"><?php _e( 'Webinterface', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destazure( $main ) {
		?>
	<div class="dests">

		<b><?php _e( 'Host:', 'backwpup' ); ?></b><br />
		<input id="msazureHost" name="msazureHost" type="text" value="<?php echo backwpup_get_option( $main, 'msazureHost' );?>" class="large-text" /><span class="description"><?php _e( 'Normely: blob.core.windows.net', 'backwpup' );?></span><br />
		<b><?php _e( 'Account Name:', 'backwpup' ); ?></b><br />
		<input id="msazureAccName" name="msazureAccName" type="text" value="<?php echo backwpup_get_option( $main, 'msazureAccName' );?>" class="large-text" /><br />
		<b><?php _e( 'Access Key:', 'backwpup' ); ?></b><br />
		<input id="msazureKey" name="msazureKey" type="password" value="<?php echo backwpup_get_option( $main, 'msazureKey' );?>" class="large-text" /><br />
		<b><?php _e( 'Container:', 'backwpup' ); ?></b><br />
		<input id="msazureContainerselected" name="msazureContainerselected" type="hidden" value="<?php echo backwpup_get_option( $main, 'msazureContainer' );?>" />
		<?php if ( backwpup_get_option( $main, 'msazureAccName' ) && backwpup_get_option( $main, 'msazureKey' ) ) BackWPup_Ajax_Editjob::msazure_container( array( 'msazureHost'	=> backwpup_get_option( $main, 'msazureHost' ),
																																								   'msazureAccName' => backwpup_get_option( $main, 'msazureAccName' ),
																																								   'msazureKey'	 => backwpup_get_option( $main, 'msazureKey' ),
																																								   'msazureselected'=> backwpup_get_option( $main, 'msazureContainer' ) ) ); ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'Create Container:', 'backwpup' ); ?>
		<input name="newmsazureContainer" type="text" value="" class="text" /> <br />
		<b><?php _e( 'Folder in Container:', 'backwpup' ); ?></b><br />
		<input name="msazuredir" type="text" value="<?php echo backwpup_get_option( $main, 'msazuredir' );?>" class="large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in container folder:', 'backwpup' ); ?>
			<input name="msazuremaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'msazuremaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'msazuresyncnodelete' ), true ); ?> name="msazuresyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span>
	</div>
	<div class="destlinks">
		<a href="http://www.microsoft.com/windowsazure/offers/" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="http://windows.azure.com/" target="_blank"><?php _e( 'Find Key', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destrsc( $main ) {
		?>
	<div class="dests">
		<b><?php _e( 'Username:', 'backwpup' ); ?></b><br />
		<input id="rscUsername" name="rscUsername" type="text" value="<?php echo backwpup_get_option( $main, 'rscUsername' );?>" class="large-text" /><br />
		<b><?php _e( 'API Key:', 'backwpup' ); ?></b><br />
		<input id="rscAPIKey" name="rscAPIKey" type="text" value="<?php echo backwpup_get_option( $main, 'rscAPIKey' );?>" class="large-text" /><br />
		<b><?php _e( 'Container:', 'backwpup' ); ?></b><br />
		<input id="rscContainerselected" name="rscContainerselected" type="hidden" value="<?php echo backwpup_get_option( $main, 'rscContainer' );?>" />
		<?php if ( backwpup_get_option( $main, 'rscUsername' ) && backwpup_get_option( $main, 'rscAPIKey' ) ) BackWPup_Ajax_Editjob::rsc_container( array( 'rscUsername'=> backwpup_get_option( $main, 'rscUsername' ),
																																						   'rscAPIKey'  => backwpup_get_option( $main, 'rscAPIKey' ),
																																						   'rscselected'=> backwpup_get_option( $main, 'rscContainer' ) ) ); ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'Create Container:', 'backwpup' ); ?>
		<input name="newrscContainer" type="text" value="" class="text" /> <br />
		<b><?php _e( 'Folder in container:', 'backwpup' ); ?></b><br />
		<input name="rscdir" type="text" value="<?php echo backwpup_get_option( $main, 'rscdir' );?>" class="large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in container folder:', 'backwpup' ); ?>
			<input name="rscmaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'rscmaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'rscsyncnodelete' ), true ); ?> name="rscsyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span><br />
	</div>
	<div class="destlinks">
		<a href="http://www.rackspacecloud.com/2073.html" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="https://manage.rackspacecloud.com/APIAccess.do" target="_blank"><?php _e( 'Find Key', 'backwpup' ); ?></a><br />
		<a href="https://manage.rackspacecloud.com/CloudFiles.do" target="_blank"><?php _e( 'Webinterface', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destdropbox( $main ) {
		?>
	<div class="dests">
		<?php if ( ! backwpup_get_option( $main, 'dropetoken' ) && ! backwpup_get_option( $main, 'dropesecret' ) ) { ?>
		<b><?php _e( 'Root:', 'backwpup' ); ?></b>&nbsp;
		<select name="droperoot" id="droperoot">
			<option <?php selected( backwpup_get_option( $main, 'droperoot' ), 'sandbox', true ); ?> value="sandbox"><?php _e( 'Sandbox (App folder)', 'backwpup' ); ?></option>
			<option <?php selected( backwpup_get_option( $main, 'droperoot' ), 'dropbox', true ); ?> value="dropbox"><?php _e( 'DropBox (full DropBox)', 'backwpup' ); ?></option>
		</select><br />
		<b><?php _e( 'Login:', 'backwpup' ); ?></b>&nbsp;
		<span style="color:red;"><?php _e( 'Not authenticated!', 'backwpup' ); ?></span>
		<input type="submit" name="authbutton" class="button-primary" accesskey="d" value="<?php _e( 'DropBox authenticate!', 'backwpup' ); ?>" />
		<br />
		<?php } else { ?>
		<input name="droperoot" type="hidden" value="<?php echo backwpup_get_option( $main, 'droperoot' );?>" />
		<b><?php _e( 'Root:', 'backwpup' ); ?></b>&nbsp;<?php echo (backwpup_get_option( $main, 'droperoot' ) == 'sandbox') ? _e( 'Sandbox (App folder)', 'backwpup' ) : _e( 'DropBox (full DropBox)', 'backwpup' ); ?>
		<br />
		<b><?php _e( 'Login:', 'backwpup' ); ?></b>&nbsp;
		<span style="color:green;"><?php _e( 'Authenticated!', 'backwpup' ); ?></span>
		<input type="submit" name="authbutton" class="button-primary" accesskey="d" value="<?php _e( 'Delete DropBox authentication!', 'backwpup' ); ?>" />
		<br />
		<?php } ?><br />
		<b><?php _e( 'Folder:', 'backwpup' ); ?></b><br />
		<input name="dropedir" type="text" value="<?php echo backwpup_get_option( $main, 'dropedir' );?>" class="user large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in DropBox folder:', 'backwpup' ); ?>
			<input name="dropemaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'dropemaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'dropesyncnodelete' ), true ); ?> name="dropesyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span>
	</div>
	<div class="destlinks">
		<a href="<?php echo apply_filters( 'backwpup_api_appkey', 'DROPBOX_CREATE_ACCOUNT', 'http://db.tt/Bm0l8dfn' ); ?>" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="https://www.dropbox.com/" target="_blank"><?php _e( 'Webinterface', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destsugarsync( $main ) {
		?>
	<div class="dests">
		<b><?php _e( 'E-mail address:', 'backwpup' ); ?></b><br />
		<input id="sugaruser" name="sugaruser" type="text" value="<?php echo backwpup_get_option( $main, 'sugaruser' );?>" class="large-text" /><br />
		<b><?php _e( 'Password:', 'backwpup' ); ?></b><br />
		<input id="sugarpass" name="sugarpass" type="password" value="<?php echo backwpup_get_option( $main, 'sugarpass' );?>" class="large-text" /><br />
		<b><?php _e( 'Root:', 'backwpup' ); ?></b><br />
		<input id="sugarrootselected" name="sugarrootselected" type="hidden" value="<?php echo backwpup_get_option( $main, 'sugarroot' );?>" />
		<?php if ( backwpup_get_option( $main, 'sugaruser' ) && backwpup_get_option( $main, 'sugarpass' ) ) BackWPup_Ajax_Editjob::sugarsync_root( array( 'sugaruser'		=> backwpup_get_option( $main, 'sugaruser' ),
																																						  'sugarpass'		=> backwpup_decrypt( backwpup_get_option( $main, 'sugarpass' ) ),
																																						  'sugarrootselected'=> backwpup_get_option( $main, 'sugarroot' ) ) ); ?>
		<br />
		<b><?php _e( 'Folder:', 'backwpup' ); ?></b><br />
		<input name="sugardir" type="text" value="<?php echo backwpup_get_option( $main, 'sugardir' );?>" class="large-text" /><br />
		<span class="nosync"><?php _e( 'Max. backup files in folder:', 'backwpup' ); ?>
			<input name="sugarmaxbackups" type="text" size="3" value="<?php echo backwpup_get_option( $main, 'sugarmaxbackups' );?>" class="small-text" /><span class="description"><?php _e( '(Oldest files will be deleted first.)', 'backwpup' );?></span><br /></span>
		<span class="sync"><input class="checkbox" value="1" type="checkbox" <?php checked( backwpup_get_option( $main, 'sugarsyncnodelete' ), true ); ?> name="sugarsyncnodelete" /> <?php _e( 'Do not delete files on sync destination!', 'backwpup' ); ?>
			<br /></span>
	</div>
	<div class="destlinks">
		<a href="http://www.anrdoezrs.net/click-5425765-10671858" target="_blank"><?php _e( 'Create Account', 'backwpup' ); ?></a><br />
		<a href="https://sugarsync.com" target="_blank"><?php _e( 'Webinterface', 'backwpup' ); ?></a><br />
	</div>
	<br class="clear" />
	<?php
	}

	public static function destmail( $main ) {
		?>
	<b><?php _e( 'E-mail address:', 'backwpup' ); ?></b><br />
	<input name="mailaddress" id="mailaddress" type="text" value="<?php echo backwpup_get_option( $main, 'mailaddress' );?>" class="large-text" />
	<br />
	<?php echo __( 'Max. File Size for sending Backups with mail:', 'backwpup' ) . '<input name="mailefilesize" type="text" value="' . backwpup_get_option( $main, 'mailefilesize' ) . '" class="small-text" />MB<br />'; ?>
	<?php
	}

	//ever display needed boxes
	public static function displayneeded( $hidden ) {
		$newhidden = array();
		foreach ( $hidden as $hiddenid ) {
			if ( $hiddenid != 'backwpup_jobedit_save' && $hiddenid != 'backwpup_jobedit_schedule' )
				$newhidden[] = $hiddenid;
		}
		return $newhidden;
	}
}

