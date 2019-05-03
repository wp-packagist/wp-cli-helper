<?php

class WP_CLI_Google_Drive {
	/**
	 * List Of Scope for User auth in Google Drive
	 *
	 * @var string
	 * @see https://developers.google.com/identity/protocols/googlescopes
	 */
	public static $Scope = "https://www.googleapis.com/auth/drive profile email";
	/**
	 * Google Drive API Url
	 *
	 * @var string
	 */
	public static $ApiUrl = "https://www.googleapis.com/drive/v3";
	/**
	 * Basic Auth Url in Google oauth Service
	 *
	 * @var string
	 */
	public static $AuthUrl = "https://www.googleapis.com/oauth2/v4";
	/**
	 * Refresh Token Url
	 *
	 * @var string
	 */
	public static $refresh_token_url = 'https://accounts.google.com/o/oauth2/token';
	/**
	 * Upload Url in Google Drive Service
	 *
	 * @var string
	 */
	public static $UploadUrl = "https://www.googleapis.com/upload/drive/v3";
	/**
	 * Default Redirect Url for PHP CLI
	 *
	 * @var string
	 */
	public static $redirect_url = "urn:ietf:wg:oauth:2.0:oob";
	/**
	 * Get User info Url
	 *
	 * @var string
	 */
	public static $user_info_url = 'https://www.googleapis.com/oauth2/v1/userinfo';
	/**
	 * Set Default Request Timeout for connect to Google API
	 *
	 * @var int
	 */
	public static $request_timeout = 300;
	/**
	 * Json Header For API Service
	 *
	 * @var array
	 */
	public static $json_header_request = array( 'Accept' => 'application/json' );
	/**
	 * Json Content type
	 *
	 * @var array
	 */
	public static $json_content_type = array( 'Content-Type' => 'application/json' );
	/**
	 * Header Auth Token
	 *
	 * @var string
	 */
	public static $auth_header = 'Bearer';
	/**
	 * Config name in WP-CLI
	 *
	 * @var string
	 */
	public static $config_name = 'gdrive';
	/**
	 * Filed Connecting error to Google API
	 *
	 * @var array
	 */
	public static $failed_connecting = array( 'error' => true, 'message' => 'Failed connecting to Google API. please try again.' );
	/**
	 * Require params for WP-CLI Config
	 *
	 * @var array
	 */
	public static $require_config_params = array( 'access_token', 'refresh_token', 'client_id', 'client_secret', 'timestamp' );
	/**
	 * Refresh Token Time
	 *
	 * @var int
	 */
	public static $refresh_token_time = 45; //Minute
	/**
	 * Folder MimeType in Google Drive
	 *
	 * @var string
	 */
	public static $folder_mime_type = "application/vnd.google-apps.folder";
	/**
	 * Public Permission ID
	 *
	 * @var string
	 */
	public static $public_permission_id = 'anyoneWithLink';
	/**
	 * Preg filename
	 *
	 * @var string
	 */
	public static $preg_filename = '/[^a-zA-Z0-9-_. ]/';

	/**
	 * Create Auth Url
	 *
	 * @param $client_ID
	 * @return string
	 */
	public static function create_auth_url( $client_ID ) {
		return "https://accounts.google.com/o/oauth2/auth?client_id=" . $client_ID . "&redirect_uri=" . self::$redirect_url . "&scope=" . urlencode( self::$Scope ) . "&response_type=code";
	}

	/**
	 * Set User Token in WP-CLI Config File
	 *
	 * @param array $arg
	 */
	public static function save_user_token_in_wp_cli_config( $arg = array() ) {

		// Load Config
		$wp_cli_config  = new WP_CLI_CONFIG( 'global' );
		$current_config = $wp_cli_config->load_config_file();

		// Add To Config Array
		foreach ( self::$require_config_params as $key ) {
			if ( isset( $arg[ $key ] ) ) {
				$current_config[ self::$config_name ][ $key ] = $arg[ $key ];
			}
		}

		// Save File
		$wp_cli_config->save_config_file( $current_config );
	}

