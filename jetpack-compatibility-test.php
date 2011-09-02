<?php

/*
 * Plugin Name: Jetpack Compatibility Test
 * Description: Tests your site's compatibily with Jetpack.
 * Plugin URI: http://jetpack.me/
 * Version: 1.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 * License: GPL2+
 */

class Jetpack_Compatibility_Test {
	var $tests = array();

	function &init() {
		static $instance = false;

		if ( $instance ) {
			return $instance;
		}

		$instance = new Jetpack_Compatibility_Test;
		return $instance;
	}

	function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		$hook = add_plugins_page( 'Jetpack Compatibility TEST', 'Jetpack Compatibility TEST', 'manage_options', 'jetpack-compatibility-test', array( $this, 'admin_page' ) );
		add_action( "load-$hook", array( $this, 'admin_page_load' ) );
	}

	function admin_page_load() {
		add_action( 'admin_head', array( $this, 'admin_head' ) );

		$this->tests['http']  = wp_remote_get(  'http://jetpack.wordpress.com/jetpack.test/1/' );
		$this->tests['https'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );
	}

	function admin_head() {
?>
<style type="text/css">
#jetpack-compatibility-test-select-all {
	font-weight: normal;
}
textarea#jetpack-compatibility-test-wrapper {
	width: 90%;
}
</style>
<script type="text/javascript">
jQuery( function( $ ) {
	$( '#jetpack-compatibility-test-select-all' ).click( function() {
		var wrapper = $( '#jetpack-compatibility-test-wrapper' ),
		    text = $.trim( wrapper.text() ),
		    height = wrapper.height() * 1.2,
		    textArea = $( '<textarea id="jetpack-compatibility-test-wrapper" readonly="readonly" />' ).text( text ).height( height );

		wrapper.replaceWith( textArea );
		textArea.select();
		$( this ).hide();

		return false;
	} );
} );
</script>
<?php
	}

	function admin_page() {
?>
	<h2>Jetpack Compatibility Test <a id="jetpack-compatibility-test-select-all" class="button" href="#">Select All</a></h2>

	<div id="jetpack-compatibility-test-wrapper">
<h3>TEST: HTTP Connection</h3>
<?php $this->output( $this->tests['http'] ); ?>

<h3>TEST: HTTPS Connection</h3>
<?php $this->output( $this->tests['https'] ); ?>
</div>
<?php
	}

	function output( $data ) {
		echo '<pre>' . esc_html( print_r( $data, 1 ) ) . "</pre>\n";
	}
}

add_action( 'init', array( 'Jetpack_Compatibility_Test', 'init' ) );
