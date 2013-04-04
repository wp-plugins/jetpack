<?php

/**
 * Module Name: Jetpack Debugger
 * Module Description: A debugging platform for the Jetpack plugin. Find out why Jetpack isn't working for you and submit a help request direct from your Dashboard.
 * First Introduced: 2.3
 * Sort Order: 999
 * Requires Connection: Yes
 */

// 1. Determine if we are on a network site or not
// if ( is_multisite() )
// 	Jetpack::update_option( 'is_network_site', 1 );
// else
// 	Jetpack::update_option( 'is_network_site', 0 );
//
// 2. Since these are some of the common issues, let's start the debug process by syncing some common details.
// Jetpack_Sync::sync_options( __FILE__,
// 	'home',
// 	'siteurl',
// 	'blogname',
// 	'gmt_offset',
// 	'timezone_string',
// 	'is_network_site',
// );

add_action( 'jetpack_admin_menu', 'jetpack_debug_add_menu_handler' );

function jetpack_debug_add_menu_handler() {
	$hook = add_submenu_page( 'jetpack', 'Debug', 'Debug', 'manage_options', 'jetpack-debugger', 'jetpack_debug_menu_display_handler' );
	add_action( 'admin_head-'.$hook, 'jetpack_debug_admin_head' );
}

function jetpack_debug_menu_display_handler() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'jetpack' ) );

	global $current_user;
	get_currentuserinfo();

	$offer_ticket_submission = false;

	$self_xml_rpc_url = site_url( 'xmlrpc.php' );

	$tests = array();

	$tests['http']  = wp_remote_get( 'http://jetpack.wordpress.com/jetpack.test/1/' );
	//uncomment to make the tests fail
	//$tests['http']  = wp_remote_get( 'http://asdf/jetpack.test/1/' );
	
	$tests['https'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );

	if ( preg_match( '/^https:/', $self_xml_rpc_url ) ) {
		$tests['self']      = wp_remote_get( preg_replace( '/^https:/', 'http:', $self_xml_rpc_url ) );
		$tests['self-sec']  = wp_remote_get( $self_xml_rpc_url, array( 'sslverify' => true ) );
	} else {
		$tests['self']      = wp_remote_get( $self_xml_rpc_url );
	}
	 
	?>

	<div class="wrap">
		<h2><?php esc_html_e( 'Jetpack Debugging Center', 'jetpack' ); ?></h2>
		<h3>Tests</h3>
		<div class="debug-test-container">
		<?php foreach ( $tests as $test_name => $test_result ) : 
			if ( is_wp_error( $test_result ) ) {
				$test_class = 'jetpack-test-error';
				$offer_ticket_submission = true;
				$status = __( 'System Failure!', 'jetpack' );
				$result = esc_html( $test_result->get_error_message() );
			} else {
				$response_code = wp_remote_retrieve_response_code( $test_result );
				if ( empty( $response_code ) ) {
					$test_class = 'jetpack-test-error';;
					$offer_ticket_submission = true;
					$status = __( 'Failed!', 'jetpack' );
				} elseif ( '200' == $response_code ) {
					$test_class = 'jetpack-test-success';
					$status = __( 'Passed!', 'jetpack' );
				} else {
					$test_class = 'jetpack-test-error';
					$offer_ticket_submission = true;
					$status = __( 'Failed!', 'jetpack' );
				}
				$result = esc_html( print_r( $test_result, 1 ) );
			} ?>
			<div class="jetpack-test-results <?php echo $test_class; ?>">
				<p>
					<a class="jetpack-test-heading" href="#"><?php echo $status; ?>: <?php echo $test_name; ?>
					<span class="noticon noticon-collapse"></span>
					</a>
				</p>
				<pre class="jetpack-test-details"><?php echo esc_html( $result ); ?></pre>
			</div>
		<?php endforeach; ?>
		</div>
		<div id="contact-message">
			<p>Having a problem using the Jetpack plugin on your blog? Be sure to go through this checklist before contacting us. You may be able to solve it all by yourself!</p>
			<ul>
				<li>Have you looked through the <a href="http://jetpack.me/support/" rel="nofollow">Jetpack support page</a>? Many common issues and questions are explained there. </li>
				<li>Did you see if your question is in the <a href="http://jetpack.me/about/" rel="nofollow">Jetpack FAQ</a>? </li>
				<li>Have you seen if someone asked your question in the <a href="http://wordpress.org/support/plugin/jetpack" rel="nofollow">Jetpack Plugin support forum on WordPress.org</a>?</li>
				<li><a class="jetpack-show-contact-form" href="#">Contact Jetpack support</a></li>
			</ul>
			<form id="contactme" method="post" action="http://en.support.wordpress.com/contact/#return"<?php if ( ! $offer_ticket_submission ): ?> style="display:none"<?php endif; ?>>
				<input type="hidden" name="user_id" id="user_id" value="7554348">
				<input type="hidden" name="jetpack" id="jetpack" value="needs-service">
				<input type="hidden" name="support_is_open" id="support_open_or_closed" value="open">
				<input type="hidden" name="keywords" id="keywords" value="">
				<input type="hidden" name="contact_form" id="contact_form" value="1">
				<input type="hidden" name="cleo" id="cleo" value="excluded">
				<input type="hidden" name="blog_url" id="blog_url" value="<?php echo esc_attr( site_url() ); ?>">
				<input type="hidden" name="subject" id="subject" value="from: <?php echo esc_attr( site_url() ); ?> Jetpack contact form">
				<input type="hidden" name="debug_info" id="debug_info" value="
				
