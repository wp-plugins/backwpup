<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 * Class for BackWPup display logs / working page
 */
class BackWPup_Page_Working {
	public static function load() {
		nocache_headers(); //no cache

		if ( isset($_GET['starttype']) && $_GET['starttype'] == 'runnow' && backwpup_get_option( 'cfg', 'runnowalt' ) && ! empty($_GET['jobid']) ) {
			check_admin_referer( 'job-runnow' );
			backwpup_jobrun_url( 'runnowalt', $_GET['jobid'], true );
		}

		if ( ! empty($_GET['logfile']) )
			check_admin_referer( 'view-log_' . basename( trim( $_GET['logfile'] ) ) );

		if ( ! empty($_GET['jobid']) )
			$_GET['logfile'] = backwpup_get_option( 'job_' . $_GET['jobid'], 'logfile' );

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab( array(
			'id'		 => 'overview',
			'title'	  => __( 'Overview' ),
			'content'	=>
			'<p>' . __( 'Here you see a working jobs or a logfile', 'backwpup' ) . '</p>'
		) );

		//add css for Admin Section
		wp_enqueue_style( 'backwpup_working', plugins_url( '', dirname( __FILE__ ) ) . '/css/working.css', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : backwpup_get_version()), 'screen' );
		//add java for Admin Section
		wp_enqueue_script( 'backwpup_working', plugins_url( '', dirname( __FILE__ ) ) . '/js/working.js', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : backwpup_get_version()), true );

	}

	public static function page() {
		?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php echo esc_html( __( 'BackWPup Working', 'backwpup' ) ); ?></h2>
		<?php
		$backwpup_message = '';
		$backupdata       = backwpup_get_workingdata();
		if ( ! empty($backupdata) ) {
			$backwpup_message .= sprintf( __( 'Job "%s" is running.', 'backwpup' ), backwpup_get_option( $backupdata['JOBMAIN'], 'name' ) );
			$backwpup_message .= " <a class=\"submitdelete\" href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=abort', 'abort-job' ) . "\">" . __( 'Abort!', 'backwpup' ) . "</a>";
		}
		if ( isset($backwpup_message) && ! empty($backwpup_message) )
			echo '<div id="message" class="updated"><p>' . $backwpup_message . '</p></div>';
		if ( ! empty($backupdata) ) {
			wp_nonce_field( 'backwpupworking_ajax_nonce', 'backwpupworkingajaxnonce', false );
			//read logfile
			if ( file_exists( $backupdata['LOGFILE'] ) && strtolower( substr( $backupdata['LOGFILE'], - 3 ) ) == '.gz' )
				$logfiledata = gzfile( $backupdata['LOGFILE'] );
			else
				$logfiledata = file( $backupdata['LOGFILE'] );
			echo "<input type=\"hidden\" name=\"logfile\" id=\"logfile\" value=\"" . $backupdata['LOGFILE'] . "\">";
			echo "<input type=\"hidden\" name=\"logpos\" id=\"logpos\" value=\"" . count( $logfiledata ) . "\">";
			echo "<div id=\"showworking\">";
			$header = true;
			foreach ( $logfiledata as $line ) {
				$line = trim( $line );
				if ( strripos( $line, '<body' ) !== false ) { //find end of header
					$header = false;
					continue;
				}
				if ( $header ) // jump over header
					continue;
				echo $line . "\n";
			}
			echo "</div>";
			echo "<div id=\"runniginfos\">";
			$stylewarning = " style=\"display:none;\"";
			if ( $backupdata['WARNING'] > 0 )
				$stylewarning = "";
			echo "<span id=\"warningsid\"" . $stylewarning . ">" . __( 'Warnings:', 'backwpup' ) . " <span id=\"warnings\">" . $backupdata['WARNING'] . "</span></span><br/>";
			$styleerror = " style=\"display:none;\"";
			if ( $backupdata['ERROR'] > 0 )
				$styleerror = "";
			echo "<span id=\"errorid\"" . $styleerror . ">" . __( 'Errors:', 'backwpup' ) . " <span id=\"errors\">" . $backupdata['ERROR'] . "</span></span>";
			echo "<div>";
			echo "<div class=\"clear\"></div>";
			echo "<div class=\"progressbar\"><div id=\"progressstep\" style=\"width:" . $backupdata['STEPSPERSENT'] . "%;\">" . $backupdata['STEPSPERSENT'] . "%</div></div>";
			echo "<div class=\"progressbar\"><div id=\"progresssteps\" style=\"width:" . $backupdata['STEPPERSENT'] . "%;\">" . $backupdata['STEPPERSENT'] . "%</div></div>";
		} elseif ( ! empty($_GET['logfile']) && file_exists( $_GET['logfile'] ) ) {
			echo '<div id="showlogfile">';
			//read logfile
			$logfile     = $_GET['logfile'];
			$logfiledata = array();
			if ( file_exists( $logfile ) && strtolower( substr( $logfile, - 3 ) ) == '.gz' )
				$logfiledata = gzfile( $logfile );
			elseif ( file_exists( $logfile ) )
				$logfiledata = file( $logfile );
			$header = true;
			foreach ( $logfiledata as $line ) {
				$line = trim( $line );
				if ( strripos( $line, '<body' ) !== false ) { //find header end
					$header = false;
					continue;
				}
				if ( $header ) // jump over header
					continue;
				if ( $line != '</body>' && $line != '</html>' && ! $header ) //no Footer
					echo $line . "\n";
			}
			echo "</div>";
			echo "<div class=\"clear\"></div>";
		}
		?>
	</div>
	<?php
	}
}


