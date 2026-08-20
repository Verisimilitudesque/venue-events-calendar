<?php
/**
 * Small helpers for reading the "event" ACF fields safely, regardless of how
 * each field's "Return Format" is configured in ACF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Fields {

	/**
	 * Get every displayable value for a single event post, already normalized.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function get_event_data( $post_id ) {
		$has_acf = function_exists( 'get_field' );

		$artist_name      = $has_acf ? get_field( 'artist_name', $post_id ) : get_post_meta( $post_id, 'artist_name', true );
		$tour_name        = $has_acf ? get_field( 'tour_name', $post_id ) : get_post_meta( $post_id, 'tour_name', true );
		$event_date_raw   = $has_acf ? get_field( 'event_date', $post_id, false ) : get_post_meta( $post_id, 'event_date', true );
		$event_time       = $has_acf ? get_field( 'event_time', $post_id ) : get_post_meta( $post_id, 'event_time', true );
		$artist_image_raw = $has_acf ? get_field( 'artist_image', $post_id ) : get_post_meta( $post_id, 'artist_image', true );
		$link_to_tickets  = $has_acf ? get_field( 'link_to_tickets', $post_id ) : get_post_meta( $post_id, 'link_to_tickets', true );
		$upgrade_raw      = $has_acf ? get_field( 'upgrade_available', $post_id ) : get_post_meta( $post_id, 'upgrade_available', true );
		$event_category   = $has_acf ? get_field( 'event_category', $post_id ) : get_post_meta( $post_id, 'event_category', true );
		$event_venue      = $has_acf ? get_field( 'event_venue', $post_id ) : get_post_meta( $post_id, 'event_venue', true );

		$timestamp = self::parse_date_to_timestamp( $event_date_raw );

		return array(
			'id'                => $post_id,
			'permalink'         => get_permalink( $post_id ),
			'artist_name'       => (string) $artist_name,
			'tour_name'         => (string) $tour_name,
			'title'             => self::build_title( $artist_name, $tour_name, $post_id ),
			'event_date_raw'    => $event_date_raw,
			'event_timestamp'   => $timestamp,
			'event_date_display'=> $timestamp ? date_i18n( 'D, M j Y', $timestamp ) : '',
			'event_date_iso'    => $timestamp ? date_i18n( 'Y-m-d', $timestamp ) : '',
			'event_time'        => (string) $event_time,
			'artist_image_url'  => self::resolve_image_url( $artist_image_raw ),
			'artist_image_alt'  => self::resolve_image_alt( $artist_image_raw, (string) $artist_name ),
			'link_to_tickets'   => self::resolve_url( $link_to_tickets ),
			'upgrade_available' => self::to_bool( $upgrade_raw ),
			'event_category'    => is_array( $event_category ) ? implode( ', ', $event_category ) : (string) $event_category,
			'event_venue'       => (string) $event_venue,
		);
	}

	/**
	 * Render the card/row title as two lines: artist name, then tour name
	 * underneath. Both parts are escaped here, so the return value is safe to
	 * echo directly. Returns '' when there is nothing to show.
	 *
	 * Uses a real block-level element rather than a <br> so the two lines can
	 * be styled (and spaced) independently in CSS.
	 *
	 * @param array $data Output of get_event_data().
	 * @return string
	 */
	public static function render_title_lines( $data ) {
		$artist = trim( (string) $data['artist_name'] );
		$tour   = trim( (string) $data['tour_name'] );

		// Neither ACF field filled in — fall back to the WP post title.
		if ( '' === $artist && '' === $tour ) {
			return '<span class="vec-title__artist">' . esc_html( get_the_title( $data['id'] ) ) . '</span>';
		}

		$html = '';
		if ( '' !== $artist ) {
			$html .= '<span class="vec-title__artist">' . esc_html( $artist ) . '</span>';
		}
		if ( '' !== $tour ) {
			$html .= '<span class="vec-title__tour">' . esc_html( $tour ) . '</span>';
		}

		return $html;
	}

	/**
	 * Combine artist + tour into a single-line "Artist Name: Tour Name" string.
	 * Still used where markup isn't possible — calendar chips, title/alt
	 * attributes, aria labels. Falls back gracefully if either piece is empty.
	 */
	public static function build_title( $artist_name, $tour_name, $post_id = 0 ) {
		$artist_name = trim( (string) $artist_name );
		$tour_name   = trim( (string) $tour_name );

		if ( $artist_name && $tour_name ) {
			return $artist_name . ': ' . $tour_name;
		}
		if ( $artist_name ) {
			return $artist_name;
		}
		if ( $tour_name ) {
			return $tour_name;
		}
		return $post_id ? get_the_title( $post_id ) : '';
	}

	/**
	 * ACF image fields can return an array, an attachment ID, or a raw URL
	 * depending on the field's Return Format setting. Normalize to a URL.
	 */
	public static function resolve_image_url( $image_raw ) {
		if ( is_array( $image_raw ) && ! empty( $image_raw['url'] ) ) {
			return $image_raw['url'];
		}
		if ( is_numeric( $image_raw ) ) {
			$url = wp_get_attachment_image_url( (int) $image_raw, 'large' );
			if ( $url ) {
				return $url;
			}
		}
		if ( is_string( $image_raw ) && $image_raw !== '' ) {
			return $image_raw;
		}
		return '';
	}

	public static function resolve_image_alt( $image_raw, $fallback ) {
		if ( is_array( $image_raw ) && ! empty( $image_raw['alt'] ) ) {
			return $image_raw['alt'];
		}
		return $fallback;
	}

	/**
	 * ACF link fields can return an array (url/title/target) or a plain URL
	 * string depending on the field type (Link vs URL).
	 */
	public static function resolve_url( $link_raw ) {
		if ( is_array( $link_raw ) && ! empty( $link_raw['url'] ) ) {
			return $link_raw['url'];
		}
		if ( is_string( $link_raw ) ) {
			return $link_raw;
		}
		return '';
	}

	/**
	 * Normalize whatever upgrade_available comes back as into a real bool.
	 *
	 * A native ACF True/False field always returns a PHP bool via get_field(),
	 * so the is_bool() branch is the common path. But this also tolerates the
	 * field having been built as a Radio/Button Group/Select with "Yes"/"No"
	 * choices instead — those return the choice string exactly as typed in
	 * ACF (commonly "Yes", capitalized), which a case-sensitive check would
	 * silently treat as falsy and hide the badge on every event.
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		if ( is_numeric( $value ) ) {
			// Both sides must be cast to the same type before a strict
			// comparison — 0 !== (float) $value would compare int against
			// float and always be true regardless of value, since the types
			// alone would differ.
			return 0.0 !== (float) $value;
		}

		$normalized = strtolower( trim( (string) $value ) );

		return in_array( $normalized, array( '1', 'true', 'yes', 'y', 'on' ), true );
	}

	/**
	 * ACF date picker fields are usually stored as Ymd (e.g. 20260815).
	 * Accept that plus a handful of other common formats gracefully.
	 */
	public static function parse_date_to_timestamp( $raw ) {
		if ( empty( $raw ) ) {
			return 0;
		}
		if ( is_numeric( $raw ) && strlen( (string) $raw ) === 8 ) {
			$ts = DateTime::createFromFormat( 'Ymd', (string) $raw );
			if ( $ts ) {
				return $ts->getTimestamp();
			}
		}
		$ts = strtotime( (string) $raw );
		return $ts ? $ts : 0;
	}

	/**
	 * Get the configured choices for the event_category ACF select field so
	 * the filter dropdown always matches whatever the site admin has set up.
	 *
	 * @return array label => value pairs (value => label really, keyed by value)
	 */
	public static function get_event_category_choices() {
		$field = self::find_event_category_field();

		if ( $field && ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
			return $field['choices'];
		}

		// Last resort: no ACF choices could be read (e.g. a field group saved
		// in a way acf_get_field_groups() doesn't surface it), but events
		// already exist with a category value saved on them. Build the list
		// from whatever distinct values are actually in use so the dropdown
		// still works, even though it won't show unused choices.
		return self::get_event_category_values_in_use();
	}

	/**
	 * Locate the event_category field definition by reading the field GROUPS
	 * attached to the "event" post type directly. This is deliberately not
	 * get_field_object()-on-a-sample-post (fails when zero events exist yet)
	 * nor acf_get_field('event_category') (that call expects a field KEY like
	 * "field_abc123", not a field NAME — passing a name is unreliable and
	 * commonly just returns false). Reading the field groups works regardless
	 * of how many posts exist.
	 *
	 * @return array|null
	 */
	protected static function find_event_category_field() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return null;
		}

		$field_groups = acf_get_field_groups( array( 'post_type' => VEC_POST_TYPE ) );

		if ( empty( $field_groups ) ) {
			return null;
		}

		foreach ( $field_groups as $group ) {
			$fields = acf_get_fields( $group );
			if ( empty( $fields ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				if ( isset( $field['name'] ) && 'event_category' === $field['name'] ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * Distinct event_category values actually saved on published events,
	 * used only as a fallback when the ACF field's configured choices
	 * couldn't be read at all.
	 *
	 * @return array value => value (no separate label available here)
	 */
	protected static function get_event_category_values_in_use() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return array();
		}

		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_value != ''
				ORDER BY pm.meta_value ASC",
				'event_category',
				VEC_POST_TYPE
			)
		);

		if ( empty( $values ) ) {
			return array();
		}

		return array_combine( $values, $values );
	}
}
