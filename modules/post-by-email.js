jetpack_post_by_email = {
	init: function() {
		jQuery( '#jp-pbe-enable' ).click( jetpack_post_by_email.enable );
		jQuery( '#jp-pbe-regenerate' ).click( jetpack_post_by_email.regenerate );
		jQuery( '#jp-pbe-disable' ).click( jetpack_post_by_email.disable );
	},

	enable: function() {
		jQuery( '#jp-pbe-enable' ).attr( 'disabled', 'disabled' );

		var data = {
				action: 'jetpack_post_by_email_enable'
		};

		jQuery.post( ajaxurl, data, jetpack_post_by_email.handle_enabled );
	},

	handle_enabled: function( response ) {
		// TODO: Sanity check response
		jQuery( '#jp-pbe-regenerate' ).removeAttr( 'disabled' );
		jQuery( '#jp-pbe-disable' ).removeAttr( 'disabled' );

		jQuery( '#jp-pbe-enable' ).fadeOut( 400, function() {
			jQuery( '#jp-pbe-email' ).html( response );
			jQuery( '#jp-pbe-info' ).fadeIn();
		});
	},

	regenerate: function() {
		jQuery( '#jp-pbe-regenerate' ).attr( 'disabled', 'disabled' );
		jQuery( '#jp-pbe-disable' ).attr( 'disabled', 'disabled' );

		var data = {
				action: 'jetpack_post_by_email_regenerate'
		};

		jQuery.post( ajaxurl, data, jetpack_post_by_email.handle_regenerated );
	},
	
	handle_regenerated: function( response ) {
		// TODO: Sanity check response
		jQuery( '#jp-pbe-email-wrapper' ).fadeOut( 400, function() {
			jQuery( '#jp-pbe-email' ).html( response );
			jQuery( '#jp-pbe-email-wrapper' ).fadeIn();
		});

		jQuery( '#jp-pbe-regenerate' ).removeAttr( 'disabled' );
		jQuery( '#jp-pbe-disable' ).removeAttr( 'disabled' );
	},

	disable: function() {
		jQuery( '#jp-pbe-regenerate' ).attr( 'disabled', 'disabled' );
		jQuery( '#jp-pbe-disable' ).attr( 'disabled', 'disabled' );

		var data = {
				action: 'jetpack_post_by_email_disable'
		};

		jQuery.post( ajaxurl, data, jetpack_post_by_email.handle_disabled );
	},

	handle_disabled: function( response ) {
		// TODO: Sanity check response
		jQuery( '#jp-pbe-enable' ).removeAttr( 'disabled' );

		jQuery( '#jp-pbe-info' ).fadeOut( 400, function() {
			jQuery( '#jp-pbe-enable' ).fadeIn();
		});
	}
};

jQuery( function() { jetpack_post_by_email.init(); } );
