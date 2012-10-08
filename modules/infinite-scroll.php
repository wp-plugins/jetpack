<?php
/**
 * Module Name: Infinite Scroll
 * Module Description: On the frontpage blog post view, automatically pull the next set of posts into view when the reader approaches the bottom of the page.
 * Sort Order: 14
 * First Introduced: 1.9
 */

/**
 * Enable "Configure" button on module card
 *
 * @uses Jetpack::enable_module_configurable, Jetpack::module_configuration_load
 * @action jetpack_modules_loaded
 * @return null
 */
function infinite_scroll_loaded() {
	Jetpack::enable_module_configurable( __FILE__ );
	Jetpack::module_configuration_load( __FILE__, 'infinite_scroll_configuration_load' );
}
add_action( 'jetpack_modules_loaded', 'infinite_scroll_loaded' );

/**
 * Redirect configure button to Settings > Reading
 *
 * @uses wp_safe_redirect, admin_url
 * @return null
 */
function infinite_scroll_configuration_load() {
	wp_safe_redirect( admin_url( 'options-reading.php#infinite-scroll-options' ) );
	exit;
}

/**
 * Register spinner scripts included in Carousel module.
 *
 * @uses wp_script_is, wp_register_script, plugins_url
 * @action wp_enqueue_scripts
 * @return null
 */
function infinite_scroll_register_spin_scripts() {
	if ( ! wp_script_is( 'spin', 'registered' ) )
		wp_register_script( 'spin', plugins_url( 'carousel/spin.js', __FILE__ ), false, '1.2.4' );

	if ( ! wp_script_is( 'jquery.spin', 'registered' ) )
		wp_register_script( 'jquery.spin', plugins_url( 'carousel/jquery.spin.js', __FILE__ ) , array( 'jquery', 'spin' ) );
}
add_action( 'wp_enqueue_scripts', 'infinite_scroll_register_spin_scripts', 5 );

/**
 * Load main IS file
 */
require_once( dirname( __FILE__ ) . "/infinite-scroll/infinity.php" );