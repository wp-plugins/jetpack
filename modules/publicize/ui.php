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
		
		<a href="<?php echo $this->publicize->connect_url( 'twitter' ); ?>"><?php _e( 'Connect to Twitter', 'jetpack' ); ?></a>
		
		<pre>
			<?php
				$connections = Jetpack::get_option( 'publicize_connections' );
				print_r($connections);
			?>
		</pre>
		
		</form> <?php
	}
	
}