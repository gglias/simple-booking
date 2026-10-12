/**
 * Admin booking form: month calendar with live slots, the same way the
 * customer sees it. Reads the same REST endpoints, so availability can never
 * disagree between the two.
 */
/* global sbkAdminCal, SBKCalendar */
( function () {
	'use strict';

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'sbkAdminCalendar', function () {
			return {
				today: sbkAdminCal.today,
				maxDate: sbkAdminCal.maxDate,
				weekdaysShort: sbkAdminCal.weekdaysShort,
				month: null,
				days: {},
				slots: [],
				date: null,
				time: null,
				loading: false,
				error: '',

				init: function () {
					var start = sbkAdminCal.initialDate > this.today ? sbkAdminCal.initialDate : this.today;
					var p = SBKCalendar.parts( start );

					this.month = { year: p.year, month: p.month };
					this.loadDays();
				},

				// ── HTTP ────────────────────────────────────────────────
				request: function ( path, params ) {
					var self = this;
					var query = [];

					Object.keys( params ).forEach( function ( key ) {
						var value = params[ key ];

						if ( Array.isArray( value ) ) {
							if ( value.length ) {
								query.push( key + '=' + value.join( ',' ) );
							}
							return;
						}

						if ( value !== null && value !== undefined && value !== '' ) {
							query.push( key + '=' + encodeURIComponent( value ) );
						}
					} );

					this.error = '';
					this.loading = true;

					return fetch( sbkAdminCal.restUrl + path + ( query.length ? '?' + query.join( '&' ) : '' ), {
						headers: { 'X-WP-Nonce': sbkAdminCal.nonce }
					} ).then( function ( response ) {
						return response.json().then( function ( body ) {
							if ( ! response.ok ) {
								throw new Error( ( body && body.message ) || sbkAdminCal.i18n.error );
							}
							return body;
						} );
					} ).catch( function ( err ) {
						self.error = err.message || sbkAdminCal.i18n.error;
						throw err;
					} ).finally( function () {
						self.loading = false;
					} );
				},

				loadDays: function () {
					var self = this;
					var range = SBKCalendar.monthRange( this.month, this.today, this.maxDate );

					if ( ! range ) {
						this.days = {};
						return;
					}

					this.request( '/days', {
						staff_id: sbkAdminCal.staffId,
						service_id: sbkAdminCal.serviceId,
						extras: sbkAdminCal.extras,
						from: range.from,
						to: range.to
					} ).then( function ( data ) {
						var map = {};

						data.forEach( function ( day ) {
							map[ day.date ] = day.available;
						} );

						self.days = map;
					} ).catch( function () {} );
				},

				loadSlots: function () {
					var self = this;

					this.request( '/slots', {
						staff_id: sbkAdminCal.staffId,
						service_id: sbkAdminCal.serviceId,
						extras: sbkAdminCal.extras,
						date: this.date
					} ).then( function ( data ) {
						self.slots = data;
					} ).catch( function () {
						self.slots = [];
					} );
				},

				// ── Calendar ────────────────────────────────────────────
				cells: function () {
					return SBKCalendar.cells( this.month, this.days );
				},

				monthTitle: function () {
					return sbkAdminCal.months[ this.month.month ] + ' ' + this.month.year;
				},

				canGoPrev: function () {
					return SBKCalendar.canGoPrev( this.month, this.today );
				},

				canGoNext: function () {
					return SBKCalendar.canGoNext( this.month, this.maxDate );
				},

				shift: function ( delta ) {
					this.month = SBKCalendar.shiftMonth( this.month, delta );
					this.loadDays();
				},

				pickDate: function ( date ) {
					this.date = date;
					this.time = null;
					this.slots = [];
					this.loadSlots();
				},

				pickTime: function ( slot ) {
					this.time = slot.time;
				},

				dateLabel: function () {
					if ( ! this.date ) {
						return '';
					}

					var p = SBKCalendar.parts( this.date );

					return this.weekdaysShort[ SBKCalendar.weekdayIndex( this.date ) ] +
						' ' + p.day + ' ' + sbkAdminCal.monthsGenitive[ p.month ];
				},

				/**
				 * What the receptionist is about to write down.
				 */
				summary: function () {
					if ( ! this.date || ! this.time ) {
						return sbkAdminCal.i18n.pickTime;
					}

					return this.dateLabel() + ' · ' + this.time;
				}
			};
		} );
	} );
}() );
