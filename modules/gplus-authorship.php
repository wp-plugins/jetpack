<?php
/**
* Module Name: Google+ Profile
* Module Description: Show a link to your Google+ in the sharing area of your posts and add your blog URL to your Google+ profile.
* Sort Order: 10
* First Introduced: 2.5
* Requires Connection: Yes
* Auto Activate: Yes
*/
add_action( 'init', function() {
	new GPlus_Authorship;
} );

class GPlus_Authorship {

	function __construct() {
		$this->in_jetpack = ( defined( 'IS_WPCOM' ) && IS_WPCOM ) ? false : true;
		if ( $this->in_jetpack ) {
			require "gplus-authorship/admin/ui.php";
			$gplus_admin = new GPlus_Authorship_Admin;
			add_action( 'save_post', array( GPlus_Authorship_Admin, 'save_post_meta' ) );
			add_action( 'wp_ajax_save_gplus_profile_data', array( $this, 'save_profile_data' ) );
			add_filter( 'the_content', array( $this, 'post_output_wrapper' ), 22, 1 );
		} else {
			add_filter( 'post_flair', array( $this, 'post_output_wrapper' ), 22 );
		}
		add_action( 'wp_head', array( $this, 'link_tag_styles_and_scripts' ) );
		add_filter( 'the_author', array( $this, 'overwrite_the_author' ) );
		add_filter( 'the_content', array( $this, 'overwrite_rel_attrs' ) );
	}

	function show_on_this_post() {
		global $post;
		$show = apply_filters( 'gplus_authorship_show', true, $post );
		$author = $this->information( $post->post_author );
		if ( empty( $author ) )
			$show = false;
		$meta = get_post_meta( $post->ID, 'gplus_authorship_disabled', true );
		if ( isset( $meta ) && true == $meta )
			$show = false;
		return $show;
	}

	function information( $author ) {
		$authors = get_option( 'gplus_authors', array() );
		return ( empty( $authors[ $author ] ) ? array() : $authors[ $author ] );
	}

	/**
	* Both overwrite_rel_attrs and rel_callback remove 'author' from the rel attribute of a link if it is found
	* and if the post is being viewed on a single page, with G+ authorship enabled.
	* based on prior art from privatize_link in wp-content/mu-plugins/private-blog.php
	*/
	function overwrite_rel_attrs( $content ) {
		if ( !is_single() ) // don't bother unless we are on a page where the G+ authorship link is actually being displayed
			return $content;
		if ( !$this->show_on_this_post() ) // G+ isn't enabled
			return $content;
		return preg_replace_callback( '#<a\s+[^>]+>#i', array( $this, 'rel_callback' ), $content );
	}

	function rel_callback( $link ) {
		$link = $link[0]; // preg replace returns as array

		$dom = new DOMDocument;
		$link = mb_convert_encoding( $link, 'HTML-ENTITIES', 'UTF-8' );
		@$dom->loadHTML( "<html><body>$link</a></body></html>" );
		$link_node = false;
		foreach ( $dom->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'html' === strtolower( $child->tagName ) ) {
				$link_node = $child->firstChild->firstChild;
				break;
			}
		}

		// Don't bother if it's not actually a link (pointing to another document) or if there is no rel attribute.
		if ( !$link_node )
			return $link;
		if ( !$link_node->hasAttribute( 'href' ) )
			return $link;
		if ( !$link_node->hasAttribute( 'rel' ) )
			return $link;

		$rels = explode( ' ', $link_node->getAttribute( 'rel' ) );

		// delete 'author' from the list
		if ( ( $key = array_search( 'author', $rels ) ) !== false ) {
			unset( $rels[$key] );
		}

		// if there was more then one part of the attribute, set the new value, otherwise just get rid of the attribute all together
		if ( count( $rels ) > 0 )
			$link_node->setAttribute( 'rel', join( ' ', $rels ) );
		else
			$link_node->removeAttribute( 'rel' );

