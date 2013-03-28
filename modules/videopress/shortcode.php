<?php

class Jetpack_VideoPress_Shortcode {
	public $js_loaded = false;
	const min_width = 60;

	function __construct() {
		add_shortcode( 'videopress', array( $this, 'shortcode_callback' ) );
		add_shortcode( 'wpvideo', array( $this, 'shortcode_callback' ) );
	}

	/**
	 * Translate a 'videopress' or 'wpvideo' shortcode and arguments into a video player display.
	 *
	 * @link http://codex.wordpress.org/Shortcode_API Shortcode API
	 * @param array $attr shortcode attributes
	 * @return string HTML markup or blank string on fail
	 */
	public function shortcode_callback( $attr, $content = '' ) {
		global $content_width;

		$guid = $attr[0];
		if ( ! self::is_valid_guid( $guid ) )
			return '';

		$attr = shortcode_atts( array(
			'w' => 0,
			'freedom' => false,
			'flashonly' => false,
			'autoplay' => false,
			'hd' => false
		), $attr );

		$attr['forcestatic'] = false;

		$attr['freedom'] = (bool) $attr['freedom'];
		$attr['hd'] = (bool) $attr['hd'];
		$attr['width'] = absint( $attr['w'] );

		if ( $attr['width'] < self::min_width )
			$attr['width'] = 0;
		elseif ( isset( $content_width ) && $content_width > self::min_width && $attr['width'] > $content_width )
			$attr['width'] = 0;

		if ( $attr['width'] === 0 && isset( $content_width ) && $content_width > self::min_width )
			$attr['width'] = $content_width;

		if ( ( $attr['width'] % 2 ) === 1 )
			$attr['width']--;

		$options = apply_filters( 'videopress_shortcode_options', array(
			'freedom' => $attr['freedom'],
			'force_flash' => (bool) $attr['flashonly'],
			'autoplay' => (bool) $attr['autoplay'],
			'forcestatic' => $attr['forcestatic'],
			'hd' => (bool) $attr['hd']
		) );

		$this->enqueue_scripts();

		require_once( dirname( __FILE__ ) . '/class.videopress-video.php' );
		require_once( dirname( __FILE__ ) . '/class.videopress-player.php' );

		$player = new VideoPress_Player( $guid, $attr['width'], $options );
		if ( $player instanceOf VideoPress_Player ) {
			if ( is_feed() )
				return $player->asXML();
			else
				return $player->asHTML();
		}
	}

	function enqueue_scripts() {
		if ( $this->js_loaded )
			return;

		wp_enqueue_script( 'videopress', set_url_scheme( 'http://v0.wordpress.com/js/videopress.js' ), array( 'jquery', 'swfobject' ), '1.09' );

		$this->js_loaded = true;
	}

	/**
	 * Validate user-supplied guid values against expected inputs
	 *
	 * @since 1.1
	 * @param string $guid video identifier
	 * @return bool true if passes validation test
	 */
	public static function is_valid_guid( $guid ) {
		if ( ! empty( $guid ) && strlen( $guid ) === 8 && ctype_alnum( $guid ) )
			return true;
		else
			return false;
	}
}
new Jetpack_VideoPress_Shortcode;