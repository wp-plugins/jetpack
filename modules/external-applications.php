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

function jetpack_external_applications_add_media_uplooad_xmlrpc_methods( $methods, $core_methods, $user ) {
	if ( !$user || is_wp_error( $user ) ) {
		return $methods;
	}

	if ( !isset( $core_methods['metaWeblog.editPost'] ) ) {
		return $methods;
	}

	$methods['metaWeblog.newMediaObject'] = $core_methods['metaWeblog.newMediaObject'];
	$methods['jetpack.updateAttachmentParent'] = 'jetpack_external_applications_update_attachment_parent';

	return $methods;
}

function jetpack_external_applications_update_attachment_parent( $args ) {
	// Don't use "raw".  Authentication handled by Jetpack's XML-RPC Server

	$attachment_id = (int) $args[0];
	$parent_id     = (int) $args[1];

	return wp_update_post( array(
		'ID'          => $attachment_id,
		'post_parent' => $parent_id,
	) );
}

add_filter( 'jetpack_xmlrpc_methods', 'jetpack_external_applications_add_media_uplooad_xmlrpc_methods', 10, 3 );
