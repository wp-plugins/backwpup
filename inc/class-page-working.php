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
	}

	/**
	 *
	 * Output javascript
	 *
	 * @return nothing
	 */
	public static function javascript() {
		wp_enqueue_script( 'backwpup_working', plugins_url( '', dirname( __FILE__ ) ) . '/js/working.js', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), true );
		wp_localize_script('backwpup_working','BackWPup',array('ajaxurl'=>plugins_url( '', dirname( __FILE__ ) ) . '/ajax.php','abspath'=>ABSPATH));
	}

	/**
	 *
	 * Output css
	 *
	 * @return nothing
	 */
	public static function css() {
		wp_enqueue_style( 'backwpup_working', plugins_url( '', dirname( __FILE__ ) ) . '/css/working.css', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), 'screen' );
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
			if ( file_exists( $backupdata['LOGFILE'] ) && strtolower( substr( $backupdata['LOGFILE'], - 3 ) ) == '.gz' ) {
				$gzlogfiledata = gzfile(  $backupdata['LOGFILE'] );
				$logfiledata = implode('',$gzlogfiledata);
			} else {
				$logfiledata = file_get_contents( $backupdata['LOGFILE'],false,NULL,0 );
			}
			preg_match('/<body[^>]*>/si',$logfiledata,$match);
			if (!empty($match[0]))
				$startpos=strpos($logfiledata,$match[0])+strlen($match[0]);
			else
				$startpos=0;
			$endpos=stripos($logfiledata,'</body>');
			if (empty($endpos))
				$endpos=strlen( $logfiledata );
			$length=strlen( $logfiledata )-(strlen( $logfiledata )-$endpos)-$startpos;
			echo "<input type=\"hidden\" name=\"logfile\" id=\"logfile\" value=\"" . $backupdata['LOGFILE'] . "\">";
			echo "<input type=\"hidden\" name=\"logpos\" id=\"logpos\" value=\"" . strlen( $logfiledata ) . "\">";
			echo "<div id=\"showworking\">";
			echo  substr($logfiledata,$startpos,$length);
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
			if ( file_exists( $logfile ) && strtolower( substr( $logfile, - 3 ) ) == '.gz' ) {
				$gzlogfiledata = gzfile( $logfile );
				$logfiledata = implode('',$gzlogfiledata);
			} elseif ( file_exists( $logfile ) ) {
				$logfiledata = file_get_contents( $logfile,false,NULL,0 );
			}
			preg_match('/<body[^>]*>/si',$logfiledata,$match);
			if (!empty($match[0]))
				$startpos=strpos($logfiledata,$match[0])+strlen($match[0]);
			else
				$startpos=0;
			$endpos=stripos($logfiledata,'</body>');
			if (false === $endpos)
				$endpos=strlen( $logfiledata );
			$length=strlen( $logfiledata )-(strlen( $logfiledata )-$endpos)-$startpos;
			echo substr($logfiledata,$startpos,$length);
			echo "</div>";
			echo "<div class=\"clear\"></div>";
		}
		?>
	</div>
	<?php
	}
}