<?php
/**
 * Plugin Name: UplinkSync — Booking CTAs + on-page intake form & inline cal.com embed
 * Description: The site's single booking surface. A brand-styled intake form (intent, name, email, and the scheduling questions the event type requires) opens in an on-page dialog; on submit it mounts an INLINE cal.com embed in the same panel, prefilled, so the visitor only picks a time and confirms. Booking never navigates away from uplinksync.com. All booking targets are the company namespace `team/uplinksync/*`; the personal `dirwin/*` profile is no longer a booking target anywhere on the site. The hero/drone/contact markup is produced by the Hostinger AI theme + saved block content in the WP DB (not tracked files), so this mu-plugin rewrites the rendered document on the way out.
 *
 * v3.0.0 (2026-08-08, owner decision + Booking #2 / Booking #3):
 *   1. NAMESPACE. Every booking target moves from the owner's PERSONAL profile
 *      (`dirwin/it-consult`, `dirwin/uav-service`) to the company team profile
 *      `team/uplinksync/*`. UAV now points at the real consultation event type
 *      `team/uplinksync/uav-consult` (cal.com EventType 9) rather than the
 *      hidden legacy `uav-service` bypass (EventType 7, hidden 2026-08-03).
 *   2. NO CONTEXT SWITCH. The chooser modal ("which path?" -> cal.com pop-up)
 *      is replaced by a custom intake FORM that syncs its answers into an
 *      INLINE cal.com embed rendered in the same dialog. Nothing navigates to
 *      book.uplinksync.com.
 *   3. ORPHANS. Any anchor anywhere in the rendered document that points at
 *      book.uplinksync.com (e.g. the "Book a Consultation" button in the
 *      /contact/ CTA band, which lives in the WP database, not in this repo) is
 *      rewritten into a trigger for the same dialog, and its no-JS href is
 *      normalised onto the team namespace.
 *
 * Accessibility: the dialog is navy `#173258` with `#FFFFFF` text (12.86:1),
 * `#C9D8EE` helper text (8.90:1), `#1F4375` fields with white text (9.92:1) and
 * `#A8C4EA` borders (5.55:1 on the field, 7.19:1 on the panel). The house accent
 * `#5697F3` is NEVER used for text or for a meaning-bearing edge against a light
 * surface — it fails there (2.80:1 on `#F7F9FB`, 2.95:1 on `#FFFFFF`). It appears
 * only as the outer half of a two-tone focus ring whose inner half is the navy
 * panel colour, so the indicator is 4.36:1 against navy and the navy separator is
 * 12.86:1 against white — both above the 3:1 non-text floor in both directions.
 *
 * Version: 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical cal.com instance (live, verified serving book.uplinksync.com).
 */
const UPLINKSYNC_BOOK_ORIGIN = 'https://book.uplinksync.com';

/**
 * CANONICAL BOOKING NAMESPACE — the company team profile.
 *
 * Owner decision (2026-08-08): "Booking through the website should be through
 * team/uplinksync/*". The personal `dirwin/*` profile must not be a booking
 * target on the public site. Both team event types are live and verified
 * serving slots (cal.com Team 1 "UplinkSync LLC").
 *
 *   team/uplinksync/it-consult   EventType 6  — IT / Web consultation, 30m
 *   team/uplinksync/uav-consult  EventType 9  — UAV / drone consultation, 30m
 *
 * `team/uplinksync/uav-service` (EventType 7) is deliberately NOT used: it was
 * hidden on 2026-08-03 as a scheduling bypass. Drone *work* (uav-weekend /
 * uav-weekday, with their 3-day / 3-week lead times) is booked by the owner
 * after the consultation, not from the public site.
 */
const UPLINKSYNC_BOOK_CONSULT_SLUG = 'team/uplinksync/it-consult';
const UPLINKSYNC_BOOK_UAV_SLUG     = 'team/uplinksync/uav-consult';
const UPLINKSYNC_BOOK_UAV_ENABLED  = true;

function uplinksync_book_url( $slug ) {
	return UPLINKSYNC_BOOK_ORIGIN . '/' . ltrim( $slug, '/' );
}

/**
 * Front-end GET only. Never touch admin, REST, AJAX, JSON, feeds or embeds, so
 * the block editor and store management keep rendering their own markup.
 */
function uplinksync_book_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_embed() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

