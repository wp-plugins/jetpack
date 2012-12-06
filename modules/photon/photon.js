(function($){
	/**
	 * For images lacking explicit dimensions and needing them, try to add them.
	 */
	var restore_dims = function() {
		$( 'img[data-recalc-dims]' ).each( function() {
			var width = this.width,
				height = this.height;

			if ( width && height ) {
				$( this ).attr( {
					width: width,
					height: height
				} );

				reset_for_retina( this );
			}
		} );
	},

	/**
	 * Modify given image's markup so that devicepx-jetpack.js will act on the image and it won't be reprocessed by this script.
	 */
	reset_for_retina = function( img ) {
		$( img ).removeAttr( 'data-recalc-dims' ).removeAttr( 'scale' );
	};

	/**
	 * Check both when page loads, and when IS is triggered.
	 */
	$( document ).ready( restore_dims );
	$( document.body ).on( 'post-load', restore_dims );
})(jQuery);