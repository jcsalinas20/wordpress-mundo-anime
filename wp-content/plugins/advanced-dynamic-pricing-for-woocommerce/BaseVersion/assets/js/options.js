jQuery( document ).ready( function ( $ ) {


	$( '.section_choice' ).click( function () {

		$( '.section_choice' ).removeClass( 'active' );
		$( this ).addClass( 'active' );

		$( '.settings-section' ).removeClass( 'active' );
		$( '#' + $( this ).data( 'section' ) + '_section' ).addClass( 'active' );

		window.location.href = $( this ).attr( 'href' );
	} );

	setTimeout( function () {
		if ( window.location.hash.indexOf( 'section' ) !== - 1 ) {
			$( '.section_choice[href="' + window.location.hash + '"]' ).click()
		} else {
			$( '.section_choice' ).first().click()
		}
	}, 0 );

	// control visibility for options which depends on load_in_backend option
	setTimeout(function () {
		$('#load_in_backend').change(function () {
			if (this.checked) {
				$('.load-in-backed-checked').show()
			} else {
				$('.load-in-backed-checked').hide()
			}
		}).trigger('change');
	}, 0);

} );