<?php
/**
 * Class for creating File Archives
 */
class BackWPup_Create_Archive {

	/**
	 * Achieve file with full path
	 *
	 * @var string
	 */
	private $file = '';

	/**
	 * Compression method
	 *
	 * @var string Method off compression Methods are ZipArchive, PclZip, Tar, TarGz, TarBz2, gz, bz2
	 */
	private $method = '';

	/**
	 * Open handel for files.
	 */
	private $filehandel = '';

	/**
	 * class handel for ZipArchive.
	 *
	 * @var ZipArchive
	 */
	private $ziparchive = NULL;

	/**
	 * class handel for PclZip.
	 *
	 * @var PclZip
	 */
	private $pclzip = NULL;

	/**
	 * Saved encoding will restored on __destruct
	 *
	 * @var string
	 */
	private $previous_encoding = '';

	/**
	 * File cont off added files to handel somethings that depends on it
	 *
	 * @var int number of files added
	 */
	private $file_count = 0;

	/**
	 * Set archive Parameter
	 *
	 * @param $file string File with full path of the archive
	 * @throws BackWPup_Create_Archive_Exception
	 */
	public function __construct( $file ) {


		//check param
		if ( empty( $file ) )
			throw new BackWPup_Create_Archive_Exception(  __( 'The file name of an archive cannot be empty ', 'backwpup' ) );

		//set file
		$this->file = trim( $file );

		//check folder can used
		if ( ! is_dir( dirname( $this->file ) ) ||! is_writable( dirname( $this->file ) ) )
			throw new BackWPup_Create_Archive_Exception( sprintf( _x( 'Folder %s for the archive not found','%s = Folder name', 'backwpup' ), dirname( $this->file ) ) );


		//set and check method and get open handle
		if ( strtolower( substr( $this->file, -7 ) ) == '.tar.gz' ) {
			if ( ! function_exists( 'gzencode' ) )
				throw new BackWPup_Create_Archive_Exception( __( 'Functions for gz compression not available', 'backwpup' ) );
			$this->method = 'TarGz';
			$this->filehandel = fopen( $this->file, 'c+b');
			$eof_compressed = gzencode( pack( "a1024", "" ) );
			//remove tar end of file
			if ( filesize( $this->file ) > strlen( $eof_compressed ) ) {
				fseek( $this->filehandel, - strlen( $eof_compressed ), SEEK_END );
				$last_blocs = fread( $this->filehandel, strlen( $eof_compressed ) );
				//overwrite tar end of file
				if ( $last_blocs == $eof_compressed )
					fseek( $this->filehandel, - strlen( $eof_compressed ), SEEK_END );
			}
		}
		elseif ( strtolower( substr( $this->file, -8 ) ) == '.tar.bz2' ) {
			if ( ! function_exists( 'bzcompress' ) )
				throw new BackWPup_Create_Archive_Exception( __( 'Functions for bz2 compression not available', 'backwpup' ) );
			$this->method = 'TarBz2';
			$this->filehandel = fopen( $this->file, 'c+b');
			$eof_compressed = bzcompress( pack( "a1024", "" ) );
			//remove tar end of file
			if ( filesize( $this->file ) > strlen( $eof_compressed ) ) {
				fseek( $this->filehandel, - strlen( $eof_compressed ), SEEK_END );
				$last_blocs = fread( $this->filehandel, strlen( $eof_compressed ) );
				//overwrite tar end of file
				if ( $last_blocs == $eof_compressed )
					fseek( $this->filehandel, - strlen( $eof_compressed ), SEEK_END );
			}
		}
		elseif ( strtolower( substr( $this->file, -4 ) ) == '.tar' ) {
			$this->method = 'Tar';
			$this->filehandel = fopen( $this->file, 'c+b');
			//remove tar end of file
			if ( filesize( $this->file ) > 1024 ) {
				fseek( $this->filehandel, -1024, SEEK_END );
				$last_blocs = fread( $this->filehandel, 1024 );
				//overwrite tar end of file
				if ( $last_blocs == pack( "a1024", "" ) )
					fseek( $this->filehandel, -1024, SEEK_END );
			}
		}
		elseif ( strtolower( substr( $this->file, -4 ) ) == '.zip' ) {
			$this->method = 'PclZip';
			if ( class_exists( 'ZipArchive' ) ) {
				$this->method = 'ZipArchive';
				$this->ziparchive = new ZipArchive();
				$res = $this->ziparchive->open( $this->file, ZipArchive::CREATE );
				if ( $res !== TRUE )
					throw new BackWPup_Create_Archive_Exception( sprintf( _x( 'Can not create zip archive: %d','ZipArchive open() result', 'backwpup' ), $res ) );
			}
			if ( $this->get_method() == 'PclZip' && ! function_exists( 'gzencode' ) )
				throw new BackWPup_Create_Archive_Exception( __( 'Functions for gz compression not available', 'backwpup' ) );
			if( $this->get_method() == 'PclZip' ) {
				define( 'PCLZIP_TEMPORARY_DIR', BackWPup::get_plugin_data( 'TEMP' ) );
				if ( ini_get( 'mbstring.func_overload' ) && function_exists( 'mb_internal_encoding' ) ) {
					$this->previous_encoding = mb_internal_encoding();
					mb_internal_encoding( 'ISO-8859-1' );
				}
				require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
				$this->pclzip = new PclZip( $this->file );
			}
		}
		elseif ( strtolower( substr( $this->file, -3 ) ) == '.gz' ) {
			if ( ! function_exists( 'gzencode' ) )
				throw new BackWPup_Create_Archive_Exception( __( 'Functions for gz compression not available', 'backwpup' ) );
			$this->method = 'gz';
			$this->filehandel = fopen( 'compress.zlib://' . $this->file, 'wb');
		}
		elseif ( strtolower( substr( $this->file, -4 ) ) == '.bz2' ) {
			if ( ! function_exists( 'bzcompress' ) )
				throw new BackWPup_Create_Archive_Exception( __( 'Functions for bz2 compression not available', 'backwpup' ) );
			$this->method = 'bz2';
			$this->filehandel = fopen( 'compress.bzip2://' . $this->file, 'w');
		}
		else {
			throw new BackWPup_Create_Archive_Exception( sprintf( _x( 'Method to archive file %s not detected','%s = file name', 'backwpup' ), basename( $this->file ) ) );
		}

		//check file handle
		if ( ! empty( $this->filehandel ) && ! is_resource( $this->filehandel ) )
			throw new BackWPup_Create_Archive_Exception( __( 'Can not open archive file', 'backwpup' ) );

	}


