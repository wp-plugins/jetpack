<?php
/**
 * Module Name: External Applications
 * Module Description: Allow third-party services to securely connect to your blog to work with your data and offer you new functionality.
 * Sort Order: 100
 * First Introduced: 1.6
 */

// Stub

function jetpack_external_applications_toggle() {
	$jetpack = Jetpack::init();
	
	$available_modules = Jetpack::get_available_modules();
	$active_modules = Jetpack::get_active_modules();
	$modules = array();
	foreach ( $available_modules as $available_module ) {
		$modules[$available_module] = in_array( $available_module, $active_modules );
	}
	$modules['vaultpress'] = class_exists( 'VaultPress' ) || function_exists( 'vaultpress_contact_service' );
	
	$new_state = ( true == $modules['external-applications'] ) ? 0 : 1;
	
	$modules['external-applications'] = $new_state;
	
	$sync_data = compact( 'modules' );

	Jetpack::xmlrpc_async_call( 'jetpack.syncContent', $sync_data );
}

add_action( 'jetpack_deactivate_module_external-applications', 'jetpack_external_applications_toggle' );
add_action( 'jetpack_activate_module_external-applications', 'jetpack_external_applications_toggle' );