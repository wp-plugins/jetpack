<?php
/**
 * Module Name: WordPress.com Notifications
 * Module Description: Generate notifications and display the Notifications toolbar menu on your site.
 * Sort Order: 1
 * First Introduced: 1.1
 */

class Jetpack_Notifications {
	var $jetpack = false;
	var $always_show_toolbar = false;

	/**
	 * Singleton
	 * @static
	 */
	function &init() {
		static $instance = array();

		if ( !$instance ) {
			$instance[0] =& new Jetpack_Notifications;
		}

		return $instance[0];
	}

	function Jetpack_Notifications() {
		add_action( 'jetpack_modules_loaded', array( &$this, 'enable_configuration' ) );
		add_action( 'init', array( &$this, 'action_init' ) );
		$this->always_show_toolbar = get_option( 'jp_notes_always_show_toolbar', 1 );
		if ( $this->always_show_toolbar )
			add_filter( 'show_admin_bar', '__return_true' , 1000 );
	}

	function wpcom_static_url($file) {
		$i = hexdec( substr( md5( $file ), -1 ) ) % 2;
		$http = is_ssl() ? 'https' : 'http';
		$url = $http . '://s' . $i . '.wordpress.com' . $file;
		return $url;
	}

	function action_init() {
		wp_enqueue_style( 'notes-admin-bar-rest', $this->wpcom_static_url( '/wp-content/mu-plugins/notes/admin-bar-rest.css' ), array(), '2012-05-18a' );
		wp_enqueue_script( 'spin', $this->wpcom_static_url( '/wp-includes/js/spin.js' ), array( 'jquery' ) );
		wp_enqueue_script( 'jquery.spin', $this->wpcom_static_url( '/wp-includes/js/jquery/jquery.spin.js' ), array( 'jquery', 'spin' ) );
		wp_enqueue_script( 'notes-postmessage', $this->wpcom_static_url( '/wp-content/js/postmessage.js' ), array(), '20120525', true );
		wp_enqueue_script( 'mustache', $this->wpcom_static_url( '/wp-content/js/mustache.js' ), null, '2012-05-04', true );
		wp_enqueue_script( 'underscore', $this->wpcom_static_url( '/wp-content/js/underscore.js' ), null, '2012-05-04', true );
		wp_enqueue_script( 'backbone', $this->wpcom_static_url( '/wp-content/js/backbone.js' ), array( 'jquery', 'underscore' ), '2012-05-04', true );
		wp_enqueue_script( 'notes-rest-common', $this->wpcom_static_url( '/wp-content/mu-plugins/notes/notes-rest-common.js' ), array( 'backbone', 'mustache' ), '2012-05-24a', true );
		wp_enqueue_script( 'notes-admin-bar-rest', $this->wpcom_static_url( '/wp-content/mu-plugins/notes/admin-bar-rest.js' ), array( 'jquery', 'underscore', 'backbone' ), '20120927', true );
		add_action( 'admin_bar_menu', array( &$this, 'admin_bar_menu'), 120 );
		add_action( 'wp_print_scripts', array( &$this, 'print_js'), 0 );
	}

	function admin_bar_menu() {
		global $wp_admin_bar, $current_blog;

		if ( !is_object( $wp_admin_bar ) )
			return;

		$classes = 'wpnt-loading';

		$noticon = '//s0.wp.com/wp-content/mu-plugins/notes/images/noticon-empty.png';

		$wp_admin_bar->add_menu( array(
			'id'     => 'notes',
			'title'  => '<span id="wpnt-notes-unread-count" class="' . esc_attr( $classes ) . '">'
					. '<img width="14px" height="14px" src="' . esc_url( $noticon ) . '" style="display: inline-block; width: 14px; height: 14px; overflow-x: hidden; overflow-y: hidden;" /></span>',
			'meta'   => array(
				'html'  => '<div id="wpnt-notes-panel" style="display:none"><div class="wpnt-notes-panel-header"><span class="wpnt-notes-header">' . __('Notifications') . '</span><span class="wpnt-notes-panel-link"></span></div></div>',
				'class' => 'menupop',
			),
			'parent' => 'top-secondary',
		) );

		if ( !is_user_logged_in() ) {
			$menu = array(
				'title'  => __( 'Log In', 'jetpack' ),
				'href'   => wp_login_url(),
				'parent' => 'top-secondary',
			);

			$blog_name = get_bloginfo('name');
			$menu[ 'meta' ][ 'title' ] = empty( $blog_name ) ? __( 'Log In', 'jetpack' ) : sprintf( __( 'Log In to %s', 'jetpack' ), $blog_name );

			$wp_admin_bar->add_menu( $menu );
		}
	}

	function print_js() {
?>
<script type="text/javascript">
/* <![CDATA[ */
	var wpNotesIsJetpackClient = true;
/* ]]> */
</script>
<?php
	}

	// Add Configuration Page
	function enable_configuration() {
		Jetpack::enable_module_configurable( __FILE__ );
		Jetpack::module_configuration_load( __FILE__, array( &$this, 'load_settings_page' ) );
		add_action( 'admin_init', array( &$this, 'configure' ) );
	}

	function load_settings_page() {
		wp_safe_redirect( admin_url( 'options-discussion.php#jetpack-notifications-settings' ) );
		exit;
	}

	/**
	 * Jetpack_Notifications::configure()
	 *
	 * Jetpack Notifications configuration screen.
	 */
	function configure() {
		// Create the section
		add_settings_section(
			'jetpack_notes',
			__( 'Jetpack Notifications Settings', 'jetpack' ),
			array( $this, 'notes_settings_section' ),
			'discussion'
		);

		/** Optionally always show the Toolbar ********************************/
		add_settings_field(
			'jetpack_notes_option_always_show_toolbar',
			__( 'Toolbar', 'jetpack' ),
			array( $this, 'notes_option_always_show_toolbar' ),
			'discussion',
			'jetpack_notes'
		);

		register_setting(
			'discussion',
			'jp_notes_always_show_toolbar'
		);
	}

	/**
	 * Discussions setting section blurb
	 *
	 */
	function notes_settings_section() {
	?>
		<p id="jetpack-notifications-settings"><?php _e( 'Change how your site interacts with the WordPress.com Notifications System.', 'jetpack' ); ?></p>
	<?php
	}

	function notes_option_always_show_toolbar() {
	?>
		<p class="description">
			<input type="checkbox" name="jp_notes_always_show_toolbar" id="jetpack-notes-always_show_toolbar" value="1" <?php checked( $this->always_show_toolbar ); ?> />
			<?php _e( "Always show the Toolbar so visitors can view their notifications from your site", 'jetpack' ); ?>
		</p>
	<?php
	}
}

Jetpack_Notifications::init();