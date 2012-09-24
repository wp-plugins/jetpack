<?php
/**
 * Module Name: Publicize
 * Module Description: Publish your posts to Twitter, Facebook, Tumblr, LinkedIn, and Yahoo! (description to change)
 * Sort Order: 1
 * First Introduced: 1.9
 */
 
require_once dirname( __FILE__ ) . '/publicize/publicize.php';
require_once dirname( __FILE__ ) . '/publicize/ui.php';
new Publicize_UI();

add_action( 'jetpack_modules_loaded', 'publicize_loaded' );

function publicize_loaded() {
        Jetpack::enable_module_configurable( __FILE__ );
        Jetpack::module_configuration_load( __FILE__, 'publicize_configuration_load' );
}

function publicize_configuration_load() {
        wp_safe_redirect( menu_page_url( 'sharing', false ) );
        exit;
}