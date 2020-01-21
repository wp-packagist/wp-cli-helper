<?php

class WP_CLI_FileSystem {
	/**
	 * Check is Writable file or Folder
	 *
	 * @param $path
	 * @return array
	 */
	public static function is_writable( $path ) {

		// Normalize Path
		$path = self::normalize_path( $path );

		// Check Exist File or Folder
		if ( ! file_exists( $path ) ) {
			return array( 'status' => false, 'message' => "The '$path' path not found." );
		}

		// Check Is Writable File or Folder
		if ( @is_writable( $path ) ) {
			return array( 'status' => true, 'normalize-path' => $path, 'info' => pathinfo( $path ), 'type' => ( is_dir( $path ) ? 'dir' : 'file' ), 'size' => ( is_file( $path ) ? filesize( $path ) : 0 ) );
		}

		// Return Error
		return array( 'status' => false, 'message' => "Permission denied.The '$path' path not writable." );
	}

	/**
	 * Remove File
	 *
	 * @param $path
	 * @return array
	 */
	public static function remove_file( $path ) {

		// Check Writable File
		$is_writable = self::is_writable( $path );
		if ( $is_writable['status'] === false ) {
			return $is_writable;
		}

		// Check File or Folder
		if ( $is_writable['type'] == "dir" ) {
			return array( 'status' => false, 'message' => ".The '$path' must be file." );
		}

		// Remove File
		if ( @unlink( $is_writable['normalize-path'] ) === true ) {
			return array( 'status' => true );
		} else {
			return array( 'status' => false, 'message' => "The '$path' file could not be deleted." );
		}
	}

	/**
	 * Remove Complete Folder with all files.
	 *
	 * @param $dir
	 * @param bool $remove_folder
	 * @param array $except
	 * @return array
	 */
	public static function remove_dir( $dir, $remove_folder = false, $except = array() ) {

		// Check Writable File
		$is_writable = self::is_writable( $dir );
		if ( $is_writable['status'] === false ) {
			return $is_writable;
		}

		// Check File or Folder
		if ( $is_writable['type'] == "file" ) {
			return array( 'status' => false, 'message' => "The '$dir' must be dir." );
		}

		//rmdir function
		$rmdir = function ( $path ) {
			if ( ! @rmdir( $path ) ) {
				$rmdir_error_array = error_get_last();
				return array( 'status' => false, 'message' => 'Cannot remove directory. ' . $rmdir_error_array['message'] );
			}

			return array( 'status' => true );
		};

		// Get List Of file and Folder this path
		$di = new \RecursiveDirectoryIterator( $is_writable['normalize-path'], \FilesystemIterator::SKIP_DOTS );
		$ri = new \RecursiveIteratorIterator( $di, \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $ri as $file ) {

			// Check file in except
			$_removed = true;
			foreach ( $except as $path ) {
				if ( trim( str_replace( $dir, "", self::normalize_path( $file ) ), "/" ) == trim( $path, "/" ) ) {
					$_removed = false;
					break;
				}
			}

			// Remove Action
			if ( $_removed === true ) {
				if ( $file->isDir() ) {
					$remove = $rmdir( $file );
				} else {
					$remove = self::remove_file( $file );
				}

				// Check Error
				if ( $remove['status'] === false ) {
					return $remove;
				}
			}
		}

		// Check Removed Folder
		if ( $remove_folder ) {
			$rmdir( $dir );
		}

		return array( 'status' => true );
	}

	/**
	 * Create Folder
	 *
	 * @param $name
	 * @param $path
	 * @param int $permission
	 * @return array
	 */
	public static function create_dir( $name, $path, $permission = 0755 ) {

		// Check Writable Folder
		$is_writable = self::is_writable( $path );
		if ( ! $is_writable['status'] ) {
			return $is_writable;
		}

		// Prepare Path
		$dir_path = self::path_join( $is_writable['normalize-path'], $name );

		// Create directory
		if ( ! @mkdir( $dir_path, $permission, true ) ) {
			$mkdirErrorArray = error_get_last();
			return array( 'status' => false, 'message' => 'Cannot create directory. ' . $mkdirErrorArray['message'] );
		}

		return array( 'status' => true, 'path' => $dir_path );
	}

