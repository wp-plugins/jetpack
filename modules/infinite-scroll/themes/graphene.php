<?php
/**
 * Enable support for Infinite Scroll in Graphene if the homepage isn't a static page
 *
 * @uses get_option, add_theme_support, add_action
 * @action after_setup_theme
 * @return null
 */
function graphene_infinite_scroll_init() {
	if ( 0 != get_option( 'page_on_front' ) )
		return;

	add_theme_support( 'infinite-scroll', array(
		'container'      => 'content-main',
		'render'         => 'graphene_infinite_scroll_render',
		'footer_widgets' => 'footer-widget-area'
	) );

	add_action( 'wp_enqueue_scripts', 'graphene_infinite_scroll_css' );
}
add_action( 'after_setup_theme', 'graphene_infinite_scroll_init' );

/**
 * Rendering function for IS
 *
 * @uses have_posts, the_post, get_template_part
 * @return string
 */
function graphene_infinite_scroll_render() {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'loop', 'index' );
	}
}

/**
 * Enqueue theme-specific CSS
 *
 * @uses wp_enqueue_style
 * @action wp_enqueue_scripts, plugin_dir_url
 * @return null
 */
function graphene_infinite_scroll_css() {
	wp_enqueue_style( 'infinite-graphene', plugin_dir( 'graphene.css', __FILE__ ), array( 'the-neverending-homepage' ), '20121003' );
}
