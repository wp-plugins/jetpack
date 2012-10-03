<?php
/** 
* This should be a direct synced copy of WordPress.com code.
* For now though we will just show some simple links / interfae for dev purposes.
*/

class Publicize_UI {
	
	function __construct() {
		$this->publicize = new Publicize;
		
		//pre_admin_screen_sharing
		add_action( 'pre_admin_screen_sharing', array( &$this, 'admin_page' ) );
	}
	
	function admin_page() { ?>
		<form action="" id="publicize-form">
		<h3 id="publicize"><?php _e( 'Publicize', 'jetpack' ) ?></h3>
		<p><?php _e( 'Connect your blog to popular social networking sites and automatically share new posts with your friends.', 'jetpack' ) ?></p>
		
		<p>
			<a href="<?php echo esc_url( $this->publicize->connect_url( 'twitter' ) ); ?>"><?php _e( 'Connect to Twitter', 'jetpack' ); ?></a> | 
			<a href="<?php echo esc_url( $this->publicize->connect_url( 'facebook' ) ); ?>"><?php _e( 'Connect to Facebook', 'jetpack' ); ?></a> |
			<a href="<?php echo esc_url( $this->publicize->connect_url( 'tumblr' ) ); ?>"><?php _e( 'Connect to Tumblr', 'jetpack' ); ?></a> | 
			<a href="<?php echo esc_url( $this->publicize->connect_url( 'linkedin' ) ); ?>"><?php _e( 'Connect to LinkedIn', 'jetpack' ); ?></a> |
			<a href="<?php echo esc_url( $this->publicize->connect_url( 'yahoo' ) ); ?>"><?php _e( 'Connect to Yahoo!', 'jetpack' ); ?></a>
		</p>

		<p><a href="<?php echo esc_url( $this->publicize->refresh_url() ); ?>">Refresh Connections</a></p>
		
		<?php
			$all_connections = Jetpack::get_option( 'publicize_connections' );
			
			foreach( $all_connections as $service_name => $connections ) {
				
				echo "<h2>" . esc_html( $service_name ) . "</h2>";
				foreach( $connections as $id => $connection ) {
					?>
						<p><strong><?php echo esc_html( $connection['external_display'] ); ?></strong>
						<p><a href="<?php echo esc_url( $this->publicize->disconnect_url( $service_name, $id ) ); ?>">Disconnect</a></p>
					<?php if ( $connection['connection_data']['user_id'] > 0 ) { ?>
						<p><a href="<?php echo esc_url( $this->publicize->globalize_url( $service_name, $id, 'globalize' ) ); ?>">Globalize</a></p>
					<?php } else { ?>
						<p><a href="<?php echo esc_url( $this->publicize->globalize_url( $service_name, $id, 'unglobalize' ) ); ?>">Unglobalize</a></p>
					<?php } 
					
					// there should be checks here that only allow a user to delete their own connection
				}
				
			}
			
			echo "<pre>"; print_r($all_connections); echo "</pre>";
		?>
		
		</form> <?php
	}
	
}