	/**
	 * Closes open archive on shutdown.
	 */
	public function __destruct() {

		//set encoding back
		if ( ! empty( $this->previous_encoding ) )
			mb_internal_encoding( $this->previous_encoding );

		//write tar file end
		if ( $this->get_method() == 'Tar' ) {
			fwrite( $this->filehandel, pack( "a1024", "" ) );
		}
		elseif ( $this->get_method() == 'TarGz' ) {
			fwrite( $this->filehandel, gzencode( pack( "a1024", "" ) ) );
		}
		elseif ( $this->get_method() == 'TarBz2' ) {
			fwrite( $this->filehandel, bzcompress( pack( "a1024", "" ) ) );
		}

		//close PclZip Class
		if ( is_object( $this->pclzip ) ) {
			unset( $this->pclzip );
		}

		//close PclZip Class
		if ( is_object( $this->ziparchive ) ) {
			$this->ziparchive_status();
			$this->ziparchive->close();
			unset( $this->ziparchive );
		}

		//close file if open
		if ( is_resource( $this->filehandel ) )
			fclose( $this->filehandel );
	}

	/**
	 * Get method that the archive uses
	 *
	 * @return string of compression method
	 */
	public function get_method() {

		return $this->method;
	}


	/**
	 * Adds a file to Archive
	 *
	 * @param $file_name       string
	 * @param $name_in_archive string
	 * @return bool Add worked or not
	 * @throws BackWPup_Create_Archive_Exception
	 */
	public function add_file( $file_name, $name_in_archive = '' ) {

	    //check param
		if ( empty( $file_name ) )
			throw new BackWPup_Create_Archive_Exception(  __( 'File name cannot be empty', 'backwpup' ) );

		if ( ! is_file( $file_name ) || ! is_readable( $file_name ) ) {
			trigger_error( sprintf( _x( 'File %s does not exist or is not readable', 'File path to add to archive', 'backwpup' ), $file_name ), E_USER_WARNING );
			return false;
		}

		if ( empty( $name_in_archive ) )
			$name_in_archive = $file_name;

		switch ( $this->get_method() ) {
			case 'gz':
				if ( $this->file_count > 0 ) {
					trigger_error( __( 'This archive method can only add one file', 'backwpup' ), E_USER_WARNING );
					return false;
				}
				//add file to archive
				if ( ! ( $fd = fopen( $file_name, 'rb' ) ) ) {
					trigger_error( sprintf( __( 'Can not open source file %s to archive', 'backwpup' ), $file_name ), E_USER_WARNING );
					return false;
				}
				while ( ! feof( $fd ) )
					fwrite( $this->filehandel, fread( $fd, 8192 ) );
				fclose( $fd );
				break;
			case 'bz':
				if ( $this->file_count > 0 ) {
					trigger_error( __( 'This archive method can only add one file', 'backwpup' ), E_USER_WARNING );
					return false;
				}
				//add file to archive
				if ( ! ( $fd = fopen( $file_name, 'rb' ) ) ) {
					trigger_error( sprintf( __( 'Can not open source file %s to archive', 'backwpup' ), $file_name ), E_USER_WARNING );
					return false;
				}
				while ( ! feof( $fd ) )
					fwrite( $this->filehandel, bzcompress( fread( $fd, 8192 ) ) );
				fclose( $fd );
				break;
			case 'Tar':
			case 'TarGz':
			case 'TarBz2':
				if ( ! $this->tar_file( $file_name, $name_in_archive ) );
					return FALSE;
				break;
			case 'ZipArchive':
				//close and reopen, all added files are open on fs
				if ( $this->file_count >= 10 ) { //35 works with PHP 5.2.4 on win
					$this->ziparchive_status();
					$this->ziparchive->close();
					$this->ziparchive->open( $this->file, ZipArchive::CREATE );
					$this->file_count = 0;
				}
				if ( ! $this->ziparchive->addFile( $file_name, $name_in_archive ) ) {
					trigger_error( sprintf( __( 'Can not add "%s" to zip archive!', 'backwpup' ), $name_in_archive ), E_USER_ERROR );
					return false;
				}
				break;
			case 'PclZip':
					if ( 0 == $this->pclzip->add( array( array(
													   	PCLZIP_ATT_FILE_NAME          => $file_name,
														PCLZIP_ATT_FILE_NEW_FULL_NAME => $name_in_archive
														) ) )
								) {
						trigger_error( sprintf( __( 'PclZip archive add error: %s', 'backwpup' ), $this->pclzip->errorInfo( TRUE ) ), E_USER_ERROR );
						return false;
					}
				break;
		}

		$this->file_count++;

		return TRUE;
	}


