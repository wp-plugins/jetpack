<?php 
/**
 * Class: Instapost_Renderer
 * Authors: apeatling
 * Started: Nov 24, 2011
 *
 * Manage when Instapost code is loaded as well as rendering functions for admin bar new post.
 */
class Jetpack_Instapost_Renderer {
	/**
	 * Instapost_Renderer::__construct()
	 *
	 * Construct the Instapost class.
	 */
	public function __construct() {
		global $current_blog, $current_user;

		// Load an instance of Instapost if the conditions are correct.
		if ( ! $this->can_load() )
			return;
		$this->jetpack = Jetpack::init();
		// Add the admin bar new post menu.
		if ( is_user_logged_in() && is_admin_bar_showing() ) {
			add_action( 'admin_bar_menu', array( $this, 'adminbar_new_post_menu' ), 130 );
			add_action( 'wp_footer', array( $this, 'adminbar_new_post_menu_js' ), 500 );
		}

		add_action( 'jetpack_instapost_action_bar_buttons', array( $this, 'render_bloglist' ) );
	}

	/**
	 * @private Instapost_Renderer::can_load()
	 *
	 * Checks the environment to see if Instapost code can be loaded.
	 */
	private function can_load() {

		// If there is no logged in user then we're done.
		if ( is_user_logged_in() ) {
			wp_enqueue_style( 'instapost-reset', site_url( 'wp-content/plugins/' . dirname( plugin_basename( __FILE__ ) ) . '/css/reset.css' ), '', '20120720' );
			wp_enqueue_style( 'instapost', site_url( 'wp-content/plugins/' . dirname( plugin_basename( __FILE__ ) ) . '/jetpack-instapost.css' ), array( 'instapost-reset' ), '20120720' );
			$this->jetpack_options = get_option( 'jetpack_options' );
			if ( false == $this->jetpack_options )
				return false;
			return true;
		}

		return false;
	}

	/**
	 * Instapost_Renderer::add_cross_subdomain_js()
	 *
	 * When a post preview loads in an iframe we need to add this snippet of JS to the preview page
	 * in order to manipulate it from the parent page.
	 */
	public function add_cross_subdomain_js() {
		echo '<script type="text/javascript">document.domain = "wordpress.com";</script>';
	}

	/**
	 * Instapost_Renderer::adminbar_new_post_menu()
	 *
	 * Render the "New Post" admin bar menu so that users can post to any of their blogs
	 * no matter where they are.
	 */
	 public function adminbar_new_post_menu( $wp_admin_bar ) {
		if ( !is_object( $wp_admin_bar ) )
			return false;

		$wp_admin_bar->add_menu( array(
			'parent'    => 'top-secondary',
			'id' => 'ab-new-post',
			'title' => __( 'New Post' ),
			'href' => 'http://wordpress.com/#!/post/',
		) );
	}

	public function adminbar_new_post_menu_js() {
		global $current_blog;
		global $blog_id;
		$iframe_src = ( is_ssl() ) ? 'https://' : 'http://';
		$iframe_src .= 'postto.wordpress.com/?abpost&bid=' . $this->jetpack_options['id'];
		$iframe_height = 289;
	?>
		<script type="text/javascript">
			jQuery(document).ready( function($) {
				var iptOpen, iptClose;

				iptOpen = function() {
					var iframe = $( 'iframe#ab-post' );

					$( '#wp-admin-bar-ab-new-post' ).addClass('hover');

					if ( ! iframe.length ) {
						iframe = $( '<iframe id="ab-post" name="ab-post" src="<?php echo esc_url( $iframe_src ); ?>"></iframe>' );

						$( 'body' ).prepend( '<div id="ab-post-options"><a href="" id="ab-close-button"><?php _e( 'Close' ) ?></a> | <a href="" id="ab-popout-button"><?php _e( 'Pop-out' ) ?></a></div>' );
						$( 'div#ab-post-options' ).hide();
						$( 'body' ).prepend( iframe );
						iframe.unbind( 'load' ).bind( 'load', function() { $( 'div#ab-post-options' ).fadeIn(); });
	
					} else {
						iframe.animate( { 'height': '<?php echo $iframe_height; ?>px' }, 350, function() {
							$(this).removeClass( 'noborder' );
							$( 'div#ab-post-options' ).fadeIn();
						} );
					}

					$( 'body, #wpadminbar' ).animate( { 'margin-top': '<?php echo ($iframe_height + 4); ?>px' }, 350 );
					$( 'iframe#ab-post' ).animate( { 'height': '<?php echo ($iframe_height + 4); ?>px' }, 350 );
				};

				iptClose = function() {
					$( '#wp-admin-bar-ab-new-post' ).removeClass('hover').mouseout();

					$( 'div#ab-post-options' ).hide();
					$( 'iframe#ab-post' ).addClass( 'noborder' ).animate( { 'height': '0' }, 300 );
					$( 'body, #wpadminbar' ).animate( { 'margin-top': '0' }, 300 );
				};

				// move all clicks to the body tag
				$('body').bind('click.iptframe', function(e) {
					var t = $(e.target);

					if ( t.is( '#wp-admin-bar-ab-new-post a' ) ) {
						e.preventDefault();
						if ( !$( 'iframe#ab-post' ).height() > 0 )
							iptOpen();
						else
							iptClose();
					} else if ( t.is( '#ab-close-button' ) ) {
						e.preventDefault();
						iptClose();
					} else if ( t.is( '#ab-popout-button' ) ) {
						e.preventDefault();
						window.open( '<?php echo $iframe_src; ?>', 'popoutpost', 'width=850, height=320' );
						iptClose();
					}
				});
			});
		</script>
	<?php
	}
}
new Jetpack_Instapost_Renderer;