		$link = $dom->saveXML( $link_node );
		$link = rtrim( $link, '/>' ) . '>';
		return $link;
	}

	/**
	* Jetpack Only
	*/
	function save_profile_data() {
		global $current_user;
		check_ajax_referer( 'gplus-connect', 'state' );
		if ( !is_numeric( $_POST['id'] ) )
			return;
		$connections = get_option( 'gplus_authors', array() );
		$connections[ $current_user->ID ]['name'] = $_POST['name'];
		$connections[ $current_user->ID ]['id'] = $_POST['id'];
		$connections[ $current_user->ID ]['url'] = esc_url_raw( $_POST['url'] );
		$connections[ $current_user->ID ]['profile_image'] = esc_url_raw( $_POST['profile_image'] );
		update_option( 'gplus_authors', $connections );
	}

	/**
	* Google+ insits we don't have two different bylines..
	* so we will display their G+ username in the byline here too
	*/
	function overwrite_the_author( $author_name ) {
		global $post;

		if ( !$this->show_on_this_post() )
			return $author_name;

		$author = $this->information( $post->post_author );
		return esc_html( $author['name'] );
	}

	function byline( $post ) {
		$author = $this->information( $post->post_author );
		$image = '<img src="' . esc_url( $author['profile_image'] ) . '?sz=40" alt="" width="20" height="20" align="absmiddle" /> ';
		$byline = sprintf( '<a href="%1$s">%2$s</a><a rel="author" href="%1$s" class="gplus-profile">%3$s</a>', esc_url( $author['url'] ), $image, esc_html( $author['name'] ) );
		return apply_filters( 'gplus_authorship_byline', $byline, $post );
	}

	function link_tag_styles_and_scripts() {
		if ( !is_single() )
			return;
		if  ( get_post_type() != 'post' )
			return;
		if ( !$this->show_on_this_post() )
			return;

		global $post;
		$author = $this->information( $post->post_author );
		echo '<link rel="author" href="'. esc_url( $author['url'] ) .'" title="' . esc_attr( $author['name'] ) . ' ' . __( 'on Google+', 'jetpack' ) .'" /> ' . "\n";
		if ( $this->in_jetpack )
			$css = plugins_url( 'gplus-authorship/style.css', __FILE__ );
		else
			$css = plugins_url( 'gplus/style.css', __FILE__ );
		wp_enqueue_style( 'gplus', $css );
		wp_enqueue_script( 'plusone', '//apis.google.com/js/plusone.js' );
	}

	function follow_button( $post ) {
		$author = $this->information( $post->post_author );
		return '<span class="g-follow-wrapper"><span class="g-follow" data-href="' .  esc_url( $author['url'] ) . '" data-rel="author" data-height="15"></span></span>';
	}

	function post_output_wrapper( $text = '', $echo = false ) {
		global $post, $wp_current_filter;

		if ( !is_single() )
			return $text;
		if  ( get_post_type() != 'post' )
			return $text;
		$author = $this->information( $post->post_author );
		if ( empty( $author ) )
			return $text;

		// Don't allow G+ to be added to the_content more than once (prevent infinite loops)
		$done = false;
		foreach ( $wp_current_filter as $filter ) {
			if ( 'the_content' == $filter ) {
				if ( $done )
					return $text;
				else
					$done = true;
			}
		}

		if ( !$this->show_on_this_post())
			return $text;

		$output = '';
		$output .= '<div class="sharedaddy sd-block sd-social sd-gplus">';
		$output .= '<h3 class="sd-title">' . __( 'Google+', 'jetpack' ) . '</h3>';
		$output .= '<div class="sd-content">';
		$output .= $this->byline( $post );
		$output .= $this->follow_button( $post );
		$output .= '</div>';
		$output .= '</div>';

		if ( $echo )
			echo $text . $output;
		else
			return $text . $output;
	}

}

?>