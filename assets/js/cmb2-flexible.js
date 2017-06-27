(function( $, cmb ) {
	$( '.cmb2-add-flexible-row' ).on( 'click', function(e) {
		e.preventDefault();

		var button = $( this );
		var metabox = button.closest( '.cmb2-postbox' ).attr( 'id' );
		var flexible_group = button.closest( '.cmb-flexible-group' );
		var field_id = flexible_group.data( 'fieldid' );
		var type = button.attr( 'data-type' );
		var latest = flexible_group.find( '.cmb-flexible-row' ).last();
		var latest_index = latest.data( 'groupindex' ); 


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
				latest.after( el );
			}
		});
	} );
})( jQuery, window.CMB2 );