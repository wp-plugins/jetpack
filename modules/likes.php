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

		if ( !$instance ) {
			$instance = new Jetpack_Likes;
		}

		return $instance;
	}

	function __construct() {
		$this->in_jetpack = ( defined( 'IS_WPCOM' ) && IS_WPCOM ) ? false : true;

		add_action( 'init', array( &$this, 'action_init' ) );

		if ( $this->in_jetpack) {
			add_action( 'jetpack_activate_module_likes',   array( $this, 'module_toggle' ) );
			add_action( 'jetpack_deactivate_module_likes', array( $this, 'module_toggle' ) );

			$active = Jetpack::get_active_modules();
			
			if ( ! in_array( 'sharedaddy', $active ) && !in_array( 'publicize', $active ) ) {
				add_action( 'admin_menu', array( $this, 'sharing_menu' ) );	// we don't have a sharing page yet
			}

			if ( in_array( 'publicize', $active ) && !in_array( 'sharedaddy', $active ) ) {
				add_action( 'pre_admin_screen_sharing', array( $this, 'sharing_block' ), 20 ); // we have a sharing page but not the global options area
				add_action( 'pre_admin_screen_sharing', array( $this, 'updated_message' ), -10 );
			}

			if( ! in_array( 'sharedaddy', $active ) ) {
				add_action( 'admin_init', array( $this, 'process_update_requests_if_sharedaddy_not_loaded' ) );
			}
		}

		add_action( 'sharing_global_options', array( $this, 'admin_settings_init' ), 20 );
		add_action( 'sharing_admin_update',   array( $this, 'admin_settings_callback' ) );
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
				<label><?php _e( 'WordPress.com Likes are', 'jetpack' ); ?></label>
			</th>
			<td>
				<div>
					<label>
						<input type="radio" class="code" name="wpl_default" value="on" <?php checked( $this->is_enabled_sitewide(), true ); ?> />
						<?php _e( 'On for all posts', 'jetpack' ); ?>
					</label>
				</div>
				<div>
					<label>
						<input type="radio" class="code" name="wpl_default" value="off" <?php checked( $this->is_enabled_sitewide(), false ); ?> />
						<?php _e( 'Turned on per post', 'jetpack' ); ?>
					</label>
				<div>
			</td>
		</tr>
	<?php }

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
		add_submenu_page( 'options-general.php', __( 'Sharing Settings', 'jetpack' ), __( 'Sharing', 'jetpack' ), 'manage_options', 'sharing', array( $this, 'sharing_page' ) );
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
			<h2><?php _e( 'Sharing Settings', 'jetpack' ); ?></h2>
			<?php do_action( 'pre_admin_screen_sharing' ) ?>
			<?php $this->sharing_block(); ?>
		</div> <?php
	}

	/**
	 * Returns the settings have been saved message.
	 */
	function updated_message() {
		if ( isset( $_GET['update'] ) && $_GET['update'] == 'saved' )
			echo '<div class="updated"><p>'.__( 'Settings have been saved', 'jetpack' ).'</p></div>';
	}

	/**
	 * Returns just the "sharing buttons" w/ like option block, so it can be inserted into different sharing page contexts
	 */
	function sharing_block() { ?>
		<h3><?php _e( 'Sharing Buttons', 'jetpack' ) ?></h3>
		<form method="post" action="">
		<table class="form-table">
		<tbody>	
			<?php do_action( 'sharing_global_options' ); ?>
		</tbody>
		</table>

		<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Changes', 'jetpack' ); ?>" />
		</p>
		
		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'sharing-options' );?>" />
		</form> <?php
	}

	/**
	 * Returns the current state of the "WordPress.com Likes are" option.
	 * @return boolean true if enabled sitewide, false if not
	 */
	function is_enabled_sitewide() {
		return (bool) apply_filters( 'wpl_is_enabled_sitewide', ! get_option( 'disabled_likes' ) );
	}

	function action_init() {
		if ( is_admin() )
			return;

		if ( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST )
			return;

		add_filter( 'the_content', array( &$this, 'post_likes' ), 10, 1 );
		add_filter( 'comment_text', array( &$this, 'comment_likes' ), 10, 2 );
	}

	function post_likes( $content ) {
		global $post;

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

		$iframe = "<iframe height='80px' width='100%' src='$src'></iframe>";
		return $content . $iframe;
	}

	function comment_likes( $content, $comment = null ) {
		if ( empty( $comment ) )
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

		$src = sprintf( '%s://jetpack.me/like-widget/?blog_id=%d&comment_id=%d', $protocol, $blog_id, $comment->comment_ID );

		$iframe = "<iframe height='40px' width='100%' src='$src'></iframe>";
		return $content . $iframe;
	}
}

Jetpack_Likes::init();
