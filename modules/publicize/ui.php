<?php
/** 
* This should be a direct synced copy of WordPress.com code.
* For now though we will just show some simple links / interfae for dev purposes.
*/

class Publicize_UI {
	
	function __construct() {
		$this->publicize = new Publicize;

		add_action( 'load-settings_page_sharing', array( $this, 'load_assets' ) );

		//pre_admin_screen_sharing
		add_action( 'pre_admin_screen_sharing', array( $this, 'admin_page' ) );
	}

	/**
	* styling for the sharing screen and popups
	* JS for the options and switching
	*/
	function load_assets() {
		wp_enqueue_script(
			'publicize',
			plugins_url( 'assets/publicize.js', __FILE__ ),
			array( 'jquery', 'thickbox' ),
			'20120925'
		);

		wp_enqueue_style(
			'publicize',
			plugins_url( 'assets/publicize.css', __FILE__ ),
			array(),
			'20120925'
		);
	}

	/**
	* Lists the current user's publicized accounts for the blog
	* looks exactly like Publicize v1 for now, UI and functionality updates will come after the move to keyring
	*/
	function admin_page() {
		$_blog_id = get_current_blog_id();

		?>

  		<form action="" id="publicize-form">
	  		<h3 id="publicize"><?php _e( 'Publicize' ) ?></h3>
	  		<p>
	  			<?php esc_html_e( 'Connect your blog to popular social networking sites and automatically share new posts with your friends.' ) ?>
	  			<?php esc_html_e( 'You can make a connection for just yourself or for all users on your blog. Shared connections are marked with the (Shared) text.' ); ?>
	  		</p>

	  		<div id="publicize-services-block">
		  		<?php
		  		$all_services = $this->publicize->get_services();
				$connected_services = Jetpack::get_option( 'publicize_connections' );
				$connected_services = array_merge( $all_services, $connected_services );

				if ( $connected_services ) :
			  		foreach ( $connected_services as $service_name => $connections ) : 
			  		?>
			  			<div class="publicize-service-entry">
				  			<div id="<?php echo esc_attr( $service_name ); ?>" class="publicize-service-left">
				  				<a href="<?php echo esc_url( $this->publicize->connect_url( $service_name ) ); ?>"><span class="pub-logos" id="<?php echo esc_attr( $service_name ); ?>">&nbsp;</span></a>
				  			</div>

				  			<div class="publicize-service-right">
				  				<?php if ( $connections ) : ?>
				  					<ul>
					  					<?php
										foreach( $connections as $id => $connection ) :

											// if ( 'facebook' == $service->get_name() && isset( $cmeta->meta['facebook_page_token'] ) ) {
											// 	$response = $service->request( 'https://graph.facebook.com/' . $cmeta->meta['facebook_page'] );

											// 	$connection_display = $response->name;
											// 	$profile_link = $response->link;
											// } elseif ( 'tumblr' == $service->get_name() && isset( $cmeta->meta['tumblr_base_hostname'] ) ) {
											// 	$connection_display = $cmeta->meta['tumblr_base_hostname'];
											// 	$profile_link = $service->get_profile_link( $c );
											// } else {
												// $profile_link = $service->get_profile_link( $c );
												$connection_display = $connection['external_display'];
												if ( empty( $connection_display ) )
													$connection_display = $connection['external_name'];
											// }

											?>
											<li>
												<a class="publicize-profile-link" href="<?php //echo esc_attr( $profile_link ); ?>">
													<?php echo esc_attr( $connection_display );?>
												</a>

												<?php if ( 0 == $connection['connection_data']['user_id'] ) : ?>
													<small>(Shared)</small>
												<?php endif; ?>
												
												<a class="pub-disconnect-button" title="<?php esc_html_e( 'Disconnect' ); ?>" href="<?php echo esc_url( $this->publicize->disconnect_url( $service_name, $id ) ); ?>">×</a>

											</li>

											<?php 
										endforeach;
					  					?>
					  				</ul>
					  			<?php endif; ?>
						  		<a id="<?php echo esc_attr( $name ); ?>" class="publicize-add-connection" href="<?php echo esc_url( $this->publicize->connect_url( $service_name ) ); ?>"><?php esc_html_e( sprintf( __( 'Add new %s connection.' ), ucwords( $service_name ) ) ); ?></a>
				  			</div>
				  		</div>
					<?php endforeach; ?>
				<?php endif; ?>
	  		</div>

			<?php wp_nonce_field( "wpas_posts_{$_blog_id}", "_wpas_posts_{$_blog_id}_nonce" ); ?>
			<input type="hidden" id="wpas_ajax_blog_id" name="wpas_ajax_blog_id" value="<?php echo $_blog_id; ?>" />
	  	</form><?php

	}
	
}