<?php
/** 
* Jetpack Specific File - However, it should implement the same functions as WordPress.com so
* the UI/shared bits can call the same things. (@todo)
*/

class Publicize {

	function __construct() {
		add_action( 'load-settings_page_sharing', array( $this, 'admin_page_load' ) );
		add_action( 'wp_ajax_publicize_facebook_options_page', array( $this, 'options_page_facebook' ) );
		add_action( 'wp_ajax_publicize_facebook_options_save', array( $this, 'options_save_facebook' ) );
		add_action( 'wp_ajax_publicize_tumblr_options_page', array( $this, 'options_page_tumblr' ) );
		add_action( 'wp_ajax_publicize_tumblr_options_save', array( $this, 'options_save_tumblr' ) );
	}
	
	function admin_page_load() {
		if ( isset( $_GET['action'] ) ) {
		
			if ( isset( $_GET['service'] ) )
				$service_name = $_GET['service'];
			
			switch ( $_GET['action'] ) {
				case 'request':
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );
					
					$verification = Jetpack::create_nonce( 'publicize' );
				
					$stats_options = get_option( 'stats_options' );
					$stats_id = isset( $stats_options['blog_id'] ) ? $stats_options['blog_id'] : null;
			
					$user = wp_get_current_user();
					$redirect = $this->api_url( $service_name, urlencode_deep( array(
						'action'       => 'request',
						'redirect_uri' => add_query_arg( array( 'action'   => 'done' ), menu_page_url( 'sharing', false ) ),
						'for'          => 'publicize', // required flag that says this connection is intended for publicize
						'siteurl'      => site_url(),
						'state'        => $user->ID,
						'blog_id'      => $stats_id,
						'secret_1'	   => $verification['secret_1'],
						'secret_2'     => $verification['secret_2'],
						'eol'		   => $verification['eol'],
					) ) );
					wp_redirect( $redirect );
					exit;
				break;
				
				case 'refresh':
				case 'completed':
					// Jetpack blog requests Publicize Connections via new XML-RPC method
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.fetchPublicizeConnections' );
					
					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;
				
				case 'delete':
					$id = $_GET['id'];
					
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );
					
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.deletePublicizeConnection', $id );
					
					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;
				
				case 'globalize':
				case 'unglobalize':
					$id = $_GET['id'];
				
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );
					
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.globalizePublicizeConnection', $id, $_GET['action'] );
				
					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;
				
				case 'temporary_options_example':
					$id = $_GET['id'];
				
					check_admin_referer( 'keyring-request', 'kr_nonce' );
					check_admin_referer( "keyring-request-$service_name", 'nonce' );
					
					$options = array( 'tumblr_base_hostname' => $_GET['blog_selected'] );
					
					Jetpack::load_xml_rpc_client();
					$xml = new Jetpack_IXR_Client();
					$xml->query( 'jetpack.setPublicizeOptions', $id, $options );
					
