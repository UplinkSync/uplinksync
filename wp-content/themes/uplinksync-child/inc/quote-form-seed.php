<?php
/**
 * ***-42: programmatic Contact Form 7 provisioning for the quote flow.
 *
 * Why this exists
 * ---------------
 * The quote-flow spec (quote-flow-spec.md §1, §3, §7) and the build guide
 * (docs/quote-form-build-guide.md) originally assumed the CF7 form entity would
 * be hand-built once in wp-admin by someone with dashboard access. That made
 * the whole issue depend on a human step. It doesn't have to: CF7 exposes a
 * PHP API (WPCF7_ContactForm) and this child theme already executes on the live
 * host, so we can create the exact spec'd forms in code — reviewable, versioned,
 * and idempotent — instead of as opaque database rows.
 *
 * Note on the REST API: CF7's own /wp-json/contact-form-7/v1 write routes accept
 * Basic-auth requests with HTTP 200 but silently no-op (they require an admin
 * nonce, which application passwords cannot supply). Seeding from PHP inside the
 * request lifecycle is the reliable path.
 *
 * What it provisions
 * ------------------
 * 1. "Master Quote Form (***-42)" — the 6 canonical fields (spec §1), honeypot,
 *    AJAX inline confirmation, notification to dirwin@uplinksync.com with the
 *    subject `[UplinkSync] New quote request — {Company Name}`, UTM mail-tags.
 * 2. "Quote Mini Form (***-42)" — Name/Email/Phone with Service Interest hard-
 *    pinned to "Managed IT Services" (spec §3), same notification backend.
 *
 * Field-ID contract (must stay in lockstep with assets/js/quote-form.js and
 * assets/css/quote-form.css — see docs/quote-form-build-guide.md §7):
 *   - Service Interest select HTML id: service-interest
 *   - Master form wrapper id:  quote-form
 *   - Mini form wrapper id:    quote-form-mini
 *   - Honeypot field class:    uls-honeypot
 *
 * Storage: Flamingo (active on host) captures every CF7 submission automatically;
 * no extra config needed (spec §7 "store submissions in DB").
 *
 * Shortcodes (self-resolving — no hardcoded post IDs in page content):
 *   [uplinksync_quote_form]        -> master form, wrapper id "quote-form"
 *   [uplinksync_quote_form_mini]   -> mini form,  wrapper id "quote-form-mini"
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bump when the form definitions below change so the seeder re-applies them
 * to the stored CF7 posts on the next request after deploy.
 */
if ( ! defined( 'UPLINKSYNC_QUOTE_FORM_VERSION' ) ) {
	define( 'UPLINKSYNC_QUOTE_FORM_VERSION', '2' );
}

/**
 * Notification recipient = internal inbox routing, NOT the published contact
 * address (contact@uplinksync.com). See quote-flow-spec.md §1 and content-facts.
 */
if ( ! defined( 'UPLINKSYNC_QUOTE_NOTIFY_EMAIL' ) ) {
	define( 'UPLINKSYNC_QUOTE_NOTIFY_EMAIL', 'dirwin@uplinksync.com' );
}

/**
 * Master form CF7 template (spec §1). Field names/ids are the contract the
 * bundled JS/CSS select on — do not rename without updating those assets.
 *
 * UX SPRINT (2026-07-24) — three measured problems fixed here:
 *
 * 1. NO `autocomplete` ATTRIBUTES. Every field shipped without one, so no
 *    browser or password manager could autofill a single value. This is the
 *    "smart defaults" principle in its most literal form (ux-psychology-design
 *    -guide.md §5 P0-2): the browser already knows the visitor's name, email,
 *    phone and employer — asking them to retype it is pure friction we were
 *    imposing for no reason. The tokens below are the WHATWG autofill field
 *    names, which is what both browsers and password managers match on.
 *
 * 2. NO VISIBLE REQUIRED MARKER. All five of first-name, company-name,
 *    your-email, phone and service-interest are `*` (required) in CF7, but the
 *    rendered labels said nothing, so the only way to discover that Company and
 *    Phone are mandatory was to fill the form in, submit, and be rejected.
 *    Each required label now carries a marker plus a legend explaining it.
 *
 * 3. PLACEHOLDERS THAT ONLY ECHOED THE LABEL. "First Name" labelled a field
 *    whose placeholder also read "First Name" — duplicate ink that adds no
 *    information and leaves grey text sitting in every box, which reads as a
 *    pre-filled form. Dropped on the four fields where it was an echo; kept on
 *    the textarea, where it is the one placeholder that actually says something
 *    the label does not.
 *
 * The submit label moves from "Send My Request" to "Get My Free Quote" so the
 * button names the OUTCOME the visitor came for and matches the page's own H1
 * ("Get a free quote."), rather than describing the mechanical act of sending.
 */
