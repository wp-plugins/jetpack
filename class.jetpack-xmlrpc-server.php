<?php

/**
 * Just a sack of functions.  Not actually an IXR_Server
 */
class Jetpack_XMLRPC_Server {
	/**
	 * The current error object
	 */
	var $error = null;

	/**
	 * Whitelist of the XML-RPC methods available to the Jetpack Server. If the 
	 * user is not authenticated (->login()) then the methods are never added,
	 * so they will get a "does not exist" error.
	 */
	function xmlrpc_methods( $core_methods ) {
		if ( !$user = $this->login() ) {
			return array();
		}

		return apply_filters( 'jetpack_xmlrpc_methods', array(
			'jetpack.testConnection'    => array( $this, 'test_connection' ),
			'jetpack.testAPIUserCode'   => array( $this, 'test_api_user_code' ),
			'jetpack.featuresAvailable' => array( $this, 'features_available' ),
			'jetpack.featuresEnabled'   => array( $this, 'features_enabled' ),
			'jetpack.getPost'           => array( $this, 'get_post' ),
			'jetpack.getComment'        => array( $this, 'get_comment' ),  
			'jetpack.jsonAPI'           => array( $this, 'json_api' ),
		), $core_methods );
	}

	/**
	 * Whitelist of the bootstrap XML-RPC methods
	 */
	function bootstrap_xmlrpc_methods() {
		return array(
			'jetpack.verifyRegistration' => array( $this, 'verify_registration' ),
		);
	}

	/**
	 * Verifies that Jetpack.WordPress.com received a registration request from this site
	 *
	 * @return WP_Error|string secret_2 on success, WP_Error( error_code => error_code, error_message => error description, error_data => status code ) on failure
	 *
	 * Possible error_codes:
	 *
	 * verify_secret_1_missing
	 * verify_secret_1_malformed
	 * verify_secrets_missing: No longer have verification secrets stored
	 * verify_secrets_mismatch: stored secret_1 does not match secret_1 sent by Jetpack.WordPress.com
	 */
	function verify_registration( $verify_secret ) {
		if ( empty( $verify_secret ) ) {
			return $this->error( new Jetpack_Error( 'verify_secret_1_missing', sprintf( 'The required "%s" parameter is missing.', 'secret_1' ), 400 ) );
		} else if ( !is_string( $verify_secret ) ) {
			return $this->error( new Jetpack_Error( 'verify_secret_1_malformed', sprintf( 'The required "%s" parameter is malformed.', 'secret_1' ), 400 ) );
		}

		$secrets = Jetpack::get_option( 'register' );
		if ( !$secrets || is_wp_error( $secrets ) ) {
			Jetpack::delete_option( 'register' );
			return $this->error( new Jetpack_Error( 'verify_secrets_missing', 'Verification took too long', 400 ) );
		}

		@list( $secret_1, $secret_2, $secret_eol ) = explode( ':', $secrets );
		if ( empty( $secret_1 ) || empty( $secret_2 ) || empty( $secret_eol ) || $secret_eol < time() ) {
			Jetpack::delete_option( 'register' );
			return $this->error( new Jetpack_Error( 'verify_secrets_missing', 'Verification took too long', 400 ) );
		}

		if ( $verify_secret !== $secret_1 ) {
			Jetpack::delete_option( 'register' );
			return $this->error( new Jetpack_Error( 'verify_secrets_mismatch', 'Secret mismatch', 400 ) );
		}

		Jetpack::delete_option( 'register' );

		return $secret_2;
	}

	/**
	 * Wrapper for wp_authenticate( $username, $password );
	 *
	 * @return WP_User|IXR_Error
	 */
	function login() {
		$user = wp_authenticate( 'username', 'password' );
		if ( is_wp_error( $user ) ) {
			if ( 'authentication_failed' == $user->get_error_code() ) { // Generic error could mean most anything.
				$this->error = new Jetpack_Error( 'invalid_request', 'Invalid Request', 403 );
			} else {
				$this->error = $user;
			}
			return false;
		} else if ( !$user ) { // Shouldn't happen.
			$this->error = new Jetpack_Error( 'invalid_request', 'Invalid Request', 403 );
			return false;
		}

		return $user;
	}

	/**
	 * Returns the current error as an IXR_Error
	 *
	 * @return null|IXR_Error
	 */
	function error( $error = null ) {
		if ( !is_null( $error ) ) {
			$this->error = $error;
		}

		if ( is_wp_error( $this->error ) ) {
			$code = $this->error->get_error_data();
			if ( !$code ) {
				$code = -10520;
			}
			$message = sprintf( 'Jetpack: [%s] %s', $this->error->get_error_code(), $this->error->get_error_message() );
			return new IXR_Error( $code, $message );
		} else if ( is_a( $this->error, 'IXR_Error' ) ) {
			return $this->error;
		}

		return false;
	}

/* API Methods */