					if ( !$xml->isError() ) {
						$response = $xml->getResponse();
						Jetpack::update_option( 'publicize_connections', $response );
					}
				break;
			}
			
			
		}
	}

	/**
	* Gets a URL to the public-api actions. Works like WP's admin_url
	*
	* @param string $service Shortname of a specific service.
	* @return URL to specific public-api process
	*/
	// on WordPress.com this is/calls Keyring::admin_url
	function api_url( $service = false, $params = array() ) {
		$url = apply_filters( 'publicize_api_url', 'https://public-api.wordpress.com/connect/?jetpack=publicize' );
		
		if ( $service )
			$url = add_query_arg( array( 'service' => $service ), $url );
		
		if ( count ( $params ) )
			$url = add_query_arg( $params, $url );
			
		return $url;
	}
	
	function connect_url( $service_name ) {
		return add_query_arg( array (
			'action'   => 'request',
			'service'  =>  $service_name,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}
	
	// just for development - should be removed before release
	function refresh_url() {
		return add_query_arg( array (
			'action'   => 'refresh',
		), menu_page_url( 'sharing', false ) );
	}
	
	// just for development - should be removed before release
	function temporary_options_example_url( $service_name, $id, $blog_selected ) {
		return add_query_arg( array (
			'action'        => "temporary_options_example",
			'service'       => $service_name,
			'id'            => $id,
			'blog_selected' => $blog_selected,
			'kr_nonce'      => wp_create_nonce( 'keyring-request' ),
			'nonce'         => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );		
	}
	
	function disconnect_url( $service_name, $id ) {
		return add_query_arg( array (
			'action'   => 'delete',
			'service'  => $service_name,
			'id'       => $id,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}
	
	function globalize_url( $service_name, $id, $action = 'globalize' ) {
		return add_query_arg( array (
			'action'   => $action,
			'service'  => $service_name,
			'id'       => $id,
			'kr_nonce' => wp_create_nonce( 'keyring-request' ),
			'nonce'    => wp_create_nonce( "keyring-request-$service_name" ),
		), menu_page_url( 'sharing', false ) );
	}

	function get_services() {
		return array(
			'facebook' => array(),
			'twitter'  => array(),
			'yahoo'    => array(),
			'tumblr'   => array(),
			'linkedin' => array(),
		);
	}

	function options_page_facebook() {
		$connected_services = Jetpack::get_option( 'publicize_connections' );
		$connection = $connected_services['facebook'][$_POST['connection']];
		$options_to_show = $connection['connection_data']['meta']['options_responses'];

		// Nonce check
		check_admin_referer( 'options_page_facebook_' . $_REQUEST['connection'] );
		
		$me = $options_to_show[0];
		$pages = $options_to_show[1]['data'];

		$profile_checked = true;
		$page_selected = false;

		if ( !empty( $connection['connection_data']['meta']['facebook_page'] ) && !empty( $connection['connection_data']['meta']['facebook_page_token'] ) ) {
			$found = false;
			if ( is_array( $pages->data ) ) {
				foreach ( $pages->data as $page ) {
					if ( $page->id == $connection['connection_data']['meta']['facebook_page'] ) {
						$found = true;
						break;
					}
				}
			}

			if ( $found ) {
				$profile_checked = false;
				$page_selected = $connection['connection_data']['meta']['facebook_page'];
			} else {
				// Reset page token
				//$this->store_token( null );
			}
		}
		
		?>

		<div id="thickbox-content">

			<p><?php _e('Publicize to my <strong>Facebook Wall</strong>:') ?></p>
			<table id="option-profile">
				<tbody>
					<tr>
						<td class="radio"><input type="radio" name="option" id="<?php echo esc_attr( $me['id'] ) ?>" value="" <?php checked( $profile_checked, true ); ?> /></td>
						<td class="thumbnail"><label for="<?php echo esc_attr( $me['id'] ) ?>"><img src="<?php echo esc_url( $me['picture']['data']['url'] ) ?>" width="50" height="50" /></label></td>
						<td class="details"><label for="<?php echo esc_attr( $me['id'] ) ?>"><?php echo esc_html( $me['name'] ) ?></label></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $pages ) : ?>

				<p><?php _e('Publicize to my <strong>Facebook Page</strong>:') ?></p>
				<table id="option-fb-fanpage">
					<tbody>

						<?php foreach ( $pages as $i => $page ) : ?>
							<?php if ( ! isset( $page['perms'] ) ) { continue; } ?>
							<?php if ( ! ( $i % 2 ) ) : ?>
								<tr>
							<?php endif; ?>
									<td class="radio"><input type="radio" name="option" id="<?php echo esc_attr( $page['id'] ) ?>" value="<?php echo esc_attr( $page['access_token'] ) ?>" <?php checked( $page_selected && $page_selected == $page['id'], true ); ?> /></td>
									<td class="thumbnail"><label for="<?php echo esc_attr( $page['id'] ) ?>"><img src="<?php echo esc_url( str_replace( '_s', '_q', $page['picture']['data']['url'] ) ) ?>" width="50" height="50" /></label></td>
									<td class="details">
										<label for="<?php echo esc_attr( $page['id'] ) ?>">
											<span class="name"><?php echo esc_html( $page['name'] ) ?></span><br/>
											<span class="category"><?php echo esc_html( $page['category'] ) ?></span>
										</label>
									</td>
							<?php if ( ( $i % 2 ) || ( $i == count( $pages ) - 1 ) ): ?>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>

					</tbody>
				</table>

			<?php endif; ?>

			<p style="text-align: center;">
				<input type="submit" value="<?php esc_attr_e('Save these settings') ?>" class="button fb-options save-options" name="save" data-connection="<?php echo esc_attr( $_REQUEST['connection'] ); ?>" rel="<?php echo wp_create_nonce('save_fb_token_' . $_REQUEST['connection'] ) ?>" />
			</p><br/>
		</div>

		<?php
	}

	function options_save_facebook() {
		// Nonce check
		check_admin_referer( 'save_fb_token_' . $_REQUEST['connection'] );

		$id = $_POST['connection'];

		// Check for a numeric page ID
		$page_id = (int) $_POST['selected_id'];
		if ( !ctype_digit( $page_id ) )
			die( 'Security check' );

		if ( isset( $_POST['selected_id'] ) && empty( $_POST['token'] ) ) {
			// Publish to User Wall/Profile
			$options = array(
				'facebook_page'       => null,
				'facebook_page_token' => null,
				'facebook_profile'    => true
			);

		} else {
			if ( !isset( $_POST['token'] ) || !isset( $_POST['selected_id'] ) ) {
				return;
			}

			// Publish to Page
			$page_token = $_POST['token'];
			$page_token = urlencode( $page_token );
			$options = array(
				'facebook_page'       => $page_id,
				'facebook_page_token' => $page_token,
				'facebook_profile'    => null
			);
		}

		Jetpack::load_xml_rpc_client();
		$xml = new Jetpack_IXR_Client();
		$xml->query( 'jetpack.setPublicizeOptions', $id, $options );
		
		if ( !$xml->isError() ) {
			$response = $xml->getResponse();
			Jetpack::update_option( 'publicize_connections', $response );
		}
	}

	function options_page_tumblr() {
		// Nonce check
		check_admin_referer( 'options_page_tumblr_' . $_REQUEST['connection'] );

		$connected_services = Jetpack::get_option( 'publicize_connections' );
		$connection = $connected_services['tumblr'][$_POST['connection']];
		$options_to_show = $connection['connection_data']['meta']['options_responses'];
		$request = $options_to_show[0];

		$blogs = $request['response']['user']['blogs'];
		$blog_selected = false;

		if ( !empty( $connection['connection_data']['meta']['tumblr_base_hostname'] ) ) {
			foreach ( $blogs as $blog ) {
				if ( $connection['connection_data']['meta']['tumblr_base_hostname'] == $this->get_basehostname( $blog['url'] ) ) {
					$blog_selected = $connection['connection_data']['meta']['tumblr_base_hostname'];
					break;
				}
			}

		}

		// Use their Primary blog if they haven't selected one yet
		if ( !$blog_selected ) {
			foreach ( $blogs as $blog ) {
				if ( $blog['primary'] )
					$blog_selected = $this->get_basehostname( $blog['url'] );
			}
		} ?>

		<div id="thickbox-content">

			<p><?php _e('Publicize to my <strong>Tumblr blog</strong>:') ?></p>

			<ul id="option-tumblr-blog">

			<?php
			foreach ( $blogs as $blog ) {
				$url = $this->get_basehostname( $blog['url'] ); ?>
				<li>
					<input type="radio" name="option" id="<?php echo esc_attr( $url ) ?>" value="<?php echo esc_attr( $url ) ?>" <?php checked( $blog_selected == $url, true ); ?> />
					<label for="<?php echo esc_attr( $url ) ?>"><span class="name"><?php echo esc_html( $blog['title'] ) ?></span></label>
				</li>
			<?php } ?>

			</ul>

			<p style="text-align: center;">
				<input type="submit" value="<?php esc_attr_e( 'Save these settings' ) ?>" class="button tumblr-options save-options" name="save" data-connection="<?php echo esc_attr( $_REQUEST['connection'] ); ?>" rel="<?php echo wp_create_nonce( 'save_tumblr_blog_' . $_REQUEST['connection'] ) ?>" />
			</p> <br />
		</div> 

		<?php
	}

	function get_basehostname( $url ) {
		return parse_url( $url, PHP_URL_HOST );
	}

	function options_save_tumblr() {
		// Nonce check
		check_admin_referer( 'save_tumblr_blog_' . $_REQUEST['connection'] );

		$id = $_POST['connection'];
		
		$options = array( 'tumblr_base_hostname' => $_POST['selected_id'] );
		
		Jetpack::load_xml_rpc_client();
		$xml = new Jetpack_IXR_Client();
		$xml->query( 'jetpack.setPublicizeOptions', $id, $options );
		
		if ( !$xml->isError() ) {
			$response = $xml->getResponse();
			Jetpack::update_option( 'publicize_connections', $response );
		}
	}
}
?>