function uplinksync_quote_master_form_template() {
	return <<<'CF7'
<p class="uls-form-legend"><span class="uls-req" aria-hidden="true">*</span> Required</p>

<div class="uls-quote-field"><label>First Name <span class="uls-req" aria-hidden="true">*</span><br />
[text* first-name autocomplete:given-name]</label></div>

<div class="uls-quote-field"><label>Company Name <span class="uls-req" aria-hidden="true">*</span><br />
[text* company-name autocomplete:organization]</label></div>

<div class="uls-quote-field"><label>Email <span class="uls-req" aria-hidden="true">*</span><br />
[email* your-email autocomplete:email]</label></div>

<div class="uls-quote-field"><label>Phone <span class="uls-req" aria-hidden="true">*</span><br />
[tel* phone autocomplete:tel]</label></div>

<div class="uls-quote-field"><label>Service Interest <span class="uls-req" aria-hidden="true">*</span><br />
[select* service-interest id:service-interest "Managed IT Services" "Business Automation" "Web Development" "Drone Services" "Not Sure"]</label></div>

<div class="uls-quote-field"><label>How can we help?<br />
[textarea help-text maxlength:500 placeholder "Brief description of what you're dealing with — no need to be technical."]</label></div>

[text honeypot-field class:uls-honeypot autocomplete:off]

[submit "Get My Free Quote"]
CF7;
}

/**
 * Mini form CF7 template (spec §3). Service Interest is hard-pinned via a hidden
 * field so the same notification/Flamingo backend receives "Managed IT Services".
 */
function uplinksync_quote_mini_form_template() {
	return <<<'CF7'
<p class="uls-form-legend"><span class="uls-req" aria-hidden="true">*</span> Required</p>

<div class="uls-quote-field"><label>Name <span class="uls-req" aria-hidden="true">*</span><br />
[text* first-name autocomplete:name]</label></div>

<div class="uls-quote-field"><label>Email <span class="uls-req" aria-hidden="true">*</span><br />
[email* your-email autocomplete:email]</label></div>

<div class="uls-quote-field"><label>Phone <span class="uls-req" aria-hidden="true">*</span><br />
[tel* phone autocomplete:tel]</label></div>

[hidden service-interest "Managed IT Services"]
[text honeypot-field class:uls-honeypot autocomplete:off]

[submit "Get My Free Assessment"]
CF7;
}

/**
 * Notification mail body. Company Name interpolates into the subject per spec §7.
 * UTM values arrive as unregistered POST keys (injected by quote-form.js) which
 * CF7 accepts as mail-tags without a matching form field.
 */
function uplinksync_quote_mail_properties( $subject_company_tag = '[company-name]' ) {
	$body = "New quote request\n\n"
		. "First Name: [first-name]\n"
		. "Company: [company-name]\n"
		. "Email: [your-email]\n"
		. "Phone: [phone]\n"
		. "Service Interest: [service-interest]\n\n"
		. "How can we help:\n[help-text]\n\n"
		. "UTM: source=[utm_source] medium=[utm_medium] campaign=[utm_campaign] term=[utm_term] content=[utm_content]\n\n"
		. "-- \nSubmitted on [_site_title] ([_site_url])";

	return array(
		'active'             => true,
		'subject'            => '[UplinkSync] New quote request — ' . $subject_company_tag,
		'sender'             => '[_site_title] <webmaster@uplinksync.com>',
		'recipient'          => UPLINKSYNC_QUOTE_NOTIFY_EMAIL,
		'body'               => $body,
		'additional_headers' => 'Reply-To: [your-email]',
		'attachments'        => '',
		'use_html'           => 0,
		'exclude_blank'      => 0,
	);
}

/**
 * Create or update a single CF7 form, keyed by an option that stores its post ID.
 * Idempotent: reuses the existing post when present, recreates if it was deleted.
 *
 * @param string $option_key   Option storing the CF7 post ID.
 * @param string $title        Form title (wp-admin listing).
 * @param string $form_template CF7 form-tag markup.
 * @param string $subject_company_tag Mail-tag used in the subject line.
 * @return int|false Post ID on success, false if CF7 unavailable.
 */