	/**
	 * Get WP-CLI Config for Google drive
	 *
	 * @throws Exception
	 */
	public static function get_config() {
		try {
			$wp_cli_config = WP_CLI_CONFIG::get();
		} catch ( \Exception $e ) {
			$wp_cli_config = array();
		}
		if ( ! isset( $wp_cli_config[ self::$config_name ] ) || ! is_array( $wp_cli_config[ self::$config_name ] ) ) {
			return false;
		}

		return $wp_cli_config[ self::$config_name ];
	}

	/**
	 * Get User Token From WP-CLI Config
	 *
	 * @throws Exception
	 */
	public static function access_token() {
		$auth = self::auth();
		if ( $auth === true ) {
			$get_config = self::get_config();
			if ( isset( $get_config['access_token'] ) ) {
				return $get_config['access_token'];
			}
		}
		return false;
	}

	/**
	 * Check Error Response From Google API
	 *
	 * @param $request
	 * @return array
	 */
	public static function response( $request ) {

		// Convert Json to array
		$response = json_decode( $request->body, true );

		// Check Error Response
		if ( isset( $response['error'] ) || isset( $response['error_description'] ) ) {
			$data = '';
			if ( isset( $response['error'] ) ) {
				$data .= $response['error'] . ', ';
			}
			if ( isset( $response['error_description'] ) ) {
				$data .= $response['error_description'];
			}
			return array( 'error' => true, 'message' => $data );
		}

		return $response;
	}

	/**
	 * Get User Token By Code
	 *
	 * @see https://developers.google.com/identity/protocols/OAuth2WebServer
	 * @param $code
	 * @param $client_ID
	 * @param $client_server
	 * @return mixed
	 */
	public static function get_token_by_code( $code, $client_ID, $client_server ) {

		// Set Params
		$url    = self::$AuthUrl . "/token";
		$params = "code=" . $code . "&client_id=" . $client_ID . "&client_secret=" . $client_server . "&redirect_uri=" . self::$redirect_url . "&grant_type=authorization_code";

		// Request
		$request = \WP_CLI\Utils\http_request( "POST", $url, $params, self::$json_header_request, array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			return array_merge( array( 'timestamp' => time() ), self::response( $request ) );
		}

		return self::$failed_connecting;
	}

	/**
	 * Get User information by Access Token
	 *
	 * @see https://developers.google.com/identity/sign-in/web/backend-auth
	 * @param $access_token
	 * @return bool|mixed
	 */
	public static function get_user_info_by_access_token( $access_token ) {
		$request = \WP_CLI\Utils\http_request( "GET", self::$user_info_url, array( 'alt' => 'json', 'access_token' => $access_token ), self::$json_header_request, array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			return self::response( $request );
		}

		return false;
	}

	/**
	 * Get User Token By Refresh Token
	 *
	 * @see https://www.daimto.com/google-authentication-with-curl/
	 * @param $RefreshToken
	 * @param $client_ID
	 * @param $client_server
	 * @return bool|mixed
	 */
	public static function get_token_by_refresh_token( $RefreshToken, $client_ID, $client_server ) {

		// Set Params
		$params = "client_id=" . urlencode( $client_ID ) . "&client_secret=" . urlencode( $client_server ) . "&refresh_token=" . urlencode( $RefreshToken ) . "&grant_type=refresh_token";

		// Request
		$request = \WP_CLI\Utils\http_request( "POST", self::$refresh_token_url, $params, false, array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			return self::response( $request );
		}

		return false;
	}

	/**
	 * User Auth
	 *
	 * @param bool $run_in_cli
	 * @return bool
	 * @throws Exception
	 */
	public static function auth( $run_in_cli = true ) {

		// Require Parameter
		$gdrive = self::get_config();
		if ( $gdrive === false ) {
			return false;
		}
		foreach ( self::$require_config_params as $key ) {
			if ( ! isset( $gdrive[ $key ] ) || ( isset( $gdrive[ $key ] ) and empty( $gdrive[ $key ] ) ) ) {
				return false;
			}
		}

		// Check Expire Time For Refresh Token
		$now = time();
		if ( ( $gdrive['timestamp'] + ( self::$refresh_token_time * 60 ) ) <= $now ) {
			$new_token = self::get_token_by_refresh_token( $gdrive['refresh_token'], $gdrive['client_id'], $gdrive['client_secret'] );
			if ( isset( $new_token['error'] ) ) {
				return false;
			} else {
				self::save_user_token_in_wp_cli_config( array( 'access_token' => $new_token['access_token'], 'timestamp' => time() ) );
				if ( $run_in_cli ) {
					# Sleep for await to Change File config
					sleep( 3 );
				}
				return true;
			}
		}

		return true;
	}

