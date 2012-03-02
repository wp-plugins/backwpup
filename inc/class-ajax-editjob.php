<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 * Class for Ajax job edit	page
 */
class BackWPup_Ajax_Editjob {

	/**
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function cron_text( $args = '' ) {
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			if ( empty($_POST['cronminutes']) || $_POST['cronminutes'][0] == '*' ) {
				if ( ! empty($_POST['cronminutes'][1]) )
					$_POST['cronminutes'] = array( '*/' . $_POST['cronminutes'][1] );
				else
					$_POST['cronminutes'] = array( '*' );
			}
			if ( empty($_POST['cronhours']) || $_POST['cronhours'][0] == '*' ) {
				if ( ! empty($_POST['cronhours'][1]) )
					$_POST['cronhours'] = array( '*/' . $_POST['cronhours'][1] );
				else
					$_POST['cronhours'] = array( '*' );
			}
			if ( empty($_POST['cronmday']) || $_POST['cronmday'][0] == '*' ) {
				if ( ! empty($_POST['cronmday'][1]) )
					$_POST['cronmday'] = array( '*/' . $_POST['cronmday'][1] );
				else
					$_POST['cronmday'] = array( '*' );
			}
			if ( empty($_POST['cronmon']) || $_POST['cronmon'][0] == '*' ) {
				if ( ! empty($_POST['cronmon'][1]) )
					$_POST['cronmon'] = array( '*/' . $_POST['cronmon'][1] );
				else
					$_POST['cronmon'] = array( '*' );
			}
			if ( empty($_POST['cronwday']) || $_POST['cronwday'][0] == '*' ) {
				if ( ! empty($_POST['cronwday'][1]) )
					$_POST['cronwday'] = array( '*/' . $_POST['cronwday'][1] );
				else
					$_POST['cronwday'] = array( '*' );
			}
			$cronstamp = implode( ",", $_POST['cronminutes'] ) . ' ' . implode( ",", $_POST['cronhours'] ) . ' ' . implode( ",", $_POST['cronmday'] ) . ' ' . implode( ",", $_POST['cronmon'] ) . ' ' . implode( ",", $_POST['cronwday'] );
			$ajax      = true;
		}
		echo '<div id="cron-text">';
		_e( 'Working as <a href="http://wikipedia.org/wiki/Cron" target="_blank">Cron</a> job schedule:', 'backwpup' );
		echo ' <i><b><nobr>' . $cronstamp . '</nobr></b></i><br />';
		list($cronstr['minutes'], $cronstr['hours'], $cronstr['mday'], $cronstr['mon'], $cronstr['wday']) = explode( ' ', $cronstamp, 5 );
		if ( false !== strpos( $cronstr['minutes'], '*/' ) || ($cronstr['minutes'] == '*') ) {
			$repeatmins = str_replace( '*/', '', $cronstr['minutes'] );
			if ( $repeatmins == '*' || empty($repeatmins) )
				$repeatmins = 5;
			echo '<span style="color:red;">' . str_replace( '%d', $repeatmins, __( 'ATTENTION: Job runs every %d mins.!!!', 'backwpup' ) ) . '</span><br />';
		}
		if ( false !== strpos( $cronstr['hours'], '*/' ) || ($cronstr['hours'] == '*') ) {
			$repeathouer = str_replace( '*/', '', $cronstr['hours'] );
			if ( $repeathouer == '*' || empty($repeathouer) )
				$repeathouer = 1;
			echo '<span style="color:red;">' . str_replace( '%d', $repeathouer, __( 'ATTENTION: Job runs every %d houers.!!!', 'backwpup' ) ) . '</span><br />';
		}
		$nextrun = BackWPup_Cron::cron_next( $cronstamp );
		if ( 2147483647 == $nextrun ) {
			echo '<span style="color:red;">' . __( 'ATTENTION: Can\'t calculate cron!!!', 'backwpup' ) . '</span><br />';
		} else {
			_e( 'Next runtime:', 'backwpup' );
			echo ' <b>' . date_i18n( 'D, j M Y, H:i', BackWPup_Cron::cron_next( $cronstamp ) ) . '</b>';
		}
		echo "</div>";
		if ( $ajax )
			die();
		else
			return;
	}


	public static function db_tables( $args = '') {
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$dbname  = $_POST['dbname'];
			$dbhost  = $_POST['dbhost'];
			$dbuser   = $_POST['dbuser'];
			$dbpassword = $_POST['dbpassword'];
			$jobmain = $_POST['jobmain'];
			$ajax          = true;
		}
		$dbconnection=@mysql_connect($dbhost,$dbuser,backwpup_decrypt($dbpassword));
		if (!$dbconnection or empty($dbname)) {
			echo '<div id="dbtables"></div>';
			if ( $ajax )
				die();
			else
				return;
		}
		$res = mysql_query( 'SHOW FULL TABLES FROM `' . $dbname . '`', $dbconnection );
		echo '<div id="dbtables">';
		while ( $table = mysql_fetch_row($res) ) {
			$tabletype='';
			if ($table[1]!='BASE TABLE')
				$tabletype=' <i>(' . strtolower($table[1]) . ')</i>';
			echo '	<input class="checkbox" type="checkbox"' . checked( ! in_array( $table[0], backwpup_get_option( $jobmain, 'dbexclude' ) ), true, false ) . ' name="jobtabs[]" value="' . rawurlencode( $table[0] ) . '"/> ' . $table[0] . $tabletype.'<br />';
		}
		echo '</div>';
		if ( $ajax )
			die();
		else
			return;
	}

	public static function db_databases( $args = '') {
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$dbselected  = (isset($_POST['dbname'])) ? $_POST['dbname']:'';
			$dbhost  = $_POST['dbhost'];
			$dbuser   = $_POST['dbuser'];
			$dbpassword = $_POST['dbpassword'];
			$ajax          = true;
		}
		$dbconnection=@mysql_connect($dbhost,$dbuser,backwpup_decrypt($dbpassword),true);
		if (!$dbconnection) {
			echo '<span id="dbname" style="color:red;">'.__('No DB connection!','backwpup').'</span>';
			if ( $ajax )
				die();
			else
				return;
		}
		$res = mysql_query( 'SHOW DATABASES',$dbconnection );
		echo '<select id="dbname" name="dbname">';
		while ($db = mysql_fetch_array($res) ) {
			echo '<option' . selected( $db['Database'], $dbselected, false ) . ' value="' . $db['Database'] . '">' . $db['Database'] . '</option>';
		}
		echo '</select>';
		if ( $ajax )
			die();
		else
			return;
	}

	/**
	 * ajax/normal get buckets select box
	 *
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function aws_buckets( $args = '' ) {
		$error = '';
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$awsAccessKey  = $_POST['awsAccessKey'];
			$awsSecretKey  = $_POST['awsSecretKey'];
			$awsselected   = $_POST['awsselected'];
			$awsdisablessl = isset($_POST['awsdisablessl']);
			$ajax          = true;
		}
		echo '<span id="awsBucketerror" style="color:red;">';
		if ( ! empty($awsAccessKey) && ! empty($awsSecretKey) ) {
			try {
				$s3 = new AmazonS3(array( 'key'				  => $awsAccessKey,
										  'secret'			   => $awsSecretKey,
										  'certificate_authority'=> true ));
				if ( ! empty($awsdisablessl) )
					$s3->disable_ssl( true );
				$buckets = $s3->list_buckets();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}
		if ( empty($awsAccessKey) )
			_e( 'Missing access key!', 'backwpup' );
		elseif ( empty($awsSecretKey) )
			_e( 'Missing secret access key!', 'backwpup' );
		elseif ( ! empty($error) )
			echo $error;
		elseif ( $buckets->status < 200 || $buckets->status >= 300 )
			echo $buckets->status . ': ' . $buckets->body->Message;
		elseif ( ! isset($buckets) || count( $buckets->body->Buckets->Bucket ) < 1 )
			_e( 'No bucket fount!', 'backwpup' );
		echo '<br /></span>';
		if ( isset($buckets) && is_object( $buckets->body->Buckets->Bucket ) ) {
			echo '<select name="awsBucket" id="awsBucket">';
			foreach ( $buckets->body->Buckets->Bucket as $bucket ) {
				echo "<option " . selected( strtolower( $awsselected ), strtolower( $bucket->Name ), false ) . ">" . $bucket->Name . "</option>";
			}
			echo '</select>';
		}
		if ( $ajax )
			die();
		else
			return;
	}


	/**
	 *
	 * ajax/normal get buckests select box
	 *
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function gstorage_buckets( $args = '' ) {
		$error = '';
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$GStorageAccessKey = $_POST['GStorageAccessKey'];
			$GStorageSecret    = $_POST['GStorageSecret'];
			$GStorageselected  = $_POST['GStorageselected'];
			$ajax              = true;
		}
		echo '<span id="GStorageBucketerror" style="color:red;">';
		if ( ! empty($GStorageAccessKey) && ! empty($GStorageSecret) ) {
			try {
				$gstorage = new AmazonS3(array( 'key'				  => $GStorageAccessKey,
												'secret'			   => $GStorageSecret,
												'certificate_authority'=> true ));
				$gstorage->set_hostname( 'commondatastorage.googleapis.com' );
				$gstorage->allow_hostname_override( false );
				$buckets = $gstorage->list_buckets();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}
		if ( empty($GStorageAccessKey) )
			_e( 'Missing access key!', 'backwpup' );
		elseif ( empty($GStorageSecret) )
			_e( 'Missing secret access key!', 'backwpup' );
		elseif ( ! empty($error) )
			echo $error;
		elseif ( $buckets->status < 200 || $buckets->status >= 300 )
			echo $buckets->status . ': ' . $buckets->body->Message;
		elseif ( count( $buckets->body->Buckets->Bucket ) < 1 )
			_e( 'No bucket fount!', 'backwpup' );
		echo '<br /></span>';
		if ( isset($buckets) && is_object( $buckets->body->Buckets->Bucket ) ) {
			echo '<select name="GStorageBucket" id="GStorageBucket">';
			foreach ( $buckets->body->Buckets->Bucket as $bucket ) {
				echo "<option " . selected( strtolower( $GStorageselected ), strtolower( $bucket->Name ), false ) . ">" . $bucket->Name . "</option>";
			}
			echo '</select>';
		}
		if ( $ajax )
			die();
		else
			return;
	}

	/**
	 *
	 * ajax/normal get Container for RSC select box
	 *
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function rsc_container( $args = '' ) {
		$error = '';
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$rscUsername = $_POST['rscUsername'];
			$rscAPIKey   = $_POST['rscAPIKey'];
			$rscselected = $_POST['rscselected'];
			$ajax        = true;
		}
		echo '<span id="rscContainererror" style="color:red;">';
		if ( ! empty($rscUsername) && ! empty($rscAPIKey) ) {
			try {
				$auth = new CF_Authentication($rscUsername, $rscAPIKey);
				$auth->authenticate();
				$conn       = new CF_Connection($auth);
				$containers = $conn->get_containers();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}
		if ( empty($rscUsername) )
			_e( 'Missing Username!', 'backwpup' );
		elseif ( empty($rscAPIKey) )
			_e( 'Missing API Key!', 'backwpup' );
		elseif ( ! empty($error) )
			echo $error;
		elseif ( ! is_array( $containers ) )
			_e( "No Container's found!", 'backwpup' );
		echo '<br /></span>';
		if ( ! empty($containers) ) {
			echo '<select name="rscContainer" id="rscContainer">';
			foreach ( $containers as $container ) {
				echo "<option " . selected( strtolower( $rscselected ), strtolower( $container->name ), false ) . ">" . $container->name . "</option>";
			}
			echo '</select>';
		}
		if ( $ajax )
			die();
		else
			return;
	}

	/**
	 *
	 * ajax/normal get container select box
	 *
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function msazure_container( $args = '' ) {
		$error = '';
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$msazureHost     = $_POST['msazureHost'];
			$msazureAccName  = $_POST['msazureAccName'];
			$msazureKey      = $_POST['msazureKey'];
			$msazureselected = $_POST['msazureselected'];
			$ajax            = true;
		}
		echo '<span id="msazureContainererror" style="color:red;">';
		if ( ! empty($msazureHost) && ! empty($msazureAccName) && ! empty($msazureKey) ) {
			try {
				$storageClient = new Microsoft_WindowsAzure_Storage_Blob($msazureHost, $msazureAccName, $msazureKey);
				$Containers    = $storageClient->listContainers();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}
		if ( empty($msazureHost) )
			_e( 'Missing Hostname!', 'backwpup' );
		elseif ( empty($msazureAccName) )
			_e( 'Missing Account Name!', 'backwpup' );
		elseif ( empty($msazureKey) )
			_e( 'Missing Access Key!', 'backwpup' );
		elseif ( ! empty($error) )
			echo $error;
		elseif ( empty($Containers) )
			_e( 'No Container found!', 'backwpup' );
		echo '<br /></span>';
		if ( ! empty($Containers) ) {
			echo '<select name="msazureContainer" id="msazureContainer">';
			foreach ( $Containers as $Container ) {
				echo "<option " . selected( strtolower( $msazureselected ), strtolower( $Container->Name ), false ) . ">" . $Container->Name . "</option>";
			}
			echo '</select>';
		}
		if ( $ajax )
			die();
		else
			return;
	}

	/**
	 * ajax/normal get SugarSync roots select box
	 *
	 * @static
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function sugarsync_root( $args = '' ) {
		$error = '';
		if ( is_array( $args ) ) {
			extract( $args );
			$ajax = false;
		} else {
			check_ajax_referer( 'backwpupeditjob_ajax_nonce' );
			$sugaruser         = $_POST['sugaruser'];
			$sugarpass         = $_POST['sugarpass'];
			$sugarrootselected = $_POST['sugarrootselected'];
			$ajax              = true;
		}
		echo '<span id="sugarrooterror" style="color:red;">';
		if ( ! empty($sugarpass) && ! empty($sugaruser) ) {
			try {
				$sugarsync   = new BackWPup_Dest_SugarSync($sugaruser, backwpup_decrypt( $sugarpass ));
				$user        = $sugarsync->user();
				$syncfolders = $sugarsync->get( $user->syncfolders );
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}
		}
		if ( empty($sugaruser) )
			_e( 'Missing Username!', 'backwpup' );
		elseif ( empty($sugarpass) )
			_e( 'Missing Password!', 'backwpup' );
		elseif ( ! empty($error) )
			echo $error;
		elseif ( ! is_object( $syncfolders ) )
			_e( 'No Syncfolders found!', 'backwpup' );
		echo '<br /></span>';
		if ( isset($syncfolders) && is_object( $syncfolders ) ) {
			echo '<select name="sugarroot" id="sugarroot">';
			foreach ( $syncfolders->collection as $roots ) {
				echo "<option " . selected( strtolower( $sugarrootselected ), strtolower( $roots->ref ), false ) . " value=\"" . $roots->ref . "\">" . $roots->displayName . "</option>";
			}
			echo '</select>';
		}
		if ( $ajax )
			die();
		else
			return;
	}
}