function uplinksync_book_start_buffer() {
	if ( ! uplinksync_book_should_filter() ) {
		return;
	}
	// Priority 2: opens after the contact/social (0) and homepage-nav (1) buffers.
	// Output buffers unwind LIFO, so this one closes first and its injected CTAs
	// are still visible to the outer passes (which touch disjoint markup).
	ob_start( 'uplinksync_book_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_book_start_buffer', 2 );

/**
 * The "Book a consultation" button. It is a BUTTON, not a link: booking happens
 * in an on-page dialog, so there is no destination to navigate to. With JS off
 * the dialog's own two path links remain real booking URLs (see the dialog
 * markup), which is the progressive-enhancement floor.
 */
function uplinksync_book_consult_markup() {
	return '<div class="wp-block-button uplinksync-book-cta uplinksync-book-consult">'
		. '<button type="button" class="wp-block-button__link wp-element-button uls-book-link" '
		. 'data-uls-book-open="uls-book-modal" data-uls-book-intent="it" '
		. 'aria-haspopup="dialog" aria-controls="uls-book-modal">Book a consultation</button></div>';
}

/**
 * The UAV/consultation block for the homepage Air/drone surface — the dialog
 * trigger. The dialog itself is a single page-global element injected once by
 * uplinksync_book_inject_runtime(), so it is never duplicated per surface.
 * `uplinksync-book-uav` is kept for injector idempotency.
 *
 * Direct UAV *service* booking is still not publicly linked (owner directive);
 * the UAV path leads to the UAV consultation event type.
 */
function uplinksync_book_uav_markup() {
	return '<div class="uplinksync-book-cta uplinksync-book-uav uls-consult-block" data-uls-reveal data-uls-reveal-delay="0.16">'
		. '<div class="wp-block-button uls-consult-trigger-wrap">'
		. '<button type="button" '
		. 'class="wp-block-button__link wp-element-button uls-consult-trigger" '
		. 'data-uls-book-open="uls-book-modal" aria-haspopup="dialog" aria-controls="uls-book-modal">'
		. 'Book a free consultation</button></div>'
		. '</div>';
}

function uplinksync_book_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$html = uplinksync_book_inject_consult( $html );
	if ( UPLINKSYNC_BOOK_UAV_ENABLED ) {
		$html = uplinksync_book_inject_uav( $html );
	}
	// Runs BEFORE the runtime injection so orphan anchors it converts still count
	// as "a booking CTA is on this page" and pull the dialog + runtime in.
	$html = uplinksync_book_adopt_orphan_links( $html );
	$html = uplinksync_book_inject_runtime( $html );

	return $html;
}

/**
 * True on any WooCommerce commerce surface — the shop archive, single products,
 * product category/tag archives, cart, checkout and account. An IT/Web
 * "Book a consultation" CTA has no place on a stock-image licensing page, so the
 * consult injector is suppressed here: the /shop/ licensing block's own
 * "Get in touch" -> /contact/ button was being matched by the fallback below and
 * getting an IT-consult button injected beside it.
 */
function uplinksync_book_is_commerce_surface() {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		return true;
	}
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return true;
	}
	if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
		return true;
	}
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return true;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return true;
	}
	return false;
}

/**
 * Inject "Book a consultation" as a sibling button immediately after the
 * top-of-funnel "Talk to a specialist" CTA (which links to /contact/, the quote
 * path). Booking augments the quote CTA; the original anchor is untouched.
 * Idempotent — skip if the CTA is already present.
 */
function uplinksync_book_inject_consult( $html ) {
	if ( false !== strpos( $html, 'uplinksync-book-consult' ) ) {
		return $html;
	}
	// Never inject the IT/Web consult CTA on a commerce surface. The stock-image
	// storefront is a licensing funnel, not an IT-services funnel; its own
	// "Get in touch" -> /contact/ button must not sprout a consultation CTA.
	if ( uplinksync_book_is_commerce_surface() ) {
		return $html;
	}
	// Anchor on the "Talk to a specialist" button's own closing </a></div>, and
	// insert our button div right after that wrapping .wp-block-button </div>.
	// Non-greedy so we match this specific anchor's closing tags only.
	$pattern = '#(>Talk to a specialist</a>\s*</div>)#';
	$injected = preg_replace(
		$pattern,
		'$1' . uplinksync_book_consult_markup(),
		$html,
		1,
		$count
	);
	if ( null !== $injected && $count > 0 ) {
		return $injected;
	}

	// Fallback for the /contact/ page only: place the consultation CTA next to the
	// first /contact/ (quote) link's button. Scoped to is_page('contact') so it
	// can never latch onto an incidental /contact/ link on some other page.
	if ( ! ( function_exists( 'is_page' ) && is_page( 'contact' ) ) ) {
		return $html;
	}
	$pattern2 = '#(href="https://uplinksync\.com/contact/"[^>]*>[^<]*</a>\s*</div>)#';
	$injected2 = preg_replace(
		$pattern2,
		'$1' . uplinksync_book_consult_markup(),
		$html,
		1,
		$count2
	);
	if ( null !== $injected2 && $count2 > 0 ) {
		return $injected2;
	}
	return $html;
}

/**
 * Inject the UAV consultation trigger on the Air/drone surface, right after the
 * UAV capability pills. Idempotent.
 */
function uplinksync_book_inject_uav( $html ) {
	if ( false !== strpos( $html, 'uplinksync-book-uav' ) ) {
		return $html;
	}
	// Anchor immediately after the UAV capability pills group (…"Progress records"),
	// which is where the block sits today. Non-greedy, first match only.
	$pattern = '#(Progress records</p>\s*</div>)#';
	$injected = preg_replace(
		$pattern,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count
	);
	if ( null !== $injected && $count > 0 ) {
		return $injected;
	}
	// Fallback: after the "Scoped UAV work…" intro paragraph.
	$pattern_intro = '#(Scoped UAV work[^<]*</p>)#';
	$injected_intro = preg_replace(
		$pattern_intro,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count_intro
	);
	if ( null !== $injected_intro && $count_intro > 0 ) {
		return $injected_intro;
	}

	// Fallback: after the "Aerial Photography & Video" heading block's paragraph.
	$pattern2 = '#(Aerial Photography\s*&amp;\s*Video</h3>\s*<p[^>]*>[^<]*</p>)#';
	$injected2 = preg_replace(
		$pattern2,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count2
	);
	if ( null !== $injected2 && $count2 > 0 ) {
		return $injected2;
	}
	return $html;
}

