<?php
/**
 * Plugin Name:       Venue Event Calendar
 * Plugin URI:        https://example.com/
 * Description:       Displays a filterable, mobile-responsive grid / list / calendar of events (grid, list or calendar view) built on the "event" ACF custom post type. Use the [venue_events] shortcode to place it anywhere.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Venue
 * Text Domain:       venue-event-calendar
 * Domain Path:       /languages
 *
 * Requires the Advanced Custom Fields plugin and a custom post type with
 * post key "event" that has the following ACF fields on it:
 *
 *   artist_name        (Text)
 *   tour_name           (Text)
 *   event_date          (Date Picker)
 *   artist_image        (Image)
 *   link_to_tickets     (URL / Link)
 *   upgrade_available   (True / False)
 *   event_category      (Select, with choices)
 *   event_venue          (Text)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'VEC_VERSION', '6.3.0' );
define( 'VEC_PLUGIN_FILE', __FILE__ );
define( 'VEC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VEC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VEC_POST_TYPE', 'event' );
// Number of cards the slider view shows at once. Shared by the shortcode's
// initial server render and the AJAX handler that powers the arrow clicks,
// so they can never disagree on the window size.
define( 'VEC_SLIDER_WINDOW', 3 );

/**
 * Core includes.
 */
require_once VEC_PLUGIN_DIR . 'includes/class-vec-icons.php';
require_once VEC_PLUGIN_DIR . 'includes/class-vec-fields.php';
require_once VEC_PLUGIN_DIR . 'includes/class-vec-query.php';
require_once VEC_PLUGIN_DIR . 'includes/class-vec-render.php';
require_once VEC_PLUGIN_DIR . 'includes/class-vec-shortcode.php';
require_once VEC_PLUGIN_DIR . 'includes/class-vec-ajax.php';

/**
 * Bootstrap the plugin once all plugins are loaded so ACF is available.
 */
function vec_init_plugin() {
	if ( ! class_exists( 'ACF' ) ) {
		add_action( 'admin_notices', 'vec_missing_acf_notice' );
	}

	VEC_Shortcode::init();
	VEC_Ajax::init();
}
add_action( 'plugins_loaded', 'vec_init_plugin' );

/**
 * Register a widget area for the ad space shown in the "View as" bar
 * (between the view switcher and the Event Category dropdown). This gives
 * site editors a normal WordPress admin screen — Appearance > Widgets — to
 * drop in a Custom HTML block (an ad image, a script tag, whatever) without
 * touching the shortcode or any code. If nothing is added to this widget
 * area, the ad slot simply doesn't render.
 *
 * Note this is a single, site-wide ad zone: every [venue_events] instance
 * on the page shows the same content from this widget area. If you need a
 * different ad per shortcode instance, use the ad_image / ad_link / ad_alt
 * shortcode attributes instead — those still work and take over automatically
 * whenever this widget area is empty.
 */
function vec_register_ad_widget_area() {
	register_sidebar(
		array(
			'name'          => __( 'Venue Events – Ad Space', 'venue-event-calendar' ),
			'id'            => 'vec-ad-slot',
			'description'   => __( 'Appears in the "View as" bar of the [venue_events] shortcode, between the view switcher and the Event Category dropdown. Add a Custom HTML block to place an ad image, script, or any other code here.', 'venue-event-calendar' ),
			'before_widget' => '<div class="vec-ad-slot-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<span class="vec-screen-reader-text">',
			'after_title'   => '</span>',
		)
	);
}
add_action( 'widgets_init', 'vec_register_ad_widget_area' );

/**
 * Admin notice if ACF isn't active.
 */
function vec_missing_acf_notice() {
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Venue Event Calendar works best with Advanced Custom Fields active and the "event" custom post type / fields configured. Some fields may not display correctly until ACF is active.', 'venue-event-calendar' );
	echo '</p></div>';
}

/**
 * Enqueue front-end assets only when the shortcode is actually used on the page.
 * VEC_Shortcode marks a flag when it renders so we avoid loading assets everywhere.
 */
function vec_enqueue_assets() {
	wp_register_style(
		'vec-font-dm-sans',
		'https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&display=swap',
		array(),
		null
	);
	wp_register_style( 'vec-style', VEC_PLUGIN_URL . 'assets/css/vec-style.css', array( 'vec-font-dm-sans' ), VEC_VERSION );
	wp_register_script( 'vec-script', VEC_PLUGIN_URL . 'assets/js/vec-script.js', array(), VEC_VERSION, true );

	wp_localize_script(
		'vec-script',
		'VEC_Settings',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'vec_nonce' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vec_enqueue_assets' );

/**
 * Actually enqueue (assets were only registered above) — called from the shortcode
 * so the CSS/JS only ship on pages that use it.
 */
function vec_load_assets() {
	wp_enqueue_style( 'vec-font-dm-sans' );
	wp_enqueue_style( 'vec-style' );
	wp_enqueue_script( 'vec-script' );
}
