<?php

/**
 * Module Name: Jetpack Debugger
 * Module Description: A debugging platform for the Jetpack plugin. Find out why Jetpack isn't working for you and submit a help request direct from your Dashboard.
 * First Introduced: 2.3
 * Sort Order: 999
 * Requires Connection: Yes
 */

// 1. Determine if we are on a network site or not
// if ( is_multisite() )
// 	Jetpack::update_option( 'is_network_site', 1 );
// else
// 	Jetpack::update_option( 'is_network_site', 0 );
//
// 2. Since these are some of the common issues, let's start the debug process by syncing some common details.
// Jetpack_Sync::sync_options( __FILE__,
// 	'home',
// 	'siteurl',
// 	'blogname',
// 	'gmt_offset',
// 	'timezone_string',
// 	'is_network_site',
// );

add_action( 'jetpack_admin_menu', 'jetpack_debug_add_menu_handler' );

function jetpack_debug_add_menu_handler() {
	add_submenu_page( 'jetpack', 'Debug', 'Debug', 'manage_options', 'jetpack-debugger', 'jetpack_debug_menu_display_handler' );
}

function jetpack_debug_menu_display_handler() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'jetpack' ) );

	$offer_ticket_submission = false;

	$self_xml_rpc_url = site_url( 'xmlrpc.php' );

	$tests = array();

	$tests['http']  = wp_remote_get(  'http://jetpack.wordpress.com/jetpack.test/1/' );
	$tests['https'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );

	if ( preg_match( '/^https:/', $self_xml_rpc_url ) ) {
		$this->tests['self']      = wp_remote_get( preg_replace( '/^https:/', 'http:', $self_xml_rpc_url ) );
		$this->tests['self-sec']  = wp_remote_get( $self_xml_rpc_url, array( 'sslverify' => true ) );
	} else {
		$this->tests['self']      = wp_remote_get( $self_xml_rpc_url );
	}
	?>

	<div class="wrap">
		<h2><?php esc_html_e( 'Jetpack Debugging Center', 'jetpack' ); ?></h2>

		<div class="debug-test-container">
		<?php
		foreach ( $tests as $test_name => $test_result ) :
			?><div class="debug-<?php echo $test_name;?>-test"><?php

			if ( is_wp_error( $test_result ) ) :
				$offer_ticket_submission = true;
				?><span class="test-failure"><?php esc_html_e( 'System Failure!', 'jetpack' ); ?></span><?php
				?><p><?php esc_html_e( $test_result->get_error_message() ); ?></p><?php
			endif;

			$response_code = wp_remote_retrieve_response_code( $test_result );

			if ( empty( $response_code ) ) :
				?><span class="test-failure"><?php esc_html_e( 'System Failure!', 'jetpack' ); ?></span><?php
				$offer_ticket_submission = true;
				?><p><?php esc_html_e( 'There was an error with this test. Please report it using the link below.', 'jetpack' ); ?></p><?php
			endif;

			if ( '200' == $response_code ) :

			else :
				$offer_ticket_submission = true;
				?><span class="test-failure"><?php esc_html_e( 'System Failure!', 'jetpack' ); ?></span><?php
			endif;

			?></div><?php
		endforeach;
		?>
		</div>
	</div>
<?php
}