/**
 * ORPHAN ADOPTION — the reason a "one namespace" sweep cannot be done in this
 * repo alone.
 *
 * Some booking CTAs are not produced by this plugin: the "Book a Consultation"
 * button in the /contact/ CTA band ("Rather book a time than fill in a form?")
 * is saved BLOCK CONTENT in the WordPress database. Left alone it is a plain
 * anchor that sends the visitor to book.uplinksync.com — exactly the context
 * switch the owner ruled out — and historically it has drifted onto the wrong
 * namespace.
 *
 * So: rewrite every anchor in the rendered document whose href points at the
 * cal.com origin into a trigger for the on-page dialog, and normalise the href
 * it keeps (the no-JS fallback) onto the team namespace. The dialog's own path
 * links are skipped — they are already ours and already correct.
 *
 * @param string $html Rendered document.
 * @return string
 */
function uplinksync_book_adopt_orphan_links( $html ) {
	if ( false === stripos( $html, UPLINKSYNC_BOOK_ORIGIN ) ) {
		return $html;
	}

	$origin_re = preg_quote( UPLINKSYNC_BOOK_ORIGIN, '#' );

	return preg_replace_callback(
		'#<a\b([^>]*?)href="(' . $origin_re . '/[^"]*)"([^>]*)>#i',
		function ( $m ) {
			$before = $m[1];
			$href   = $m[2];
			$after  = $m[3];
			$attrs  = $before . $after;

			// Already one of ours (dialog path link or an adopted trigger): leave it.
			if ( false !== stripos( $attrs, 'data-uls-book' ) || false !== stripos( $attrs, 'uls-consult-path' ) ) {
				return $m[0];
			}

			$path   = (string) wp_parse_url( $href, PHP_URL_PATH );
			$intent = ( false !== stripos( $path, 'uav' ) || false !== stripos( $path, 'drone' ) ) ? 'uav' : 'it';
			$slug   = ( 'uav' === $intent ) ? UPLINKSYNC_BOOK_UAV_SLUG : UPLINKSYNC_BOOK_CONSULT_SLUG;

			// Normalise the no-JS fallback href onto the canonical team namespace,
			// preserving any query string the block author added.
			$query    = (string) wp_parse_url( $href, PHP_URL_QUERY );
			$new_href = uplinksync_book_url( $slug ) . ( '' !== $query ? '?' . $query : '' );

			return '<a' . $before . 'href="' . esc_url( $new_href ) . '"' . $after
				. ' data-uls-book-open="uls-book-modal" data-uls-book-intent="' . esc_attr( $intent ) . '"'
				. ' aria-haspopup="dialog" aria-controls="uls-book-modal">';
		},
		$html
	);
}

/**
 * Options for the two intake forms, mirrored from the live cal.com bookingFields
 * of EventType 6 (it-consult) and EventType 9 (uav-consult). VALUES must match
 * cal.com exactly or the prefill silently drops the answer.
 */
function uplinksync_book_field_options() {
	return array(
		'goal' => array(
			'new-website' => 'New website',
			'helpdesk'    => 'Systems / help-desk support',
			'security'    => 'Security',
			'cloud-m365'  => 'Cloud / M365',
			'networking'  => 'Networking',
			'other'       => 'Other',
		),
		'site-type' => array(
			'residential-roof'      => 'Residential / roof',
			'commercial-building'   => 'Commercial building',
			'land-acreage'          => 'Land / acreage (mapping)',
			'construction-progress' => 'Construction / progress',
			'event'                 => 'Event',
			'other'                 => 'Other',
		),
		'deliverables' => array(
			'photos'            => 'Photos',
			'video'             => 'Video',
			'orthomosaic'       => 'Orthomosaic / mapping',
			'inspection-report' => 'Inspection report',
		),
	);
}

function uplinksync_book_select_options( $map, $placeholder ) {
	$out = '<option value="">' . esc_html( $placeholder ) . '</option>';
	foreach ( $map as $value => $label ) {
		$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
	}
	return $out;
}

/**
 * The booking dialog: a custom intake form that becomes an inline cal.com embed.
 *
 * Screen 1 "details" — intent, name, email, and the questions the chosen event
 * type marks required in cal.com. Collecting them here (rather than leaving them
 * on the cal.com step) is the whole point: they are pushed into the embed as
 * prefill, so the scheduling step is a calendar the visitor only has to pick a
 * time on, not a second form.
 *
 * Screen 2 "schedule" — the inline embed, in the same panel, plus a "change
 * details" affordance back to screen 1.
 *
 * Screen 3 "done" — an on-page confirmation driven by the embed's
 * `bookingSuccessful` event, so the visitor gets immediate feedback without the
 * page ever changing.
 *
 * No-JS floor: the <noscript> block carries the two real booking URLs on the
 * team namespace.
 */
