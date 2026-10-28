<?php
/**
 * UplinkSync estimator — markup for the [uls_quote_flow] shortcode and the site-wide modal.
 *
 * Ported from database-resident Code Snippets #82 (flow) and #84 (modal). Every question,
 * option, label and customer-facing sentence of the live flow is kept; the layout follows
 * the Quiet Light mock-up (enrich/mockups/quote.html): navy header band with the stepper,
 * two-column step 2 with a sticky estimate column, native calendar in step 3, sticky footer
 * bar. Element IDs used by the old JS are kept so behaviour stays traceable line-for-line.
 *
 * data-uls-book-direct on the book.uplinksync.com fallback links: uplinksync-booking-ctas.php
 * adopts every anchor to that origin as a trigger for ITS dialog unless the anchor carries a
 * data-uls-book* attribute. These links must stay plain links (the "never a dead end" path).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon (decorative, aria-hidden).
 *
 * @param string $name Icon key.
 * @param int    $size Pixel size.
 * @return string
 */
function uls_estimator_icon( $name, $size = 18 ) {
	$paths = array(
		'check'  => '<path d="M5 12l4 4 10-10"/>',
		'drone'  => '<circle cx="5" cy="5" r="2.5"/><circle cx="19" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M7 7l3 3M17 7l-3 3M7 17l3-3M17 17l-3-3"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/>',
		'it'     => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4M7 8h4M7 11h6"/>',
		'left'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
		'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'card'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
		'cal'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
		'video'  => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/>',
		'alert'  => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/>',
		'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
	);
	$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/**
 * Contact email used in fallbacks.
 *
 * @return string
 */
function uls_estimator_contact_email() {
	return 'contact@uplinksync.com';
}

/**
 * Company phone (UPLINKSYNC_PHONE, owned by another MR; empty = hidden everywhere).
 *
 * @return string
 */
function uls_estimator_phone() {
	return ( defined( 'UPLINKSYNC_PHONE' ) && is_string( UPLINKSYNC_PHONE ) ) ? trim( UPLINKSYNC_PHONE ) : '';
}

/**
 * <option> list for a whitelist of values (value === label).
 *
 * @param string[] $values Values.
 * @return string
 */
function uls_estimator_options_html( $values ) {
	$h = '';
	foreach ( $values as $v ) {
		$h .= '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
	}
	return $h;
}

/**
 * The flow. [uls_quote_flow preset="uav|it"].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function uls_estimator_flow_markup( $atts = array() ) {
	$atts     = shortcode_atts( array( 'preset' => '' ), $atts, 'uls_quote_flow' );
	$preset   = in_array( $atts['preset'], array( 'uav', 'it' ), true ) ? $atts['preset'] : '';
	$endpoint = admin_url( 'admin-ajax.php' );
	$opts     = uls_estimator_booking_options();
	$email    = uls_estimator_contact_email();
	$phone    = uls_estimator_phone();
	$it_url   = uls_estimator_fallback_url( 'it' );
	$ck       = uls_estimator_icon( 'check', 14 );

	ob_start();
	?>
<div class="uls-qf" id="uls-qf" data-preset="<?php echo esc_attr( $preset ); ?>" data-endpoint="<?php echo esc_url( $endpoint ); ?>">
  <div class="qf-console">
    <div class="qf-head">
      <ol class="qf-stepper" id="qf-stepper" aria-label="Quote steps">
        <li class="qf-step on" data-s="1" aria-current="step"><span class="qf-num"><span class="qf-n">1</span><?php echo $ck; // phpcs:ignore ?></span><span class="qf-lbl" data-quote="Project type" data-book="Your details">Project type</span></li>
        <li class="qf-bar qf-quote-only" aria-hidden="true"></li>
        <li class="qf-step qf-quote-only" data-s="2"><span class="qf-num"><span class="qf-n">2</span><?php echo $ck; // phpcs:ignore ?></span><span class="qf-lbl">Your estimate</span></li>
        <li class="qf-bar" aria-hidden="true"></li>
        <li class="qf-step" data-s="3"><span class="qf-num"><span class="qf-n">3</span><?php echo $ck; // phpcs:ignore ?></span><span class="qf-lbl">Book a time</span></li>
      </ol>
    </div>

    <!-- STEP 1 -->
    <section class="qf-pane" data-pane="1" aria-labelledby="qf-s1title">
      <div class="qf-body">
        <p class="qf-eyebrow">Start with a free consultation</p>
        <h2 class="qf-h1" id="qf-s1title">What kind of project can we help with?</h2>
        <p class="qf-lede">Pick one — or both. We&#8217;ll tailor the next step to what you need. No obligation, and nothing you enter here is a commitment.</p>
        <div class="qf-choices">
          <button class="qf-choice uav" type="button" aria-pressed="false" data-pick="uav">
            <span class="qf-check" aria-hidden="true"><?php echo $ck; // phpcs:ignore ?></span>
            <span class="qf-ic" aria-hidden="true"><?php echo uls_estimator_icon( 'drone', 22 ); // phpcs:ignore ?></span>
            <span class="qf-ch3">Drone / UAV work</span>
            <span class="qf-cp">Aerial photography, mapping &amp; surveying, roof &amp; structure inspection.</span>
          </button>
          <button class="qf-choice it" type="button" aria-pressed="false" data-pick="it">
            <span class="qf-check" aria-hidden="true"><?php echo $ck; // phpcs:ignore ?></span>
            <span class="qf-ic" aria-hidden="true"><?php echo uls_estimator_icon( 'it', 22 ); // phpcs:ignore ?></span>
            <span class="qf-ch3">IT / technology</span>
            <span class="qf-cp">Managed IT, automation &amp; AI, web development and support.</span>
          </button>
        </div>
        <p class="qf-selhint" id="qf-selhint" aria-live="polite">Select at least one to continue.</p>
      </div>
      <div class="qf-actions">
        <span class="qf-spacer"></span>
        <button class="qf-btn primary" id="qf-to2" type="button" aria-disabled="true" aria-describedby="qf-selhint">Continue</button>
      </div>
    </section>

    <!-- BOOK A CALL (booking-only entry: every "Book a call" CTA lands here, then step 3) -->
    <section class="qf-pane hidden" data-pane="b" aria-labelledby="qf-btitle">
      <div class="qf-body">
        <p class="qf-eyebrow">Free consultation</p>
        <h2 class="qf-h1" id="qf-btitle">Book a call</h2>
        <p class="qf-lede">A few details so we come prepared, then pick a time. You&#8217;ll talk to the person who does the work.</p>
        <div class="qf-choices qf-choices--compact" role="radiogroup" aria-label="What is the call about?">
          <button class="qf-choice it" type="button" role="radio" aria-checked="false" aria-pressed="false" data-bpick="it">
            <span class="qf-check" aria-hidden="true"><?php echo $ck; // phpcs:ignore ?></span>
            <span class="qf-ic" aria-hidden="true"><?php echo uls_estimator_icon( 'it', 22 ); // phpcs:ignore ?></span>
            <span class="qf-ch3">IT / technology</span>
          </button>
          <button class="qf-choice uav" type="button" role="radio" aria-checked="false" aria-pressed="false" data-bpick="uav">
            <span class="qf-check" aria-hidden="true"><?php echo $ck; // phpcs:ignore ?></span>
            <span class="qf-ic" aria-hidden="true"><?php echo uls_estimator_icon( 'drone', 22 ); // phpcs:ignore ?></span>
            <span class="qf-ch3">Drone / UAV</span>
          </button>
        </div>
        <fieldset class="qf-contact">
          <legend class="qf-legend">Your details</legend>
          <p class="qf-req">Fields marked <i aria-hidden="true">*</i> are required.</p>
          <div class="qf-grid2">
            <div class="qf-field"><label class="qf-l" for="qf-bname">Your name <i aria-hidden="true">*</i></label>
              <input id="qf-bname" class="qf-input" type="text" autocomplete="name" required maxlength="100"></div>
            <div class="qf-field"><label class="qf-l" for="qf-bemail">Email <i aria-hidden="true">*</i></label>
              <input id="qf-bemail" class="qf-input" type="email" autocomplete="email" required maxlength="254"></div>
          </div>
          <div class="hidden" id="qf-bIt">
            <div class="qf-field"><label class="qf-l" for="qf-bgoal">Main goal <i aria-hidden="true">*</i></label>
              <select id="qf-bgoal" class="qf-select"><?php echo uls_estimator_options_html( $opts['goal'] ); // phpcs:ignore ?></select></div>
            <div class="qf-field"><label class="qf-l" for="qf-bsetup">Current setup &amp; main pain points <i aria-hidden="true">*</i></label>
              <textarea id="qf-bsetup" class="qf-input qf-textarea" rows="3" maxlength="1000" placeholder="What you have today, and what isn&#8217;t working"></textarea></div>
          </div>
          <div class="hidden" id="qf-bUav">
            <div class="qf-grid2">
              <div class="qf-field"><label class="qf-l" for="qf-bsite">Site address or location <i aria-hidden="true">*</i></label>
                <input id="qf-bsite" class="qf-input" type="text" autocomplete="street-address" maxlength="200" placeholder="Street address or nearest town"></div>
              <div class="qf-field"><label class="qf-l" for="qf-bsiteType">Site type <i aria-hidden="true">*</i></label>
                <select id="qf-bsiteType" class="qf-select"><?php echo uls_estimator_options_html( $opts['site-type'] ); // phpcs:ignore ?></select></div>
            </div>
            <fieldset class="qf-subfs" id="qf-bdeliv"><legend class="qf-l">Deliverables needed <i aria-hidden="true">*</i></legend>
              <div class="qf-grid2"><?php foreach ( $opts['deliverables'] as $d ) : ?>
                <label class="qf-check-row"><input type="checkbox" name="qf-bdeliv" value="<?php echo esc_attr( $d ); ?>"><span><?php echo esc_html( $d ); ?></span></label>
              <?php endforeach; ?></div>
            </fieldset>
          </div>
          <div class="qf-field"><label class="qf-l" for="qf-bnotes">Anything else? <span class="qf-opt">optional</span></label>
            <textarea id="qf-bnotes" class="qf-input qf-textarea" rows="2" maxlength="1000"></textarea></div>
          <p class="qf-help">Used only to book your call and reply to you.</p>
          <p class="qf-cerr hidden" id="qf-berr" role="alert"><?php echo uls_estimator_icon( 'alert', 16 ); // phpcs:ignore ?><span id="qf-berrText"></span></p>
        </fieldset>
      </div>
      <div class="qf-actions">
        <button class="qf-btn ghost" type="button" id="qf-toQuote">Get an estimate instead</button>
        <span class="qf-spacer"></span>
        <button class="qf-btn primary" id="qf-bto3" type="button">Find a time</button>
      </div>
    </section>

    <!-- STEP 2 -->
    <section class="qf-pane hidden" data-pane="2" aria-labelledby="qf-s2title">
      <div class="qf-body">
        <p class="qf-eyebrow" id="qf-s2eyebrow">Your instant ballpark</p>
        <h2 class="qf-h1" id="qf-s2title">Tell us a little more</h2>
        <p class="qf-lede" id="qf-s2lede"></p>

        <!-- track tabs (both-selected only) -->
        <div class="qf-tabs hidden" id="qf-tabs" role="tablist" aria-label="Project tracks">
          <button class="qf-tab on" type="button" data-tab="uav" role="tab" aria-selected="true"><?php echo uls_estimator_icon( 'drone', 16 ); // phpcs:ignore ?>Drone / UAV<span class="qf-dot" aria-hidden="true"></span></button>
          <button class="qf-tab" type="button" data-tab="it" role="tab" aria-selected="false"><?php echo uls_estimator_icon( 'it', 16 ); // phpcs:ignore ?>IT / technology<span class="qf-dot" aria-hidden="true"></span></button>
        </div>

        <div class="qf-s2grid">
          <div class="qf-s2main">
            <!-- UAV block -->
            <div id="qf-uavBlock" class="hidden">
              <div class="qf-section-head"><span class="qf-pill"><?php echo uls_estimator_icon( 'drone', 14 ); // phpcs:ignore ?>Drone / UAV</span></div>
              <div class="qf-field">
                <label class="qf-l" for="qf-uavLine">What do you need flown?</label>
                <select id="qf-uavLine" class="qf-select">
                  <option value="real_estate">Real-estate / property photography</option>
                  <option value="mapping">Mapping &amp; surveying</option>
                  <option value="inspection">Roof / structure inspection</option>
                  <option value="tower">Tower / industrial / other</option>
                </select>
              </div>
              <div id="qf-uavOpts"></div>
              <div class="qf-grid2" id="qf-uavLogistics">
                <div class="qf-field"><label class="qf-l" for="qf-uavMiles">Round-trip travel (miles) <span class="qf-opt">optional</span></label>
                  <input id="qf-uavMiles" class="qf-input" type="number" min="0" step="1" inputmode="numeric" placeholder="e.g. 30"></div>
                <div class="qf-field"><label class="qf-l" for="qf-uavTiming">How soon?</label>
                  <select id="qf-uavTiming" class="qf-select"><option value="standard">Standard scheduling</option><option value="rush">Rush (expedited)</option><option value="same_day">Same-day</option></select></div>
              </div>
            </div>

            <!-- IT block -->
            <div id="qf-itBlock" class="hidden">
              <div class="qf-section-head"><span class="qf-pill"><?php echo uls_estimator_icon( 'it', 14 ); // phpcs:ignore ?>IT / technology</span></div>
              <div class="qf-field">
                <label class="qf-l" for="qf-itLine">What area is this?</label>
                <select id="qf-itLine" class="qf-select">
                  <option value="managed">Managed IT / support</option>
                  <option value="automation">Automation &amp; AI</option>
                  <option value="web">Web development</option>
                  <option value="mixed">A mix / not sure yet</option>
                </select>
              </div>
              <div id="qf-itEngage" class="qf-engage hidden"></div>
              <div id="qf-itOpts"></div>
              <div class="qf-field" id="qf-itWhenWrap"><label class="qf-l" for="qf-itWhen">Timeline</label>
                <select id="qf-itWhen" class="qf-select"><option>Exploring</option><option>Next 1&ndash;3 months</option><option>ASAP / urgent</option></select></div>
              <div class="qf-field" id="qf-itScopeWrap"><label class="qf-l" for="qf-itScope">Anything else we should know? <span class="qf-opt">optional</span></label>
                <input id="qf-itScope" class="qf-input" type="text" placeholder="e.g. 12 workstations + a server, want managed support &amp; backups"></div>
            </div>

            <!-- contact capture (needed to book + route internal lead) -->
            <fieldset class="qf-contact" id="qf-contact">
              <legend class="qf-legend">Your details</legend>
              <p class="qf-req">Fields marked <i aria-hidden="true">*</i> are required.</p>
              <div class="qf-grid2">
                <div class="qf-field"><label class="qf-l" for="qf-name">Your name <i aria-hidden="true">*</i></label>
                  <input id="qf-name" class="qf-input" type="text" autocomplete="name" required maxlength="100"></div>
                <div class="qf-field"><label class="qf-l" for="qf-email">Email <i aria-hidden="true">*</i></label>
                  <input id="qf-email" class="qf-input" type="email" autocomplete="email" required maxlength="254"></div>
              </div>
              <div class="qf-grid2">
                <div class="qf-field"><label class="qf-l" for="qf-phone">Phone <span class="qf-opt">optional</span></label>
                  <input id="qf-phone" class="qf-input" type="tel" autocomplete="tel" maxlength="40"></div>
                <div class="qf-field"><label class="qf-l" for="qf-company">Company <span class="qf-opt">optional</span></label>
                  <input id="qf-company" class="qf-input" type="text" autocomplete="organization" maxlength="120"></div>
              </div>
              <!-- Booking needs these on the call's calendar entry (Cal.com required fields). -->
              <div class="qf-field hidden" id="qf-goalWrap"><label class="qf-l" for="qf-goal">Main goal for the call <i aria-hidden="true">*</i></label>
                <select id="qf-goal" class="qf-select"><?php echo uls_estimator_options_html( $opts['goal'] ); // phpcs:ignore ?></select></div>
              <div class="qf-grid2 hidden" id="qf-siteWrap">
                <div class="qf-field"><label class="qf-l" for="qf-site">Site address or location <i aria-hidden="true">*</i></label>
                  <input id="qf-site" class="qf-input" type="text" autocomplete="street-address" maxlength="200" placeholder="Street address or nearest town"></div>
                <div class="qf-field"><label class="qf-l" for="qf-siteType">Site type <i aria-hidden="true">*</i></label>
                  <select id="qf-siteType" class="qf-select"><?php echo uls_estimator_options_html( $opts['site-type'] ); // phpcs:ignore ?></select></div>
              </div>
              <?php $trap = 'f' . substr( md5( uniqid( '', true ) ), 0, 10 ); // Random, meaningless name: nothing for autofill to match (review L3). ?>
              <div class="qf-hp" aria-hidden="true"><input type="text" name="<?php echo esc_attr( $trap ); ?>" id="<?php echo esc_attr( $trap ); ?>" data-qf-trap="1" tabindex="-1" autocomplete="off" value=""></div>
              <p class="qf-help">Used only to book your call and reply to you.</p>
              <p class="qf-cerr hidden" id="qf-cerr" role="alert"><?php echo uls_estimator_icon( 'alert', 16 ); // phpcs:ignore ?><span id="qf-cerrText">Please add your name and a valid email so we can prepare your consultation.</span></p>
            </fieldset>
          </div>

          <aside class="qf-s2side" aria-label="Your estimate">
            <div data-side="uav" class="hidden">
              <div class="qf-readout" id="qf-uavReadout" aria-live="polite">
                <div class="qf-rk" id="qf-uavRk">Indicative ballpark</div>
                <div class="qf-rv" id="qf-uavRv">&mdash;</div>
                <div class="qf-rn" id="qf-uavRn"></div>
              </div>
              <div id="qf-uavContact" class="qf-scopebox hidden"><b>Bespoke aerial work.</b> Tower, industrial and specialty jobs are scoped on a quick call — no instant number, we&#8217;ll tailor it.</div>
            </div>
            <div data-side="it" class="hidden">
              <div class="qf-readout it hidden" id="qf-itReadout" aria-live="polite">
                <div class="qf-rk it" id="qf-itRk">Estimated monthly range</div>
                <div class="qf-rv"><span id="qf-itRvNum">&mdash;</span> <span class="qf-unit" id="qf-itUnit">/month</span></div>
                <div class="qf-rn" id="qf-itRn"></div>
              </div>
              <div class="qf-scopebox hidden" id="qf-itIntake">
                <b>One-time build? Let&#8217;s scope it live.</b> One-time projects are quoted after a short <b>intake appointment</b> &mdash; so we get the details right instead of guessing a number. Nothing to enter here; just continue and pick a time in the next step.
              </div>
              <div class="qf-scopebox hidden" id="qf-itScopebox">
                <b>Not sure yet? No problem.</b> When it&#8217;s a mix, the variables (headcount, existing kit, integrations) move the number too much for an honest instant figure &mdash; so instead of a made-up number, we&#8217;ll prepare a <b>tailored quote</b> and confirm scope on a short call.
              </div>
            </div>

            <!-- combined summary (both-selected only) -->
            <div class="qf-combo hidden" id="qf-combo">
              <div class="qf-comboHead">Your combined estimate</div>
              <div class="qf-comboGrid">
                <div class="qf-comboCard uav"><div class="qf-comboK">Drone / UAV</div><div class="qf-comboV" id="qf-comboUav">&mdash;</div><div class="qf-comboU" id="qf-comboUavU"></div></div>
                <div class="qf-comboCard it"><div class="qf-comboK">IT / technology</div><div class="qf-comboV" id="qf-comboIt">&mdash;</div><div class="qf-comboU" id="qf-comboItU"></div></div>
              </div>
              <p class="qf-comboNote">Two separate lines &mdash; aerial work is a one-time project, managed IT support may be monthly. A person confirms both on a short scoping call; nothing here is a binding quote.</p>
            </div>
          </aside>
        </div>
        <p class="qf-fineprint" id="qf-s2fine">Whatever we estimate here is a starting point for the conversation &mdash; a person always confirms the real number. Continue to book a free consultation and lock in your details.</p>
      </div>
      <div class="qf-mini" id="qf-mini" aria-hidden="true"></div>
      <div class="qf-actions">
        <button class="qf-btn ghost" type="button" data-back="1" id="qf-back2">Back</button>
        <span class="qf-note">A person always confirms the real number.</span>
        <button class="qf-btn primary" id="qf-to3" type="button">Continue to scheduling</button>
      </div>
    </section>

    <!-- STEP 3 -->
    <section class="qf-pane hidden" data-pane="3" aria-labelledby="qf-s3title">
      <div id="qf-schedWrap">
        <div class="qf-body">
          <p class="qf-eyebrow" id="qf-s3eyebrow">Almost there</p>
          <h2 class="qf-h1" id="qf-s3title">Book your free consultation</h2>
          <p class="qf-lede" id="qf-s3lede">Pick a time that works and we&#8217;ll come prepared with your project details in hand.</p>
          <div class="qf-q3grid">
            <div class="qf-cal">
              <div class="qf-calhead">
                <h3 class="qf-month" id="qf-month" aria-live="polite">&nbsp;</h3>
                <div class="qf-calnav">
                  <button type="button" class="qf-iconbtn" id="qf-prev" aria-label="Previous month"><?php echo uls_estimator_icon( 'left', 16 ); // phpcs:ignore ?></button>
                  <button type="button" class="qf-iconbtn" id="qf-next" aria-label="Next month"><?php echo uls_estimator_icon( 'right', 16 ); // phpcs:ignore ?></button>
                </div>
              </div>
              <table class="qf-grid" role="grid" aria-labelledby="qf-month">
                <thead><tr><th scope="col" abbr="Sunday">Su</th><th scope="col" abbr="Monday">Mo</th><th scope="col" abbr="Tuesday">Tu</th><th scope="col" abbr="Wednesday">We</th><th scope="col" abbr="Thursday">Th</th><th scope="col" abbr="Friday">Fr</th><th scope="col" abbr="Saturday">Sa</th></tr></thead>
                <tbody id="qf-days"></tbody>
              </table>
              <p class="qf-tz"><?php echo uls_estimator_icon( 'globe', 16 ); // phpcs:ignore ?><span>Times shown in <b id="qf-tzName">your time zone</b> &middot; <button type="button" class="qf-linkbtn" id="qf-tzChange" aria-expanded="false" aria-controls="qf-tzPick">Change</button></span></p>
              <div class="qf-field qf-tzpick hidden" id="qf-tzPick"><label class="qf-l" for="qf-tz">Time zone</label><select id="qf-tz" class="qf-select"></select></div>
            </div>

            <div class="qf-slots" id="qf-slotCol" aria-live="polite">
              <h3 class="qf-dayh" id="qf-dayTitle">&nbsp;</h3>
              <p class="qf-sub" id="qf-daySub"></p>
              <div class="qf-slotlist" id="qf-slotList" role="group" aria-labelledby="qf-dayTitle"></div>
              <div class="qf-state hidden" id="qf-stLoading" aria-busy="true"><span class="qf-sr">Finding open times…</span><div class="qf-sk"></div><div class="qf-sk"></div><div class="qf-sk"></div></div>
              <div class="qf-state hidden" id="qf-stEmpty">
                <h4 id="qf-emptyTitle">No times on this day</h4>
                <p>Try the next available day — or we can find a time by email.</p>
                <button type="button" class="qf-btn ghost" id="qf-nextAvail">Next available</button>
                <a class="qf-tlink" href="mailto:<?php echo esc_attr( $email ); ?>">Email us instead <?php echo uls_estimator_icon( 'right', 16 ); // phpcs:ignore ?></a>
              </div>
              <div class="qf-state hidden" id="qf-cal-fallback">
                <div class="qf-alert" role="alert"><?php echo uls_estimator_icon( 'alert', 18 ); // phpcs:ignore ?><span><b>Error:</b> <span id="qf-errText">we couldn&#8217;t load the calendar just now. Your estimate and details are saved.</span></span></div>
                <a id="qf-cal-fallback-link" class="qf-btn primary" href="<?php echo esc_url( $it_url ); ?>" target="_blank" rel="noopener" data-uls-book-direct="1">Book on book.uplinksync.com</a>
                <p>Or email <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php if ( '' !== $phone ) : ?> or call <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?> — we reply within one business day.</p>
              </div>
              <div class="qf-sumcard" id="qf-sum">
                <div><?php echo uls_estimator_icon( 'user', 16 ); // phpcs:ignore ?><span>Booking as <b id="qf-sumName"></b> &middot; <span id="qf-sumEmail"></span></span></div>
                <div id="qf-sumEstRow"><?php echo uls_estimator_icon( 'card', 16 ); // phpcs:ignore ?><span>Estimate attached: <b id="qf-sumEst"></b></span></div>
              </div>
            </div>
          </div>
          <p class="qf-fineprint" id="qf-s3fine">Your details and estimate travel with the booking so we arrive prepared. A person always confirms your final price &mdash; nothing here is a binding quote.</p>
        </div>
        <div class="qf-actions">
          <button class="qf-btn ghost" type="button" data-back="2">Back</button>
          <span class="qf-note" id="qf-selNote" aria-live="polite">Pick a day and a time.</span>
          <button class="qf-btn primary" id="qf-confirm" type="button" aria-disabled="true">Confirm booking</button>
        </div>
      </div>

      <div id="qf-doneWrap" class="hidden">
        <div class="qf-body">
          <div class="qf-confirm">
            <span class="qf-big"><?php echo uls_estimator_icon( 'check', 36 ); // phpcs:ignore ?></span>
            <p class="qf-okline"><?php echo uls_estimator_icon( 'check', 16 ); // phpcs:ignore ?>Booking confirmed</p>
            <h2 class="qf-h1" id="qf-doneTitle" tabindex="-1">You&#8217;re booked.</h2>
            <p class="qf-lede">We&#8217;ll send a calendar invite and a summary of what you told us to <b id="qf-doneEmail"></b>. Talk soon.</p>
            <div class="qf-okcard">
              <div><?php echo uls_estimator_icon( 'cal', 18 ); // phpcs:ignore ?><span><b id="qf-doneWhen"></b> <span id="qf-doneTz"></span></span></div>
              <div><?php echo uls_estimator_icon( 'video', 18 ); // phpcs:ignore ?><span id="qf-doneWhat">Free consultation with Doug, UplinkSync (video call)</span></div>
              <div id="qf-doneEstRow"><?php echo uls_estimator_icon( 'card', 18 ); // phpcs:ignore ?><span>Your estimate: <span id="qf-doneEst"></span> (not a quote)</span></div>
            </div>
            <div class="qf-okacts"><a class="qf-btn ghost" id="qf-gcal" href="#" target="_blank" rel="noopener">Add to Google Calendar</a><a class="qf-btn ghost" id="qf-ics" href="#" download="uplinksync-call.ics">Download .ics</a></div>
          </div>
        </div>
        <div class="qf-actions qf-actions--end">
          <button class="qf-btn primary" type="button" id="qf-done" data-uqm-close="1">Done</button>
        </div>
      </div>
    </section>
  </div>
  <noscript><p class="qf-noscript">JavaScript is off, so the estimator can&#8217;t run here. Book a free consultation directly at <a href="<?php echo esc_url( $it_url ); ?>" data-uls-book-direct="1">book.uplinksync.com</a> or email <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>.</p></noscript>
</div>
	<?php
	return ob_get_clean();
}

/**
 * The site-wide modal (snippet 84), printed in wp_footer on every front-end page except
 * /request-quote/, which renders the flow inline as the no-JS fallback.
 *
 * @return string
 */
function uls_estimator_modal_markup() {
	return '<div class="uls-qmodal" id="uls-qmodal" hidden>'
		. '<div class="uls-qmodal__ov" data-uqm-close="1"></div>'
		. '<div class="uls-qmodal__dlg" role="dialog" aria-modal="true" aria-label="Request a quote" tabindex="-1">'
		. '<button type="button" class="uls-qmodal__x" data-uqm-close="1" aria-label="Close">'
		. '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
		. '<div class="uls-qmodal__scroll">' . uls_estimator_flow_markup() . '</div>'
		. '</div></div>';
}