	/**
	 * Get List Of Files and Folder From Google Drive
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_list( $args = array() ) {
		// Check Parameter
		$default = array(
			'access_token' => self::access_token(),
			/**
			 * Search Argument
			 * @see https://developers.google.com/drive/api/v3/search-files
			 */
			'q'            => "'root' in parents and trashed=false",
			'fields'       => '*',
			'corpora'      => 'user',
			'orderBy'      => 'folder,name'
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Set Params
		$url = self::$ApiUrl . "/files?corpora=" . $arg['corpora'] . "&orderBy=" . $arg['orderBy'] . "&q=" . urlencode( $arg['q'] ) . "&fields=" . $arg['fields'];

		// Request
		$request = \WP_CLI\Utils\http_request( "GET", $url, null, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ), array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			$data = self::response( $request );
			return $data['files'];
		}

		return self::$failed_connecting;
	}

	/**
	 * Get Folder or File Id by Path
	 *
	 * @param $path
	 * @return array|mixed
	 * @throws Exception
	 */
	public static function get_id_by_path( $path ) {

		// Sanitize Path and get List
		$list = array_filter( explode( "/", self::sanitize_path( $path ) ), function ( $value ) {
			return $value !== '';
		} );

		// First Get All List File in MY DRIVE
		$root_files = self::file_list();
		if ( isset( $root_files['error'] ) ) {
			return false;
		}

		// Start Nested Search
		foreach ( $list as $route ) {

			$_found = false;
			foreach ( $root_files as $file ) {
				if ( $file['name'] == $route ) {
					$route_info = array( 'id' => $file['id'], 'mimeType' => $file['mimeType'], 'name' => $file['name'] );
					$_found     = true;
					break;
				}
			}

			if ( $_found === true and isset( $route_info ) ) {
				if ( end( $list ) == $route ) {
					return $route_info;
				} else {

					$root_files = self::file_list( array(
						'q' => "'" . $route_info['id'] . "' in parents and trashed=false"
					) );
					if ( isset( $root_files['error'] ) ) {
						return false;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Sanitize Path of folder or file in Google Drive
	 *
	 * @param $path
	 * @return string
	 */
	public static function sanitize_path( $path ) {
		return trim( WP_CLI_Util::remove_double_slash( WP_CLI_Util::backslash_to_slash( trim( $path ) ) ), "/" );
	}

	/**
	 * Get information about file wit ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_get( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => '',
			'fields'       => '*'
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Set Params
		$url = self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ) . "?fields=" . $arg['fields'];

		// Request
		$request = \WP_CLI\Utils\http_request( "GET", $url, null, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ), array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}

	/**
	 * Remove File By ID.
	 * For Clear All files in trash you can set fileId = trash.
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_remove( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => '',
			'trashed'      => false
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		if ( $arg['trashed'] === false ) { # Remove Complete File

			$request = \WP_CLI\Utils\http_request( "DELETE", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ), null, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ), array( 'timeout' => self::$request_timeout ) );
			/**
			 * List Status:
			 *
			 * 204 -> Remove complete.
			 * 404 -> file not found.
			 */
			if ( $request->status_code === 404 ) {
				return array( 'error' => true, 'message' => 'Your file or folder not found.' );
			}
			if ( in_array( $request->status_code, array( 200, 202, 204, 205 ) ) ) {
				return self::response( $request );
			}

		} else {

			// Move File To Trash
			$request = \WP_CLI\Utils\http_request( "PATCH", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ), json_encode( array( 'trashed' => true ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
			if ( $request->status_code === 200 ) {
				return self::response( $request );
			}

		}

		return self::$failed_connecting;
	}

	/**
	 * Change file Permission by ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_permission( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => '',
			/**
			 * permission list : public | private
			 */
			'permission'   => 'public'
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Private File for anyone
		if ( $arg['permission'] == "private" ) {

			$request = \WP_CLI\Utils\http_request( "DELETE", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ) . "/permissions/" . self::$public_permission_id, null, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ), array( 'timeout' => self::$request_timeout ) );
			if ( $request->status_code === 404 ) {
				return array( 'error' => true, 'message' => 'Your file or folder is not public.' );
			}
			if ( in_array( $request->status_code, array( 200, 202, 204, 205 ) ) ) {
				return self::response( $request );
			}

			return self::$failed_connecting;
		} else {

			// Public File for anyone
			$permission = array(
				'fileId' => urlencode( $arg['fileId'] ),
				'role'   => 'reader',
				'type'   => 'anyone'
			);

			$request = \WP_CLI\Utils\http_request( "POST", self::$ApiUrl . '/files/' . urlencode( $arg['fileId'] ) . '/permissions', json_encode( $permission ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
			if ( 200 === $request->status_code ) {
				return self::response( $request );
			}

			return self::$failed_connecting;
		}

	}

	/**
	 * Rename a file or folder by ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_rename( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => '',
			'new_name'     => ''
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		$file_name = preg_replace( self::$preg_filename, '', $arg['new_name'] );
		$request   = \WP_CLI\Utils\http_request( "PATCH", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ), json_encode( array( 'name' => $file_name ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
		if ( $request->status_code === 200 ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}

	/**
	 * Copy File To Another Folder by ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_copy( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => '',
			'toId'         => '' // the folder ID that file copy.
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		$request = \WP_CLI\Utils\http_request( "POST", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ) . "/copy", json_encode( array( 'parents' => array( $arg['toId'] ) ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
		if ( $request->status_code === 200 ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}

	/**
	 * Move a file or folder by ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_move( $args = array() ) {

		$default = array(
			'access_token'  => self::access_token(),
			'fileId'        => '',
			'currentParent' => '',
			'toId'          => ''
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		$request = \WP_CLI\Utils\http_request( "PATCH", self::$ApiUrl . "/files/" . $arg['fileId'] . "?addParents=" . $arg['toId'] . "&removeParents=" . $arg['currentParent'], json_encode( array( "fileId" => urlencode( $arg['fileId'] ), "fields" => "id, parents" ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
		if ( $request->status_code === 200 ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}


	/**
	 * Create Folder in Google Drive
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function create_folder( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'name'         => '',
			'parentId'     => ''
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Sanitize Folder name
		$folder_name = preg_replace( self::$preg_filename, '', $arg['name'] );

		// Check Exist Folder name in Parent
		$list = self::file_list( array( 'q' => "'" . $arg['parentId'] . "' in parents and trashed=false" ) );
		if ( isset( $list['error'] ) ) {
			return array( 'error' => false, 'message' => $list['message'] );
		} else {
			foreach ( $list as $files ) {
				if ( $files['name'] == $folder_name ) {
					return array( 'error' => false, 'message' => "This folder name now exists." );
				}
			}
		}

		// Request Create Folder
		$request = \WP_CLI\Utils\http_request( "POST", self::$ApiUrl . "/files", json_encode( array( "mimeType" => self::$folder_mime_type, "name" => $folder_name, "parents" => array( $arg['parentId'] ) ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
		if ( $request->status_code === 200 ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}

	/**
	 * Restore file or folder by ID
	 *
	 * @param array $args
	 * @return array
	 * @throws Exception
	 */
	public static function file_restore( $args = array() ) {

		$default = array(
			'access_token' => self::access_token(),
			'fileId'       => ''
		);
		$arg     = WP_CLI_Util::parse_args( $args, $default );

		// Move File To Trash
		$request = \WP_CLI\Utils\http_request( "PATCH", self::$ApiUrl . "/files/" . urlencode( $arg['fileId'] ), json_encode( array( 'trashed' => false ) ), array_merge( self::$json_content_type, self::$json_header_request, array( 'Authorization' => self::$auth_header . ' ' . $arg['access_token'] ) ), array( 'timeout' => self::$request_timeout ) );
		if ( $request->status_code === 200 ) {
			return self::response( $request );
		}

		return self::$failed_connecting;
	}

}