-----------------------------------------
<?php echo esc_attr( var_export( $tests, true ) ); ?>
">
		
				<div class="formbox">
					<label for="message" class="h">Please describe the problem you are having.</label>
					<textarea name="message" cols="40" rows="7" id="did"></textarea>
				</div>
		
				<div id="name_div" class="formbox">
					<label class="h" for="your_name">Name</label>
		  			<span class="errormsg"><?php echo esc_html_e( 'Let us know your name.' ); ?></span>
					<input name="your_name" type="text" id="your_name" value="<?php echo $current_user->display_name; ?>" size="40">
				</div>
		
				<div id="email_div" class="formbox">
					<label class="h" for="your_email">E-mail</label>
		  			<span class="errormsg"><?php echo esc_html_e( 'Use a valid email address.' ); ?></span>
					<input name="your_email" type="text" id="your_email" value="<?php echo $current_user->user_email; ?>" size="40">
				</div>
		
				<div style="clear: both;"></div>
		
				<div id="blog_div" class="formbox">
					<div id="submit_div" class="contact-support">
					<input type="submit" name="submit" value="Contact Support">
					</div>
				</div>
				<div style="clear: both;"></div>
			</form>
		</div>
	</div>
<?php
}

function jetpack_debug_admin_head() {
	?>
	<style type="text/css">
		
		.debug-test-container {
			margin: 10px;	
		}
		
		.jetpack-test-results {
			margin-bottom: 10px;
			border-radius: 3px;
		}
		.jetpack-test-results a.jetpack-test-heading {
			padding: 4px 6px;
			display: block;
			text-decoration: none;
			color: inherit;
		}
		.jetpack-test-details {
			margin: 4px 6px;
			padding: 10px;
			overflow: auto;
			display: none;
		}
		.jetpack-test-results p {
			margin: 0;
			padding: 0;
		}
		.jetpack-test-success {
			background: #EFF8DF;
			border: solid 1px #B2D37D;
		}
		.jetpack-test-error {
			background: #FFEBE8;
			border: solid 1px #C00;
		}
		.jetpack-test-skipped {
			background: #f5f5f5;
			border: solid 1px #ccc;
		}
		
		.jetpack-test-results .noticon {
			float: right;
		}
				
		form#contactme {
			border: 1px solid #dfdfdf;
			background: #eaf3fa;
			padding: 20px;
			margin: 10px;
			background-color: #eaf3fa;
			-webkit-border-radius: 5px;
			-khtml-border-radius: 5px;
			-moz-border-radius: 5px;
			-o-border-radius: 5px;
			border-radius: 5px;
			font-size: 15px;
			font-family: "Open Sans", "Helvetica Neue", sans-serif;
		}
		
		form#contactme label.h {
			color: #444;
			display: block;
			font-weight: bold;
			margin: 0 0 7px 10px;
			text-shadow: 1px 1px 0 #fff;
		}
		
		.formbox {
			margin: 0 0 25px 0;
		}
		
		.formbox input[type="text"], .formbox input[type="email"], .formbox input[type="url"], .formbox textarea {
			border: 1px solid #e5e5e5;
			-webkit-border-radius: 11px;
			-khtml-border-radius: 11px;
			-moz-border-radius: 11px;
			-o-border-radius: 11px;
			border-radius: 11px;
			-webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
			-moz-box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
			-khtml-box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
			-o-box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
			box-shadow: inset 0 1px 1px rgba(0,0,0,0.1);
			color: #666;
			font-size: 14px;
			padding: 10px;
			width: 97%;
		}
		.formbox .contact-support input[type="submit"] {
			float: right;
			margin: 0 !important;
			-webkit-border-radius: 20px !important;
			-moz-border-radius: 20px !important;
			-khtml-border-radius: 20px !important;
			border-radius: 20px !important;
			cursor: pointer;
			font-size: 13pt !important;
			height: auto !important;
			margin: 0 0 2em 10px !important;
			padding: 8px 16px !important;
			background-color: #ddd;
			border: 1px solid rgba(0,0,0,0.05);
			border-top-color: rgba(255,255,255,0.1);
			border-bottom-color: rgba(0,0,0,0.15);
			color: #333;
			font-weight: 400;
			display: inline-block;
			text-align: center;
			text-decoration: none;
		}

		.formbox span.errormsg {
			margin: 0 0 10px 10px;
			color: #d00;
			display: none;
		}
		
		.formbox.error span.errormsg {
			display: block;
		} 
	</style>
	<script type="text/javascript">
	jQuery( document ).ready( function($) {

		$( '.jetpack-test-heading' ).on( 'click', function() {
			$( this ).parents( '.jetpack-test-results' ).find( '.jetpack-test-details' ).slideToggle();
			return false;
		} );

		$( '.jetpack-show-contact-form' ).on( 'click', function() {
			$('form#contactme').slideToggle();
			return false;
		} );
		
		
		$('form#contactme').on("submit", function(e){
			var form = $(this);
			var message = form.find('#did');
			var name = form.find('#your_name');
			var email = form.find('#your_email')
			var validation_error = false;
			if( !name.val() ) {
				name.parents('.formbox').addClass('error');
				validation_error = true;
			}
			if( !email.val() ) {
				email.parents('.formbox').addClass('error');
				validation_error = true;
			}
			if ( validation_error ) {
				return false;				
			}
			message.val(message.val() + $('#debug_info').val());
			return true;
    	});
    	
	} );
	</script>
	<?php
}
