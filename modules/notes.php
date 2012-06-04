<?php
/**
 * Module Name: WordPress.com Notifications
 * Module Description: Generate notifications and display the Notifications toolbar menu on your site.
 * Sort Order: 1
 * First Introduced: 1.1
 */

class Jetpack_Notifications {
	var $jetpack = false;

	/**
	 * Singleton
	 * @static
	 */
	function &init() {
		static $instance = array();

		if ( !$instance ) {
			$instance[0] =& new Jetpack_Notifications;
		}

		return $instance[0];
	}

	function Jetpack_Notifications() {
		add_action( 'init', array( &$this, 'action_init' ) );
	}

	function plugins_url($file) {
		$http = is_ssl() ? 'https' : 'http';
		$url = $http . '://s0.wordpress.com/wp-content/mu-plugins/notes/' . $file;
		// Reduce .. in URLs
		do {
			$prev_url = $url;
			$url = preg_replace( '#/[^/]+/\.\./#', '/', $url );
		} while ( $url != $prev_url );
		return $url;
	}

	function action_init() {
		wp_enqueue_style( 'notes-admin-bar-rest', $this->plugins_url( 'admin-bar-rest.css' ), array(), '2012-05-18a' );
		wp_enqueue_script( 'spin', $this->plugins_url( '../../../wp-includes/js/spin.js' ), array( 'jquery' ) );
		wp_enqueue_script( 'jquery.spin', $this->plugins_url( '../../../wp-includes/js/jquery/jquery.spin.js' ), array( 'jquery', 'spin' ) );
		wp_enqueue_script( 'notes-postmessage', $this->plugins_url( '../../js/postmessage.js' ), array(), '20120525', true );
		wp_enqueue_script( 'mustache', $this->plugins_url( 'mustache.js' ), null, '2012-05-04', true );
		wp_enqueue_script( 'underscore', $this->plugins_url( 'underscore-min.js' ), null, '2012-05-04', true );
		wp_enqueue_script( 'backbone', $this->plugins_url( 'backbone-min.js' ), array( 'jquery', 'underscore' ), '2012-05-04', true );
		wp_enqueue_script( 'notes-rest-common', $this->plugins_url( 'notes-rest-common.js' ), array( 'backbone', 'mustache' ), '2012-05-24a', true );
		wp_enqueue_script( 'notes-admin-bar-rest', $this->plugins_url( 'admin-bar-rest.js' ), array( 'jquery', 'underscore', 'backbone' ), '20120525', true );
		add_action( 'admin_bar_menu', array( &$this, 'admin_bar_menu'), 120 );
	}

	function admin_bar_menu() {
		global $wp_admin_bar, $current_blog;

		if ( !is_object( $wp_admin_bar ) )
			return;

		$classes = 'wpnt-loading';

		$noticon = '//s0.wp.com/wp-content/mu-plugins/notes/images/noticon-empty.png';

		$wp_admin_bar->add_menu( array(
			'id'     => 'notes',
			'title'  => '<span id="wpnt-notes-unread-count" class="' . esc_attr( $classes ) . '">'
					. '<img width="14px" height="14px" src="' . esc_url( $noticon ) . '" style="display: inline-block; width: 14px; height: 14px; overflow-x: hidden; overflow-y: hidden;" /></span>',
			'meta'   => array(
				'html'  => '<div id="wpnt-notes-panel" style="display:none"><div class="wpnt-notes-panel-header"><span class="wpnt-notes-header">' . __('Notifications') . '</span><span class="wpnt-notes-panel-link"></span></div></div>',
				'class' => 'menupop',
			),
			'parent' => 'top-secondary',
		) );
	}
}

Jetpack_Notifications::init();