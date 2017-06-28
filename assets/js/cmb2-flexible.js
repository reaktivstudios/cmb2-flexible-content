(function( $, cmb ) {
	$( '.cmb2-add-flexible-row' ).on( 'click', function(e) {
		e.preventDefault();

		var button = $( this );
		var metabox = button.closest( '.cmb2-postbox' ).attr( 'id' );
		var flexible_group = button.closest( '.cmb-flexible-group' );
		var field_id = flexible_group.data( 'fieldid' );
		var type = button.attr( 'data-type' );
		var flexible_rows = flexible_group.find( '.cmb-flexible-rows' ).last();
		var latest = flexible_group.find( '.cmb-flexible-row' ).last();
		var latest_index;

		if ( latest.length > 0 ) {
			latest_index = latest.data( 'groupindex' ); 
		}

		button.closest( '.cmb-flexible-add-list' ).addClass( 'hidden' );
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
	} );

	$( '.cmb-flexible-add-button' ).on( 'click', function(e) {
		e.preventDefault();

		var list = $( this ).next( '.cmb-flexible-add-list' ).removeClass( 'hidden' );
	} );

	$( '.cmb-remove-flexible-row' ).on( 'click', function(e) {
		e.preventDefault();

		var button = $( this );
		var $parent = button.closest( '.cmb-flexible-row' );
		var newNum = $parent.data( 'groupindex' );
		var $next = $parent.next( '.cmb-flexible-row' );
		var prevNum = $next.data( 'groupindex' );
		$parent.remove();

		if ( $next.length > 0 ) {
			$next.attr( 'data-groupindex', newNum );
			$next.find( cmb.repeatEls ).each( function() {
				var $this = $( this );
				var name = $this.attr( 'name' );

				if ( typeof name !== 'undefined' ) {
					var $newName = name.replace( '[' + prevNum + ']', '[' + newNum + ']' );
					$this.attr( 'name', $newName );
				}
			})
		}
	} );
	
})( jQuery, window.CMB2 );