<?php
/**
 * Inline SVG icon markup for the view switcher (grid / list / calendar).
 * Recreated from the venue's supplied icon set as scalable currentColor SVGs
 * — no image requests, and the color follows the button's CSS (green by
 * default, whatever the active/hover state wants otherwise).
 *
 * These strings are static and authored by us (not user input), so they're
 * safe to echo directly without escaping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEC_Icons {

	/**
	 * @param string $name grid | list | calendar | carousel | chevron
	 * @return string Raw <svg>…</svg> markup, or '' for an unknown name.
	 */
	public static function get( $name ) {
		switch ( $name ) {
			case 'grid':
				return self::grid();
			case 'list':
				return self::list_icon();
			case 'calendar':
				return self::calendar();
			case 'carousel':
				return self::carousel();
			case 'chevron':
				return self::chevron();
			default:
				return '';
		}
	}

	/**
	 * A single rounded-square outline divided into 4 window panes by a plus
	 * of straight lines — an outline glyph to match the weight of the list
	 * and calendar icons, rather than 4 solid filled blocks.
	 */
	protected static function grid() {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<rect x="2" y="2" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2.2"/>'
			. '<line x1="12" y1="2" x2="12" y2="22" stroke="currentColor" stroke-width="2.2"/>'
			. '<line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="2.2"/>'
			. '</svg>';
	}

	/**
	 * Three rows of a small leading dot + a wide rounded bar.
	 */
	protected static function list_icon() {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<circle cx="2.5" cy="4" r="2.5" fill="currentColor"/>'
			. '<rect x="8" y="2" width="16" height="4" rx="2" fill="currentColor"/>'
			. '<circle cx="2.5" cy="12" r="2.5" fill="currentColor"/>'
			. '<rect x="8" y="10" width="16" height="4" rx="2" fill="currentColor"/>'
			. '<circle cx="2.5" cy="20" r="2.5" fill="currentColor"/>'
			. '<rect x="8" y="18" width="16" height="4" rx="2" fill="currentColor"/>'
			. '</svg>';
	}

	/**
	 * A rounded frame with two binder tabs, a header bar, and a 4x3 grid of
	 * date dots — the classic "calendar" glyph from the supplied icon.
	 */
	protected static function calendar() {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<rect x="6" y="0.5" width="2.4" height="5" rx="1.2" fill="currentColor"/>'
			. '<rect x="15.6" y="0.5" width="2.4" height="5" rx="1.2" fill="currentColor"/>'
			. '<rect x="1" y="3" width="22" height="20" rx="3" stroke="currentColor" stroke-width="2"/>'
			. '<rect x="1" y="7.5" width="22" height="3" fill="currentColor"/>'
			. '<circle cx="5.5" cy="13.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="10.5" cy="13.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="15.5" cy="13.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="20.5" cy="13.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="5.5" cy="17.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="10.5" cy="17.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="15.5" cy="17.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="20.5" cy="17.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="5.5" cy="21.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="10.5" cy="21.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="15.5" cy="21.5" r="1.4" fill="currentColor"/>'
			. '<circle cx="20.5" cy="21.5" r="1.4" fill="currentColor"/>'
			. '</svg>';
	}

	/**
	 * A main card flanked by two partial/peeking cards — the standard
	 * "carousel/slider" glyph, matching the stroke+fill mix the other icons
	 * already use.
	 */
	protected static function carousel() {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<rect x="0.5" y="6" width="4" height="12" rx="1.5" fill="currentColor" opacity="0.45"/>'
			. '<rect x="19.5" y="6" width="4" height="12" rx="1.5" fill="currentColor" opacity="0.45"/>'
			. '<rect x="6.5" y="2" width="11" height="20" rx="2.5" stroke="currentColor" stroke-width="2.2"/>'
			. '</svg>';
	}

	/**
	 * A simple left-pointing chevron for the slider's Previous/Next arrow
	 * buttons. The Next button reuses this same icon, mirrored with CSS
	 * (transform: scaleX(-1)) rather than drawing a second glyph.
	 */
	protected static function chevron() {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<path d="M15 4L7 12L15 20" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
			. '</svg>';
	}
}
