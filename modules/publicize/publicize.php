<?php

abstract class Publicize_Base {

	/**
	* Services that are currently connected to the current user
	* through publicize.
	*/
	var $connected_services;

	/**
	* Sservices that are supported by publicize. They don't
	* neccessarly need to be connected to the current user.
	*/
	var $services;

	/**
	* key names for post meta
	*/
	var $ADMIN_PAGE        = 'wpas';
	var $POST_MESS         = '_wpas_mess';
	var $POST_SKIP         = '_wpas_skip_'; // connection id appended to indicate that a connection should NOT be publicized to
	var $POST_DONE         = '_wpas_done_'; // connection id appended to indicate a connection has already been publicized to
	var $USER_AUTH         = 'wpas_authorize';
	var $USER_OPT          = 'wpas_';
	var $PENDING           = '_publicize_pending'; // ready for Publicize to do its thing
	var $POST_SERVICE_DONE = '_publicize_done_external'; // array of external ids where we've Publicized

	/**
	* default pieces of the message used in constructing the
	* content pushed out to other social networks
	*/
	var $default_prefix  = '';
	var $default_message = '%title%';
	var $default_suffix  = ' %url%';

	/**
	 * What WP capability is require to create/delete global connections?
	 * All users with this cap can unglobalize all other global connections, and globalize any of their own
	 * Globalized connections cannot be unselected by users without this capability when publishing
	 */
	const GLOBAL_CAP = 'edit_others_posts';

	/**
	* Sets up the basics of Publicize
	*/
	function __construct() {
		$this->default_message = Publicize_Util::build_sprintf( array(
			apply_filters( 'wpas_default_message', $this->default_message ),
			'title',
			'url',
		) );

		$this->default_prefix = Publicize_Util::build_sprintf( array(
			apply_filters( 'wpas_default_prefix', $this->default_prefix ),
			'url',
		) );

		$this->default_suffix = Publicize_Util::build_sprintf( array(
			apply_filters( 'wpas_default_suffix', $this->default_suffix ),
			'url',
		) );


		// stage 1 and 2 of 3-stage Publicize. Flag for Publicize on creation, save meta,
		// then check meta and publicze based on that. stage 3 implemented on wpcom
		add_action( 'transition_post_status', array( $this, 'flag_post_for_publicize' ), 10, 3 );
		add_action( 'save_post', array( &$this, 'save_meta' ), 20, 2 );
	}

	/**
	* Functions to be implemented by the extended class (publicize-wpcom or publicize-jetpack)
	*/
	abstract function get_connection_id( $connection );
	abstract function connect_url( $service_name );
	abstract function disconnect_url( $service_name, $id );
	abstract function get_connection_meta( $connection );
	abstract function get_services( $filter );
	abstract function get_connections( $service, $_blog_id, $_user_id );
	abstract function get_connection( $service, $id, $_blog_id, $_user_id );
	abstract function flag_post_for_publicize( $new_status, $old_status, $post );
	abstract function save_meta( $post_id, $post );

	/**
	* Shared Functions
	*/
	
	/**
	* Returns an external URL to the connection's profile
	*/ 
	function get_profile_link( $service_name, $c ) {
		$cmeta = $this->get_connection_meta( $c );
		
		if ( isset( $cmeta['connection_data']['meta']['link'] ) ) {
			return $cmeta['connection_data']['meta']['link'];
		} elseif ( 'facebook' == $service_name && isset( $cmeta['connection_data']['meta']['facebook_page'] ) ) {
			return 'http://facebook.com/' . $cmeta['connection_data']['meta']['facebook_page'];
		} elseif ( 'facebook' == $service_name ) {
			return 'http://www.facebook.com/' . $cmeta['external_id'];
		} elseif ( 'tumblr' == $service_name && isset( $cmeta['connection_data']['meta']['tumblr_base_hostname'] ) ) {
			 return 'http://' . $cmeta['connection_data']['meta']['tumblr_base_hostname'];
		} elseif ( 'twitter' == $service_name ) {
			return 'http://twitter.com/' . $cmeta['external_name'];
		} else if ( 'yahoo' == $service_name ) {
			return 'http://profile.yahoo.com/' . $cmeta['external_id'];
		} else {
			return false; // no fallback. we just won't link it
		}
	}

	/**
	* Returns a display name for the connection
	*/
	function get_display_name( $service_name, $c ) {
		$cmeta = $this->get_connection_meta( $c );
		
		if ( isset( $cmeta['connection_data']['meta']['display_name'] ) ) {
			return $cmeta['connection_data']['meta']['display_name'];
		} elseif ( 'tumblr' == $service_name && isset( $cmeta['connection_data']['meta']['tumblr_base_hostname'] ) ) {
			 return $cmeta['connection_data']['meta']['tumblr_base_hostname'];
		} elseif ( 'twitter' == $service_name ) {
			return '@' . $cmeta['external_name'];
		} else {
			$connection_display = $cmeta['external_display'];
			if ( empty( $connection_display ) )
				$connection_display = $cmeta['external_name'];
			return $connection_display;
		}
	}

	function get_service_label( $service_name ) {
		switch ( $service_name ) {
			case 'twitter':
				return 'Twitter';
			break;
			case 'facebook':
				return 'Facebook';
			break;
			case 'yahoo':
				return 'Yahoo!';
			break;
			case 'linkedin':
				return 'LinkedIn';
			break;
			case 'tumblr':
				return 'Tumblr';
			break;
		}
	}

	function show_options_popup( $service_name, $c ) {
		$cmeta = $this->get_connection_meta( $c );

		// always show if no selection has been made for facebook
		if ( 'facebook' == $service_name && empty( $cmeta['connection_data']['meta']['facebook_profile'] ) && empty( $cmeta['connection_data']['meta']['facebook_page'] ) )
			return true;

		// always show if no selection has been made for tumblr
		if ( 'tumblr' == $service_name && empty ( $cmeta['connection_data']['meta']['tumblr_base_hostname'] ) )
			return true;

		// otherwise, just show if this is the completed step / first load
		if ( 'completed' == $_GET['action'] && !empty( $_GET['service'] ) && $service_name == $_GET['service'] && ! in_array( $_GET['service'], array( 'facebook', 'tumblr' ) ) )
			return true;

		return false;
	}

	function user_id() {
		global $current_user;
		return $current_user->ID;
	}

	function blog_id() {
		return get_current_blog_id();
	}

	/**
	* Returns true if a user has a connection to a particular service, false otherwise
	*/
	function is_enabled( $service, $_blog_id = false, $_user_id = false ) {
		if ( !$_blog_id )
			$_blog_id = $this->blog_id();

		if ( !$_user_id )
			$_user_id = $this->user_id();

		$connections = $this->get_connections( $service, $_blog_id, $_user_id );
		return ( is_array( $connections ) && count( $connections ) > 0 ? true : false );
	}
}
