var cmb_flexible = {};

(function( $, cmb ) {

	cmb_flexible.init = function() {
		var $metabox = cmb.metabox();

		$metabox
			.on( 'click', '.cmb2-add-flexible-row',   cmb_flexible.addFlexibleRow )
			.on( 'click', '.cmb-flexible-add-button', cmb_flexible.removeFlexibleHiddenClass )
			.on( 'click', '.cmb-remove-flexible-row', cmb_flexible.removeFlexibleRow )
			.on( 'click', '.cmb-shift-flexible-rows', cmb_flexible.shiftRows );
	}

	cmb_flexible.addFlexibleRow = function( evt ) {
		evt.preventDefault();

		var $this            = $( this );
		var metabox          = $this.closest( '.cmb2-postbox' ).attr( 'id' );
		var flexible_group   = $this.closest( '.cmb-flexible-group' );
		var field_id         = flexible_group.attr( 'data-fieldid' );
		var type             = $this.attr( 'data-type' );
		var flexible_rows    = flexible_group.find( '.cmb-flexible-rows' ).last();
		var latest           = flexible_group.find( '.cmb-flexible-row' ).last();
		var latest_index;

		if ( latest.length > 0 ) {
			latest_index = latest.attr( 'data-groupindex' );
		}

		$this.closest( '.cmb-flexible-add-list' ).addClass( 'hidden' );

		$( flexible_group ).css( { opacity: 0.5 } );

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				type: type,
				metabox_id: metabox,
				field_id: field_id,
				latest_index: latest_index,
				action: 'get_flexible_content_row',
				cmb2_ajax_nonce: cmb2_l10.ajax_nonce,
			},

			success: function( response ) {
				$( flexible_group ).css( { opacity: 1 } );
				var el = response.data;
				var newRow = flexible_rows.append( el );

				// Initialize CMB at the end so that JS hooks work correctly.
				cmb.newRowHousekeeping( newRow );
				cmb.afterRowInsert( newRow );

				$( newRow ).find( '.cmb2-wysiwyg-placeholder' ).each( function() {
					$this = $( this );
					data  = $this.data();

					data.id    = $this.attr( 'id' );
					data.name  = $this.attr( 'name' );
					data.value = '';

					cmb_flexible.initWysiwyg( $this, data );
				});
			}
		});
	}

	cmb_flexible.removeFlexibleHiddenClass = function( evt ) {
		evt.preventDefault();
		var list = $( this ).next( '.cmb-flexible-add-list' ).removeClass( 'hidden' );
	}

	cmb_flexible.removeFlexibleRow = function( evt ) {
		evt.preventDefault();
		var $this    = $( this );
		var $parent  = $this.closest( '.cmb-flexible-row' );


		$parent.nextAll( '.cmb-flexible-row' ).each(function() {
			var $next = $( this );
			var prevNum = $( this ).attr( 'data-groupindex' );
			cmb_flexible.updateFlexibleNames( $next, prevNum );
		} );

		window.CMB2.wysiwyg.destroy( $parent.find( '.wp-editor-area' ).attr( 'id' ) );

		$parent.remove();
	}

	cmb_flexible.updateFlexibleNames = function( $el, prevNum, newNum ) {
		if ( $el.length > 0 ) {
			newNum = newNum || prevNum - 1;
			$el.attr( 'data-groupindex', newNum );
			$el.find( cmb.repeatEls ).each( function() {
				var $this = $( this );
				var name = $this.attr( 'name' );

				if ( typeof name !== 'undefined' ) {
					// We add an extra '[' (as in '][') so that sub-fields are not effected, only the parent.
					var $newName = name.replace( '[' + prevNum + '][', '[' + newNum + '][' );
					$this.attr( 'name', $newName );
				}
			} );
		}
	}

	cmb_flexible.shiftRows = function( evt ) {
		evt.preventDefault();

		var $this     = $( this );
		var $from     = $this.closest( '.cmb-flexible-row' );
		var fromNum   = $from.attr( 'data-groupindex' );
		var direction = $this.hasClass( 'move-up' ) ? 'up' : 'down';
		var $goto     = 'up' === direction ? $from.prev( '.cmb-flexible-row' ) : $from.next( '.cmb-flexible-row' );
		var gotoNum   = $goto.attr( 'data-groupindex' );

		$from.find( '.wp-editor-wrap textarea' ).each( function() {
			window.CMB2.wysiwyg.destroy( $( this ).attr( 'id' ) );
		} );


		if ( 'up' === direction && 0 === parseInt( fromNum ) ) {
			return false;
		}

		if ( 'up' === direction ) {
			$goto.before( $from );
		}

		if ( 'down' === direction ) {
			$goto.after( $from );
		}

		cmb_flexible.updateFlexibleNames( $from, fromNum, gotoNum );
		cmb_flexible.updateFlexibleNames( $goto, gotoNum, fromNum );

		$from.each( function() {
			cmb_flexible.setWysiwygRow( $( this ) );
		} );
	}

	cmb_flexible.setWysiwygRow = function( $el ) {
		var wysiwyg = $el.find( '.wp-editor-wrap textarea' );
		var wysiwyg_id = wysiwyg.attr( 'id' );

		if ( tinyMCEPreInit.mceInit[ wysiwyg_id ] ) {
			window.CMB2.wysiwyg.initRow( $el );
		} else {
			var $toReplace = wysiwyg.closest( '.cmb2-wysiwyg-inner-wrap' );
			data          = $toReplace.data();
			data.fieldid  = data.id;
			data.id       = data.groupid + '_' + data.iterator + '_' + data.fieldid;
			data.name     = data.groupid + '[' + data.iterator + '][' + data.fieldid + ']';
			data.value    = $toReplace.find( '.wp-editor-area' ).length ? $toReplace.find( '.wp-editor-area' ).val() : '';

			cmb_flexible.initWysiwyg( $toReplace, data );
		}
	}

	cmb_flexible.initWysiwyg = function( $toReplace, data ) {
		var mceData = tinyMCEPreInit.mceInit.content;
		mceData.selector = '#' + data.id;

		var qtData = tinyMCEPreInit.qtInit.content;
		qtData.id = data.id;
		$.extend( data, {
			template: wp.template( 'cmb2-wysiwyg-' + data.groupid + '-' + data.fieldid ),
			defaults: {
				mce: mceData,
				qt: qtData,
			}
		} );

		$toReplace.replaceWith( data.template( data ) );
		window.tinyMCE.init( mceData );

		if ( 'function' === typeof window.quicktags ) {
			window.quicktags( qtData );
		}

		$( document.getElementById( data.id ) ).parents( '.wp-editor-wrap' ).removeClass( 'html-active' ).addClass( 'tmce-active' );
	}


	$( cmb_flexible.init );
})( jQuery, window.CMB2 );