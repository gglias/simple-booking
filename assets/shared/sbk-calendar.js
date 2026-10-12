/**
 * Calendar date maths, shared by the frontend flow and the admin booking form.
 *
 * Pure functions on 'YYYY-MM-DD' strings: no Date parsing of strings anywhere,
 * so a browser in another timezone can never shift a day.
 */
( function () {
	'use strict';

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	/**
	 * @param {number} year  Full year.
	 * @param {number} month 0-11.
	 * @param {number} day   1-31.
	 * @return {string} YYYY-MM-DD
	 */
	function dateString( year, month, day ) {
		return year + '-' + pad( month + 1 ) + '-' + pad( day );
	}

	function daysInMonth( year, month ) {
		return new Date( year, month + 1, 0 ).getDate();
	}

	/**
	 * Split a date string into numbers, avoiding Date string parsing.
	 *
	 * @param {string} date YYYY-MM-DD.
	 * @return {{year:number,month:number,day:number}} month is 0-11.
	 */
	function parts( date ) {
		var bits = String( date ).split( '-' );

		return { year: +bits[ 0 ], month: +bits[ 1 ] - 1, day: +bits[ 2 ] };
	}

	/**
	 * Weekday index with Monday first (0 = Monday ... 6 = Sunday).
	 *
	 * @param {string} date YYYY-MM-DD.
	 * @return {number}
	 */
	function weekdayIndex( date ) {
		var p = parts( date );

		return ( new Date( p.year, p.month, p.day ).getDay() + 6 ) % 7;
	}

	/**
	 * Shift a date string by whole days.
	 *
	 * @param {string} date  YYYY-MM-DD.
	 * @param {number} delta Days to add.
	 * @return {string}
	 */
	function addDays( date, delta ) {
		var p = parts( date );
		var d = new Date( p.year, p.month, p.day );

		d.setDate( d.getDate() + delta );

		return dateString( d.getFullYear(), d.getMonth(), d.getDate() );
	}

	/**
	 * Grid cells for one month, Monday first, with leading blanks.
	 *
	 * @param {{year:number,month:number}} cursor    Visible month.
	 * @param {Object}                     available date => bool.
	 * @return {Array} Cells with key, date, day, available.
	 */
	function cells( cursor, available ) {
		var out = [];
		var lead = ( new Date( cursor.year, cursor.month, 1 ).getDay() + 6 ) % 7;
		var total = daysInMonth( cursor.year, cursor.month );
		var i;

		for ( i = 0; i < lead; i++ ) {
			out.push( { key: 'blank-' + i, date: null } );
		}

		for ( i = 1; i <= total; i++ ) {
			var date = dateString( cursor.year, cursor.month, i );

			out.push( {
				key: date,
				date: date,
				day: i,
				available: true === ( available || {} )[ date ]
			} );
		}

		return out;
	}

	/**
	 * Move the visible month.
	 *
	 * @param {{year:number,month:number}} cursor Current month.
	 * @param {number}                     delta  Months to add.
	 * @return {{year:number,month:number}}
	 */
	function shiftMonth( cursor, delta ) {
		var month = cursor.month + delta;
		var year = cursor.year;

		while ( month < 0 ) {
			month += 12;
			year -= 1;
		}

		while ( month > 11 ) {
			month -= 12;
			year += 1;
		}

		return { year: year, month: month };
	}

	/**
	 * The date range to ask the API for, clamped to today and the last bookable
	 * day so we never request dates that cannot be booked anyway.
	 *
	 * @param {{year:number,month:number}} cursor  Visible month.
	 * @param {string}                     today   YYYY-MM-DD.
	 * @param {string}                     maxDate YYYY-MM-DD.
	 * @return {{from:string,to:string}|null} Null when the month is fully outside the window.
	 */
	function monthRange( cursor, today, maxDate ) {
		var first = dateString( cursor.year, cursor.month, 1 );
		var last = dateString( cursor.year, cursor.month, daysInMonth( cursor.year, cursor.month ) );
		var from = first < today ? today : first;
		var to = maxDate && last > maxDate ? maxDate : last;

		if ( from > to ) {
			return null;
		}

		return { from: from, to: to };
	}

	/**
	 * Whether the visible month still contains bookable dates.
	 */
	function canGoPrev( cursor, today ) {
		return dateString( cursor.year, cursor.month, 1 ) > today;
	}

	function canGoNext( cursor, maxDate ) {
		var next = shiftMonth( cursor, 1 );

		return ! maxDate || dateString( next.year, next.month, 1 ) <= maxDate;
	}

	window.SBKCalendar = {
		pad: pad,
		parts: parts,
		dateString: dateString,
		daysInMonth: daysInMonth,
		weekdayIndex: weekdayIndex,
		addDays: addDays,
		cells: cells,
		shiftMonth: shiftMonth,
		monthRange: monthRange,
		canGoPrev: canGoPrev,
		canGoNext: canGoNext
	};
}() );
