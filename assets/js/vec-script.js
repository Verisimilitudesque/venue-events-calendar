/**
 * Venue Event Calendar — front-end behavior.
 * Vanilla JS, no external dependencies. Supports multiple shortcode
 * instances on the same page.
 */
( function () {
	'use strict';

	function debounce( fn, wait ) {
		var t;
		return function () {
			var args = arguments;
			var ctx = this;
			clearTimeout( t );
			t = setTimeout( function () {
				fn.apply( ctx, args );
			}, wait );
		};
	}

	function qs( root, sel ) {
		return root.querySelector( sel );
	}
	function qsa( root, sel ) {
		return Array.prototype.slice.call( root.querySelectorAll( sel ) );
	}

	function pad2( n ) {
		return ( '0' + n ).slice( -2 );
	}
	function toISO( d ) {
		return d.getFullYear() + '-' + pad2( d.getMonth() + 1 ) + '-' + pad2( d.getDate() );
	}
	function fromISO( s ) {
		if ( ! s ) {
			return null;
		}
		var parts = s.split( '-' );
		return new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );
	}
	function formatDisplay( d ) {
		return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } );
	}

	/**
	 * A self-contained date-range picker popover: click the trigger button to
	 * open it, click a start day then an end day to select a range, Reset /
	 * Cancel / Apply control committing the selection. Calls onApply(from, to)
	 * (ISO "Y-m-d" strings, or '' if cleared) only when Apply is pressed.
	 */
	function initDatePopover( container, onApply ) {
		var trigger = qs( container, '.vec-datefield' );
		var popover = qs( container, '.vec-date-popover' );
		if ( ! trigger || ! popover ) {
			return;
		}

		var grid         = qs( popover, '[data-vec-picker-grid]' );
		var monthLabel   = qs( popover, '.vec-date-popover__month-label' );
		var fromValueEl  = qs( popover, '.vec-date-popover__from-value' );
		var toValueEl    = qs( popover, '.vec-date-popover__to-value' );
		var prevBtn      = qs( popover, '.vec-cal-pick-prev' );
		var nextBtn      = qs( popover, '.vec-cal-pick-next' );
		var resetBtn     = qs( popover, '.vec-date-reset' );
		var cancelBtn    = qs( popover, '.vec-date-cancel' );
		var applyBtn     = qs( popover, '.vec-date-apply' );
		var valueDisplay = qs( trigger, '.vec-datefield__value' );

		var today = new Date();
		today.setHours( 0, 0, 0, 0 );

		var pickerMonth = { year: today.getFullYear(), month: today.getMonth() };
		var temp        = { from: null, to: null };
		var applied     = { from: null, to: null };

		function renderGrid() {
			var year = pickerMonth.year;
			var month = pickerMonth.month;
			var first = new Date( year, month, 1 );
			var daysInMonth = new Date( year, month + 1, 0 ).getDate();
			var startWeekday = first.getDay();

			if ( monthLabel ) {
				monthLabel.textContent = first.toLocaleDateString( undefined, { month: 'long', year: 'numeric' } );
			}

			var html = '<div class="vec-date-popover__weekdays">';
			[ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ].forEach( function ( wd ) {
				html += '<div class="vec-date-popover__weekday">' + wd + '</div>';
			} );
			html += '</div><div class="vec-date-popover__days">';

			for ( var i = 0; i < startWeekday; i++ ) {
				html += '<span class="vec-date-popover__day vec-date-popover__day--muted"></span>';
			}

			for ( var day = 1; day <= daysInMonth; day++ ) {
				var d = new Date( year, month, day );
				var classes = [ 'vec-date-popover__day' ];

				if ( d.getTime() === today.getTime() ) {
					classes.push( 'vec-date-popover__day--today' );
				}
				if ( temp.from && d.getTime() === temp.from.getTime() ) {
					classes.push( 'vec-date-popover__day--start' );
				}
				if ( temp.to && d.getTime() === temp.to.getTime() ) {
					classes.push( 'vec-date-popover__day--end' );
				}
				if ( temp.from && temp.to && d.getTime() > temp.from.getTime() && d.getTime() < temp.to.getTime() ) {
					classes.push( 'vec-date-popover__day--in-range' );
				}

				html += '<button type="button" class="' + classes.join( ' ' ) + '" data-date="' + toISO( d ) + '">' + day + '</button>';
			}

			html += '</div>';
			grid.innerHTML = html;

			qsa( grid, '[data-date]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var d = fromISO( btn.getAttribute( 'data-date' ) );

					if ( ! temp.from || ( temp.from && temp.to ) ) {
						temp.from = d;
						temp.to = null;
					} else if ( d.getTime() < temp.from.getTime() ) {
						temp.from = d;
						temp.to = null;
					} else {
						temp.to = d;
					}

					updateSummary();
					renderGrid();
				} );
			} );
		}

		function updateSummary() {
			if ( fromValueEl ) {
				fromValueEl.textContent = temp.from ? formatDisplay( temp.from ) : '—';
			}
			if ( toValueEl ) {
				toValueEl.textContent = temp.to ? formatDisplay( temp.to ) : '—';
			}
		}

		function updateTriggerLabel() {
			if ( ! valueDisplay ) {
				return;
			}
			if ( applied.from && applied.to ) {
				valueDisplay.textContent = formatDisplay( applied.from ) + ' – ' + formatDisplay( applied.to );
			} else if ( applied.from ) {
				valueDisplay.textContent = formatDisplay( applied.from );
			} else {
				valueDisplay.textContent = ( trigger.getAttribute( 'data-default-label' ) || 'Select dates' );
			}
		}

		function openPopover() {
			temp.from = applied.from;
			temp.to = applied.to;
			var seedDate = applied.from || today;
			pickerMonth = { year: seedDate.getFullYear(), month: seedDate.getMonth() };
			updateSummary();
			renderGrid();
			popover.hidden = false;
			trigger.setAttribute( 'aria-expanded', 'true' );
			document.addEventListener( 'click', onOutsideClick, true );
			document.addEventListener( 'keydown', onKeydown, true );
		}

		function closePopover() {
			popover.hidden = true;
			trigger.setAttribute( 'aria-expanded', 'false' );
			document.removeEventListener( 'click', onOutsideClick, true );
			document.removeEventListener( 'keydown', onKeydown, true );
		}

		function onOutsideClick( e ) {
			if ( ! popover.contains( e.target ) && e.target !== trigger && ! trigger.contains( e.target ) ) {
				closePopover();
			}
		}

		function onKeydown( e ) {
			if ( 'Escape' === e.key ) {
				closePopover();
			}
		}

		trigger.addEventListener( 'click', function () {
			if ( popover.hidden ) {
				openPopover();
			} else {
				closePopover();
			}
		} );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				pickerMonth.month -= 1;
				if ( pickerMonth.month < 0 ) {
					pickerMonth.month = 11;
					pickerMonth.year -= 1;
				}
				renderGrid();
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				pickerMonth.month += 1;
				if ( pickerMonth.month > 11 ) {
					pickerMonth.month = 0;
					pickerMonth.year += 1;
				}
				renderGrid();
			} );
		}

		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				temp.from = null;
				temp.to = null;
				updateSummary();
				renderGrid();
			} );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', function () {
				closePopover();
			} );
		}

		if ( applyBtn ) {
			applyBtn.addEventListener( 'click', function () {
				applied.from = temp.from;
				applied.to = temp.to;
				updateTriggerLabel();
				trigger.setAttribute( 'data-from', applied.from ? toISO( applied.from ) : '' );
				trigger.setAttribute( 'data-to', applied.to ? toISO( applied.to ) : '' );
				closePopover();
				onApply( applied.from ? toISO( applied.from ) : '', applied.to ? toISO( applied.to ) : '' );
			} );
		}
	}

	function initInstance( container ) {
		var state = {
			view: container.getAttribute( 'data-view' ) || 'grid',
			paged: parseInt( container.getAttribute( 'data-paged' ), 10 ) || 1,
			month: container.getAttribute( 'data-month' ) || '',
			postsPerPage: parseInt( container.getAttribute( 'data-posts-per-page' ), 10 ) || 9,
			hidePast: container.getAttribute( 'data-hide-past' ) === '1',
			showPagination: container.getAttribute( 'data-show-pagination' ) !== '0',
			lockedCategory: container.getAttribute( 'data-locked-category' ) || '',
			search: '',
			dateFrom: '',
			dateTo: '',
			category: '',
		};

		var results        = qs( container, '.vec-results' );
		var paginationWrap  = qs( container, '.vec-pagination-wrap' );
		var calendarNav     = qs( container, '.vec-calendar-nav' );
		var calendarLabel   = calendarNav ? qs( calendarNav, '.vec-calendar-nav__label' ) : null;
		var searchInput     = qs( container, '.vec-input-search' );
		var categorySelect  = qs( container, '.vec-input-category' );
		var searchBtn       = qs( container, '.vec-search-btn' );
		var viewButtons     = qsa( container, '.vec-view-btn' );

		function setLoading( isLoading ) {
			container.classList.toggle( 'is-loading', isLoading );
			results.setAttribute( 'aria-busy', isLoading ? 'true' : 'false' );
		}

		// Pixels per second the carousel scrolls at. The track is rendered as
		// two identical passes back-to-back so a translateX(-50%) loop is
		// seamless (see VEC_Render::carousel()) — halving the measured width
		// gives the distance a single loop actually needs to cover, so the
		// visual speed stays constant no matter how many events are in it.
		var CAROUSEL_PX_PER_SECOND = 60;

		function setupCarousels() {
			qsa( results, '.vec-carousel__track' ).forEach( function ( track ) {
				var half = track.scrollWidth / 2;
				if ( half > 0 ) {
					track.style.animationDuration = ( half / CAROUSEL_PX_PER_SECOND ) + 's';
				}
			} );
		}

		function request() {
			setLoading( true );

			var body = new URLSearchParams();
			body.set( 'action', 'vec_filter_events' );
			body.set( 'nonce', window.VEC_Settings ? window.VEC_Settings.nonce : '' );
			body.set( 'view', state.view );
			body.set( 'search', state.search );
			body.set( 'date_from', state.dateFrom );
			body.set( 'date_to', state.dateTo );
			body.set( 'category', state.category );
			body.set( 'locked_category', state.lockedCategory );
			body.set( 'paged', state.paged );
			body.set( 'posts_per_page', state.postsPerPage );
			body.set( 'hide_past', state.hidePast ? '1' : '0' );
			body.set( 'show_pagination', state.showPagination ? '1' : '0' );
			body.set( 'month', state.month );

			var ajaxUrl = window.VEC_Settings ? window.VEC_Settings.ajaxUrl : '/wp-admin/admin-ajax.php';

			fetch( ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						return;
					}
					var data = json.data;
					results.innerHTML = data.html;
					setupCarousels();

					if ( 'calendar' === state.view ) {
						if ( calendarLabel ) {
							calendarLabel.textContent = data.month_label;
						}
						if ( calendarNav ) {
							calendarNav.hidden = false;
						}
						if ( paginationWrap ) {
							paginationWrap.hidden = true;
						}
					} else {
						if ( calendarNav ) {
							calendarNav.hidden = true;
						}
						if ( paginationWrap ) {
							// The carousel is a self-looping strip, not paged.
							var showPager = state.showPagination && 'carousel' !== state.view;
							paginationWrap.hidden = ! showPager;
							if ( showPager ) {
								paginationWrap.innerHTML = data.pagination || '';
								bindPaginationButtons();
							}
						}
						state.paged = data.paged || 1;
					}

					container.setAttribute( 'data-paged', state.paged );
					container.setAttribute( 'data-month', state.month );
				} )
				['catch']( function () {
					// Network/parse failure: leave the current results in place
					// rather than blanking them out, and clear the busy state.
				} )
				['finally']( function () {
					setLoading( false );
				} );
		}

		function bindPaginationButtons() {
			qsa( container, '[data-vec-page]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					if ( btn.disabled ) {
						return;
					}
					state.paged = parseInt( btn.getAttribute( 'data-vec-page' ), 10 ) || 1;
					request();
					// Scroll the widget back into view on page change.
					container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} );
			} );
		}

		function switchView( newView ) {
			if ( newView === state.view ) {
				return;
			}
			state.view = newView;
			state.paged = 1;
			container.setAttribute( 'data-view', newView );

			viewButtons.forEach( function ( btn ) {
				var active = btn.getAttribute( 'data-view' ) === newView;
				btn.classList.toggle( 'is-active', active );
				btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );

			if ( 'calendar' === newView && ! state.month ) {
				state.month = container.getAttribute( 'data-month' ) || '';
			}

			request();
		}

		// Wire up controls.
		if ( searchInput ) {
			searchInput.addEventListener(
				'input',
				debounce( function () {
					state.search = searchInput.value.trim();
					state.paged = 1;
					request();
				}, 400 )
			);
		}

		if ( searchBtn ) {
			searchBtn.addEventListener( 'click', function () {
				state.search = searchInput ? searchInput.value.trim() : '';
				state.paged = 1;
				request();
			} );
		}

		initDatePopover( container, function ( from, to ) {
			state.dateFrom = from;
			state.dateTo = to;
			state.paged = 1;
			request();
		} );

		if ( categorySelect ) {
			categorySelect.addEventListener( 'change', function () {
				state.category = categorySelect.value;
				state.paged = 1;
				request();
			} );
		}

		viewButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				switchView( btn.getAttribute( 'data-view' ) );
			} );
		} );

		bindPaginationButtons();
		setupCarousels();

		if ( calendarNav ) {
			var prevBtn = qs( calendarNav, '.vec-cal-prev' );
			var nextBtn = qs( calendarNav, '.vec-cal-next' );

			function shiftMonth( delta ) {
				var parts = ( state.month || container.getAttribute( 'data-month' ) ).split( '-' );
				var year = parseInt( parts[ 0 ], 10 );
				var month = parseInt( parts[ 1 ], 10 ) - 1 + delta;

				var d = new Date( year, month, 1 );
				var newYear = d.getFullYear();
				var newMonth = ( '0' + ( d.getMonth() + 1 ) ).slice( -2 );
				state.month = newYear + '-' + newMonth;
				request();
			}

			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					shiftMonth( -1 );
				} );
			}
			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					shiftMonth( 1 );
				} );
			}
		}
	}

	function init() {
		qsa( document, '.vec-events' ).forEach( initInstance );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