function uplinksync_book_modal_markup() {
	$opts    = uplinksync_book_field_options();
	$it_url  = esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_CONSULT_SLUG ) );
	$uav_url = esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_UAV_SLUG ) );

	$deliverables = '';
	foreach ( $opts['deliverables'] as $value => $label ) {
		$deliverables .= '<label class="uls-bk-check"><input type="checkbox" name="uls-bk-deliverables" '
			. 'value="' . esc_attr( $value ) . '"><span>' . esc_html( $label ) . '</span></label>';
	}

	$html = '<div class="uls-book-modal" id="uls-book-modal" role="dialog" aria-modal="true" '
		. 'aria-labelledby="uls-book-modal-title" hidden>'
		. '<div class="uls-book-modal__overlay" data-uls-book-close="1"></div>'
		. '<div class="uls-book-modal__panel" role="document" tabindex="-1">'
		. '<button type="button" class="uls-book-modal__close" data-uls-book-close="1" aria-label="Close booking">&#215;</button>';

	// ---- Screen 1: details -------------------------------------------------
	$html .= '<div class="uls-bk-screen" data-uls-bk-screen="details">'
		. '<h2 class="uls-book-modal__title" id="uls-book-modal-title">Book a free consultation</h2>'
		. '<p class="uls-book-modal__desc">Two quick things and we&#8217;ll show you our calendar — you stay right here on the site.</p>'
		. '<form class="uls-bk-form" id="uls-bk-form" novalidate>'

		// Intent.
		. '<fieldset class="uls-bk-fs">'
		. '<legend class="uls-bk-legend">What can we help with?</legend>'
		. '<div class="uls-bk-intents">'
		. '<label class="uls-bk-intent"><input type="radio" name="uls-bk-intent" value="it" id="uls-bk-intent-it">'
		. '<span class="uls-bk-intent__box"><span class="uls-bk-intent__t">IT / Web services</span>'
		. '<span class="uls-bk-intent__s">Websites, systems &amp; help-desk, security, cloud / M365, networking.</span></span></label>'
		. '<label class="uls-bk-intent"><input type="radio" name="uls-bk-intent" value="uav" id="uls-bk-intent-uav">'
		. '<span class="uls-bk-intent__box"><span class="uls-bk-intent__t">UAV / drone project</span>'
		. '<span class="uls-bk-intent__s">Listing photo &amp; video, events, progress records &mdash; inspection and mapping also available.</span></span></label>'
		. '</div></fieldset>'

		// Everything below is revealed once an intent is chosen (minimum steps first).
		. '<div class="uls-bk-rest" id="uls-bk-rest" hidden>'
		. '<div class="uls-bk-row">'
		. '<div class="uls-bk-field"><label for="uls-bk-name">Your name</label>'
		. '<input type="text" id="uls-bk-name" name="name" autocomplete="name" required></div>'
		. '<div class="uls-bk-field"><label for="uls-bk-email">Email</label>'
		. '<input type="email" id="uls-bk-email" name="email" autocomplete="email" required></div>'
		. '</div>'

		// IT-only questions (cal.com EventType 6 required fields).
		. '<div class="uls-bk-group" data-uls-bk-group="it" hidden>'
		. '<div class="uls-bk-field"><label for="uls-bk-goal">Main goal</label>'
		. '<select id="uls-bk-goal" name="goal">' . uplinksync_book_select_options( $opts['goal'], 'Select the main goal' ) . '</select></div>'
		. '<div class="uls-bk-field"><label for="uls-bk-setup">Current setup &amp; main pain points</label>'
		. '<textarea id="uls-bk-setup" name="current-setup" rows="3" placeholder="What you have today, and what isn&#8217;t working"></textarea></div>'
		. '</div>'

		// UAV-only questions (cal.com EventType 9 required fields).
		. '<div class="uls-bk-group" data-uls-bk-group="uav" hidden>'
		. '<div class="uls-bk-field"><label for="uls-bk-site">Project location / site address</label>'
		. '<input type="text" id="uls-bk-site" name="site-location" placeholder="Street address or coordinates of the site"></div>'
		. '<div class="uls-bk-field"><label for="uls-bk-sitetype">Site type &amp; size</label>'
		. '<select id="uls-bk-sitetype" name="site-type">' . uplinksync_book_select_options( $opts['site-type'], 'Select the site type' ) . '</select></div>'
		. '<fieldset class="uls-bk-fs uls-bk-fs--tight"><legend class="uls-bk-legend">Deliverables needed</legend>'
		. '<div class="uls-bk-checks" id="uls-bk-deliverables">' . $deliverables . '</div></fieldset>'
		. '</div>'

		. '<div class="uls-bk-field"><label for="uls-bk-notes">Anything else? <span class="uls-bk-opt">optional</span></label>'
		. '<textarea id="uls-bk-notes" name="notes-extra" rows="2"></textarea></div>'

		. '<p class="uls-bk-error" id="uls-bk-error" role="alert" hidden></p>'
		. '<div class="uls-bk-actions">'
		. '<button type="submit" class="uls-bk-submit">Find a time</button>'
		. '<span class="uls-bk-hint">Free, no obligation. You&#8217;ll pick a slot on the next screen — without leaving this page.</span>'
		. '</div>'
		. '</div>'
		. '</form>'
		. '<noscript><p class="uls-bk-hint">JavaScript is off, so the calendar can&#8217;t load here. '
		. 'Book directly: <a href="' . $it_url . '">IT / Web consultation</a> or '
		. '<a href="' . $uav_url . '">UAV / drone consultation</a>.</p></noscript>'
		. '</div>';

	// ---- Screen 2: schedule ------------------------------------------------
	$html .= '<div class="uls-bk-screen" data-uls-bk-screen="schedule" hidden>'
		. '<div class="uls-bk-schedbar">'
		. '<button type="button" class="uls-bk-back" data-uls-bk-back="1">&#8592; Change details</button>'
		. '<p class="uls-bk-summary" id="uls-bk-summary"></p>'
		. '</div>'
		. '<div class="uls-bk-embed" id="uls-book-embed" aria-busy="true"></div>'
		. '<p class="uls-bk-status" id="uls-bk-status" role="status" aria-live="polite">Loading available times&#8230;</p>'
		. '</div>';

	// ---- Screen 3: confirmation -------------------------------------------
	$html .= '<div class="uls-bk-screen uls-bk-doneScreen" data-uls-bk-screen="done" hidden>'
		. '<p class="uls-bk-tick" aria-hidden="true">&#10003;</p>'
		. '<h2 class="uls-book-modal__title">You&#8217;re booked</h2>'
		. '<p class="uls-book-modal__desc" id="uls-bk-doneMsg">A confirmation is on its way to your inbox. '
		. 'We&#8217;ll be in touch before the call with anything we need.</p>'
		. '<div class="uls-bk-actions"><button type="button" class="uls-bk-submit" data-uls-book-close="1">Done</button></div>'
		. '</div>';

	$html .= '</div></div>';

	return $html;
}

