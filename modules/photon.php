<?php
/**
 * Module Name: Photon
 * Module Description:
 * Sort Order: 15
 * First Introduced: 1.9
 */

class Jetpack_Photon {
	/**
	 *
	 */
	private static $__instance = null;

	// Allowed extensions must match http://code.trac.wordpress.org/browser/photon/index.php#L31
	protected $extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'png'
	);

	/**
	 * Singleton implementation
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_Photon' ) )
			self::$__instance = new Jetpack_Photon;

		return self::$__instance;
	}

	/**
	 *
	 */
	private function __construct() {
		if ( ! function_exists( 'jetpack_photon_url' ) )
			return;

		// Images in post content
		add_filter( 'the_content', array( $this, 'filter_the_content' ), 999999 );

		// Featured images aka post thumbnails
		add_action( 'begin_fetch_post_thumbnail_html', array( $this, 'action_begin_fetch_post_thumbnail_html' ) );
		add_action( 'end_fetch_post_thumbnail_html', array( $this, 'action_end_fetch_post_thumbnail_html' ) );
	}

	/**
	 * Note: Photon won't re-Photon URLs, so pass them anyway with our size adjustments
	 */
	public function filter_the_content( $content ) {
		if ( false != preg_match_all( '#<img(.+?)src=["|\'](.+?)["|\'](.+?)/?>#i', $content, $images ) ) {
			global $content_width;

			foreach ( $images[0] as $index => $tag ) {
				$src = $src_orig = $images[2][ $index ];

				// Ensure the image extension is acceptable
				$url_info = parse_url( $src );
				$extension = strtolower( pathinfo( $url_info['path'], PATHINFO_EXTENSION ) );

				if ( ! is_array( $url_info ) || ! in_array( $extension, $this->extensions ) )
					continue;

				// Find the width and height attributes
				$width = $height = false;
				foreach ( array( 1, 3 ) as $search_index ) {
					if ( false === $width && preg_match( '#width=["|\']?(\d+)["|\']?#i', $images[ $search_index ][ $index ], $width_string ) )
						$width = (int) $width_string[1];

					if ( false === $height && preg_match( '#height=["|\']?(\d+)["|\']?#i', $images[ $search_index ][ $index ], $height_string ) )
						$height = (int) $height_string[1];
				}

				if ( ( false === $width || false === $height ) && false != preg_match( '#(-\d+x\d+)\.(' . implode('|', $this->extensions ) . '){1}$#i', $src, $width_height_string ) ) {
					$width = (int) $width_height_string[1];
					$height = (int) $width_height_string[2];
				}

				// If width or height are available, constrain to $content_width
				if ( false !== $width && is_numeric( $content_width ) ) {
					if ( $width > $content_width && false !== $height ) {
						$height = ( $content_width * $height ) / $width;
						$width = $content_width;
					}
					elseif ( $width > $content_width ) {
						$width = $content_width;
					}

					if ( false === $height )
						$height = 9999;
				}

				// Set a width if none is found and height is available, either $content_width or a very large value
				// Large value is used so as to not unnecessarily constrain image when passed to Photon
				if ( false === $width && false !== $height )
					$width = is_numeric( $content_width ) ? (int) $content_width : 9999;

				// Set a height if none is found and width is available, using a large value
				if ( false === $height && false !== $width )
					$height = 9999;

				// As a last resort, ensure that image won't be larger than $content_width if it is set.
				if ( false === $width && is_numeric( $content_width ) ) {
					$width = (int) $content_width;
					$height = 9999;
				}

				// Build URL
				if ( false != preg_match( '#(-\d+x\d+)\.(' . implode('|', $this->extensions ) . '){1}$#i', $src, $src_parts ) ) {
					$src = str_replace( $src_parts[1], '', $src );
				}

				$args = array();

				if ( false !== $width && false !== $height )
					$args['fit'] = $width . ',' . $height;

				$photon_url = jetpack_photon_url( $src, $args );

				// Modify image tag if Photon function provides a URL
				// Ensure changes are only applied to the current image by copying and modifying the matched tag, then replacing the entire tag with our modified version.
				if ( $src != $photon_url ) {
					$new_tag = $tag;

					// Supplant the original source value with our Photon URL
					$photon_url = esc_url( $photon_url );
					$new_tag = str_replace( $src_orig, $photon_url, $new_tag );

					// Remove the width and height arguments from the tag to prevent stretching
					$new_tag = preg_replace( '#(width|height)=["|\']?(\d+)["|\']?\s{1}#i', '', $new_tag );

					$content = str_replace( $tag, $new_tag, $content );
				}

			}
		}

		return $content;
	}

	/**
	 *
	 */
	public function action_begin_fetch_post_thumbnail_html() {
		add_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 10, 3 );
	}

	/**
	 *
	 */
	public function action_end_fetch_post_thumbnail_html() {
		remove_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 10, 3 );
	}

	/**
	 *
	 */
	public function filter_image_downsize( $image, $attachment_id, $size ) {
		$image_url = wp_get_attachment_url( $attachment_id );

		if ( $image_url ) {
			global $_wp_additional_image_sizes;

			if ( array_key_exists( $size, $_wp_additional_image_sizes ) ) {
				$image_args = $_wp_additional_image_sizes[ $size ];

				$photon_args = array();

				if ( $image_args['crop'] )
					$photon_args['resize'] = $image_args['width'] . ',' . $image_args['height'];
				else
					$photon_args['fit'] = $image_args['width'] . ',' . $image_args['height'];

				$image = array(
					jetpack_photon_url( $image_url, $photon_args ),
					false,
					false
				);
			}
		}

		return $image;
	}
}

Jetpack_Photon::instance();