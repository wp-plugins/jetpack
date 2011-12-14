<?php

/*
 * Plugin Name: Jetpack Compatibility Test
 * Description: Tests your site's compatibily with Jetpack.
 * Plugin URI: http://jetpack.me/
 * Version: 1.4
 * Author: Automattic
 * Author URI: http://automattic.com/
 * License: GPL2+
 */

class Jetpack_Compatibility_Test {
	var $tests = array();

	function init() {
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
		$self_xml_rpc_url = site_url( 'xmlrpc.php' );

		$this->tests['integer_tests'] = $this->integer_tests();
		$this->tests['wp_generate_password'] = $this->wp_generate_password();
		$this->tests['wp_rand'] = $this->wp_rand();
		$this->tests['http']  = wp_remote_get(  'http://jetpack.wordpress.com/jetpack.test/1/' );
		$this->tests['https'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );
		if ( preg_match( '/^https:/', $self_xml_rpc_url ) ) {
			$this->tests['self']      = wp_remote_get( preg_replace( '/^https:/', 'http:', $self_xml_rpc_url ) );
			$this->tests['self-sec']  = wp_remote_get( $self_xml_rpc_url, array( 'sslverify' => true ) );
		} else {
			$this->tests['self']      = wp_remote_get( $self_xml_rpc_url );
		}
	}

	function wp_generate_password() {
		$lengths = array( 1, 5, 10, 16, 32, 32 );
		foreach ( $lengths as $length ) {
			$password = wp_generate_password( $length, false );
			$r[] = sprintf( '%2d -> %2d:%s', $length, strlen( $password ), $password );
		}
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$r['random_password'] = apply_filters( 'random_password', $chars );
		$r['steps'] = array();
		for ( $i = 0; $i < 32; $i++ ) {
			$format = $i < 10 ? '%3d' : '%2d';
			$rand = wp_rand( 0, 61 );
			$r['steps'][] = sprintf( "$format -> %s", $rand, substr($chars, $rand, 1) );
		}
		if ( class_exists( 'ReflectionFunction' ) && is_callable( 'ReflectionFunction', 'export' ) ) {
			$r['ReflectionFunction'] = "\n" . ReflectionFunction::export( 'wp_generate_password', true );
		} else {
			$r['ReflectionFunction'] = null;
		}
		return $r;
	}

	function wp_rand() {
		$r = array(
			var_export( $GLOBALS['rnd_value'], 1 ),
		);
		$rands = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$format = $i < 10 ? '%3d' : '%2d';
			$rand = wp_rand( 0, 61 );
			$rands[] = $rand;
			$r[] = sprintf( "$format:%s", $rand, strrev( $GLOBALS['rnd_value'] ) );
		}
		$r['MIN'] = min( $rands );
		$r['MAX'] = max( $rands );
		if ( class_exists( 'ReflectionFunction' ) && is_callable( 'ReflectionFunction', 'export' ) ) {
			$r['ReflectionFunction'] = "\n" . ReflectionFunction::export( 'wp_rand', true );
		} else {
			$r['ReflectionFunction'] = null;
		}
		return $r;
	}

	function prepend_type( $value ) {
		$type = gettype( $value );
		return "($type) $value";
	}

	function integer_tests() {
		$r = array();

		$value = "d0000000";
		$r['$value = "d0000000";'] = $this->prepend_type( $value );

		$value = hexdec( $value );
		$r['$value = hexdec( $value );'] = $this->prepend_type( $value );

		$value = abs( $value );
		$r['$value = abs( $value );'] = $this->prepend_type( $value );

		$min = 0;
		$r['$min = 0;'] = $this->prepend_type( $min );

		$max = 61;
		$r['$max = 61;'] = $this->prepend_type( $max );

		$r['4294967295 + 1'] = $this->prepend_type( 4294967295 + 1 );
		$r['$value / (4294967295 + 1)'] = $this->prepend_type( $value / (4294967295 + 1) );
		$r['$max - $min + 1'] = $this->prepend_type( $max - $min + 1 );
		$r['($max - $min + 1) * ($value / (4294967295 + 1))'] = $this->prepend_type( ($max - $min + 1) * ($value / (4294967295 + 1)) );

		$out = $min + (($max - $min + 1) * ($value / (4294967295 + 1)));
		$r['$out = $min + (($max - $min + 1) * ($value / (4294967295 + 1)));'] = $this->prepend_type( $out );

		$out = intval( $out );
		$r['$out = intval( $out );'] = $this->prepend_type( $out );

		$out = abs( $out );
		$r['$out = abs( $out );'] = $this->prepend_type( $out );

		return $r;
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
<h3>TEST: Integer Tests</h3>
<?php $this->output( $this->tests['integer_tests'] ); ?>

<h3>TEST: <code>wp_generate_password()</code></h3>
<?php $this->output( $this->tests['wp_generate_password'] ); ?>

<h3>TEST: <code>wp_rand()</code></h3>
<?php $this->output( $this->tests['wp_rand'] ); ?>

<h3>TEST: HTTP Connection</h3>
<?php $this->output( $this->tests['http'] ); ?>

<h3>TEST: HTTPS Connection</h3>
<?php $this->output( $this->tests['https'] ); ?>

<h3>TEST: Self Connection</h3>
<?php $this->output( $this->tests['self'] ); ?>

<?php if ( isset( $this->tests['self-sec'] ) ) : ?>
<h3>TEST: Self Connection (HTTPS)</h3>
<?php $this->output( $this->tests['self-sec'] ); ?>
<?php endif; ?>

</div>
<?php
	}

	function output( $data ) {
		echo '<pre>' . esc_html( print_r( $data, 1 ) ) . "</pre>\n";
	}
}

add_action( 'init', array( 'Jetpack_Compatibility_Test', 'init' ) );
