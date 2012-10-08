<?php
/**
 * Module Name: JSON API
 * Module Description: Allow applications to securely access your content through the cloud.
 * Sort Order: 100
 * First Introduced: 1.9
 */

function jetpack_external_applications_toggle() {
	$jetpack = Jetpack::init();
	$jetpack->sync->register( 'noop' );
}

add_action( 'jetpack_activate_module_external-applications',   'jetpack_external_applications_toggle' );
add_action( 'jetpack_deactivate_module_external-applications', 'jetpack_external_applications_toggle' );
