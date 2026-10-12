/* global jQuery, wp, sbkAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		// Colour picker for the staff calendar colour.
		if ( $.fn.wpColorPicker ) {
			$( '.sbk-color-field' ).wpColorPicker();
		}

		// Media picker for the staff photo.
		var frame = null;

		$( '.sbk-media__pick' ).on( 'click', function ( e ) {
			e.preventDefault();

			var $wrapper = $( this ).closest( '.sbk-media' );

			if ( ! frame ) {
				frame = wp.media( {
					title: sbkAdmin.chooseImage,
					button: { text: sbkAdmin.useImage },
					library: { type: 'image' },
					multiple: false
				} );
			}

			frame.off( 'select' ).on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$wrapper.find( '#sbk-avatar-id' ).val( attachment.id );
				$wrapper.find( '.sbk-media__preview' ).html( $( '<img>' ).attr( 'src', url ) );
			} );

			frame.open();
		} );

		$( '.sbk-media__clear' ).on( 'click', function ( e ) {
			e.preventDefault();

			var $wrapper = $( this ).closest( '.sbk-media' );
			$wrapper.find( '#sbk-avatar-id' ).val( '' );
			$wrapper.find( '.sbk-media__preview' ).empty();
		} );

		// Override fields stay disabled until the service is assigned.
		$( '.sbk-assign__toggle' ).on( 'change', function () {
			var enabled = $( this ).is( ':checked' );
			$( this ).closest( 'tr' ).find( '.sbk-assign__field' ).prop( 'disabled', ! enabled );
		} );

		// --- Weekly schedule -------------------------------------------------

		function intervalMarkup( weekday, start, end ) {
			return $( '<div class="sbk-interval"></div>' ).append(
				$( '<input type="time" step="300">' )
					.attr( 'name', 'week[' + weekday + '][start][]' )
					.val( start || '' ),
				$( '<span class="sbk-interval__dash">—</span>' ),
				$( '<input type="time" step="300">' )
					.attr( 'name', 'week[' + weekday + '][end][]' )
					.val( end || '' ),
				$( '<button type="button" class="button-link sbk-interval__remove">✕</button>' )
					.attr( 'aria-label', sbkAdmin.removeLabel )
			);
		}

		function refreshDayState( $row ) {
			var empty = 0 === $row.find( '.sbk-interval' ).length;
			$row.find( '.sbk-day-closed' ).prop( 'hidden', ! empty );
		}

		$( '.sbk-day__add' ).on( 'click', function () {
			var $row = $( this ).closest( '.sbk-day-row' );
			var weekday = $row.data( 'weekday' );

			$row.find( '.sbk-day' ).append( intervalMarkup( weekday, '09:00', '17:00' ) );
			refreshDayState( $row );
		} );

		$( '.sbk-week' ).on( 'click', '.sbk-interval__remove', function () {
			var $row = $( this ).closest( '.sbk-day-row' );

			$( this ).closest( '.sbk-interval' ).remove();
			refreshDayState( $row );
		} );

		$( '.sbk-day__copy' ).on( 'click', function () {
			if ( ! window.confirm( sbkAdmin.confirmCopy ) ) {
				return;
			}

			var $source = $( this ).closest( '.sbk-day-row' );
			var intervals = $source.find( '.sbk-interval' ).map( function () {
				var $inputs = $( this ).find( 'input[type="time"]' );
				return { start: $inputs.eq( 0 ).val(), end: $inputs.eq( 1 ).val() };
			} ).get();

			$( '.sbk-day-row' ).each( function () {
				var $row = $( this );

				if ( $row.is( $source ) ) {
					return;
				}

				var weekday = $row.data( 'weekday' );
				var $day = $row.find( '.sbk-day' ).empty();

				intervals.forEach( function ( interval ) {
					$day.append( intervalMarkup( weekday, interval.start, interval.end ) );
				} );

				refreshDayState( $row );
			} );
		} );

		// --- Exceptions ------------------------------------------------------

		$( '.sbk-exc-type input[type="radio"]' ).on( 'change', function () {
			$( '.sbk-exc-window' ).prop( 'hidden', '1' === $( this ).val() );
		} );

		// Delete confirmations.
		$( '.sbk-delete-staff' ).on( 'click', function () {
			return window.confirm( sbkAdmin.confirmStaff );
		} );

		$( '.sbk-delete-service' ).on( 'click', function () {
			return window.confirm( sbkAdmin.confirmSvc );
		} );

		$( '.sbk-delete-exception' ).on( 'click', function () {
			return window.confirm( sbkAdmin.confirmExc );
		} );

		$( '.sbk-delete-extra' ).on( 'click', function () {
			return window.confirm( sbkAdmin.confirmExtra );
		} );
	} );
}( jQuery ) );