	/**
	 * Output status of ZipArchive
	 */
	private function ziparchive_status() {

		if ( $this->ziparchive->status > 0 ) {
			$zip_error = $this->ziparchive->status;
			if ( $this->ziparchive->status == 1 )
				$zip_error = __( '(ER_MULTIDISK) Multi-disk zip archives not supported', 'backwpup' );
			if ( $this->ziparchive->status == 2 )
				$zip_error = __( '(ER_RENAME) Renaming temporary file failed', 'backwpup' );
			if ( $this->ziparchive->status == 3 )
				$zip_error = __( '(ER_CLOSE) Closing zip archive failed', 'backwpup' );
			if ( $this->ziparchive->status == 4 )
				$zip_error = __( '(ER_SEEK) Seek error', 'backwpup' );
			if ( $this->ziparchive->status == 5 )
				$zip_error = __( '(ER_READ) Read error', 'backwpup' );
			if ( $this->ziparchive->status == 6 )
				$zip_error = __( '(ER_WRITE) Write error', 'backwpup' );
			if ( $this->ziparchive->status == 7 )
				$zip_error = __( '(ER_CRC) CRC error', 'backwpup' );
			if ( $this->ziparchive->status == 8 )
				$zip_error = __( '(ER_ZIPCLOSED) Containing zip archive was closed', 'backwpup' );
			if ( $this->ziparchive->status == 9 )
				$zip_error = __( '(ER_NOENT) No such file', 'backwpup' );
			if ( $this->ziparchive->status == 10 )
				$zip_error = __( '(ER_EXISTS) File already exists', 'backwpup' );
			if ( $this->ziparchive->status == 11 )
				$zip_error = __( '(ER_OPEN) Can\'t open file', 'backwpup' );
			if ( $this->ziparchive->status == 12 )
				$zip_error = __( '(ER_TMPOPEN) Failure to create temporary file', 'backwpup' );
			if ( $this->ziparchive->status == 13 )
				$zip_error = __( '(ER_ZLIB) Zlib error', 'backwpup' );
			if ( $this->ziparchive->status == 14 )
				$zip_error = __( '(ER_MEMORY) Malloc failure', 'backwpup' );
			if ( $this->ziparchive->status == 15 )
				$zip_error = __( '(ER_CHANGED) Entry has been changed', 'backwpup' );
			if ( $this->ziparchive->status == 16 )
				$zip_error = __( '(ER_COMPNOTSUPP) Compression method not supported', 'backwpup' );
			if ( $this->ziparchive->status == 17 )
				$zip_error = __( '(ER_EOF) Premature EOF', 'backwpup' );
			if ( $this->ziparchive->status == 18 )
				$zip_error = __( '(ER_INVAL) Invalid argument', 'backwpup' );
			if ( $this->ziparchive->status == 19 )
				$zip_error = __( '(ER_NOZIP) Not a zip archive', 'backwpup' );
			if ( $this->ziparchive->status == 20 )
				$zip_error = __( '(ER_INTERNAL) Internal error', 'backwpup' );
			if ( $this->ziparchive->status == 21 )
				$zip_error = __( '(ER_INCONS) Zip archive inconsistent', 'backwpup' );
			if ( $this->ziparchive->status == 22 )
				$zip_error = __( '(ER_REMOVE) Can\'t remove file', 'backwpup' );
			if ( $this->ziparchive->status == 23 )
				$zip_error = __( '(ER_DELETED) Entry has been deleted', 'backwpup' );
			trigger_error( sprintf( _x( 'ZipArchive returns status: %s','Text of ZipArchive status Massage', 'backwpup' ), $zip_error ), E_USER_WARNING );
		}
	}

