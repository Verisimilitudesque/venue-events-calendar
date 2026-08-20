<?php
/**
 * Builds and runs the WP_Query for events based on the filter bar values.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Query {

	/**
	 * Run a filtered query for the grid/list views.
	 *
	 * @param array $args {
	 *   @type string $search        Free text, matched against artist / tour / venue.
	 *   @type string $date_from     Y-m-d
	 *   @type string $date_to       Y-m-d
	 *   @type string $category      event_category value, or '' for all
	 *   @type int    $paged
	 *   @type int    $posts_per_page
	 *   @type bool   $hide_past     Exclude events before today when no explicit date_from is set.
	 * }
	 * @return WP_Query
	 */
	public static function get_events( $args = array() ) {
		$defaults = array(
			'search'         => '',
			'date_from'      => '',
			'date_to'        => '',
			'category'       => '',
			'paged'          => 1,
			'posts_per_page' => 9,
			'hide_past'      => true,
		);
		$args = wp_parse_args( $args, $defaults );

		$meta_query = array( 'relation' => 'AND' );

		$date_from = $args['date_from'] ? self::to_ymd( $args['date_from'] ) : '';
		$date_to   = $args['date_to'] ? self::to_ymd( $args['date_to'] ) : '';

		if ( ! $date_from && $args['hide_past'] ) {
			$date_from = date_i18n( 'Ymd' );
		}

		// This clause is deliberately named ("event_date_clause") so we can sort
		// on it explicitly below. Sorting via the older meta_key + orderby
		// meta_value pair is unreliable once meta_query ALSO joins on
		// event_date — WordPress can end up ordering on the wrong join alias.
		if ( $date_from && $date_to ) {
			$meta_query['event_date_clause'] = array(
				'key'     => 'event_date',
				'value'   => array( $date_from, $date_to ),
				'compare' => 'BETWEEN',
				'type'    => 'CHAR',
			);
		} elseif ( $date_from ) {
			$meta_query['event_date_clause'] = array(
				'key'     => 'event_date',
				'value'   => $date_from,
				'compare' => '>=',
				'type'    => 'CHAR',
			);
		} elseif ( $date_to ) {
			$meta_query['event_date_clause'] = array(
				'key'     => 'event_date',
				'value'   => $date_to,
				'compare' => '<=',
				'type'    => 'CHAR',
			);
		} else {
			// No date bounds at all (hide_past="no" with no range picked) — we
			// still need the join present so ordering has something to sort on.
			$meta_query['event_date_clause'] = array(
				'key'     => 'event_date',
				'compare' => 'EXISTS',
			);
		}

		if ( $args['category'] !== '' ) {
			$meta_query[] = array(
				'key'     => 'event_category',
				'value'   => sanitize_text_field( $args['category'] ),
				'compare' => 'LIKE',
			);
		}

		if ( $args['search'] !== '' ) {
			$term = sanitize_text_field( $args['search'] );
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'artist_name',
					'value'   => $term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'tour_name',
					'value'   => $term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'event_venue',
					'value'   => $term,
					'compare' => 'LIKE',
				),
			);
		}

		$query_args = array(
			'post_type'      => VEC_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['posts_per_page'],
			'paged'          => max( 1, (int) $args['paged'] ),
			// Soonest event first. ACF's Date Picker stores dates as a
			// zero-padded Ymd string (20260815), so a plain string sort is
			// already chronological — no CAST needed, and CAST would in fact
			// break if any row held a non-numeric value.
			'orderby'        => array( 'event_date_clause' => 'ASC' ),
			'meta_query'     => $meta_query,
		);

		return new WP_Query( $query_args );
	}

	/**
	 * Get every event within a given month (used by the calendar view), ignoring
	 * the "hide past" rule so users can browse past months too.
	 *
	 * @param string $year_month Y-m, e.g. "2026-08".
	 * @param array  $extra_args Optional search/category filters to also apply.
	 * @return WP_Query
	 */
	public static function get_events_for_month( $year_month, $extra_args = array() ) {
		$first = DateTime::createFromFormat( 'Y-m-d', $year_month . '-01' );
		if ( ! $first ) {
			$first = new DateTime( 'first day of this month' );
		}
		$last = clone $first;
		$last->modify( 'last day of this month' );

		$args = wp_parse_args(
			$extra_args,
			array(
				'search'   => '',
				'category' => '',
			)
		);

		$args['date_from']      = $first->format( 'Y-m-d' );
		$args['date_to']        = $last->format( 'Y-m-d' );
		$args['hide_past']      = false;
		$args['posts_per_page'] = -1;
		$args['paged']          = 1;

		return self::get_events( $args );
	}

	/**
	 * Normalize a Y-m-d date string (from an <input type="date">) into the
	 * Ymd format ACF's Date Picker field stores in postmeta.
	 */
	public static function to_ymd( $date_string ) {
		$ts = strtotime( (string) $date_string );
		return $ts ? date_i18n( 'Ymd', $ts ) : '';
	}
}
