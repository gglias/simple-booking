/**
 * Simple Booking — the seven step flow.
 *
 * Registered before Alpine boots (WordPress prints this script first), so the
 * alpine:init listener is always in place.
 */
/* global sbkConfig, SBKCalendar */
( function () {
	'use strict';

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'sbkBooking', function () {
			return {
				// ── State ───────────────────────────────────────────────
				steps: sbkConfig.steps,
				weekdaysShort: sbkConfig.weekdaysShort,
				step: 1,
				loading: false,
				error: '',
				nonce: sbkConfig.nonce,
				today: sbkConfig.today,

				// wp_localize_script sends every scalar as a string, so anything used
				// in arithmetic or a strict comparison has to be cast back.
				maxAdvanceDays: parseInt( sbkConfig.maxAdvanceDays, 10 ) || 60,
				preselectStaff: parseInt( sbkConfig.preselectStaff, 10 ) || 0,

				staff: [],
				services: [],
				extras: [],
				slots: [],
				days: {},

				selected: { staff: null, service: null, extras: [], date: null, time: null },
				customer: { first_name: '', last_name: '', email: '', phone: '' },
				note: '',
				createAccount: false,
				website: '', // honeypot
				result: null,

				month: null, // { year, month } with month 0-11

				// ── Lifecycle ───────────────────────────────────────────
				init: function () {
					var p = SBKCalendar.parts( this.today );
					this.month = { year: p.year, month: p.month };

					this.refreshSession();
					this.loadStaff();
				},

				/**
				 * Pull a fresh nonce: the page itself may come from a cache.
				 */
				refreshSession: function () {
					var self = this;

					this.get( '/session' ).then( function ( data ) {
						if ( data && data.nonce ) {
							self.nonce = data.nonce;
							self.today = data.today;
						}
					} ).catch( function () { /* the page nonce stays as fallback */ } );
				},

				// ── View helpers ────────────────────────────────────────
				// These exist so the template carries no < or > inside an
				// attribute: wptexturize stops parsing a tag at the first >
				// and would mangle the quotes in everything after it.
				isStep: function ( n ) {
					return this.step === n && ! this.result;
				},

				isLastStep: function () {
					return this.step === this.steps.length;
				},

				isCurrent: function ( i ) {
					return this.step === i + 1;
				},

				isDone: function ( i ) {
					return this.step > i + 1;
				},

				isClickable: function ( i ) {
					return this.isDone( i ) && ! this.result;
				},

				stepClasses: function ( i ) {
					return {
						'is-current': this.isCurrent( i ),
						'is-done': this.isDone( i ),
						'is-clickable': this.isClickable( i )
					};
				},

				stepMarker: function ( i ) {
					return this.isDone( i ) ? '✓' : i + 1;
				},

				showBack: function () {
					return this.step > 1;
				},

				showNext: function () {
					return this.step < this.steps.length;
				},

				nextLabel: function () {
					if ( 3 === this.step && 0 === this.selected.extras.length ) {
						return sbkConfig.i18n.skipExtras;
					}

					return sbkConfig.i18n.continue;
				},

				// ── HTTP ────────────────────────────────────────────────
				url: function ( path, params ) {
					var url = sbkConfig.restUrl + path;
					var query = [];

					Object.keys( params || {} ).forEach( function ( key ) {
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

					return query.length ? url + '?' + query.join( '&' ) : url;
				},

				get: function ( path, params ) {
					var self = this;

					this.error = '';
					this.loading = true;

					return fetch( this.url( path, params ), {
						headers: { 'X-WP-Nonce': this.nonce }
					} ).then( function ( response ) {
						return response.json().then( function ( body ) {
							if ( ! response.ok ) {
								throw new Error( ( body && body.message ) || sbkConfig.i18n.genericError );
							}
							return body;
						} );
					} ).catch( function ( err ) {
						self.error = err.message || sbkConfig.i18n.networkError;
						throw err;
					} ).finally( function () {
						self.loading = false;
					} );
				},

				// ── Data loading ────────────────────────────────────────
				loadStaff: function () {
					var self = this;

					this.get( '/staff' ).then( function ( data ) {
						self.staff = data;

						if ( self.preselectStaff ) {
							var match = data.filter( function ( m ) {
								return m.id === self.preselectStaff;
							} );

							if ( match.length ) {
								self.pickStaff( match[ 0 ] );
							}
						}
					} ).catch( function () {} );
				},

				loadServices: function () {
					var self = this;

					this.get( '/services', { staff_id: this.selected.staff.id } ).then( function ( data ) {
						self.services = data;
					} ).catch( function () {} );
				},

				loadExtras: function () {
					var self = this;

					this.get( '/extras', { service_id: this.selected.service.id } ).then( function ( data ) {
						self.extras = data;
					} ).catch( function () {} );
				},

				loadDays: function () {
					var self = this;
					var range = this.monthRange();

					this.get( '/days', {
						staff_id: this.selected.staff.id,
						service_id: this.selected.service.id,
						extras: this.extraIds(),
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

					this.get( '/slots', {
						staff_id: this.selected.staff.id,
						service_id: this.selected.service.id,
						extras: this.extraIds(),
						date: this.selected.date
					} ).then( function ( data ) {
						self.slots = data;
					} ).catch( function () {
						self.slots = [];
					} );
				},

				// ── Selection ───────────────────────────────────────────
				// Each choice invalidates everything downstream of it: a longer
				// service or an extra changes which slots still fit.
				pickStaff: function ( member ) {
					this.selected.staff = member;
					this.selected.service = null;
					this.selected.extras = [];
					this.clearWhen();
					this.services = [];
					this.extras = [];
					this.loadServices();
					this.step = 2;
				},

				pickService: function ( service ) {
					this.selected.service = service;
					this.selected.extras = [];
					this.clearWhen();
					this.extras = [];
					this.loadExtras();
					this.step = 3;
				},

				toggleExtra: function ( extra ) {
					if ( this.hasExtra( extra.id ) ) {
						this.selected.extras = this.selected.extras.filter( function ( e ) {
							return e.id !== extra.id;
						} );
					} else {
						this.selected.extras.push( extra );
					}

					this.clearWhen();
				},

				hasExtra: function ( id ) {
					return this.selected.extras.some( function ( e ) {
						return e.id === id;
					} );
				},

				extraIds: function () {
					return this.selected.extras.map( function ( e ) {
						return e.id;
					} );
				},

				pickDate: function ( date ) {
					this.selected.date = date;
					this.selected.time = null;
					this.slots = [];
					this.loadSlots();
				},

				pickTime: function ( slot ) {
					this.selected.time = slot.time;
				},

				clearWhen: function () {
					this.selected.date = null;
					this.selected.time = null;
					this.slots = [];
					this.days = {};
				},

				// ── Calendar ────────────────────────────────────────────
				// The date maths lives in SBKCalendar so the admin booking form
				// and this flow can never drift apart.
				monthRange: function () {
					return SBKCalendar.monthRange( this.month, this.today, this.maxDate() );
				},

				monthTitle: function () {
					return sbkConfig.months[ this.month.month ] + " " + this.month.year;
				},

				calendarCells: function () {
					return SBKCalendar.cells( this.month, this.days );
				},

				maxDate: function () {
					return SBKCalendar.addDays( this.today, this.maxAdvanceDays );
				},

				canGoPrev: function () {
					return SBKCalendar.canGoPrev( this.month, this.today );
				},

				canGoNext: function () {
					return SBKCalendar.canGoNext( this.month, this.maxDate() );
				},

				shiftMonth: function ( delta ) {
					this.month = SBKCalendar.shiftMonth( this.month, delta );
					this.loadDays();
				},

				prevMonth: function () { this.shiftMonth( -1 ); },
				nextMonth: function () { this.shiftMonth( 1 ); },

				selectedDateLabel: function () {
					if ( ! this.selected.date ) {
						return '';
					}

					var p = SBKCalendar.parts( this.selected.date );

					return this.weekdaysShort[ SBKCalendar.weekdayIndex( this.selected.date ) ] +
						' ' + p.day + ' ' + sbkConfig.monthsGenitive[ p.month ];
				},

				// ── Totals ──────────────────────────────────────────────
				totalCents: function () {
					var total = this.selected.service ? this.selected.service.price_cents : 0;

					this.selected.extras.forEach( function ( e ) {
						total += e.price_cents;
					} );

					return total;
				},

				/**
				 * Formatted from the labels the server sent, so the currency
				 * format stays identical to the confirmation and the emails.
				 */
				totalLabel: function () {
					var cents = this.totalCents();
					var sample = this.selected.service ? this.selected.service.price_label : '';
					var decimal = sample.indexOf( ',' ) > -1 ? ',' : '.';
					var value = ( cents / 100 ).toFixed( 2 ).replace( '.', decimal );

					return value + ' €';
				},

				totalDuration: function () {
					var total = this.selected.service ? this.selected.service.duration_min : 0;

					this.selected.extras.forEach( function ( e ) {
						total += e.duration_min;
					} );

					return total;
				},

				totalDurationLabel: function () {
					var minutes = this.totalDuration();
					var hours = Math.floor( minutes / 60 );
					var rest = minutes % 60;

					if ( hours && rest ) {
						return hours + ' ώ ' + rest + ' λ';
					}

					return hours ? hours + ' ώ' : minutes + ' λ';
				},

				customerName: function () {
					return ( this.customer.first_name + ' ' + this.customer.last_name ).trim();
				},

				// ── Navigation ──────────────────────────────────────────
				canAdvance: function () {
					switch ( this.step ) {
						case 1: return !! this.selected.staff;
						case 2: return !! this.selected.service;
						case 3: return true; // extras are optional
						case 4: return !! this.selected.time;
						case 5: return this.validateCustomer( false );
						case 6: return true;
						default: return false;
					}
				},

				validateCustomer: function ( showError ) {
					var c = this.customer;
					var email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					var digits = ( c.phone || '' ).replace( /\D/g, '' );

					if ( ! c.first_name.trim() ) {
						if ( showError ) { this.error = sbkConfig.i18n.requiredName; }
						return false;
					}

					if ( ! email.test( c.email || '' ) ) {
						if ( showError ) { this.error = sbkConfig.i18n.requiredEmail; }
						return false;
					}

					if ( digits.length < 8 ) {
						if ( showError ) { this.error = sbkConfig.i18n.requiredPhone; }
						return false;
					}

					return true;
				},

				next: function () {
					if ( 5 === this.step && ! this.validateCustomer( true ) ) {
						return;
					}

					if ( ! this.canAdvance() ) {
						return;
					}

					this.error = '';
					this.step = Math.min( 7, this.step + 1 );

					if ( 4 === this.step ) {
						this.loadDays();
					}

					this.focusPanel();
				},

				back: function () {
					this.error = '';
					this.step = Math.max( 1, this.step - 1 );
					this.focusPanel();
				},

				goTo: function ( step ) {
					if ( step >= this.step || this.result ) {
						return;
					}

					this.error = '';
					this.step = step;

					if ( 4 === step ) {
						this.loadDays();
					}

					this.focusPanel();
				},

				focusPanel: function () {
					var panel = this.$el.querySelector( '.sbk-panel' );

					if ( panel ) {
						panel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					}
				},

				// ── Submit ──────────────────────────────────────────────
				submit: function () {
					var self = this;

					this.error = '';
					this.loading = true;

					fetch( sbkConfig.restUrl + '/appointments', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': this.nonce
						},
						body: JSON.stringify( {
							staff_id: this.selected.staff.id,
							service_id: this.selected.service.id,
							date: this.selected.date,
							time: this.selected.time,
							extras: this.extraIds(),
							customer: this.customer,
							note: this.note,
							create_account: this.createAccount,
							website: this.website
						} )
					} ).then( function ( response ) {
						return response.json().then( function ( body ) {
							if ( ! response.ok ) {
								var err = new Error( ( body && body.message ) || sbkConfig.i18n.genericError );
								err.code = body && body.code;
								throw err;
							}
							return body;
						} );
					} ).then( function ( data ) {
						self.result = data;
						self.focusPanel();
					} ).catch( function ( err ) {
						self.error = err.message || sbkConfig.i18n.networkError;

						// The slot went while the customer was reading the summary:
						// send them back to pick another time.
						if ( 'sbk_slot_taken' === err.code || 'sbk_no_slots' === err.code ) {
							self.selected.time = null;
							self.slots = [];
							self.step = 4;
							self.loadDays();

							if ( self.selected.date ) {
								self.loadSlots();
							}
						}

						// A stale nonce is recoverable: fetch a new one so a
						// second attempt works.
						if ( 'sbk_bad_nonce' === err.code || 'rest_cookie_invalid_nonce' === err.code ) {
							self.refreshSession();
						}
					} ).finally( function () {
						self.loading = false;
					} );
				},

				reset: function () {
					this.step = 1;
					this.result = null;
					this.error = '';
					this.selected = { staff: null, service: null, extras: [], date: null, time: null };
					this.customer = { first_name: '', last_name: '', email: '', phone: '' };
					this.note = '';
					this.createAccount = false;
					this.services = [];
					this.extras = [];
					this.slots = [];
					this.days = {};
					this.refreshSession();
				}
			};
		} );
	} );
}() );
