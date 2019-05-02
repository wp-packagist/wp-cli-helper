<?php

class WP_CLI_Google_Drive {
	/**
	 * List Of Scope for User auth in Google Drive
	 *
	 * @var string
	 */
	public static $Scope = "https://www.googleapis.com/auth/drive,profile,email";
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
	 * Config name in WP-CLI
	 *
	 * @var string
	 */
	public static $config_name = 'gdrive';

	/**
	 * Create Auth Url
	 *
	 * @param $client_ID
	 * @return string
	 */
	public static function create_auth_url( $client_ID ) {
		return "https://accounts.google.com/o/oauth2/auth?client_id=" . $client_ID . "&redirect_uri=" . self::$redirect_url . "&scope=" . self::$Scope . "&response_type=code";
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
		foreach ( array( 'id_token', 'access_token', 'refresh_token', 'client_id', 'client_secret' ) as $key ) {
			if ( isset( $arg[ $key ] ) ) {
				$current_config[ self::$config_name ][ $key ] = $arg[ $key ];
			}
		}

		// Save File
		$wp_cli_config->save_config_file( $current_config );
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
			return json_decode( $request->body, true );
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
		$url    = self::$AuthUrl . "/token";
		$params = "client_id=" . $client_ID . "&client_secret=" . $client_server . "&refresh_token=" . $RefreshToken . "&grant_type=refresh_token";

		// Request
		$request = \WP_CLI\Utils\http_request( "POST", $url, $params, self::$json_header_request, array( 'timeout' => self::$request_timeout ) );
		if ( 200 === $request->status_code ) {
			$data = json_decode( $request->body, true );
			return isset( $data['error'] ) ? false : $data;
		}

		return false;
	}

	/**
	 * User Auth
	 *
	 * @throws \Exception
	 */
	public static function auth() {

		// Check config in WP-CLI
		$wp_cli_config = WP_CLI_CONFIG::get();

		// Require Parameter
		$gdrive = $wp_cli_config[ self::$config_name ];
		if ( ! isset( $gdrive ) ) {
			return false;
		}
		foreach ( array( 'access_token', 'refresh_token', 'client_id', 'client_secret', 'id_token' ) as $key ) {
			if ( ! isset( $gdrive[ $key ] ) || ( isset( $gdrive[ $key ] ) and empty( $gdrive[ $key ] ) ) ) {
				return false;
			}
		}

		// Check Auth in Google
		$new_token = self::get_token_by_refresh_token( $gdrive['refresh_token'], $gdrive['client_id'], $gdrive['client_secret'] );
		if ( $new_token === false ) {
			return false;
		} else {

			// Set new Token to WP-CLI config
			self::save_user_token_in_wp_cli_config( array( 'access_token' => $new_token['access_token'], 'id_token' => $new_token['id_token'] ) );
			return true;
		}
	}


}