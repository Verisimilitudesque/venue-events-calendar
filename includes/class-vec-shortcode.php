<?php
/**
 * [venue_events] shortcode: renders the filter bar, view switcher and the
 * initial (server-rendered) batch of events. All further interaction
 * (filtering, paging, switching views, changing calendar month) happens
 * over AJAX via VEC_Ajax so the page never has to reload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Shortcode {

	protected static $instance_count = 0;

	public static function init() {
		add_shortcode( 'venue_events', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_filters'       => 'yes',
				'show_view_switcher' => 'yes',
				'default_view'       => 'grid',   // grid | list | calendar | carousel | slider
				'category'           => '',        // locks the shortcode to a single event_category value
				'posts_per_page'     => 9,
				'hide_past'          => 'yes',
				'show_pagination'    => 'yes', // hides the Previous/Next Events controls (grid & list views only)
				'show_ad'            => 'yes', // set to "no" to force-hide the ad slot, even if the widget area has content
				'ad_image'           => '',        // optional ad creative shown between the view switcher and category dropdown
				'ad_link'            => '',
				'ad_alt'             => '',
			),
			$atts,
			'venue_events'
		);

		if ( function_exists( 'vec_load_assets' ) ) {
			vec_load_assets();
		}

		self::$instance_count++;
		$instance_id = 'vec-' . self::$instance_count . '-' . substr( md5( wp_rand() ), 0, 6 );

		$show_filters       = self::is_yes( $atts['show_filters'] );
		$show_view_switcher = self::is_yes( $atts['show_view_switcher'] );
		$hide_past          = self::is_yes( $atts['hide_past'] );
		$show_pagination    = self::is_yes( $atts['show_pagination'] );
		$show_ad            = self::is_yes( $atts['show_ad'] );
		$default_view       = in_array( $atts['default_view'], array( 'grid', 'list', 'calendar', 'carousel', 'slider' ), true ) ? $atts['default_view'] : 'grid';
		$posts_per_page     = max( 1, (int) $atts['posts_per_page'] );
		$locked_category    = sanitize_text_field( $atts['category'] );
		$ad_image           = esc_url_raw( $atts['ad_image'] );
		$ad_link             = esc_url_raw( $atts['ad_link'] );
		$ad_alt               = sanitize_text_field( $atts['ad_alt'] );

		// The "View as" bar ad slot: prefer whatever's been added via
		// Appearance > Widgets (a normal backend-editable code block), and
		// only fall back to the ad_image/ad_link/ad_alt shortcode attributes
		// if that widget area is empty. show_ad="no" forces it off entirely
		// for this instance, even if the widget area has content.
		$has_ad_widget = is_active_sidebar( 'vec-ad-slot' );
		$show_ad_slot  = $show_ad && ( $has_ad_widget || $ad_image );

		$category_choices = VEC_Fields::get_event_category_choices();

		// Build the initial server-rendered results so the block works even
		// before JS loads, and so search engines see real event markup.
		if ( 'calendar' === $default_view ) {
			$current_month = date_i18n( 'Y-m' );
			$query         = VEC_Query::get_events_for_month( $current_month, array( 'category' => $locked_category ) );
			$calendar      = VEC_Render::calendar( $query, $current_month );
			$results_html  = $calendar['html'];
			$month_label   = $calendar['month_label'];
			$pagination_html = '';
			$max_pages     = 1;
			$paged         = 1;
			$slider_offset = 0;
		} elseif ( 'slider' === $default_view ) {
			// Fixed 3-card window, offset-based rather than paged — see
			// VEC_Query::get_events()'s $offset argument.
			$slider_offset   = 0;
			$query           = VEC_Query::get_events(
				array(
					'category'       => $locked_category,
					'hide_past'      => $hide_past,
					'posts_per_page' => VEC_SLIDER_WINDOW,
					'offset'         => $slider_offset,
				)
			);
			$results_html    = VEC_Render::slider( $query, $slider_offset, VEC_SLIDER_WINDOW, (int) $query->found_posts );
			$pagination_html = ''; // The slider has its own built-in Previous/Next arrows.
			$max_pages       = 1;
			$paged           = 1;
			$current_month   = date_i18n( 'Y-m' );
			$month_label     = date_i18n( 'F Y' );
		} else {
			$query = VEC_Query::get_events(
				array(
					'category'       => $locked_category,
					'posts_per_page' => $posts_per_page,
					'hide_past'      => $hide_past,
					'paged'          => 1,
				)
			);
			if ( 'list' === $default_view ) {
				$results_html = VEC_Render::list_view( $query );
			} elseif ( 'carousel' === $default_view ) {
				$results_html = VEC_Render::carousel( $query );
			} else {
				$results_html = VEC_Render::grid( $query );
			}
			// The carousel is a self-looping strip, not paged — like the
			// calendar's own month nav, it never shows Previous/Next Events.
			$pagination_html = ( $show_pagination && 'carousel' !== $default_view ) ? VEC_Render::pagination( 1, (int) $query->max_num_pages ) : '';
			$max_pages       = (int) $query->max_num_pages;
			$paged           = 1;
			$current_month   = date_i18n( 'Y-m' );
			$month_label     = date_i18n( 'F Y' );
			$slider_offset   = 0;
		}

		ob_start();
		?>
		<div
			class="vec-events"
			id="<?php echo esc_attr( $instance_id ); ?>"
			data-instance="<?php echo esc_attr( $instance_id ); ?>"
			data-posts-per-page="<?php echo esc_attr( $posts_per_page ); ?>"
			data-hide-past="<?php echo $hide_past ? '1' : '0'; ?>"
			data-show-pagination="<?php echo $show_pagination ? '1' : '0'; ?>"
			data-locked-category="<?php echo esc_attr( $locked_category ); ?>"
			data-view="<?php echo esc_attr( $default_view ); ?>"
			data-paged="<?php echo esc_attr( $paged ); ?>"
			data-max-pages="<?php echo esc_attr( $max_pages ); ?>"
			data-slider-offset="<?php echo esc_attr( $slider_offset ); ?>"
			data-month="<?php echo esc_attr( $current_month ); ?>"
		>
			<?php if ( $show_filters ) : ?>
				<div class="vec-filterbar">
					<div class="vec-filterbar__fields">
						<button
							type="button"
							class="vec-datefield"
							id="<?php echo esc_attr( $instance_id ); ?>-datefield"
							aria-haspopup="dialog"
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $instance_id ); ?>-datepopover"
							data-from=""
							data-to=""
						>
							<span class="vec-icon vec-icon--calendar-lg" aria-hidden="true"></span>
							<span class="vec-datefield__text">
								<span class="vec-datefield__label"><?php esc_html_e( 'Dates', 'venue-event-calendar' ); ?></span>
								<span class="vec-datefield__value"><?php esc_html_e( 'Select dates', 'venue-event-calendar' ); ?></span>
							</span>
							<span class="vec-datefield__chevron" aria-hidden="true"></span>
						</button>

						<div class="vec-date-popover" id="<?php echo esc_attr( $instance_id ); ?>-datepopover" role="dialog" aria-label="<?php esc_attr_e( 'Select date range', 'venue-event-calendar' ); ?>" hidden>
							<div class="vec-date-popover__nav">
								<button type="button" class="vec-btn vec-btn--pager vec-cal-pick-prev" aria-label="<?php esc_attr_e( 'Previous month', 'venue-event-calendar' ); ?>">&larr;</button>
								<span class="vec-date-popover__month-label"></span>
								<button type="button" class="vec-btn vec-btn--pager vec-cal-pick-next" aria-label="<?php esc_attr_e( 'Next month', 'venue-event-calendar' ); ?>">&rarr;</button>
							</div>
							<div class="vec-date-popover__summary">
								<div class="vec-date-popover__summary-item">
									<span class="vec-date-popover__summary-label"><?php esc_html_e( 'From', 'venue-event-calendar' ); ?></span>
									<span class="vec-date-popover__from-value">&mdash;</span>
								</div>
								<div class="vec-date-popover__summary-item">
									<span class="vec-date-popover__summary-label"><?php esc_html_e( 'To', 'venue-event-calendar' ); ?></span>
									<span class="vec-date-popover__to-value">&mdash;</span>
								</div>
							</div>
							<div class="vec-date-popover__grid" data-vec-picker-grid></div>
							<div class="vec-date-popover__actions">
								<button type="button" class="vec-btn vec-btn--text vec-date-reset"><?php esc_html_e( 'Reset', 'venue-event-calendar' ); ?></button>
								<button type="button" class="vec-btn vec-btn--secondary vec-date-cancel"><?php esc_html_e( 'Cancel', 'venue-event-calendar' ); ?></button>
								<button type="button" class="vec-btn vec-btn--primary vec-date-apply"><?php esc_html_e( 'Apply', 'venue-event-calendar' ); ?></button>
							</div>
						</div>

						<div class="vec-searchfield">
							<span class="vec-icon vec-icon--search" aria-hidden="true"></span>
							<div class="vec-searchfield__text">
								<label for="<?php echo esc_attr( $instance_id ); ?>-search" class="vec-searchfield__label"><?php esc_html_e( 'Upcoming Events', 'venue-event-calendar' ); ?></label>
								<input type="search" id="<?php echo esc_attr( $instance_id ); ?>-search" class="vec-input vec-input-search vec-searchfield__input" placeholder="<?php esc_attr_e( 'Artist, Event or Venue', 'venue-event-calendar' ); ?>" />
							</div>
						</div>

						<button type="button" class="vec-btn vec-btn--primary vec-search-btn"><?php esc_html_e( 'Search', 'venue-event-calendar' ); ?></button>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_view_switcher || $show_ad_slot || ( $show_filters && empty( $locked_category ) && ! empty( $category_choices ) ) ) : ?>
				<div class="vec-toolbar">
					<?php if ( $show_view_switcher ) : ?>
						<div class="vec-view-switcher" role="tablist" aria-label="<?php esc_attr_e( 'View as', 'venue-event-calendar' ); ?>">
							<span class="vec-view-switcher__label"><?php esc_html_e( 'View as', 'venue-event-calendar' ); ?></span>
							<button type="button" class="vec-view-btn<?php echo 'grid' === $default_view ? ' is-active' : ''; ?>" data-view="grid" role="tab" aria-selected="<?php echo 'grid' === $default_view ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Grid view', 'venue-event-calendar' ); ?>">
								<span class="vec-icon vec-icon--grid" aria-hidden="true"><?php echo VEC_Icons::get( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-authored SVG markup. ?></span>
							</button>
							<button type="button" class="vec-view-btn<?php echo 'list' === $default_view ? ' is-active' : ''; ?>" data-view="list" role="tab" aria-selected="<?php echo 'list' === $default_view ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'List view', 'venue-event-calendar' ); ?>">
								<span class="vec-icon vec-icon--list" aria-hidden="true"><?php echo VEC_Icons::get( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-authored SVG markup. ?></span>
							</button>
							<button type="button" class="vec-view-btn<?php echo 'calendar' === $default_view ? ' is-active' : ''; ?>" data-view="calendar" role="tab" aria-selected="<?php echo 'calendar' === $default_view ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Calendar view', 'venue-event-calendar' ); ?>">
								<span class="vec-icon vec-icon--calendar" aria-hidden="true"><?php echo VEC_Icons::get( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static, hand-authored SVG markup. ?></span>
							</button>
							<?php
							// Carousel is intentionally not offered as a switcher option — it's
							// only reachable via default_view="carousel" on the shortcode itself,
							// so a page can be built around it deliberately rather than a visitor
							// stumbling into it from the grid/list/calendar toggle.
							?>
						</div>
					<?php endif; ?>

					<?php if ( $show_ad_slot ) : ?>
						<div class="vec-ad-slot">
							<?php if ( $has_ad_widget ) : ?>
								<?php dynamic_sidebar( 'vec-ad-slot' ); ?>
							<?php elseif ( $ad_image ) : ?>
								<?php if ( $ad_link ) : ?><a href="<?php echo esc_url( $ad_link ); ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
								<img src="<?php echo esc_url( $ad_image ); ?>" alt="<?php echo esc_attr( $ad_alt ); ?>" loading="lazy" />
								<?php if ( $ad_link ) : ?></a><?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_filters && empty( $locked_category ) && ! empty( $category_choices ) ) : ?>
						<div class="vec-category-filter">
							<label for="<?php echo esc_attr( $instance_id ); ?>-category" class="vec-screen-reader-text"><?php esc_html_e( 'Event Category', 'venue-event-calendar' ); ?></label>
							<select id="<?php echo esc_attr( $instance_id ); ?>-category" class="vec-input-category">
								<option value=""><?php esc_html_e( 'Event Category', 'venue-event-calendar' ); ?></option>
								<?php foreach ( $category_choices as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="vec-category-filter__chevron" aria-hidden="true"></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="vec-calendar-nav" <?php echo 'calendar' === $default_view ? '' : 'hidden'; ?>>
				<button type="button" class="vec-btn vec-btn--pager vec-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'venue-event-calendar' ); ?>">&larr;</button>
				<span class="vec-calendar-nav__label"><?php echo esc_html( $month_label ); ?></span>
				<button type="button" class="vec-btn vec-btn--pager vec-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'venue-event-calendar' ); ?>">&rarr;</button>
			</div>

			<div class="vec-results" aria-live="polite" aria-busy="false">
				<?php echo $results_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped partials above. ?>
			</div>

			<div class="vec-pagination-wrap" <?php echo ( 'calendar' === $default_view || 'carousel' === $default_view || 'slider' === $default_view || ! $show_pagination ) ? 'hidden' : ''; ?>>
				<?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped partials above. ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function is_yes( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( 'yes', 'true', '1' ), true );
	}
}