/**
 * Dialog styling. Colour pairs used here, all measured (WCAG 2.x relative
 * luminance), so the settled tokens are honoured without shipping the failing
 * `#5697F3` / `#F7F9FB` combination (2.80:1):
 *
 *   #FFFFFF on #173258  12.86  panel + button text            AAA
 *   #C9D8EE on #173258   8.90  helper / secondary text        AAA
 *   #FFFFFF on #1F4375   9.92  form field text                AAA
 *   #C9D8EE on #1F4375   6.87  field placeholder              AAA
 *   #A8C4EA on #173258   7.19  field + card borders           >= 3 (non-text)
 *   #A8C4EA on #1F4375   5.55  border against field fill      >= 3 (non-text)
 *   #FFD9D0 on #173258   9.85  validation error text          AAA
 *   #173258 on #FFFFFF  12.86  primary button (navy on white) AAA
 *   #5697F3 on #173258   4.36  outer focus ring vs panel      >= 3 (non-text)
 *
 * The focus indicator is deliberately two-tone — `0 0 0 2px #173258, 0 0 0 5px
 * #5697F3` — because `#5697F3` alone against the white primary button is 2.95:1
 * and would fail 1.4.11. The navy inner ring is 12.86:1 against white and the
 * accent outer ring is 4.36:1 against navy, so the indicator clears 3:1 on both
 * of its edges whatever it sits on.
 */