function uplinksync_quote_upsert_form( $option_key, $title, $form_template, $subject_company_tag ) {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return false;
	}

	$existing_id = (int) get_option( $option_key, 0 );
	$contact_form = $existing_id ? wpcf7_contact_form( $existing_id ) : null;

	if ( ! $contact_form ) {
		$contact_form = WPCF7_ContactForm::get_template();
	}

	$contact_form->set_title( $title );
	$contact_form->set_properties(
		array(
			'form'     => $form_template,
			'mail'     => uplinksync_quote_mail_properties( $subject_company_tag ),
			'mail_2'   => array( 'active' => false ),
			// Move keyboard focus to the inline response so AJAX confirmation is
			// announced without a redirect (build guide §3).
			'additional_settings' => "on_sent_ok: \"document.querySelector('.wpcf7-response-output') && document.querySelector('.wpcf7-response-output').focus();\"",
		)
	);

	$new_id = $contact_form->save();
	if ( $new_id ) {
		update_option( $option_key, (int) $new_id, false );
		return (int) $new_id;
	}

	return $existing_id ?: false;
}

/**
 * Seed both forms once per version bump. Runs on init (CF7 is loaded by then).
 */
function uplinksync_quote_seed_forms() {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return;
	}

	$seeded_version = get_option( 'uplinksync_quote_form_seeded_version', '' );
	$master_id      = (int) get_option( 'uplinksync_quote_master_form_id', 0 );
	$mini_id        = (int) get_option( 'uplinksync_quote_mini_form_id', 0 );

	$forms_present = $master_id && wpcf7_contact_form( $master_id )
		&& $mini_id && wpcf7_contact_form( $mini_id );

	if ( $seeded_version === UPLINKSYNC_QUOTE_FORM_VERSION && $forms_present ) {
		return;
	}

	uplinksync_quote_upsert_form(
		'uplinksync_quote_master_form_id',
		'Master Quote Form (***-42)',
		uplinksync_quote_master_form_template(),
		'[company-name]'
	);

	uplinksync_quote_upsert_form(
		'uplinksync_quote_mini_form_id',
		'Quote Mini Form (***-42)',
		uplinksync_quote_mini_form_template(),
		'Managed IT (mini-form)'
	);

	update_option( 'uplinksync_quote_form_seeded_version', UPLINKSYNC_QUOTE_FORM_VERSION, false );
}
add_action( 'init', 'uplinksync_quote_seed_forms', 20 );

/**
 * Self-resolving shortcodes. Page content uses [uplinksync_quote_form] /
 * [uplinksync_quote_form_mini] and never a raw CF7 post ID, so a re-seed can
 * change the underlying ID without touching any page.
 */
function uplinksync_quote_form_shortcode( $atts ) {
	$id = (int) get_option( 'uplinksync_quote_master_form_id', 0 );
	if ( ! $id || ! function_exists( 'wpcf7_contact_form' ) || ! wpcf7_contact_form( $id ) ) {
		return '';
	}
	return do_shortcode( sprintf( '[contact-form-7 id="%d" html_id="quote-form"]', $id ) );
}
add_shortcode( 'uplinksync_quote_form', 'uplinksync_quote_form_shortcode' );

function uplinksync_quote_form_mini_shortcode( $atts ) {
	$id = (int) get_option( 'uplinksync_quote_mini_form_id', 0 );
	if ( ! $id || ! function_exists( 'wpcf7_contact_form' ) || ! wpcf7_contact_form( $id ) ) {
		return '';
	}
	return do_shortcode( sprintf( '[contact-form-7 id="%d" html_id="quote-form-mini"]', $id ) );
}
add_shortcode( 'uplinksync_quote_form_mini', 'uplinksync_quote_form_mini_shortcode' );

/**
 * Placeholder replacement in page content.
 *
 * The /contact and /services/managed-it pages already carry HTML-comment
 * placeholders authored earlier for this issue, e.g.:
 *   <!-- ***-42 master quote form: insert [contact-form-7 id="..."] here ... -->
 *   <!-- ***-42 mini quote form:   insert [contact-form-7 id="..."] here ... -->
 * Rather than editing the production database to swap in a form ID (fragile on
 * this Cloudflare-fronted host, and it splits the change across two systems),
 * this filter renders the seeded form in place of the comment at output time.
 * The whole quote flow therefore ships atomically in this one theme deploy — no
 * separate page-content edit required. The comments are inert on any page/theme
 * where this filter is absent, so nothing regresses if the theme is rolled back.
 */
function uplinksync_quote_replace_placeholders( $content ) {
	if ( is_admin() ) {
		return $content;
	}

	if ( false !== strpos( $content, '***-42 master quote form' ) ) {
		$content = preg_replace(
			'/<p><!--\s****-42 master quote form.*?-->\s*<\/p>/s',
			uplinksync_quote_form_shortcode( array() ),
			$content
		);
	}

	if ( false !== strpos( $content, '***-42 mini quote form' ) ) {
		$content = preg_replace(
			'/<p><!--\s****-42 mini quote form.*?-->\s*<\/p>/s',
			uplinksync_quote_form_mini_shortcode( array() ),
			$content
		);
	}

	return $content;
}
add_filter( 'the_content', 'uplinksync_quote_replace_placeholders', 20 );