	/**
	 * Complete Move File Or Folders
	 *
	 * @param $old_name
	 * @param $new_name
	 * @return bool
	 */
	public static function move( $old_name, $new_name ) {

		// Returns a parent directory's path (operates naively on the input string, and is not aware of the actual filesystem)
		$targetDir = dirname( $new_name );

		// here $targetDir is "/wp-content/xx/xx/x"
		if ( ! file_exists( $targetDir ) ) {
			mkdir( $targetDir, 0777, true ); // third parameter "true" allows the creation of nested directories
		}

		return rename( rtrim( $old_name, "/" ), rtrim( $new_name, "/" ) );
	}

	/**
	 * Rename File or Folder
	 *
	 * @param $from_path
	 * @param $to_path
	 * @return bool
	 */
	public static function rename( $from_path, $to_path ) {
		$rename = rename( self::normalize_path( $from_path ), self::normalize_path( $to_path ) );
		if ( $rename === true ) {
			return true;
		}
		return false;
	}

	/**
	 * Get list of file From Directory
	 *
	 * @param $dir
	 * @param bool $sub_folder
	 * @param array $results
	 * @return array
	 */
	public static function get_dir_contents( $dir, $sub_folder = false, &$results = array() ) {

		// Check Folder
		$is_writable = self::is_writable( $dir );
		if ( ! $is_writable['status'] ) {
			return $is_writable;
		}

		// Scan Dir
		$files = scandir( $dir );

		// Push All files and Folder To Array
		foreach ( $files as $key => $value ) {

			// Get RealPath
			$path = realpath( $dir . DIRECTORY_SEPARATOR . $value );

			// if is file push to array
			if ( ! is_dir( $path ) ) {
				$results[] = $path;

			} else if ( $value != "." && $value != ".." ) {

				// If Active Sub-folders run again
				if ( $sub_folder == true ) {
					self::get_dir_contents( $path, true, $results );
				}

				// else push dir path to list
				$results[] = $path;
			}
		}

		return $results;
	}

	/**
	 * Get Size Format File
	 *
	 * @see https://developer.wordpress.org/reference/functions/size_format/
	 * @param $value
	 * @param int $decimals
	 * @return string
	 */
	public static function size_format( $value, $decimals = 0 ) {
		if ( is_numeric( $value ) ) {
			$file_size = $value;
		} else {
			$file_size = filesize( self::normalize_path( $value ) );
		}

		$KB_IN_BYTES = 1024;
		$MB_IN_BYTES = 1024 * $KB_IN_BYTES;
		$GB_IN_BYTES = 1024 * $MB_IN_BYTES;
		$TB_IN_BYTES = 1024 * $GB_IN_BYTES;
		$Quantity    = array(
			'TB' => $TB_IN_BYTES,
			'GB' => $GB_IN_BYTES,
			'MB' => $MB_IN_BYTES,
			'KB' => $KB_IN_BYTES,
			'B'  => 1,
		);
		if ( 0 === $file_size ) {
			return number_format( 0, $decimals ) . ' B';
		}
		foreach ( $Quantity as $unit => $mag ) {
			if ( doubleval( $file_size ) >= $mag ) {
				return number_format( $file_size / $mag, $decimals ) . ' ' . $unit;
			}
		}

		return false;
	}

	/**
	 * Join two filesystem paths together
	 *
	 * @see https://developer.wordpress.org/reference/functions/path_join/
	 * @param $base
	 * @param $path
	 * @return string
	 */
	public static function path_join( $base, $path ) {
		return rtrim( WP_CLI_Util::backslash_to_slash( $base ), '/' ) . '/' . ltrim( WP_CLI_Util::backslash_to_slash( $path ), '/' );
	}

