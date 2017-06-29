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
		var field_id         = flexible_group.data( 'fieldid' );
		var type             = $this.attr( 'data-type' );
		var flexible_rows    = flexible_group.find( '.cmb-flexible-rows' ).last();
		var latest           = flexible_group.find( '.cmb-flexible-row' ).last();
		var latest_index;

		if ( latest.length > 0 ) {
			latest_index = latest.data( 'groupindex' ); 
		}

		$this.closest( '.cmb-flexible-add-list' ).addClass( 'hidden' );

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
				var el = response.data;
				flexible_rows.append( el );
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
		var newNum   = $parent.data( 'groupindex' );
		var $next    = $parent.next( '.cmb-flexible-row' );
		var prevNum  = $next.data( 'groupindex' );

		$parent.remove();

		cmb_flexible.updateFlexibleNames( $next, prevNum, newNum );
	}

	cmb_flexible.updateFlexibleNames = function( $el, prevNum, newNum ) {
		if ( $el.length > 0 ) {
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
		var fromNum   = $from.data( 'groupindex' );
		var direction = $this.hasClass( 'move-up' ) ? 'up' : 'down';
		var $goto     = 'up' === direction ? $from.prev( '.cmb-flexible-row' ) : $from.next( '.cmb-flexible-row' );
		var gotoNum   = $goto.data( 'groupindex' );

		if ( 'up' === direction ) {
			$goto.before( $from );
		}

		if ( 'down' === direction ) {
			$goto.after( $from );
		}

		cmb_flexible.updateFlexibleNames( $from, fromNum, gotoNum );
		cmb_flexible.updateFlexibleNames( $goto, gotoNum, fromNum );
	}

	$( cmb_flexible.init );
})( jQuery, window.CMB2 );