	/**
	 * Just authenticates with the given Jetpack credentials.
	 *
	 * @return bool|IXR_Error
	 */
	function test_connection() {
		return JETPACK__VERSION;
	}
	
	function test_api_user_code( $args ) {
		$client_id = (int) $args[0];
		$user_id   = (int) $args[1];
		$nonce     = (string) $args[2];
		$verify    = (string) $args[3];

		if ( !$client_id || !$user_id || !strlen( $nonce ) || 32 !== strlen( $verify ) ) {
			return false;
		}

		if ( !get_user_by( 'id', $user_id ) ) {
			return false;
		}

		error_log( "CLIENT: $client_id" );
		error_log( "USER:   $user_id" );
		error_log( "NONCE:  $nonce" );
		error_log( "VERIFY: $verify" );

		$jetpack_token = Jetpack_Data::get_access_token( 1 );

		$api_user_code = get_user_meta( $user_id, "jetpack_json_api_$client_id", true );
		if ( !$api_user_code ) {
			return false;
		}

		$hmac = hash_hmac( 'md5', json_encode( (object) array(
			'client_id' => (int) $client_id,
			'user_id'   => (int) $user_id,
			'nonce'     => (string) $nonce,
			'code'      => (string) $api_user_code,
		) ), $jetpack_token->secret );

		if ( $hmac !== $verify ) {
			return false;
		}

		return $user_id;
	}

	/**
	 * Returns what features are available. Uses the slug of the module files.
	 *
	 * @return array|IXR_Error
	 */
	function features_available() {
		$raw_modules = Jetpack::get_available_modules();
		$modules = array();
		foreach ( $raw_modules as $module ) {
			$modules[] = Jetpack::get_module_slug( $module );
		}

		return $modules;
	}

	/**
	 * Returns what features are enabled. Uses the slug of the modules files.
	 *
	 * @return array|IXR_Error
	 */
	function features_enabled() {
		$raw_modules = Jetpack::get_active_modules();
		$modules = array();
		foreach ( $raw_modules as $module ) {
			$modules[] = Jetpack::get_module_slug( $module );
		}

		return $modules;
	}
	
	function get_post( $id ) {
		if ( !$id = (int) $id ) {
			return false;
		}

		$jetpack = Jetpack::init();
		$post = $jetpack->get_post( $id );

		if ( $jetpack->is_post_public( $post ) )
			return $post;

		return false;
	}
	
	function get_comment( $id ) {
		if ( !$id = (int) $id ) {
			return false;
		}

		$jetpack = Jetpack::init();
		$comment = $jetpack->get_comment( $id );

		if ( !is_array( $comment ) )
			return false;

		if ( !$this->get_post( $comment['comment_post_ID'] ) )
			return false;

		return $comment;
	}
	
	function json_api( $args = array() ) {
		$json_api_args = $args[0];
		$verify_api_user_args = $args[1];

		$method    = $json_api_args[0];
		$url       = $json_api_args[1];
		$post_body = $json_api_args[2];
		$my_id     = $json_api_args[3];

		$user_id = call_user_func( array( $this, 'test_api_user_code' ), $verify_api_user_args );

		/* debugging
		error_log( "-- begin json api via jetpack debugging -- " );
		error_log( "METHOD: $method" );
		error_log( "URL: $url" );
		error_log( "POST BODY: $post_body" );
		error_log( "MY JETPACK ID: $my_id" );
		error_log( "VERIFY_ARGS: " . print_r( $verify_api_user_args, 1 ) );
		error_log( "VERIFIED USER_ID: " . (int) $user_id );
		error_log( "-- end json api via jetpack debugging -- " );
		*/

		if ( !$user_id ) {
			return false;
		}

		$old_user = wp_get_current_user();
		wp_set_current_user( $user_id );

		define( 'REST_API_REQUEST', true );
		define( 'WPCOM_JSON_API__BASE', 'public-api.wordpress.com/rest/v1' );

		// needed?
		require_once ABSPATH . 'wp-admin/includes/admin.php';

		require_once dirname( __FILE__ ) . '/class.json-api.php';
		$api = WPCOM_JSON_API::init( $method, $url, $post_body );
		require_once dirname( __FILE__ ) . '/class.json-api-endpoints.php';

		$display_errors = ini_set( 'display_errors', 0 );
		ob_start();
		$content_type = $api->serve( false );
		$output = ob_get_clean();
		ini_set( 'display_errors', $display_errors );

		$nonce = wp_generate_password( 10, false );
		$token = Jetpack_Data::get_access_token( 1 );
		$hmac  = hash_hmac( 'md5', $nonce . $output, $token->secret );

		wp_set_current_user( isset( $old_user->ID ) ? $old_user->ID : 0 );

		return array(
			(string) $output,
			(string) $nonce,
			(string) $hmac,
		);
	}
}
