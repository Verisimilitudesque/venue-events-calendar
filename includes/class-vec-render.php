<?php
/**
 * HTML rendering helpers: event card (grid), event row (list), and the
 * month calendar. Kept framework-free (no external JS libraries) so the
 * plugin has no third-party dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Render {

	/**
	 * Render the grid of event cards.
	 *
	 * @param WP_Query $query
	 * @return string
	 */
	public static function grid( $query ) {
		if ( ! $query->have_posts() ) {
			return self::empty_state();
		}

		ob_start();
		echo '<div class="vec-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$data = VEC_Fields::get_event_data( get_the_ID() );
			self::render_card( $data );
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Render the stacked list view.
	 *
	 * @param WP_Query $query
	 * @return string
	 */
	public static function list_view( $query ) {
		if ( ! $query->have_posts() ) {
			return self::empty_state();
		}

		ob_start();
		echo '<div class="vec-list">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$data = VEC_Fields::get_event_data( get_the_ID() );
			self::render_list_row( $data );
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Render the auto-scrolling carousel: its own compact card layout (full-
	 * width image, date/artist/tour underneath, no action buttons — the
	 * whole card is the link) laid out in a horizontal, continuously-sliding
	 * strip. The result set is rendered twice back-to-back so the CSS
	 * animation can translate exactly -50% and loop seamlessly, regardless
	 * of how many events are in it.
	 *
	 * @param WP_Query $query
	 * @return string
	 */
	public static function carousel( $query ) {
		if ( ! $query->have_posts() ) {
			return self::empty_state();
		}

		$rows = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$rows[] = VEC_Fields::get_event_data( get_the_ID() );
		}
		wp_reset_postdata();

		ob_start();
		?>
		<div class="vec-carousel">
			<div class="vec-carousel__track">
				<?php
				// First pass is the "real" content; the keyframe animation
				// below only ever needs to travel the width of one pass
				// (translateX(-50%)) before it's back at a visually identical
				// starting point, which is what makes the loop seamless no
				// matter how many events are in $rows. That means a second,
				// purely visual copy right after it — both passes are plain
				// siblings (not wrapped in their own container) so the track's
				// total width is exactly double one pass, keeping that -50%
				// math exact. The second pass is marked aria-hidden so screen
				// readers don't announce every event twice.
				foreach ( $rows as $data ) {
					self::render_carousel_card( $data );
				}
				foreach ( $rows as $data ) {
					self::render_carousel_card( $data, true );
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * A compact carousel card: full-width image, then date/artist/tour left
	 * aligned underneath — no Buy Tickets / More Info buttons. The whole
	 * card is a single link to the event's permalink instead.
	 */
	protected static function render_carousel_card( $data, $aria_hidden = false ) {
		?>
		<a class="vec-carousel__card" href="<?php echo esc_url( $data['permalink'] ); ?>"<?php echo $aria_hidden ? ' aria-hidden="true" tabindex="-1"' : ''; ?>>
			<div class="vec-carousel__media">
				<?php // No Upgrades badge in carousel mode — cards are small and the badge crowded the image. ?>
				<?php if ( $data['artist_image_url'] ) : ?>
					<img
						src="<?php echo esc_url( $data['artist_image_url'] ); ?>"
						alt=""
						loading="lazy"
					/>
				<?php else : ?>
					<div class="vec-carousel__media-placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
			<div class="vec-carousel__body">
				<?php if ( $data['event_date_display'] ) : ?>
					<p class="vec-carousel__date"><?php echo esc_html( $data['event_date_display'] ); ?></p>
				<?php endif; ?>
				<h3 class="vec-carousel__title">
					<?php echo VEC_Fields::render_title_lines( $data ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside render_title_lines(). ?>
				</h3>
			</div>
		</a>
		<?php
	}

	/**
	 * Render the manual slider: up to $window event cards (the same card
	 * layout as the grid — image, date, title, Buy Tickets / More Info) with
	 * Previous/Next arrow controls above them. Unlike the auto-scrolling
	 * carousel, this never moves on its own — clicking an arrow shifts the
	 * window by exactly one event (ascending date order), and each arrow
	 * disables itself once there's nothing further in that direction.
	 *
	 * @param WP_Query $query  Already scoped to the current offset/window.
	 * @param int      $offset Current window start (0-based, into the full ascending result set).
	 * @param int      $window Number of cards shown at once.
	 * @param int      $found  Total matching events, for deciding whether Next has anywhere to go.
	 * @return string
	 */
	public static function slider( $query, $offset, $window, $found ) {
		$has_prev    = $offset > 0;
		$has_next    = ( $offset + $window ) < $found;
		$prev_offset = max( 0, $offset - 1 );
		$next_offset = $offset + 1;

		ob_start();
		?>
		<div class="vec-slider">
			<div class="vec-slider__nav">
				<button
					type="button"
					class="vec-slider__arrow vec-slider__arrow--prev"
					data-vec-slider-offset="<?php echo (int) $prev_offset; ?>"
					aria-label="<?php esc_attr_e( 'Previous event', 'venue-event-calendar' ); ?>"
					<?php disabled( ! $has_prev ); ?>
				>
					<?php echo VEC_Icons::get( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-authored SVG markup. ?>
				</button>
				<button
					type="button"
					class="vec-slider__arrow vec-slider__arrow--next"
					data-vec-slider-offset="<?php echo (int) $next_offset; ?>"
					aria-label="<?php esc_attr_e( 'Next event', 'venue-event-calendar' ); ?>"
					<?php disabled( ! $has_next ); ?>
				>
					<?php echo VEC_Icons::get( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-authored SVG markup. ?>
				</button>
			</div>
			<div class="vec-slider__track">
				<?php
				if ( ! $query->have_posts() ) {
					echo self::empty_state(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from static markup.
				} else {
					while ( $query->have_posts() ) {
						$query->the_post();
						$data = VEC_Fields::get_event_data( get_the_ID() );
						self::render_card( $data );
					}
					wp_reset_postdata();
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function render_card( $data ) {
		?>
		<article class="vec-card">
			<div class="vec-card__media">
				<?php if ( $data['upgrade_available'] ) : ?>
					<span class="vec-badge"><?php esc_html_e( 'Upgrades', 'venue-event-calendar' ); ?></span>
				<?php endif; ?>
				<?php if ( $data['artist_image_url'] ) : ?>
					<img
						src="<?php echo esc_url( $data['artist_image_url'] ); ?>"
						alt="<?php echo esc_attr( $data['artist_image_alt'] ); ?>"
						loading="lazy"
					/>
				<?php else : ?>
					<div class="vec-card__media-placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
			<div class="vec-card__body">
				<?php if ( $data['event_date_display'] ) : ?>
					<p class="vec-card__date"><?php echo esc_html( $data['event_date_display'] ); ?></p>
				<?php endif; ?>
				<h3 class="vec-card__title">
					<a href="<?php echo esc_url( $data['permalink'] ); ?>">
						<?php echo VEC_Fields::render_title_lines( $data ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside render_title_lines(). ?>
					</a>
				</h3>
				<div class="vec-card__actions">
					<?php if ( $data['link_to_tickets'] ) : ?>
						<a class="vec-btn vec-btn--primary" href="<?php echo esc_url( $data['link_to_tickets'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Buy Tickets', 'venue-event-calendar' ); ?>
						</a>
					<?php endif; ?>
					<a class="vec-btn vec-btn--secondary" href="<?php echo esc_url( $data['permalink'] ); ?>">
						<?php esc_html_e( 'More Info', 'venue-event-calendar' ); ?>
					</a>
				</div>
			</div>
		</article>
		<?php
	}

	protected static function render_list_row( $data, $aria_hidden = false ) {
		?>
		<article class="vec-row"<?php echo $aria_hidden ? ' aria-hidden="true"' : ''; ?>>
			<div class="vec-row__media">
				<?php if ( $data['upgrade_available'] ) : ?>
					<span class="vec-badge"><?php esc_html_e( 'Upgrades', 'venue-event-calendar' ); ?></span>
				<?php endif; ?>
				<?php if ( $data['artist_image_url'] ) : ?>
					<img
						src="<?php echo esc_url( $data['artist_image_url'] ); ?>"
						alt="<?php echo esc_attr( $data['artist_image_alt'] ); ?>"
						loading="lazy"
					/>
				<?php else : ?>
					<div class="vec-row__media-placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
			<div class="vec-row__body">
				<div class="vec-row__info">
					<?php if ( $data['event_date_display'] ) : ?>
						<p class="vec-row__date"><?php echo esc_html( $data['event_date_display'] ); ?></p>
					<?php endif; ?>
					<h3 class="vec-row__title">
						<a href="<?php echo esc_url( $data['permalink'] ); ?>">
							<?php echo VEC_Fields::render_title_lines( $data ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside render_title_lines(). ?>
						</a>
					</h3>
					<?php if ( $data['event_venue'] ) : ?>
						<p class="vec-row__venue"><?php echo esc_html( $data['event_venue'] ); ?></p>
					<?php endif; ?>
				</div>
				<div class="vec-row__actions">
					<?php if ( $data['link_to_tickets'] ) : ?>
						<a class="vec-btn vec-btn--primary" href="<?php echo esc_url( $data['link_to_tickets'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Buy Tickets', 'venue-event-calendar' ); ?>
						</a>
					<?php endif; ?>
					<a class="vec-btn vec-btn--secondary" href="<?php echo esc_url( $data['permalink'] ); ?>">
						<?php esc_html_e( 'More Info', 'venue-event-calendar' ); ?>
					</a>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Render a full month calendar grid with event chips on their date cells.
	 *
	 * @param WP_Query $query      Events already scoped to the month.
	 * @param string   $year_month Y-m
	 * @return string
	 */
	public static function calendar( $query, $year_month ) {
		$first = DateTime::createFromFormat( 'Y-m-d', $year_month . '-01' );
		if ( ! $first ) {
			$first = new DateTime( 'first day of this month' );
		}

		$events_by_day = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$data = VEC_Fields::get_event_data( get_the_ID() );
				if ( ! $data['event_date_iso'] ) {
					continue;
				}
				$day = (int) date_i18n( 'j', $data['event_timestamp'] );
				if ( ! isset( $events_by_day[ $day ] ) ) {
					$events_by_day[ $day ] = array();
				}
				$events_by_day[ $day ][] = $data;
			}
			wp_reset_postdata();
		}

		$days_in_month  = (int) $first->format( 't' );
		$start_weekday  = (int) $first->format( 'w' ); // 0 (Sun) - 6 (Sat)
		$month_label    = date_i18n( 'F Y', $first->getTimestamp() );
		$today_iso      = date_i18n( 'Y-m-d' );

		ob_start();
		?>
		<div class="vec-calendar">
			<div class="vec-calendar__weekdays">
				<?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $wd ) : ?>
					<div class="vec-calendar__weekday"><?php echo esc_html( $wd ); ?></div>
				<?php endforeach; ?>
			</div>
			<div class="vec-calendar__grid">
				<?php for ( $i = 0; $i < $start_weekday; $i++ ) : ?>
					<div class="vec-calendar__cell vec-calendar__cell--empty"></div>
				<?php endfor; ?>

				<?php for ( $day = 1; $day <= $days_in_month; $day++ ) :
					$cell_iso  = $first->format( 'Y-m' ) . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
					$is_today  = ( $cell_iso === $today_iso );
					$day_events = isset( $events_by_day[ $day ] ) ? $events_by_day[ $day ] : array();
					?>
					<div class="vec-calendar__cell<?php echo $is_today ? ' vec-calendar__cell--today' : ''; ?>">
						<span class="vec-calendar__day-number"><?php echo esc_html( (string) $day ); ?></span>
						<?php if ( ! empty( $day_events ) ) : ?>
							<div class="vec-calendar__events">
								<?php
								$visible = array_slice( $day_events, 0, 2 );
								foreach ( $visible as $ev ) :
									?>
									<a class="vec-calendar__chip" href="<?php echo esc_url( $ev['permalink'] ); ?>" title="<?php echo esc_attr( $ev['title'] ); ?>">
										<?php if ( $ev['event_time'] ) : ?>
											<span class="vec-calendar__chip-time"><?php echo esc_html( $ev['event_time'] ); ?></span>
										<?php endif; ?>
										<span class="vec-calendar__chip-title">
											<?php echo esc_html( $ev['artist_name'] ? $ev['artist_name'] : $ev['title'] ); ?><?php if ( $ev['tour_name'] ) : ?> &mdash; <?php echo esc_html( $ev['tour_name'] ); ?><?php endif; ?>
										</span>
									</a>
									<?php
								endforeach;
								if ( count( $day_events ) > 2 ) :
									?>
									<span class="vec-calendar__more">+<?php echo (int) ( count( $day_events ) - 2 ); ?> <?php esc_html_e( 'more', 'venue-event-calendar' ); ?></span>
									<?php
								endif;
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endfor; ?>

				<?php
				// Pad the final week row with out-of-month filler cells so the
				// grid always ends on a complete row of 7.
				$total_cells    = $start_weekday + $days_in_month;
				$trailing_empty = ( 7 - ( $total_cells % 7 ) ) % 7;
				for ( $i = 0; $i < $trailing_empty; $i++ ) :
					?>
					<div class="vec-calendar__cell vec-calendar__cell--empty"></div>
					<?php
				endfor;
				?>
			</div>
		</div>
		<?php
		return array(
			'html'        => ob_get_clean(),
			'month_label' => $month_label,
		);
	}

	public static function empty_state() {
		ob_start();
		?>
		<div class="vec-empty">
			<p><?php esc_html_e( 'No events found. Try adjusting your search or dates.', 'venue-event-calendar' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the Previous Events / Next Events pagination bar.
	 */
	public static function pagination( $paged, $max_pages ) {
		if ( $max_pages <= 1 ) {
			return '';
		}
		$has_prev = $paged > 1;
		$has_next = $paged < $max_pages;
		ob_start();
		?>
		<div class="vec-pagination">
			<button type="button" class="vec-btn vec-btn--pager vec-btn--prev" data-vec-page="<?php echo (int) ( $paged - 1 ); ?>" <?php disabled( ! $has_prev ); ?>>
				<span class="vec-btn__arrow" aria-hidden="true">&larr;</span> <?php esc_html_e( 'Previous Events', 'venue-event-calendar' ); ?>
			</button>
			<button type="button" class="vec-btn vec-btn--pager vec-btn--next" data-vec-page="<?php echo (int) ( $paged + 1 ); ?>" <?php disabled( ! $has_next ); ?>>
				<?php esc_html_e( 'Next Events', 'venue-event-calendar' ); ?> <span class="vec-btn__arrow" aria-hidden="true">&rarr;</span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}
