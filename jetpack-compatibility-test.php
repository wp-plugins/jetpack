<?php

/*
 * Plugin Name: Jetpack Compatibility Test
 * Description: Tests your site's compatibily with Jetpack.
 * Plugin URI: http://jetpack.me/
 * Version: 1.5
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

		$this->tests['site_info'] = $this->site_info();
		$this->tests['http']  = wp_remote_get(  'http://jetpack.wordpress.com/jetpack.test/1/' );
		$this->tests['https'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );
		if ( preg_match( '/^https:/', $self_xml_rpc_url ) ) {
			$this->tests['self']      = wp_remote_get( preg_replace( '/^https:/', 'http:', $self_xml_rpc_url ) );
			$this->tests['self-sec']  = wp_remote_get( $self_xml_rpc_url, array( 'sslverify' => true ) );
		} else {
			$this->tests['self']      = wp_remote_get( $self_xml_rpc_url );
		}
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
	
	function site_info() {
	
		$site_info = "\r\n";
		$site_info .= "\r\n" . esc_html( "SITE_URL: " . site_url() );
		$site_info .= "\r\n" . esc_html( "HOME_URL: " . home_url() );
		
		return $site_info;
	
	}

	function admin_page() {
?>
	<h2>Jetpack Compatibility Test</h2>

	<div class="jetpack-compatibility-test-intro">
		<h3><?php esc_html_e( 'Trouble with Jetpack?', 'jetpack' ); ?></h3>
		<h4><?php esc_html_e( 'It may be caused by one of these issues, which you can diagnose yourself:', 'jetpack' ); ?></h4>
		<ol>
			<li><b><em><?php esc_html_e( 'A known issue.', 'jetpack' ); ?></em></b>  <?php echo sprintf( __( 'Some themes and plugins have <a href="%1$s" target="_blank">known conflicts</a> with Jetpack – check the <a href="%2$s" target="_blank">list</a>. (You can also browse the <a href="%3$s">Jetpack support pages</a> or <a href="%4$s">Jetpack support forum</a> to see if others have experienced and solved the problem.)', 'jetpack' ), 'http://jetpack.me/known-issues/', 'http://jetpack.me/known-issues/', 'http://jetpack.me/support/', 'http://wordpress.org/support/plugin/jetpack' ); ?></li>
			<li><b><em><?php esc_html_e( 'An incompatible plugin.', 'jetpack' ); ?></em></b>  <?php esc_html_e( "Find out by disabling all plugins except Jetpack. If the problem persists, it's not a plugin issue. If the problem is solved, turn your plugins on one by one until the problem pops up again – there's the culprit! Let us know, and we'll try to help.", 'jetpack' ); ?></li>
			<li><b><em><?php esc_html_e( 'A theme conflict.', 'jetpack' ); ?></em></b>  <?php esc_html_e( "If your problem isn't known or caused by a plugin, try activating Twenty Twelve (the default WordPress theme). If this solves the problem, something in your theme is probably broken – let the theme's author know.", 'jetpack' ); ?></li>
			<li><b><em><?php esc_html_e( 'A problem with your XMLRPC file.', 'jetpack' ); ?></em></b>  <?php echo sprintf( __( 'Load your <a href="%s">XMLRPC file</a>. It should say “XML-RPC server accepts POST requests only.” on a line by itself.', 'jetpack' ), site_url( 'xmlrpc.php' ) ); ?>
				<ul>
					<li>- <?php esc_html_e( "If it's not by itself, a theme or plugin is displaying extra characters. Try steps 2 and 3.", 'jetpack' ); ?></li>
					<li>- <?php esc_html_e( "If you get a 404 message, contact your web host. Their security may block XMLRPC.", 'jetpack' ); ?></li>
				</ul>
			</li>
		</ol>
		<p class="jetpack-show-contact-form"><?php _e( 'If none of these help you find a solution, <a target="_blank" href="http://jetpack.me/contact-support/">click here to contact Jetpack support</a>. Tell us as much as you can about the issue and what steps you\'ve tried to resolve it, and include the results of the test below.', 'jetpack' ); ?> 
		</p>
		<p><a id="jetpack-compatibility-test-select-all" class="button" href="#">Select All</a></p>
	</div>
	
	<div id="jetpack-compatibility-test-wrapper">
	
<h3>Site info</h3>
<?php $this->output( $this->tests['site_info'] ); ?>

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
