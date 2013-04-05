/**
 * VideoPress Admin
 */
(function($) {
	var media = wp.media;

	/**
	 * Create a new controller that simply adds a videopress key
	 * to the library query
	 */
	media.controller.VideoPress = media.controller.Library.extend({
		initialize: function() {
			if ( ! this.get('library') )
				this.set( 'library', media.query({ videopress: true }) );

			media.controller.Library.prototype.initialize.apply( this, arguments );
		},

		/**
		 * The original function saves content for the browse router only,
		 * so we hi-jack it a little bit.
		 */
		saveContentMode: function() {
			if ( 'videopress' !== this.get('router') )
				return;

			var mode = this.frame.content.mode(),
				view = this.frame.router.get();

			if ( view && view.get( mode ) ) {

				// Map the Upload a Video back to the regular Upload Files.
				if ( 'upload_videopress' === mode )
					mode = 'upload';

				setUserSetting( 'libraryContent', mode );
			}
		}
	});

	/**
	 * Temporary placeholder for the VideoPress uploader stuff.
	 */
	media.view.VideoPressUploader = media.View.extend({
		tagName:   'div',
		className: 'uploader-videopress',
		template:  media.template('videopress-uploader')
	});

	/**
	 * Add a custom sync function that would add a few extra
	 * options for models which are VideoPress videos.
	 */
	var attachmentSync = media.model.Attachment.prototype.sync;
	media.model.Attachment.prototype.sync = function( method, model, options ) {
		if ( model.get( 'vp_isVideoPress' ) ) {
			console.log( 'syncing ' + model.get( 'vp_guid' ) );
			options.data = _.extend( options.data || {}, {
				is_videopress: true,
				vp_nonces: model.get( 'vp_nonces' )
			} );
		}

		// Call the original sync routine.
		return attachmentSync.apply( this, arguments );
	};

	/**
	 * Extend the default Attachment Details view. Check for vp_isVideoPress before
	 * adding anything to these methods.
	 */
	var AttachmentDetails = media.view.Attachment.Details;
	media.view.Attachment.Details = AttachmentDetails.extend({

		initialize: function() {
			if ( this.model.get( 'vp_isVideoPress' ) ) {
				_.extend( this.events, {
					'click a.videopress-preview': 'vpPreview',
					'change .vp-radio': 'vpRadioChange',
					'change .vp-checkbox': 'vpCheckboxChange'
				});
			}
			return AttachmentDetails.prototype.initialize.apply( this, arguments );
		},

		render: function() {
			var r = AttachmentDetails.prototype.render.apply( this, arguments );
			if ( this.model.get( 'vp_isVideoPress' ) ) {
				var template = media.template( 'videopress-attachment' );
				var options = this.model.toJSON();

				options.can = {};
				options.can.save = !! options.nonces.update;

				this.$el.append( template( options ) );
			}
			return r;
		},

		// Handle radio buttons
		vpRadioChange: function(e) {
			$( e.target ).parents( '.vp-setting' ).find( '.vp-radio-text' ).val( e.target.value ).change();
		},

		// And checkboxes
		vpCheckboxChange: function(e) {
			$( e.target ).parents( '.vp-setting' ).find( '.vp-checkbox-text' ).val( Number( e.target.checked ) ).change();
		},

		vpPreview: function() {
			VideoPressModal.render( this );
			return this;
		}
	});

	/**
	 * Extend the post MediaFrame with our own
	 */
	var MediaFrame = media.view.MediaFrame.Post;
	media.view.MediaFrame.Post = MediaFrame.extend({
		initialize: function() {
			MediaFrame.prototype.initialize.apply( this, arguments );

			this.states.add([
				new media.controller.VideoPress({
					id: 'videopress',
					title: 'VideoPress',
					router: 'videopress',
					priority: 200,
					toolbar: 'videopress-toolbar',
					searchable: true,
					sortable: false
				})
			]);
			return this;
		},

		bindHandlers: function() {
			MediaFrame.prototype.bindHandlers.apply( this, arguments );

			this.on( 'router:create:videopress', this.createRouter, this );
			this.on( 'router:render:videopress', this.setupRouter, this );
			this.on( 'content:render:upload_videopress', this.uploadVideo, this );
			this.on( 'toolbar:create:videopress-toolbar', this.createVideoPressToolbar, this );
			this.on( 'videopress:insert', this.insert, this );
			return this;
		},

		// Runs on videopress:insert event fired by our custom toolbar
		insert: function( selection ) {
			var guid = selection.models[0].get( 'vp_guid' ).replace( /[^a-zA-Z0-9]+/, '' );
			media.editor.insert( '[wpvideo ' + guid + ']' );
			return this;
		},

		// Our router is slightly different.
		setupRouter: function( view ) {
			view.set({
				upload_videopress: {
					text:     'Upload a Video', // @todo l10n
					priority: 20
				},
				browse: {
					text:     'VideoPress Library', // @todo l10n
					priority: 40
				}
			});

			// Map the Upload Files view to the Upload a Video one (upload_videopress vs. upload)
			if ( 'upload' === this.content.mode() )
				this.content.mode( 'upload_videopress' );
		},

		// Triggered by the upload_videopress router item.
		uploadVideo: function() {
			this.content.set( new media.view.VideoPressUploader({
				controller: this
			}) );
			return this;
		},

		// Create a custom toolbar
		createVideoPressToolbar: function( toolbar ) {
			var controller = this;
			this.toolbar.set( new media.view.Toolbar({
				controller: this,
				items: {
					insert: {
						style:    'primary',
						text:     'Insert Video',
						priority: 80,
						requires: {
							library: true,
							selection: true
						},

						click: function() {
							var state = controller.state(),
								selection = state.get('selection');

							controller.close();
							state.trigger( 'videopress:insert', selection ).reset();
						}
					}
				}
			}) );
		}
	});

	/**
	 * A VideoPress Modal view that we can use to preview videos.
	 * Expects a controller object on render.
	 */
	var VideoPressModalView = Backbone.View.extend({
		'className': 'videopress-modal-container',
		'template': wp.media.template( 'videopress-media-modal' ),

		// Render the VideoPress modal with a video object by guid.
		render: function( controller ) {
			this.delegateEvents( {
				'click .videopress-modal-close': 'closeModal',
				'click .videopress-modal-backdrop': 'closeModal'
			} );

			this.model = controller.model;
			this.guid = this.model.get( 'vp_guid' );

			if ( ! this.$frame )
				this.$frame = $( '.media-frame-content' );

			this.$el.html( this.template( { 'video' : this.model.get( 'vp_embed' ) } ) );
			this.$modal = this.$( '.videopress-modal' );
			this.$modal.hide();

			this.$frame.append( this.$el );
			this.$modal.slideDown( 'fast' );

			return this;
		},

		closeModal: function() {
			var view = this;
			this.$modal.slideUp( 'fast', function() { view.remove(); } );
			return this;
		}
	});

	var VideoPressModal = new VideoPressModalView();
})(jQuery);