function uplinksync_book_styles() {
	return '<style id="uplinksync-book-cta-css">'
		. '.uplinksync-book-cta .uls-book-link,.uplinksync-book-cta .uls-consult-trigger{'
		. 'display:inline-block;border-radius:999px;border:2px solid currentColor;'
		. 'background:transparent;padding:var(--wp--preset--spacing--30,12px) var(--wp--preset--spacing--70,32px);'
		. 'text-decoration:none;cursor:pointer;line-height:1.2;font-weight:600;font:inherit;color:inherit;}'
		. '.uplinksync-book-cta{display:inline-block;margin:8px 8px 8px 0;}'
		. '.uls-consult-block{margin:8px 0 0;}'

		// Dialog shell.
		. '.uls-book-modal[hidden]{display:none;}'
		. '.uls-book-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;}'
		. '.uls-book-modal__overlay{position:absolute;inset:0;background:rgba(6,15,32,0.72);}'
		. '.uls-book-modal__panel{position:relative;width:100%;max-width:720px;max-height:calc(100vh - 40px);overflow:auto;'
		. '-webkit-overflow-scrolling:touch;background:#173258;color:#FFFFFF;border:1px solid #A8C4EA;border-radius:12px;'
		. 'padding:28px 24px 24px;box-shadow:0 20px 60px rgba(0,0,0,0.45);}'
		. '.uls-book-modal__panel:focus{outline:none;}'
		. '.uls-book-modal__close{position:absolute;top:8px;right:8px;width:44px;height:44px;display:inline-flex;'
		. 'align-items:center;justify-content:center;background:transparent;border:0;color:#FFFFFF;font-size:26px;'
		. 'line-height:1;cursor:pointer;border-radius:8px;}'
		. '.uls-book-modal__title{margin:0 40px 8px 0;font-size:1.35rem;line-height:1.2;color:#FFFFFF;}'
		. '.uls-book-modal__desc{margin:0 0 18px;font-size:0.9375rem;color:#C9D8EE;}'
		. 'body.uls-book-modal-open{overflow:hidden;}'

		// One focus treatment for everything in the dialog: two-tone, so it keeps
		// >= 3:1 against both the navy panel and the white primary button.
		. '.uls-book-modal :focus-visible{outline:none;box-shadow:0 0 0 2px #173258,0 0 0 5px #5697F3;border-radius:8px;}'
		. '.uls-book-modal .uls-bk-intent input:focus-visible + .uls-bk-intent__box{'
		. 'box-shadow:0 0 0 2px #173258,0 0 0 5px #5697F3;}'

		// Intent cards.
		. '.uls-bk-fs{border:0;padding:0;margin:0 0 18px;min-width:0;}'
		. '.uls-bk-fs--tight{margin:0 0 4px;}'
		. '.uls-bk-legend{padding:0;margin:0 0 10px;font-size:0.9375rem;font-weight:600;color:#FFFFFF;}'
		. '.uls-bk-intents{display:flex;flex-wrap:wrap;gap:12px;}'
		. '.uls-bk-intent{flex:1 1 240px;display:block;margin:0;cursor:pointer;}'
		. '.uls-bk-intent input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}'
		. '.uls-bk-intent__box{display:block;height:100%;border:1px solid #A8C4EA;border-radius:8px;background:#1F4375;'
		. 'padding:14px 16px;transition:border-color .2s ease,background .2s ease;}'
		. '.uls-bk-intent input:checked + .uls-bk-intent__box{border-color:#FFFFFF;border-width:2px;padding:13px 15px;background:#24508A;}'
		. '.uls-bk-intent__t{display:block;font-weight:600;font-size:1rem;margin-bottom:4px;color:#FFFFFF;}'
		. '.uls-bk-intent__s{display:block;font-size:0.8125rem;line-height:1.35;color:#C9D8EE;}'

		// Fields.
		. '.uls-bk-row{display:flex;flex-wrap:wrap;gap:12px;}'
		. '.uls-bk-row .uls-bk-field{flex:1 1 220px;}'
		. '.uls-bk-field{margin:0 0 14px;}'
		. '.uls-bk-field label{display:block;font-size:0.875rem;font-weight:600;margin:0 0 6px;color:#FFFFFF;}'
		. '.uls-bk-opt{font-weight:400;color:#C9D8EE;}'
		. '.uls-bk-field input,.uls-bk-field select,.uls-bk-field textarea{'
		. 'width:100%;box-sizing:border-box;font:inherit;font-size:1rem;color:#FFFFFF;background:#1F4375;'
		. 'border:1px solid #A8C4EA;border-radius:8px;padding:11px 12px;min-height:44px;}'
		. '.uls-bk-field textarea{min-height:64px;resize:vertical;}'
		. '.uls-bk-field ::placeholder{color:#C9D8EE;opacity:1;}'
		. '.uls-bk-field [aria-invalid="true"]{border-color:#FFD9D0;border-width:2px;}'
		. '.uls-bk-checks{display:flex;flex-wrap:wrap;gap:8px 16px;margin:0 0 14px;}'
		. '.uls-bk-check{display:inline-flex;align-items:center;gap:8px;font-size:0.9375rem;color:#FFFFFF;cursor:pointer;min-height:44px;}'
		. '.uls-bk-check input{width:20px;height:20px;accent-color:#5697F3;}'

		. '.uls-bk-error{margin:0 0 12px;color:#FFD9D0;font-size:0.9375rem;font-weight:600;}'
		. '.uls-bk-error[hidden]{display:none;}'
		. '.uls-bk-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:4px;}'
		. '.uls-bk-submit{font:inherit;font-weight:700;font-size:1rem;color:#173258;background:#FFFFFF;border:2px solid #FFFFFF;'
		. 'border-radius:999px;padding:12px 28px;min-height:44px;cursor:pointer;}'
		. '.uls-bk-submit[disabled]{opacity:.6;cursor:default;}'
		. '.uls-bk-hint{flex:1 1 220px;font-size:0.8125rem;color:#C9D8EE;line-height:1.35;}'

		// Schedule screen.
		. '.uls-bk-screen[hidden]{display:none;}'
		. '.uls-bk-schedbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 40px 14px 0;}'
		. '.uls-bk-back{font:inherit;font-size:0.875rem;font-weight:600;color:#FFFFFF;background:transparent;'
		. 'border:1px solid #A8C4EA;border-radius:999px;padding:9px 16px;min-height:44px;cursor:pointer;}'
		. '.uls-bk-summary{margin:0;font-size:0.875rem;color:#C9D8EE;}'
		. '.uls-bk-embed{min-height:560px;background:#FFFFFF;border-radius:8px;overflow:hidden;}'
		. '.uls-bk-embed iframe{width:100%!important;border:0;}'
		. '.uls-bk-status{margin:10px 0 0;font-size:0.8125rem;color:#C9D8EE;}'
		. '.uls-bk-status[hidden]{display:none;}'

		// Confirmation.
		. '.uls-bk-doneScreen{text-align:center;padding:18px 4px 6px;}'
		. '.uls-bk-tick{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:#FFFFFF;color:#173258;'
		. 'display:flex;align-items:center;justify-content:center;font-size:2rem;line-height:1;}'
		. '.uls-bk-doneScreen .uls-bk-actions{justify-content:center;}'

		. '@media (max-width:600px){'
		. '.uls-book-modal{padding:0;align-items:stretch;}'
		. '.uls-book-modal__panel{max-width:none;max-height:100vh;height:100vh;border:0;border-radius:0;padding:22px 16px 20px;}'
		. '.uls-bk-embed{min-height:520px;}'
		. '}'
		. '@media (prefers-reduced-motion: reduce){.uls-bk-intent__box{transition:none;}}'
		. '</style>';
}

/**
 * The dialog + embed controller.
 *
 * cal.com prefill was VERIFIED against this deployment (book.uplinksync.com,
 * team/uplinksync/it-consult) rather than assumed: `name`, `email`, and the
 * per-event-type field slugs (`goal`, `current-setup`, `timeline`, `user-count`,
 * `notes-extra` for IT; `site-location`, `site-type`, `deliverables`,
 * `notes-extra` for UAV) all arrive populated in the booker. `deliverables` is a
 * multi-select and takes an array.
 *
 * The embed is mounted with a FRESH namespace each time details are submitted,
 * because cal.com's `inline` action binds an element once; re-submitting with a
 * new namespace into a cleared container is the reliable way to re-render with
 * different prefill.
 */
