<?php

/**
 * Module Name: Likes
 * Module Description: Like all the things
 * First Introduced: 2.1
 * Sort Order: 4
 */

add_action( 'jetpack_modules_loaded', array( 'Jetpack_Likes', 'init' ) );

add_action( 'jetpack_activate_module_likes',   array( 'Jetpack_Likes', 'module_toggle' ) );
add_action( 'jetpack_deactivate_module_likes', array( 'Jetpack_Likes', 'module_toggle' ) );

class Jetpack_Likes {
	function &init() {
		static $instance = NULL;

		if ( !$instance ) {
			$instance = new Jetpack_Likes;
		}

		return $instance;
	}

	function __construct() {
		add_action( 'init', array( &$this, 'action_init' ) );
	}

	function module_toggle() {
		$jetpack = Jetpack::init();
		$jetpack->sync->register( 'noop' );
	}

	function action_init() {
		if ( ! is_admin() ) {
			add_filter( 'the_content', array( &$this, 'post_likes' ), 10, 1 );
			add_filter( 'comment_text', array( &$this, 'comment_likes' ), 10, 2 );
		}
	}

	function post_likes( $content ) {
		return $content;
	}

	function comment_likes( $content, $comment ) {
		return $content;
	}
}

