<?php
/**
 * Module Name: Likes
 * Module Description: Like all the things
 * First Introduced: 2.1
 * Sort Order: 4
 */
class Jetpack_Likes {
	function &init() {
		static $instance = NULL;

		if ( ! $instance ) {
			$instance = new Jetpack_Likes;
		}

		return $instance;
	}

	function __construct() {
		$this->in_jetpack = ( defined( 'IS_WPCOM' ) && IS_WPCOM ) ? false : true;

		add_action( 'init', array( &$this, 'action_init' ) );

		if ( $this->in_jetpack ) {
			add_action( 'jetpack_activate_module_likes',   array( $this, 'module_toggle' ) );
			add_action( 'jetpack_deactivate_module_likes', array( $this, 'module_toggle' ) );

			$active = Jetpack::get_active_modules();

			if ( ! in_array( 'sharedaddy', $active ) && ! in_array( 'publicize', $active ) ) {
				add_action( 'admin_menu', array( $this, 'sharing_menu' ) );	// we don't have a sharing page yet
			}

			if ( in_array( 'publicize', $active ) && ! in_array( 'sharedaddy', $active ) ) {
				add_action( 'pre_admin_screen_sharing', array( $this, 'sharing_block' ), 20 ); // we have a sharing page but not the global options area
				add_action( 'pre_admin_screen_sharing', array( $this, 'updated_message' ), -10 );
			}

			if( ! in_array( 'sharedaddy', $active ) ) {
				add_action( 'admin_init', array( $this, 'process_update_requests_if_sharedaddy_not_loaded' ) );
				add_action( 'sharing_global_options', array( $this, 'admin_settings_showbuttonon_init' ), 19 );
				add_action( 'sharing_admin_update', array( $this, 'admin_settings_showbuttonon_callback' ), 19 );
			}
		}

		add_action( 'sharing_global_options', array( $this, 'admin_settings_init' ), 20 );
		add_action( 'sharing_admin_update',   array( $this, 'admin_settings_callback' ), 20 );
	}

	function module_toggle() {
		$jetpack = Jetpack::init();
		$jetpack->sync->register( 'noop' );
	}

	/**
	 * The actual options block to be inserted into the sharing page.
	 */
	function admin_settings_init() { ?>
		<tr>
			<th scope="row">
				<label><?php esc_html_e( 'WordPress.com Likes are', 'jetpack' ); ?></label>
			</th>
			<td>
				<div>
					<label>
						<input type="radio" class="code" name="wpl_default" value="on" <?php checked( $this->is_enabled_sitewide(), true ); ?> />
						<?php esc_html_e( 'On for all posts', 'jetpack' ); ?>
					</label>
				</div>
				<div>
					<label>
						<input type="radio" class="code" name="wpl_default" value="off" <?php checked( $this->is_enabled_sitewide(), false ); ?> />
						<?php esc_html_e( 'Turned on per post', 'jetpack' ); ?>
					</label>
				<div>
			</td>
		</tr>
	<?php }

	/**
	 * If sharedaddy is not loaded, we don't have the "Show buttons on" yet, so we need to add that since it affects likes too.
	 */
	function admin_settings_showbuttonon_init() { ?>
		<tr valign="top">
	  	<th scope="row"><label><?php _e( 'Show buttons on', 'jetpack' ); ?></label></th>
		<td>
			<?php
				$br = false;
				$shows = array_values( get_post_types( array( 'public' => true ) ) );
				array_unshift( $shows, 'index' );
				$global = $this->get_options();
				foreach ( $shows as $show ) :
					if ( 'index' == $show ) {
						$label = __( 'Front Page, Archive Pages, and Search Results', 'jetpack' );
					} else {
						$post_type_object = get_post_type_object( $show );
						$label = $post_type_object->labels->name;
					}
			?>
				<?php if ( $br ) echo '<br />'; ?><label><input type="checkbox"<?php checked( in_array( $show, $global['show'] ) ); ?> name="show[]" value="<?php echo esc_attr( $show ); ?>" /> <?php echo esc_html( $label ); ?></label>
			<?php	$br = true; endforeach; ?>
		</td>
	  	</tr>
	<?php }