function uplinksync_book_runtime_js() {
	return <<<'JS'
(function () {
	var modal = document.getElementById('uls-book-modal');
	if (!modal) { return; }

	var cfgEl  = document.getElementById('uplinksync-book-cta-js');
	var CFG    = {};
	try { CFG = JSON.parse(cfgEl.getAttribute('data-uls-book-config') || '{}'); } catch (e) {}

	var panel   = modal.querySelector('.uls-book-modal__panel');
	var form    = document.getElementById('uls-bk-form');
	var rest    = document.getElementById('uls-bk-rest');
	var errEl   = document.getElementById('uls-bk-error');
	var statusEl= document.getElementById('uls-bk-status');
	var summary = document.getElementById('uls-bk-summary');
	var embedEl = document.getElementById('uls-book-embed');
	var lastFocus = null;
	var calLoaded = false, calLoading = false, calQueue = [];
	var nsSeq = 0;

	/* ---------- cal.com Embed SDK, loaded lazily on first submit ---------- */
	function boot(cb) {
		if (calLoaded) { cb && cb(); return; }
		if (cb) { calQueue.push(cb); }
		if (calLoading) { return; }
		calLoading = true;
		(function (C, A, L) {
			var p = function (a, ar) { a.q.push(ar); };
			var d = C.document;
			C.Cal = C.Cal || function () {
				var cal = C.Cal; var ar = arguments;
				if (!cal.loaded) { cal.ns = {}; cal.q = cal.q || []; d.head.appendChild(d.createElement('script')).src = A; cal.loaded = true; }
				if (ar[0] === L) {
					var api = function () { p(api, arguments); };
					var namespace = ar[1];
					api.q = api.q || [];
					if (typeof namespace === 'string') { cal.ns[namespace] = cal.ns[namespace] || api; p(cal.ns[namespace], ar); p(cal, ['initNamespace', namespace]); }
					else { p(cal, ar); }
					return;
				}
				p(cal, ar);
			};
		}(window, CFG.embed, 'init'));
		calLoaded = true; calLoading = false;
		var q = calQueue.slice(); calQueue = [];
		q.forEach(function (f) { f(); });
	}

	/* ---------- screens ---------- */
	function show(name) {
		modal.querySelectorAll('[data-uls-bk-screen]').forEach(function (s) {
			s.hidden = (s.getAttribute('data-uls-bk-screen') !== name);
		});
		if (panel) { panel.scrollTop = 0; }
	}

	/* ---------- intent ---------- */
	function intent() {
		var r = form && form.querySelector('input[name="uls-bk-intent"]:checked');
		return r ? r.value : '';
	}
	function applyIntent() {
		var v = intent();
		if (rest) { rest.hidden = !v; }
		modal.querySelectorAll('[data-uls-bk-group]').forEach(function (g) {
			g.hidden = (g.getAttribute('data-uls-bk-group') !== v);
		});
	}
	function preselect(v) {
		if (!v) { return; }
		var el = document.getElementById('uls-bk-intent-' + v);
		if (el) { el.checked = true; applyIntent(); }
	}

	/* ---------- validation ---------- */
	function val(id) { var el = document.getElementById(id); return el ? (el.value || '').trim() : ''; }
	function mark(el, bad) { if (el) { el.setAttribute('aria-invalid', bad ? 'true' : 'false'); } }

	function collect() {
		var v = intent();
		if (!v) { return { error: 'Pick what we can help with to continue.', focus: 'uls-bk-intent-it' }; }

		var name  = val('uls-bk-name');
		var email = val('uls-bk-email');
		mark(document.getElementById('uls-bk-name'), !name);
		if (!name)  { return { error: 'Please add your name.', focus: 'uls-bk-name' }; }
		var okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		mark(document.getElementById('uls-bk-email'), !okEmail);
		if (!okEmail) { return { error: 'Please add an email we can send the invite to.', focus: 'uls-bk-email' }; }

		var cfg = { name: name, email: email, layout: 'month_view' };
		var notes = val('uls-bk-notes');
		if (notes) { cfg['notes-extra'] = notes; }

		if (v === 'it') {
			var goal = val('uls-bk-goal');
			mark(document.getElementById('uls-bk-goal'), !goal);
			if (!goal) { return { error: 'Pick the main goal so we can prepare.', focus: 'uls-bk-goal' }; }
			var setup = val('uls-bk-setup');
			mark(document.getElementById('uls-bk-setup'), !setup);
			if (!setup) { return { error: 'A line about your current setup helps us come prepared.', focus: 'uls-bk-setup' }; }
			cfg.goal = goal;
			cfg['current-setup'] = setup;
		} else {
			var site = val('uls-bk-site');
			mark(document.getElementById('uls-bk-site'), !site);
			if (!site) { return { error: 'Where is the site? An address or coordinates is enough.', focus: 'uls-bk-site' }; }
			var stype = val('uls-bk-sitetype');
			mark(document.getElementById('uls-bk-sitetype'), !stype);
			if (!stype) { return { error: 'Pick the site type.', focus: 'uls-bk-sitetype' }; }
			var deliv = Array.prototype.slice.call(
				modal.querySelectorAll('input[name="uls-bk-deliverables"]:checked')
			).map(function (c) { return c.value; });
			if (!deliv.length) { return { error: 'Choose at least one deliverable.', focus: 'uls-bk-deliverables' }; }
			cfg['site-location'] = site;
			cfg['site-type'] = stype;
			cfg.deliverables = deliv;
		}

		return { cfg: cfg, slug: (v === 'uav') ? CFG.slugs.uav : CFG.slugs.it, name: name, email: email };
	}

	/* ---------- mount the inline embed ---------- */
	function mount(slug, cfg, who) {
		show('schedule');
		if (summary) { summary.textContent = 'Booking as ' + who; }
		if (statusEl) { statusEl.hidden = false; statusEl.textContent = 'Loading available times…'; }
		embedEl.setAttribute('aria-busy', 'true');
		embedEl.innerHTML = '';

		var ns = 'ulsbk' + (++nsSeq);
		boot(function () {
			window.Cal('init', ns, { origin: CFG.origin });
			window.Cal.ns[ns]('inline', {
				elementOrSelector: '#uls-book-embed',
				calLink: slug,
				config: cfg
			});
			window.Cal.ns[ns]('on', {
				action: 'linkReady',
				callback: function () {
					embedEl.setAttribute('aria-busy', 'false');
					if (statusEl) { statusEl.hidden = true; }
				}
			});
			window.Cal.ns[ns]('on', {
				action: 'bookingSuccessful',
				callback: function () { show('done'); }
			});
		});
	}

	/* ---------- open / close ---------- */
	function foci() {
		return Array.prototype.slice.call(
			modal.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')
		).filter(function (el) {
			return !el.closest('[hidden]') && (el.offsetWidth || el.offsetHeight || el.getClientRects().length);
		});
	}
	function openM(trigger) {
		lastFocus = trigger || document.activeElement;
		modal.hidden = false;
		document.body.classList.add('uls-book-modal-open');
		if (trigger && trigger.getAttribute) { preselect(trigger.getAttribute('data-uls-book-intent')); }
		var f = foci();
		if (f.length) { f[0].focus(); } else if (panel) { panel.focus(); }
	}
	function closeM() {
		if (modal.hidden) { return; }
		modal.hidden = true;
		document.body.classList.remove('uls-book-modal-open');
		if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) { return; }
		var opener = t.closest('[data-uls-book-open]');
		if (opener) { e.preventDefault(); openM(opener); return; }
		if (t.closest('[data-uls-book-close]')) { e.preventDefault(); closeM(); return; }
		if (t.closest('[data-uls-bk-back]')) { e.preventDefault(); show('details'); return; }
	}, false);

	document.addEventListener('keydown', function (e) {
		if (modal.hidden) { return; }
		if (e.key === 'Escape' || e.keyCode === 27) { e.preventDefault(); closeM(); return; }
		if (e.key === 'Tab' || e.keyCode === 9) {
			var f = foci();
			if (!f.length) { e.preventDefault(); return; }
			var first = f[0], last = f[f.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			else if (!modal.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
		}
	}, false);

	/* ---------- form wiring ---------- */
	if (form) {
		form.addEventListener('change', function (e) {
			if (e.target && e.target.name === 'uls-bk-intent') { applyIntent(); }
		}, false);

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var r = collect();
			if (r.error) {
				if (errEl) { errEl.hidden = false; errEl.textContent = r.error; }
				var f = document.getElementById(r.focus);
				if (f) { (f.querySelector('input') || f).focus(); }
				return;
			}
			if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
			mount(r.slug, r.cfg, r.name + ' · ' + r.email);
		}, false);
	}
}());
JS;
}