	/**
	 * Tar a file to archive
	 */
	private function tar_file( $file_name, $name_in_archive ) {

		//split filename larger than 100 chars
		if ( strlen( $name_in_archive ) <= 100 ) {
			$filename        = $name_in_archive;
			$filename_prefix = "";
		}
		else {
			$filename_offset = strlen( $name_in_archive ) - 100;
			$split_pos       = strpos( $name_in_archive, '/', $filename_offset );
			$filename        = substr( $name_in_archive, $split_pos + 1 );
			$filename_prefix = substr( $name_in_archive, 0, $split_pos );
			if ( strlen( $filename ) > 100 )
				trigger_error( sprintf( __( 'File name "%1$s" too long to be saved correctly in %2$s archive!', 'backwpup' ), $name_in_archive, $this->get_method() ), E_USER_WARNING );
			if ( strlen( $filename_prefix ) > 155 )
				trigger_error( sprintf( __( 'File path "%1$s" too long to be saved correctly in %2$s archive!', 'backwpup' ), $name_in_archive, $this->get_method() ), E_USER_WARNING );
		}
		//get file stat
		$file_stat = @stat( $file_name );
		//open file
		if ( ! ( $fd = fopen( $file_name, 'rb' ) ) ) {
			trigger_error( sprintf( __( 'Can not open source file %s to archive', 'backwpup' ), $file_name ), E_USER_WARNING );
			return FALSE;
		}
		//Set file user/group name if linux
		$fileowner = __( "Unknown", "backwpup" );
		$filegroup = __( "Unknown", "backwpup" );
		if ( function_exists( 'posix_getpwuid' ) ) {
			$info      = posix_getpwuid( $file_stat[ 'uid' ] );
			$fileowner = $info[ 'name' ];
			$info      = posix_getgrgid( $file_stat[ 'gid' ] );
			$filegroup = $info[ 'name' ];
		}
		// Generate the TAR header for this file
		$header = pack( "a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$filename, //name of file  100
			sprintf( "%07o", $file_stat[ 'mode' ] ), //file mode  8
			sprintf( "%07o", $file_stat[ 'uid' ] ), //owner user ID  8
			sprintf( "%07o", $file_stat[ 'gid' ] ), //owner group ID  8
			sprintf( "%011o", $file_stat[ 'size' ] ), //length of file in bytes  12
			sprintf( "%011o", $file_stat[ 'mtime' ] ), //modify time of file  12
			"        ", //checksum for header  8
			0, //type of file  0 or null = File, 5=Dir
			"", //name of linked file  100
			"ustar", //USTAR indicator  6
			"00", //USTAR version  2
			$fileowner, //owner user name 32
			$filegroup, //owner group name 32
			"", //device major number 8
			"", //device minor number 8
			$filename_prefix, //prefix for file name 155
			"" ); //fill block 12

		// Computes the unsigned Checksum of a file's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i ++ )
			$checksum += ord( substr( $header, $i, 1 ) );

		$checksum = pack( "a8", sprintf( "%07o", $checksum ) );
		$header   = substr_replace( $header, $checksum, 148, 8 );
		//write header
		if ( $this->get_method() == 'TarBz2' )
			fwrite( $this->filehandel, bzcompress( $header ) );
		elseif( $this->get_method() == 'TarGz' )
			fwrite( $this->filehandel, gzencode( $header ) );
		else
			fwrite( $this->filehandel, $header );

		// read/write files in 512K Blocks
		while ( ! feof( $fd ) ) {
			$file_data = fread( $fd, 512 );
			if ( strlen( $file_data ) > 0 ) {
				if ( $this->get_method() == 'TarBz2' )
					fwrite( $this->filehandel, bzcompress( pack( "a512", $file_data ) ) );
				elseif( $this->get_method() == 'TarGz' )
					fwrite( $this->filehandel, gzencode( pack( "a512", $file_data ) ) );
				else
					fwrite( $this->filehandel, pack( "a512", $file_data ) );
			}
		}
		fclose( $fd );

		return TRUE;
	}
}

/**
 * Exception Handler
 */
class BackWPup_Create_Archive_Exception extends Exception { }