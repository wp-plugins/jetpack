<?php

/**
 * Module Name: Jetpack Debugger
 * Module Description: A debugging platform for the Jetpack plugin. Find out why Jetpack isn't working for you and submit a help request direct from your Dashboard.
 * First Introduced: 2.3
 * Sort Order: 999
 * Requires Connection: No
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
	if ( current_user_can( 'manage_options' ) ) {
		$hook = add_submenu_page( 'jetpack', 'Debug', 'Debug', 'manage_options', 'jetpack-debugger', 'jetpack_debug_menu_display_handler' );
		add_action( 'admin_head-'.$hook, 'jetpack_debug_admin_head' );
		do_action( 'jetpack_module_loaded_debug' );
	}
}

function jetpack_debug_menu_display_handler() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'jetpack' ) );

	global $current_user;
	get_currentuserinfo();

	$offer_ticket_submission = false;

	$self_xml_rpc_url = site_url( 'xmlrpc.php' );

	$tests = array();

	$tests['HTTP']  = wp_remote_get( 'http://jetpack.wordpress.com/jetpack.test/1/' );
	//uncomment to make the tests fail
	//$tests['HTTP']  = wp_remote_get( 'http://jetpack/jetpack.test/1/' );
	
	$tests['HTTPS'] = wp_remote_get( 'https://jetpack.wordpress.com/jetpack.test/1/' );

	if ( preg_match( '/^https:/', $self_xml_rpc_url ) ) {
		$tests['SELF']      = wp_remote_get( preg_replace( '/^https:/', 'http:', $self_xml_rpc_url ) );
		$tests['SELF-SEC']  = wp_remote_get( $self_xml_rpc_url, array( 'sslverify' => true ) );
	} else {
		$tests['SELF']      = wp_remote_get( $self_xml_rpc_url );
	}
	
	$debug_info = "\n\n----------------------------------------------\n\nDEBUG INFO:\n";
	$user_id = get_current_user_id();
	$user_tokens = Jetpack::get_option( 'user_tokens' );
	if ( is_array( $user_tokens ) && array_key_exists( $user_id, $user_tokens ) ) {
		$user_token = $user_tokens[$user_id];
	} else {
		$user_token = '[this user has no token]';
	}
	unset( $user_tokens );

	foreach ( array(
		'CLIENT_ID'   => 'id',
		'BLOG_TOKEN'  => 'blog_token',
		'MASTER_USER' => 'master_user',
		'CERT'        => 'fallback_no_verify_ssl_certs',
		'TIME_DIFF'   => 'time_diff',
		'VERSION'     => 'version',
		'OLD_VERSION' => 'old_version',
		'PUBLIC'      => 'public',
	) as $label => $option_name ) {
		$debug_info .= "\n" . esc_html( $label . ": " . Jetpack::get_option( $option_name ) );
	}
	
	$debug_info .= "\n" . esc_html("USER_ID: " . $user_id );
	$debug_info .= "\n" . esc_html("USER_TOKEN: " . $user_token );
	$debug_info .= "\n" . esc_html("PHP_VERSION: " . PHP_VERSION );
	$debug_info .= "\n" . esc_html("WORDPRESS_VERSION: " . $GLOBALS['wp_version'] );			
	$debug_info .= "\n\nTEST RESULTS:\n\n";
	$debug_raw_info = '';
	?>

	<div class="wrap">
		<h2><?php esc_html_e( 'Jetpack Debugging Center', 'jetpack' ); ?></h2>
		<h3><?php _e( "Tests your site's compatibily with Jetpack.", 'jetpack' ); ?></h3>
		<h3>Tests</h3>
		<div class="debug-test-container">
		<?php foreach ( $tests as $test_name => $test_result ) : 
			$result = '';
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
			} 
			$debug_info .= $test_name . ': ' . $status . "\n";
			$debug_raw_info .= "\n\n" . $test_name . "\n" . esc_html( print_r( $test_result, 1 ) );
			?>
			<div class="jetpack-test-results <?php echo $test_class; ?>">
				<p>
					<a class="jetpack-test-heading" href="#"><?php echo $status; ?>: <?php echo $test_name; ?>
					<span class="noticon noticon-collapse"></span>
					</a>
				</p>
				<pre class="jetpack-test-details"><?php echo esc_html( $result ); ?></pre>
			</div>
		<?php endforeach; 
			$debug_info .= "\n\nRAW TEST RESULTS:" . $debug_raw_info ."\n";
		?>
		</div>
		<div class="entry-content">
			<h3>Troubleshooting Tips</h3>
			<h4>Having trouble with Jetpack or one of its components? Here are a few key steps to try on your own:</h4>
			<ol>
				<li>Disable all other plugins, then try connecting or using Jetpack. If Jetpack starts connecting or working properly, turn your plugins back on one-by-one until you start seeing the error again. Then note the plugin that caused this error and get in touch with us. Sometimes Jetpack and other plugins are incompatible; just let us know and we’ll see what we can do.</li>
				<li>If you are having a display issue, or the plugin step above doesn’t help, try activating Twenty Eleven or Twenty Twelve (one of the default WordPress themes) as your theme. Then try again. If your action starts working, likely something in your theme is broken and you should get in touch with your theme’s author.</li>
				<li>
					<ol>
					<li>Check your <a href="<?php echo site_url( 'xmlrpc.php' ); ?>">XMLRPC file</a>. When it loads in your browser, you should see “XML-RPC server accepts POST requests only.” on a line by itself.</li>
					<li>If you see this message, but it is not on a line by itself, a theme or plugin is displaying extra characters when it shouldn’t. See points 1 and 2 above for debugging steps.</li>
					<li>If you get an 404 Error Not Found message, contact your web host. They may have security in place that is blocking XMLRPC.</li>
					</ol>
				</li>
				<li>Check the <a href="http://jetpack.me/known-issues/" target="_blank">Known Issues list</a> and make sure you aren’t using a plugin or theme listed there.</li>
				<li><a class="jetpack-show-contact-form" href="#"><?php esc_html_e( 'Contact Jetpack support' ); ?></a></li>
			</ol>
		</div>
		<div id="contact-message" <?php if ( ! $offer_ticket_submission ): ?> style="display:none"<?php endif; ?>>
			<h4><?php _e( 'Having a problem using the Jetpack plugin on your blog? Be sure to go through this checklist before contacting us. You may be able to solve it all by yourself!' ); ?></h4>
			<ul>
				<li><?php echo sprintf( __('Have you looked through the %s? Many common issues and questions are explained there.', 'jetptack' ), '<a href="http://jetpack.me/support/" rel="nofollow">' . __( 'Jetpack support page', 'jetpack' ) . '</a>' ); ?></li>
				<li><?php echo sprintf( __('Did you see if your question is in the %s?', 'jetptack' ), '<a href="http://jetpack.me/about/" rel="nofollow">' . __( 'Jetpack FAQ', 'jetpack' ) . '</a>' ); ?></li>
				<li><?php echo sprintf( __('Have you seen if someone asked your question in the %s?', 'jetptack' ), '<a href="http://wordpress.org/support/plugin/jetpack" rel="nofollow">' . __( 'Jetpack Plugin support forum on WordPress.org', 'jetpack' ) . '</a>' ); ?></li>
			</ul>
			<form id="contactme" method="post" action="http://en.support.wordpress.com/contact/#return">
				<input type="hidden" name="user_id" id="user_id" value="<?php echo Jetpack::get_option( 'id' ) ?>">
				<input type="hidden" name="jetpack" id="jetpack" value="needs-service">
				<input type="hidden" name="keywords" id="keywords" value="">
				<input type="hidden" name="contact_form" id="contact_form" value="1">
				<input type="hidden" name="blog_url" id="blog_url" value="<?php echo esc_attr( site_url() ); ?>">
				<input type="hidden" name="subject" id="subject" value="from: <?php echo esc_attr( site_url() ); ?> Jetpack contact form">
				<input type="hidden" name="debug_info" id="debug_info" value="<?php echo esc_attr( $debug_info ); ?>">
		
				<div class="formbox">
					<label for="message" class="h"><?php _e( 'Please describe the problem you are having.' ); ?></label>
					<textarea name="message" cols="40" rows="7" id="did"></textarea>
				</div>
		
				<div id="name_div" class="formbox">
					<label class="h" for="your_name">Name</label>
		  			<span class="errormsg"><?php echo _e( 'Let us know your name.' ); ?></span>
					<input name="your_name" type="text" id="your_name" value="<?php echo $current_user->display_name; ?>" size="40">
				</div>
		
				<div id="email_div" class="formbox">
					<label class="h" for="your_email">E-mail</label>
		  			<span class="errormsg"><?php echo _e( 'Use a valid email address.' ); ?></span>
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
		
		#contact-message ul {
			margin: 0 0 20px 10px;
		}
		
		#contact-message li {
			margin: 0 0 10px 10px;
			list-style: disc;
			display: list-item;
		}
		
	</style>
	<script type="text/javascript">
	jQuery( document ).ready( function($) {

		$( '.jetpack-test-error .jetpack-test-heading' ).on( 'click', function() {
			$( this ).parents( '.jetpack-test-results' ).find( '.jetpack-test-details' ).slideToggle();
			return false;
		} );

		$( '.jetpack-show-contact-form' ).on( 'click', function() {
			$('#contact-message').slideToggle();
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
			message.val(message.val() + $('#debug_info').val() + 'jQuery version: ' + jQuery.fn.jquery );
			return true;
    	});
    	
	} );
	</script>
	<?php
}
