<?php
/** 
* Jetpack Specific File - However, it should implement the same functions as WordPress.com so
* the UI/shared bits can call the same things. (@todo)
*/

class Publicize {

	function __construct() {
		add_action( 'load-settings_page_sharing', array( $this, 'admin_page_load' ) );
	}
	
	function admin_page_load() {
		if ( isset( $_GET['action'] ) && isset( $_GET['service'] ) ) {
			$service_name = $_GET['service'];
			
			$verification = Jetpack::create_nonce( 'publicize' );
				
			$stats_options = get_option( 'stats_options' );
			$stats_id = isset( $stats_options['blog_id'] ) ? $stats_options['blog_id'] : null;
			
			switch ( $_GET['action'] ) {
				case 'request':
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );
			
					$user = wp_get_current_user();
					$redirect = $this->api_url( $service_name, urlencode_deep( array(
						'action'       => 'request',
						'redirect_uri' => add_query_arg( array( 'action'   => 'done' ), menu_page_url( 'sharing', false ) ),
						'for'          => 'publicize', // required flag that says this connection is intended for publicize
						'siteurl'      => site_url(),
						'state'        => $user->ID,
						'blog_id'      => $stats_id,
						'secret_1'	   => $verification['secret_1'],
						'secret_2'     => $verification['secret_2'],
						'eol'		   => $verification['eol'],
					) ) );
					wp_redirect( $redirect );
					exit;
				break;
				
				case 'completed':
					// Jetpack blog requests Publicize Connections via new XML-RPC method
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.fetchPublicizeConnections' );
					
					if ( $xml->isError() ) {
						// @todo error here..
					}
					
					$response = $xml->getResponse();
					
					Jetpack::update_option( 'publicize_connections', $response );
				break;
				
				case 'delete':
					/*Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.deletePublicizeConnection', $id );
					
					if ( $xml->isError() ) {
						// @todo error here..
					}
					
					$response = $xml->getResponse();
					Jetpack::update_option( 'publicize_connections', $response );*/
				break;
				
				
			}
		}
	}

	/**
	* Gets a URL to the public-api actions. Works like WP's admin_url
	*
	* @param string $service Shortname of a specific service.
	* @return URL to specific public-api process
	*/
	// on WordPress.com this is/calls Keyring::admin_url
	function api_url( $service = false, $params = array() ) {
		$url = apply_filters( 'publicize_api_url', 'https://public-api.wordpress.com/connect/?jetpack=publicize' );
		
		if ( $service )
			$url = add_query_arg( array( 'service' => $service ), $url );
		
		if ( count ( $params ) )
			$url = add_query_arg( $params, $url );
			
		return $url;
	}
	
	function connect_url( $service_name ) {
		return add_query_arg( array (
			'action'   => 'request',
			'service'  =>  $service_name,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}
	
	function disconnect_url() {
		
	}
	
}

?>