	/**
	 * Normalize Path
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_normalize_path/
	 * @param $path
	 * @return mixed|null|string|string[]
	 */
	public static function normalize_path( $path ) {
		$path = WP_CLI_Util::backslash_to_slash( $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		return $path;
	}

	/**
	 * Create Zip Archive from single file
	 *
	 * @param array $args
	 * @return array
	 */
	public static function zip_archive_file( $args = array() ) {

		// Prepare Default Params
		$default = array(
			'new_name' => '',
			'saveTo'   => '',
			'filepath' => ''
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Check SaveTo in Current Directory
		if ( empty( $arg['saveTo'] ) ) {
			$arg['saveTo'] = WP_CLI_Util::getcwd();
		}

		// Check Writable
		$is_writable = self::is_writable( $arg['saveTo'] );
		if ( ! $is_writable['status'] ) {
			return $is_writable;
		}

		// Check Active Zip Extension
		if ( ! extension_loaded( 'zip' ) ) {
			return array( 'status' => false, 'message' => 'Please install/enable the Zip PHP extension.' );
		}

		// Check Exist File
		if ( ! file_exists( $arg['filepath'] ) ) {
			return array( 'status' => false, 'message' => 'File path not found.' );
		}

		// Check Path is file
		if ( ! is_file( $arg['filepath'] ) ) {
			return array( 'status' => false, 'message' => 'Path contain a folder.' );
		}

		// Prepare Save To
		$file_name = basename( $arg['filepath'] );
		if ( ! empty( $arg['new_name'] ) ) {
			$file_name = preg_replace( '/[^a-zA-Z0-9-_. ]/', '', $arg['new_name'] );
		}

		// Check Zip Extension
		$saveTo     = WP_CLI_FileSystem::path_join( $arg['saveTo'], $file_name );
		$saveToInfo = pathinfo( $saveTo );
		if ( isset( $saveToInfo['extension'] ) and $saveToInfo['extension'] != 'zip' ) {
			$saveTo = WP_CLI_FileSystem::path_join( $arg['saveTo'], $file_name . '.zip' );
		}

		// Create Zip Archive
		$zip = new \ZipArchive();
		if ( @$zip->open( $saveTo, ZIPARCHIVE::CREATE ) !== true ) {
			return array( 'status' => false, 'message' => 'ZIP creation failed at this time.' );
		}

		// Added File To Zip
		$zip->addFile( $arg['filepath'], basename( $arg['filepath'] ) );

		// Close Zip File
		$zip->close();

		// Return
		return array( 'status' => true, 'zip_path' => $saveTo, 'name' => basename( $saveTo ), 'size' => filesize( $saveTo ) );
	}

	/**
	 * Create Zip Archive
	 *
	 * @param array $args
	 * @return array
	 */
	public static function create_zip( $args = array() ) {

		// Prepare Default Params
		$default = array(
			'source'     => '', //ABSPATH
			'saveTo'     => WP_CLI_Util::getcwd(),
			'new_name'   => '', //file.zip
			'baseFolder' => false,
			'except'     => array() //array("about.php", "css/about.css", "images/")
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Check Writable
		$is_writable = self::is_writable( $arg['saveTo'] );
		if ( ! $is_writable['status'] ) {
			return $is_writable;
		}

		// Check Active Zip Extension
		if ( ! extension_loaded( 'zip' ) ) {
			return array( 'status' => false, 'message' => 'Please install/enable the Zip PHP extension.' );
		}

		// Check Exist File
		if ( ! file_exists( $arg['source'] ) ) {
			return array( 'status' => false, 'message' => 'File path not found.' );
		}

		// Check Path is dir
		if ( ! is_dir( $arg['source'] ) ) {
			return array( 'status' => false, 'message' => 'Path contain a file.' );
		}

		// Get real path for our folder
		$rootPath = realpath( $arg['source'] );

		// Prepare Save To
		$file_name = basename( $arg['source'] );
		if ( ! empty( $arg['new_name'] ) ) {
			$file_name = preg_replace( '/[^a-zA-Z0-9-_. ]/', '', $arg['new_name'] );
		}

		// Check Zip Extension
		$saveTo     = WP_CLI_FileSystem::path_join( $arg['saveTo'], $file_name );
		$saveToInfo = pathinfo( $saveTo );
		if ( ( isset( $saveToInfo['extension'] ) and $saveToInfo['extension'] != 'zip' ) || ! isset( $saveToInfo['extension'] ) ) {
			$saveTo = WP_CLI_FileSystem::path_join( $arg['saveTo'], $file_name . '.zip' );
		}

		// Initialize archive object
		$zip = new \ZipArchive();
		if ( @$zip->open( $saveTo, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			return array( 'status' => false, 'message' => 'ZIP creation failed at this time.' );
		}

		// Create recursive directory iterator
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $rootPath ), \RecursiveIteratorIterator::LEAVES_ONLY );

		// Push List File To folder
		foreach ( $files as $name => $file ) {

			// Skip directories (they would be added automatically)
			if ( ! $file->isDir() ) {

				// Get real and relative path for current file
				$filePath     = $file->getRealPath();
				$relativePath = substr( $filePath, strlen( $rootPath ) + 1 );

				//Check except Files Or dir
				$in_zip = true;
				if ( count( $arg['except'] ) > 0 ) {
					foreach ( $arg['except'] as $path ) {

						//Check is file or dir
						if ( is_file( $path ) ) {
							if ( in_array( self::normalize_path( $relativePath ), $arg['except'] ) ) {
								$in_zip = false;
							}
						} else {

							//Check is dir
							$str_len = strlen( $path );
							if ( substr( self::normalize_path( $relativePath ), 0, $str_len ) == $path ) {
								$in_zip = false;
							}
						}
					}
				}

				// Check file Exclude From Zip
				if ( $in_zip === true ) {

					//Check if base Folder
					if ( $arg['baseFolder'] != false ) {
						$relativePath = $arg['baseFolder'] . "/" . $relativePath;
					}

					// Add current file to archive
					$zip->addFile( $filePath, $relativePath );
				}
			}
		}

		// Zip archive will be created only after closing object
		$zip->close();

		// Return
		return array( 'status' => true, 'zip_path' => $saveTo, 'name' => basename( $saveTo ), 'size' => filesize( $saveTo ) );
	}

	/**
	 * Unzip File
	 *
	 * @param $file_path
	 * @param bool $path_to_unzip | without zip file name
	 * @return bool
	 * @throws \WP_CLI\ExitException
	 * @example unzip("/wp-content/3.zip", "/wp-content/test/");
	 * We Don`t Use https://developer.wordpress.org/reference/functions/unzip_file/
	 */
	public static function unzip( $file_path, $path_to_unzip = false ) {

		// Check enable Zip Extension
		if ( ! extension_loaded( 'zip' ) ) {
			WP_CLI_Helper::error( "Please install/enable the Zip PHP extension." );
			return false;
		}
		$file = self::normalize_path( $file_path );

		// get the absolute path to $file
		if ( $path_to_unzip === false ) {
			$path = pathinfo( realpath( $file ), PATHINFO_DIRNAME );
		} else {
			$path = self::normalize_path( $path_to_unzip );
		}

		$zip = new \ZipArchive;
		$res = $zip->open( $file );
		if ( $res === true ) {
			// extract it to the path we determined above
			$zip->extractTo( $path );
			$zip->close();
		} else {
			WP_CLI_Helper::error( "Zip file not found." );
			return false;
		}

		return true;
	}

	/**
	 * Clean Folder name
	 *
	 * @param $name
	 * @param string $allowed_character
	 * @return string
	 */
	public static function sanitize_folder_name( $name, $allowed_character = '' ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9-_.' . $allowed_character . ']/', '', $name ) );
	}

	/**
	 * Load Mustache Machine
	 *
	 * @param string $ext
	 * @param string $template_path
	 * @return \Mustache_Engine
	 */
	public static function load_mustache( $template_path = '', $ext = '.mustache' ) {
		return new \Mustache_Engine( array(
			'loader' => new \Mustache_Loader_FilesystemLoader( $template_path, array( 'extension' => $ext ) ),
		) );
	}

	/**
	 * create file with content, and create folder structure if doesn't exist
	 *
	 * @param $file_path
	 * @param $data
	 * @return array|bool
	 */
	public static function file_put_content( $file_path, $data ) {
		try {
			$isInFolder = preg_match( "/^(.*)\/([^\/]+)$/", $file_path, $file_path_match );
			if ( $isInFolder ) {
				$folderName = $file_path_match[1];
				if ( ! is_dir( $folderName ) ) {

					// Create Folder
					if ( ! @mkdir( $folderName, 0777, true ) ) {
						$mkdirErrorArray = error_get_last();
						return array( 'status' => false, 'message' => 'Cannot create directory. ' . $mkdirErrorArray['message'] );
					}
				}
			}
			// File Put Content
			file_put_contents( $file_path, $data, LOCK_EX );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Checks if a folder exist
	 *
	 * @param $folder
	 * @return bool
	 */
	public static function folder_exist( $folder ) {
		$path = $folder;
		if ( function_exists( "realpath" ) ) {
			$path = realpath( $folder );
		}

		// If it exist, check if it's a directory
		return ( $path !== false AND is_dir( $path ) ) ? true : false;
	}

	/**
	 * Sort Dir By Date
	 *
	 * @param $dir
	 * @param string $sort
	 * @param array $disagree_type
	 *
	 * @return array
	 */
	public static function sort_dir_by_date( $dir, $sort = "DESC", $disagree_type = array( ".php" ) ) {

		$ignored = array( '.', '..', '.svn', '.htaccess' );
		$files   = array();
		foreach ( scandir( $dir ) as $file ) {
			if ( in_array( $file, $ignored ) ) {
				continue;
			}
			if ( count( $disagree_type ) > 0 ) {
				foreach ( $disagree_type as $type ) {
					if ( strlen( stristr( $file, $type ) ) > 0 ) {
						continue 2;
					}
				}
			}
			$files[ $file ] = filemtime( $dir . '/' . $file );
		}

		if ( $sort == "DESC" ) {
			arsort( $files, SORT_NUMERIC );
		} else {
			asort( $files, SORT_NUMERIC );
		}

		$files = array_keys( $files );
		return $files;
	}

	/**
	 * Search , Replace in File
	 *
	 * @param $file_path
	 * @param array $search
	 * @param array $replace
	 * @param bool $is_save
	 * @param string $which_line
	 * @return array|string
	 */
	public static function search_replace_file( $file_path, $search = array(), $replace = array(), $is_save = true, $which_line = '' ) {

		//Check Exist file
		if ( file_exists( $file_path ) and is_dir( $file_path ) === false ) {
			$lines    = file( $file_path );
			$new_line = array();
			$z        = 1;
			foreach ( $lines as $l ) {
				if ( $which_line != "" ) {
					if ( $z == $which_line ) {
						$new = str_replace( $search, $replace, $l );
					} else {
						$new = $l;
					}
				} else {
					$new = str_replace( $search, $replace, $l );
				}

				$new_line[] = $new;
				$z ++;
			}

			//Save file
			if ( $is_save ) {
				self::file_put_content( $file_path, $new_line );
			}

			return $new_line;
		}

		return false;
	}

	/**
	 * Read Json File
	 *
	 * @param $file_path
	 * @return bool|array
	 */
	public static function read_json_file( $file_path ) {

		//Check Exist File
		if ( ! file_exists( self::normalize_path( $file_path ) ) ) {
			return false;
		}

		//Check readable
		if ( ! is_readable( self::normalize_path( $file_path ) ) ) {
			return false;
		}

		//Read File
		$strJson = file_get_contents( $file_path );
		$array   = json_decode( $strJson, true );
		if ( $array === null ) {
			return false;
		}

		return $array;
	}

	/**
	 * Create Json File
	 *
	 * @param $file_path
	 * @param $array
	 * @param bool $JSON_PRETTY
	 * @return bool
	 */
	public static function create_json_file( $file_path, $array, $JSON_PRETTY = true ) {

		//Prepare Data
		if ( $JSON_PRETTY ) {
			$data = json_encode( $array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK );
		} else {
			$data = json_encode( $array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK );
		}

		//Save To File
		if ( self::file_put_content( $file_path, $data ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check File Age
	 *
	 * @param $path
	 * @param $hour
	 * @return bool
	 */
	public static function check_file_age( $path, $hour ) {

		//Get Now Time
		$now    = time();
		$second = 60 * 60 * $hour;

		//Check File Age
		if ( $now - filemtime( $path ) >= $second ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert array to Yaml
	 *
	 * @param $array
	 * @return string
	 */
	public static function array_to_yaml( $array ) {
		$YAML = new \Spyc();
		return $YAML->YAMLDump( $array );
	}

	/**
	 * Load Yaml File and Convert to Array
	 *
	 * @param $file_path
	 * @return array|bool
	 */
	public static function load_yaml_file( $file_path ) {
		if ( file_exists( $file_path ) and pathinfo( $file_path, PATHINFO_EXTENSION ) == "yml" ) {
			$YAML = new \Spyc();
			return $YAML->YAMLLoad( $file_path );
		}

		return false;
	}

}
