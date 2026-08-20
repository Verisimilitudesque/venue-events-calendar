=== Venue Event Calendar ===
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

A filterable, mobile-responsive event listing (grid / list / calendar views)
built on top of the "event" ACF custom post type.

== Requirements ==

* Advanced Custom Fields (free or Pro) active.
* A custom post type with post key "event" (already created).
* The following ACF fields attached to that post type:

  Field label          Field name (key)     Field type
  --------------------------------------------------------------
  Artist Name           artist_name           Text
  Tour Name              tour_name              Text
  Event Date             event_date             Date Picker
  Artist Image           artist_image           Image
  Link to Tickets        link_to_tickets         URL or Link
  Upgrade Available      upgrade_available       True / False
  Event Category         event_category          Select (with your choices configured — the choices
                                                    you set in ACF automatically populate the filter dropdown)
  Event Venue             event_venue             Text
  Event Time              event_time              Time Picker (optional — shown next to the artist/tour
                                                    name on calendar-view event chips when set)

The field NAMES above must match exactly (they're what the plugin queries
against). Field labels can be anything you like.

== Installation ==

1. Zip contents already match the required plugin folder structure —
   upload "venue-event-calendar" via Plugins > Add New > Upload Plugin,
   or drop the folder into wp-content/plugins/ over FTP/SFTP.
2. Activate "Venue Event Calendar" under Plugins.
3. Make sure ACF and your "event" post type / fields are in place (see above).
4. Drop the shortcode into any page or post:

     [venue_events]

== Shortcode usage ==

Basic — full filter bar, view switcher, grid view, 9 events per page:

    [venue_events]

Hide the filter bar (date/search/category controls) — useful for embedding
a plain events list on an individual page:

    [venue_events show_filters="no"]

Hide just the grid/list/calendar view switcher, keep the filter bar:

    [venue_events show_view_switcher="no"]

Lock the block to a single category (also hides the category dropdown
automatically) — handy for an artist- or genre-specific landing page:

    [venue_events category="rock" show_filters="no"]

Start in list, calendar, or carousel view by default:

    [venue_events default_view="list"]
    [venue_events default_view="calendar"]
    [venue_events default_view="carousel"]

Carousel is a full-width, auto-scrolling strip of compact cards — a
full-width photo, then the date/artist/tour underneath — continuously
sliding right to left. There are no Buy Tickets / More Info buttons here;
the whole card links to the event instead. It pauses whenever the
visitor's mouse is over it, and hovering an individual card shows a 1px
accent-green border rather than a background/text color change, so the
strip doesn't visually jump around while someone's trying to read or click
a card. It always pulls posts_per_page events and never shows pagination
controls — like the calendar's own month nav, there's nothing to page
through.

Change how many events show per page (grid/list views) and whether past
events are hidden:

    [venue_events posts_per_page="12" hide_past="no"]

Hide the Previous Events / Next Events controls — useful for a small,
fixed-size preview list (e.g. "next 5 events" on a homepage) where you don't
want visitors paging through the rest. This only hides the controls; it
doesn't change how many events posts_per_page pulls, so pair the two:

    [venue_events posts_per_page="5" show_pagination="no"]

(show_pagination only affects the grid and list views — the calendar view
never shows Previous/Next Events controls since it has its own month
navigation, and neither does the carousel view, since it isn't paged.)

There are two ways to fill the ad slot that appears in the "View as" bar,
between the view switcher and the Event Category dropdown:

1. Appearance > Widgets (recommended) — add a "Custom HTML" block to the
   "Venue Events – Ad Space" widget area. This is a normal WordPress admin
   screen, so anyone who can edit widgets can drop in an ad image, a script
   tag, or any other code without touching the shortcode. It's a single,
   site-wide ad zone — every [venue_events] instance on the page shows the
   same widget content.

2. Shortcode attributes — if you need a different ad per shortcode instance,
   or just want something simpler than the widget screen:

       [venue_events ad_image="https://yoursite.com/wp-content/uploads/banner.jpg"
                     ad_link="https://example.com/promo"
                     ad_alt="Season tickets on sale now"]

   ad_link and ad_alt are optional — with no ad_link the image simply
   displays without being clickable.

If both are set up, the widget area wins and the shortcode attributes are
ignored for that instance. The ad slot only renders at all when one or the
other is present — and only when show_ad isn't set to "no" (see below).

To turn the ad slot off entirely for one shortcode instance — even if the
widget area has content — set show_ad="no":

    [venue_events show_ad="no"]

All attributes (all optional):

    show_filters        yes | no     (default: yes)
    show_view_switcher  yes | no     (default: yes)
    default_view         grid | list | calendar | carousel   (default: grid)
    category              any value from your event_category field choices (default: none / all)
    posts_per_page        number (default: 9)
    hide_past              yes | no     (default: yes — hides events whose date has passed)
    show_pagination        yes | no     (default: yes — hides Previous/Next Events controls when set to no)
    show_ad                 yes | no     (default: yes — set to no to force-hide the ad slot for this instance)
    ad_image               URL of a banner image shown in the View as bar (default: none)
    ad_link                 URL the ad banner links to (default: none)
    ad_alt                   alt text for the ad banner (default: empty)

You can place the shortcode multiple times on the same page (e.g. one full
instance with filters, plus a category-locked instance elsewhere) — each
instance works independently.

== Notes ==

* Filtering, paging, view switching and calendar month navigation all
  happen over AJAX — no page reloads.
* No external JS/CSS libraries are loaded; the calendar and icons are
  built with plain CSS/JS, so there are no third-party dependencies to
  keep updated.
* The Dates filter opens a popover with a from/to range calendar and
  Reset / Cancel / Apply buttons. Nothing is filtered until Apply is
  pressed; Cancel discards an in-progress selection and Reset clears the
  range. Clicking outside the popover or pressing Escape closes it.
* Typography uses DM Sans, loaded from Google Fonts and enqueued only on
  pages where the shortcode appears. If your theme already loads DM Sans,
  or you'd rather self-host it, you can dequeue the "vec-font-dm-sans"
  handle and the widget will fall back to the system sans stack.
* Colors are set as CSS custom properties at the top of
  assets/css/vec-style.css under the `.vec-events` selector — edit those
  values to re-theme the whole widget in one place:

    --vec-green       #88bb00  buttons, badges, pagination
    --vec-icon-green  #8cc63f  filter icons, category underline + arrow
    --vec-text-muted  #7a7c79  placeholder / secondary field text
    --vec-bg          #151914  widget background
    --vec-badge-bg    #fff98f  "Upgrade Available" badge
