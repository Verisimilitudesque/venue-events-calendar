<?php
/**
 * AJAX endpoint that powers filtering, paging, view switching and calendar
 * month navigation without a page reload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Ajax {

	public static function init() {
		add_action( 'wp_ajax_vec_filter_events', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_vec_filter_events', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		check_ajax_referer( 'vec_nonce', 'nonce' );

		$view            = isset( $_POST['view'] ) ? sanitize_text_field( wp_unslash( $_POST['view'] ) ) : 'grid';
		$view            = in_array( $view, array( 'grid', 'list', 'calendar', 'carousel' ), true ) ? $view : 'grid';
		$search          = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$date_from       = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to         = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$category        = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$locked_category = isset( $_POST['locked_category'] ) ? sanitize_text_field( wp_unslash( $_POST['locked_category'] ) ) : '';
		$paged           = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;
		$posts_per_page  = isset( $_POST['posts_per_page'] ) ? max( 1, (int) $_POST['posts_per_page'] ) : 9;
		$hide_past       = isset( $_POST['hide_past'] ) ? ( '1' === sanitize_text_field( wp_unslash( $_POST['hide_past'] ) ) ) : true;
		$show_pagination = isset( $_POST['show_pagination'] ) ? ( '1' === sanitize_text_field( wp_unslash( $_POST['show_pagination'] ) ) ) : true;
		$month           = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : date_i18n( 'Y-m' );

		// A shortcode-locked category always wins over the dropdown.
		$effective_category = $locked_category !== '' ? $locked_category : $category;

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = date_i18n( 'Y-m' );
		}

		if ( 'calendar' === $view ) {
			$query = VEC_Query::get_events_for_month(
				$month,
				array(
					'search'   => $search,
					'category' => $effective_category,
				)
			);
			$calendar = VEC_Render::calendar( $query, $month );

			wp_send_json_success(
				array(
					'html'         => $calendar['html'],
					'pagination'   => '',
					'view'         => 'calendar',
					'month'        => $month,
					'month_label'  => $calendar['month_label'],
					'paged'        => 1,
					'max_pages'    => 1,
				)
			);
			return;
		}

		$query = VEC_Query::get_events(
			array(
				'search'         => $search,
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'category'       => $effective_category,
				'paged'          => $paged,
				'posts_per_page' => $posts_per_page,
				'hide_past'      => $hide_past,
			)
		);

		$max_pages = (int) $query->max_num_pages;
		// If the requested page is now out of range (e.g. filters shrank the
		// result set), clamp back to the last valid page and re-run once.
		if ( $paged > 1 && $paged > $max_pages ) {
			$paged = max( 1, $max_pages );
			$query = VEC_Query::get_events(
				array(
					'search'         => $search,
					'date_from'      => $date_from,
					'date_to'        => $date_to,
					'category'       => $effective_category,
					'paged'          => $paged,
					'posts_per_page' => $posts_per_page,
					'hide_past'      => $hide_past,
				)
			);
			$max_pages = (int) $query->max_num_pages;
		}

		if ( 'list' === $view ) {
			$html = VEC_Render::list_view( $query );
		} elseif ( 'carousel' === $view ) {
			$html = VEC_Render::carousel( $query );
		} else {
			$html = VEC_Render::grid( $query );
		}

		// The carousel is a self-looping strip, not paged.
		$show_pager = $show_pagination && 'carousel' !== $view;

		wp_send_json_success(
			array(
				'html'       => $html,
				'pagination' => $show_pager ? VEC_Render::pagination( $paged, $max_pages ) : '',
				'view'       => $view,
				'paged'      => $paged,
				'max_pages'  => $max_pages,
			)
		);
	}
}