	/**
	 * If sharedaddy is not loaded, we still need to save the the settings of the "Show buttons on" option.
	 */
	function admin_settings_showbuttonon_callback() {
		$options = get_option( 'sharing-options' );
		if ( !is_array( $options ) )
			$options = array();
		
		$shows = array_values( get_post_types( array( 'public' => true ) ) );
		$shows[] = 'index';
		$data = $_POST;

		if ( isset( $data['show'] ) ) {
			if ( is_scalar( $data['show'] ) ) {
				switch ( $data['show'] ) {
					case 'posts' :
						$data['show'] = array( 'post', 'page' );
					break;
					case 'index' :
						$data['show'] = array( 'index' );
					break;
					case 'posts-index' :
						$data['show'] = array( 'post', 'page', 'index' );
					break;
				}
			}

			if ( $data['show'] = array_intersect( $data['show'], $shows ) ) {
				$options['global']['show'] = $data['show'];
			}
		} else {
			$options['global']['show'] = array();
		}

		update_option( 'sharing-options', $options );
	}

	/**
	 * Adds the admin update hook so we can save settings even if Sharedaddy is not enabled.
	 */
	function process_update_requests_if_sharedaddy_not_loaded() {
		if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'sharing.php' || $_GET['page'] == 'sharing' ) ) {
			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sharing-options' ) ) {
				do_action( 'sharing_admin_update' );
				wp_safe_redirect( admin_url( 'options-general.php?page=sharing&update=saved' ) );
				die();
			}
		}
	}

	/**
	 * Saves the setting in the database, bumps a stat on WordPress.com
	 */
	function admin_settings_callback() {
		// We're looking for these, and doing a dance to set some stats and save
		// them together in array option.
		$new_state = !empty( $_POST['wpl_default'] ) ? $_POST['wpl_default'] : 'on';
		$db_state  = $this->is_enabled_sitewide();

		/** Default State *********************************************************/

		// Checked (enabled)
		switch( $new_state ) {
			case 'off' :
				if ( true == $db_state && ! $this->in_jetpack ) {
					bump_stats_extras( 'likes', 'disabled_likes' );
				}
				update_option( 'disabled_likes', 1 );
				break;
			case 'on'  :
			default;
				if ( false == $db_state && ! $this->in_jetpack ) {
					bump_stats_extras( 'likes', 'reenabled_likes' );
				}
				delete_option( 'disabled_likes' );
				break;
		}
	}

	/**
	 * Adds the 'sharing' menu to the settings menu.
	 * Only ran if sharedaddy and publicize are not already active.
	 */
	function sharing_menu() {
		add_submenu_page( 'options-general.php', esc_html__( 'Sharing Settings', 'jetpack' ), esc_html__( 'Sharing', 'jetpack' ), 'manage_options', 'sharing', array( $this, 'sharing_page' ) );
	}

	/**
	 * Provides a sharing page with the sharing_global_options hook
	 * so we can display the setting.
	 * Only ran if sharedaddy and publicize are not already active.
	 */
	function sharing_page() {
		$this->updated_message(); ?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br /></div>
			<h2><?php esc_html_e( 'Sharing Settings', 'jetpack' ); ?></h2>
			<?php do_action( 'pre_admin_screen_sharing' ) ?>
			<?php $this->sharing_block(); ?>
		</div> <?php
	}

	/**
	 * Returns the settings have been saved message.
	 */
	function updated_message() {
		if ( isset( $_GET['update'] ) && $_GET['update'] == 'saved' )
			echo '<div class="updated"><p>' . esc_html__( 'Settings have been saved', 'jetpack' ) . '</p></div>';
	}

	/**
	 * Returns just the "sharing buttons" w/ like option block, so it can be inserted into different sharing page contexts
	 */
	function sharing_block() { ?>
		<h3><?php esc_html_e( 'Sharing Buttons', 'jetpack' ); ?></h3>
		<form method="post" action="">
		<table class="form-table">
		<tbody>
			<?php do_action( 'sharing_global_options' ); ?>
		</tbody>
		</table>

		<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'jetpack' ); ?>" />
		</p>

		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-options' );?>" />
		</form> <?php
	}

	function action_init() {
		if ( is_admin() )
			return;

		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			 ( defined( 'APP_REQUEST' ) && APP_REQUEST ) ||
			 ( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST ) ||
			 ( defined( 'COOKIE_AUTH_REQUEST' ) && COOKIE_AUTH_REQUEST ) ||
			 ( defined( 'JABBER_SERVER' ) && JABBER_SERVER ) )
			return;

		add_filter( 'the_content', array( &$this, 'post_likes' ), 10, 1 );
		add_filter( 'comment_text', array( &$this, 'comment_likes' ), 10, 2 );

		wp_enqueue_script( 'jetpack_resize', '/wp-content/js/jquery/jquery.jetpack-resize.js', array( 'jquery' ), JETPACK__VERSION, true );
		wp_enqueue_style( 'jetpack_likes', plugins_url( 'jetpack-likes.css', __FILE__ ), array(), JETPACK__VERSION );
		add_action( 'wp_print_footer_scripts', array( $this, 'footer_script' ), 20 );
	}

	function footer_script() {
?>
<script>
jQuery( function( $ ) {
	var $iframes = $( '.post-likes-widget' );
	try {
		$iframes.Jetpack( 'resizeable' );
	} catch ( error ) {
		$iframes.height( 80 );
	}
} );
</script>
<?php
	}

	function post_likes( $content ) {
		global $post;

		if ( ! $this->is_likes_visible() )
			return $content;

		$protocol = 'http';
		if ( is_ssl() )
			$protocol = 'https';

		if ( defined( 'IS_WPCOM' ) && IS_WPCOM )
			$blog_id = get_current_blog_id();
		else {
			$jetpack = Jetpack::init();
			$blog_id = $jetpack->get_option( 'id' );
		}

		$src = sprintf( '%s://jetpack.me/like-widget/?blog_id=%d&post_id=%d', $protocol, $blog_id, $post->ID );

		$html  = '<div class="wpl-likebox sd-block sd-like"><h3 class="sd-title">' . esc_html__( 'Like this:' ) . '</h3>';
		$html .= "<iframe class='post-likes-widget' height='34px' width='100%' src='$src'></iframe>";
		$html .= '</div>';
		return $content . $html;
	}

	function comment_likes( $content, $comment = null ) {
		if ( empty( $comment ) )
			return $content;

		// does likes disable comment likes too?
		//if ( ! $this->is_likes_visible() )
		//	return $content;

		$protocol = 'http';
		if ( is_ssl() )
			$protocol = 'https';

		if ( defined( 'IS_WPCOM' ) && IS_WPCOM )
			$blog_id = get_current_blog_id();
		else {
			$jetpack = Jetpack::init();
			$blog_id = $jetpack->get_option( 'id' );
		}

		$src = sprintf( '%s://jetpack.me/like-widget/?blog_id=%d&comment_id=%d', $protocol, $blog_id, $comment->comment_ID );

		$iframe = "<iframe class='comment-likes-widget' height='40px' width='100%' src='$src'></iframe>";
		return $content . $iframe;
	}

	/**
	 * Get the 'disabled_likes' option from the DB of the current blog.
	 *
	 * @return array
	 */
	function get_options() {
		$setting             = array();
		$setting['disabled'] = get_option( 'disabled_likes'  );
		$sharing             = get_option( 'sharing-options' );

		// Default visibility settings
		if ( ! isset( $sharing['global']['show'] ) ) {
			$sharing['global']['show'] = array( 'post', 'page' );

		// Scalar check
		} elseif ( is_scalar( $sharing['global']['show'] ) ) {
			switch ( $sharing['global']['show'] ) {
				case 'posts' :
					$sharing['global']['show'] = array( 'post', 'page' );
					break;
				case 'index' :
					$sharing['global']['show'] = array( 'index' );
					break;
				case 'posts-index' :
					$sharing['global']['show'] = array( 'post', 'page', 'index' );
					break;
			}
		}

		// Ensure it's always an array (even if not previously empty or scalar)
		$setting['show'] = !empty( $sharing['global']['show'] ) ? (array) $sharing['global']['show'] : array();

		return apply_filters( 'wpl_get_options', $setting );
	}

	/** _is_ functions ************************************************************/

	/**
	 * Are likes visible in this context?
	 * 
	 * Some of this code was taken and modified from sharing_display() to ensure
	 * similar logic and filters apply here, too.
	 */
	function is_likes_visible() {

		global $wp_current_filter; // Used to check 'get_the_excerpt' filter
		global $post;              // Used to apply 'sharing_show' filter

		// Never show on feeds or previews
		if ( is_feed() || is_preview() || is_comments_popup() ) {
			$enabled = false;

		// Not a feed or preview, so what is it?
		} else {

			if ( in_the_loop() ) {
				// If in the loop, check if the current post is likeable
				$enabled = $this->is_post_likeable();
			} else {
				// Otherwise, check and see if likes are enabled sitewide
				$enabled = $this->is_enabled_sitewide();
			}

			/** Other Checks ******************************************************/

			// Do not show on excerpts
			if ( in_array( 'get_the_excerpt', (array) $wp_current_filter ) ) {
				$enabled = false;

			// Always on for a8c internal sites
			} /*elseif ( is_automattic_private() ) {
				$enabled = true;

			// Sharing Setting Overrides ****************************************

			}*/ else {
				// Single post
				if ( is_singular( 'post' ) ) {
					if ( ! $this->is_single_post_enabled() ) {
						$enabled = false;
					}

				// Single page
				} elseif ( is_page() ) {
					if ( ! $this->is_single_page_enabled() ) { 
						$enabled = false;
					}

				// Attachment
				} elseif ( is_attachment() ) {
					if ( ! $this->is_attachment_enabled() ) {
						$enabled = false;
					}

				// All other loops
				} elseif ( ! $this->is_index_enabled() ) {
					$enabled = false;
				}
			}	
		}

		// Run through the sharing filters
		$enabled = apply_filters( 'sharing_show', $enabled, $post );

		return (bool) apply_filters( 'wpl_is_likes_visible', $enabled );
	}

	/**
	 * Returns the current state of the "WordPress.com Likes are" option.
	 * @return boolean true if enabled sitewide, false if not
	 */
	function is_enabled_sitewide() {
		return (bool) apply_filters( 'wpl_is_enabled_sitewide', ! get_option( 'disabled_likes' ) );
	}

	/**
	 * Are likes enabled for this post?
	 *
	 * @param int $post_id
	 * @retun bool
	 */
	function is_post_likeable( $post_id = 0 ) {
		$post = get_post( $post_id );
		if ( !$post || is_wp_error( $post ) ) {
			return false;
		}

		$sitewide_likes_enabled = (bool) Jetpack_Likes::is_enabled_sitewide();
		$post_likes_switched    = (bool) get_post_meta( $post->ID, 'switch_like_status', true );
		
		$post_likes_enabled = $sitewide_likes_enabled;
		if ( $post_likes_switched ) {
			$post_likes_enabled = ! $post_likes_enabled;
		}

		return $post_likes_enabled;
	}

	/**
	 * Are Post Likes enabled on archive/front/search pages?
	 *
	 * @return bool
	 */
	function is_index_enabled() {
		$options = $this->get_options();
		return (bool) apply_filters( 'wpl_is_index_disabled', (bool) in_array( 'index', $options['show'] ) );
	}

	/**
	 * Are Post Likes enabled on single posts?
	 *
	 * @return bool
	 */
	function is_single_post_enabled() {
		$options = $this->get_options();
		return (bool) apply_filters( 'wpl_is_single_post_disabled', (bool) in_array( 'post', $options['show'] ) );
	}

	/**
	 * Are Post Likes enabled on single pages?
	 *
	 * @return bool
	 */
	function is_single_page_enabled() {
		$options = $this->get_options();
		return (bool) apply_filters( 'wpl_is_single_page_disabled', (bool) in_array( 'page', $options['show'] ) );
	}

	/**
	 * Are Media Likes enabled on single pages?
	 *
	 * @return bool
	 */
	function is_attachment_enabled() {
		$options = $this->get_options();
		return (bool) apply_filters( 'wpl_is_attachment_disabled', (bool) in_array( 'attachment', $options['show'] ) );
	}

}

Jetpack_Likes::init();