/**
 * Inject the dialog, its styling and the controller once, just before </body>,
 * only if a booking CTA (ours or an adopted orphan anchor) is on this page. The
 * cal.com Embed SDK itself is NOT loaded on page load — it is fetched when the
 * visitor submits the intake form, so it never blocks render.
 */
function uplinksync_book_inject_runtime( $html ) {
	$has_cta = ( false !== strpos( $html, 'uls-book-link' ) )
		|| ( false !== strpos( $html, 'uls-consult-trigger' ) )
		|| ( false !== strpos( $html, 'data-uls-book-open' ) )
		|| ( false !== strpos( $html, 'uls-estimator' ) );
	if ( ! $has_cta ) {
		return $html;
	}
	// Idempotency: never inject the runtime (or a second dialog) twice.
	if ( false !== strpos( $html, 'uplinksync-book-cta-js' ) ) {
		return $html;
	}

	$cfg = wp_json_encode(
		array(
			'origin' => UPLINKSYNC_BOOK_ORIGIN,
			'embed'  => UPLINKSYNC_BOOK_ORIGIN . '/embed/embed.js',
			'slugs'  => array(
				'it'  => UPLINKSYNC_BOOK_CONSULT_SLUG,
				'uav' => UPLINKSYNC_BOOK_UAV_SLUG,
			),
		)
	);

	$script = '<script id="uplinksync-book-cta-js" data-uls-book-config="' . esc_attr( $cfg ) . '">'
		. uplinksync_book_runtime_js()
		. '</script>';

	return str_ireplace(
		'</body>',
		uplinksync_book_styles() . uplinksync_book_modal_markup() . $script . '</body>',
		$html
	);
}
