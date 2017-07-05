var cmb_flexible = {};
window.CMB2 = window.CMB2 || {};
window.CMB2.wysiwyg = window.CMB2.wysiwyg || false;

(function( $, cmb ) {

	var l10n = window.cmb2_l10 || {};

	/**
	 * Iniitalize Flexible content field
	 */
	cmb_flexible.init = function() {
		var $metabox = cmb.metabox();

		$metabox
			.on( 'click', '.cmb2-add-flexible-row',   cmb_flexible.addFlexibleRow )
			.on( 'click', '.cmb-flexible-add-button', cmb_flexible.removeFlexibleHiddenClass )
			.on( 'click', '.cmb-shift-flexible-rows', cmb_flexible.shiftRows )
			.on( 'cmb2_remove_row', cmb_flexible.removeDisabledRows )
			.on( 'click', '.cmb-flexible-rows .cmb-remove-group-row', cmb_flexible.removeLastRow );

		$( '.cmb-flexible-wrap' ).find( '.cmb-repeatable-grouping' ).each( function() {
				$( this ).find( '.button.cmb-remove-group-row' ).before( '<a class="button cmb-shift-flexible-rows move-up alignleft" href="#"><span class="'+ l10n.up_arrow_class +'"></span></a> <a class="button cmb-shift-flexible-rows move-down alignleft" href="#"><span class="'+ l10n.down_arrow_class +'"></span></a>' );
		} );
	};

	/**
	 * Make sure no rows in flexible fields are set to disabled
	 * Reset iterators whenever a flexible field is removed.
	 *
	 * @param  {object} evt Event object.
	 */
	cmb_flexible.removeDisabledRows = function( evt ) {
		var $el = $( evt.target );
		if (  $el.find( '.cmb-flexible-rows' ).length > 0 ) {
			$el.find( '.cmb-remove-group-row' ).prop( 'disabled', false );
			cmb_flexible.resetIterators( $el );
		}
	};

	/**
	 * Add the ability to remove all rows for flexible content fields.
	 */
	cmb_flexible.removeLastRow = function() {
		var el = $(document).find( this );

		// Make sure to eliminate the final group if it exists.
		if ( el.length > 0 ) {
			var $this   = $( this );
			var $table  = $( document.getElementById( $this.data( 'selector' ) ) );
			var $parent = $this.parents( '.cmb-repeatable-grouping' );
			$parent.remove();
		}
	};

	/**
	 * Send an AJAX request for a new field and then add it to the DOM.
	 *
	 * Once a field has been added, need to make sure to initialize its 
	 * dependencies and WYSIWYG functionality
	 *
	 * @param {object} evt Event object.
	 */
	cmb_flexible.addFlexibleRow = function( evt ) {
		evt.preventDefault();

		var $this            = $( this );
		var metabox          = $this.closest( '.cmb2-postbox' ).attr( 'id' );
		var flexible_group   = $this.closest( '.cmb-repeatable-group' );
		var flexible_wrap    = flexible_group.find( '.cmb-flexible-rows' ).last();
		var field_id         = flexible_group.attr( 'data-groupid' );
		var type             = $this.attr( 'data-type' );
		var latest           = flexible_group.find('.cmb-repeatable-grouping').last();
		var latest_index;

		$( flexible_wrap ).css( { opacity: 0.5 } );

		if ( latest.length > 0 ) {
			latest_index = latest.attr( 'data-iterator' );
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
				flexible_wrap.append( el.output );
				var newRow = flexible_wrap.find( '.cmb-repeatable-grouping' ).last();
				cmb.newRowHousekeeping( newRow );
				cmb.afterRowInsert( newRow );
				$( newRow ).find( '.button.cmb-remove-group-row' ).before( '<a class="button cmb-shift-flexible-rows move-up alignleft" href="#"><span class="'+ l10n.up_arrow_class +'"></span></a> <a class="button cmb-shift-flexible-rows move-down alignleft" href="#"><span class="'+ l10n.down_arrow_class +'"></span></a>' );
				$( newRow ).find( '.cmb2-wysiwyg-placeholder' ).each( function() {
					$this = $( this );
					data  = $this.data();

					if ( data.groupid ) {

						data.id    = $this.attr( 'id' );
						data.name  = $this.attr( 'name' );
						data.value = $this.val();
						window.CMB2.wysiwyg.init( $this, data, false );
						if ( 'undefined' !== typeof window.QTags ) {
							window.QTags._buttonsInit();
						}
					}
				} );

				$( flexible_wrap ).css( { opacity: 1 } );
			}
		});
	};

	/**
	 * Show list of types on click.
	 *
	 * @param  {object} evt Event object.
	 */
	cmb_flexible.removeFlexibleHiddenClass = function( evt ) {
		evt.preventDefault();
		var list = $( this ).next( '.cmb-flexible-add-list' ).toggleClass( 'hidden' );
	};

	/**
	 * Sort through inputs and change the name attributes depending
	 * on new iterator number.
	 *
	 * @param  {object} $el     jQuery Element object.
	 * @param  {int}    prevNum Number to look for.
	 * @param  {int}    newNum  Number to change to.
	 */
	cmb_flexible.updateFlexibleNames = function( $el, prevNum, newNum ) {
		if ( $el.length > 0 ) {
			newNum = newNum || prevNum - 1;
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
	};

	/**
	 * Shift Rows by switching them in the DOM.
	 *
	 * @param  {object} evt Event object.
	 */
	cmb_flexible.shiftRows = function( evt ) {
		evt.preventDefault();

		var $this     = $( this );
		var $from     = $this.closest( '.cmb-repeatable-grouping' );
		var fromNum   = $from.attr( 'data-iterator' );
		var direction = $this.hasClass( 'move-up' ) ? 'up' : 'down';
		var $goto     = 'up' === direction ? $from.prev( '.cmb-repeatable-grouping' ) : $from.next( '.cmb-repeatable-grouping' );
		var gotoNum   = $goto.attr( 'data-iterator' );

		if ( 'up' === direction && 0 === parseInt( fromNum ) ) {
			return false;
		}

		$from.add($goto).find( '.wp-editor-wrap textarea' ).each( function() {
			window.CMB2.wysiwyg.destroy( $( this ).attr( 'id' ) );
		} );

		if ( 'up' === direction ) {
			$goto.before( $from );
		}

		if ( 'down' === direction ) {
			$goto.after( $from );
		}

		cmb_flexible.updateFlexibleNames( $from, fromNum, gotoNum );
		cmb_flexible.updateFlexibleNames( $goto, gotoNum, fromNum );

		cmb_flexible.resetIterators( $this );

		$from.add( $goto ).each(function() {
			window.CMB2.wysiwyg.initRow( $( this ) );
		} );

	};

	/**
	 * We use both the data-iterator attribute to shift rows,
	 * so need to make sure that is reset as well.
	 *
	 * @param  {object} $el jQuery element.
	 */
	cmb_flexible.resetIterators = function( $el ) {
		var $table = $el.closest( '.cmb-repeatable-group' );
		$table.find( '.cmb-repeatable-grouping' ).each( function( rowindex ) {
			var $row = $( this );
			$row.data( 'iterator', rowindex );
			$row.attr( 'data-iterator', rowindex );
		} );
	};

	$( cmb_flexible.init );
})( jQuery, window.CMB2 );
