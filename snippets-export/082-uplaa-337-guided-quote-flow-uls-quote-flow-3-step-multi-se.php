<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 82
 * name  : UPLAA-337 Guided quote flow [uls_quote_flow] (3-step: multi-select → ballpark/scope → cal.com embed)
 * scope : global
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/* UPLAA-337 Guided quote flow — shortcode [uls_quote_flow]
 *
 * Owner-approved guided replacement for the single-screen configurator.
 * - Step 1: multi-select (Drone/UAV, IT) — either/both. `preset` attr skips it.
 * - Step 2: UAV instant ballpark (reuses the LIVE ULS_ESTIMATOR_CONFIG / EMBEDDED
 *   rate model from Snippet #81 VERBATIM — no new rates; §5 disclaimer verbatim,
 *   UPLAA-370). IT path mirrors the UX but shows NO price (scope, not a number).
 *   Both = UAV ballpark + IT scope + scoping-call nudge.
 * - Step 3: cal.com inline embed (book.uplinksync.com) with name/email/notes
 *   prefill; UAV-only -> team/uplinksync/uav-consult, IT-only or both -> team/uplinksync/it-consult.
 *   Graceful "Book your consultation" button fallback if the embed is blocked.
 * - On Continue-to-scheduling it fires the existing uls337_target_quote admin-ajax
 *   handler (Snippet #81) so Doug still gets the INTERNAL target quote. The customer
 *   NEVER sees a binding price — only a soft acknowledgement + the scheduler.
 *
 * Reversible: this snippet is additive. Snippet #81 stays active-but-unreferenced
 * as instant fallback. Revert = point page 997 content back to [uls_quote_configurator].
 */
add_shortcode( 'uls_quote_flow', function ( $atts ) {
	$atts   = shortcode_atts( array( 'preset' => '' ), $atts, 'uls_quote_flow' );
	$preset = in_array( $atts['preset'], array( 'uav', 'it' ), true ) ? $atts['preset'] : '';
	$endpoint  = esc_url_raw( admin_url( 'admin-ajax.php' ) );
	$calorigin = 'https://book.uplinksync.com';
	ob_start();
	?>
<div class="uls-qf" id="uls-qf" data-preset="<?php echo esc_attr( $preset ); ?>" data-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-calorigin="<?php echo esc_attr( $calorigin ); ?>">
  <div class="qf-console">
    <div class="qf-stepper" id="qf-stepper">
      <div class="qf-step on" data-s="1"><span class="qf-num">1</span><span class="qf-lbl">Project type</span></div>
      <span class="qf-bar"></span>
      <div class="qf-step" data-s="2"><span class="qf-num">2</span><span class="qf-lbl">Your estimate</span></div>
      <span class="qf-bar"></span>
      <div class="qf-step" data-s="3"><span class="qf-num">3</span><span class="qf-lbl">Book a time</span></div>
    </div>

    <!-- STEP 1 -->
    <section class="qf-pane" data-pane="1">
      <p class="qf-eyebrow">Start with a free consultation</p>
      <h2 class="qf-h1">What kind of project can we help with?</h2>
      <p class="qf-lede">Pick one — or both. We'll tailor the next step to what you need. No obligation, and nothing you enter here is a commitment.</p>
      <div class="qf-choices">
        <button class="qf-choice uav" type="button" aria-pressed="false" data-pick="uav">
          <span class="qf-check" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12"><path d="M2 6.5 4.6 9 10 3" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span class="qf-ic" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12v6"/><circle cx="12" cy="12" r="1.6"/><path d="M4 6h6l2 3M20 6h-6M4 6 2 4M20 6l2-2M8 6 6 4M16 6l2-2"/></svg></span>
          <span class="qf-ch3">Drone / UAV work</span>
          <span class="qf-cp">Aerial photography, mapping &amp; surveying, roof &amp; structure inspection.</span>
        </button>
        <button class="qf-choice it" type="button" aria-pressed="false" data-pick="it">
          <span class="qf-check" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12"><path d="M2 6.5 4.6 9 10 3" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span class="qf-ic" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4M7 8h4M7 11h6"/></svg></span>
          <span class="qf-ch3">IT / technology</span>
          <span class="qf-cp">Managed IT, automation &amp; AI, web development and support.</span>
        </button>
      </div>
      <p class="qf-selhint" id="qf-selhint">Select at least one to continue.</p>
      <div class="qf-actions">
        <span class="qf-spacer"></span>
        <button class="qf-btn primary" id="qf-to2" type="button" disabled>Continue</button>
      </div>
    </section>

    <!-- STEP 2 -->
    <section class="qf-pane hidden" data-pane="2">
      <p class="qf-eyebrow" id="qf-s2eyebrow">Your instant ballpark</p>
      <h2 class="qf-h1" id="qf-s2title">Tell us a little more</h2>
      <p class="qf-lede" id="qf-s2lede"></p>

      <!-- track tabs (both-selected only) -->
      <div class="qf-tabs hidden" id="qf-tabs" role="tablist">
        <button class="qf-tab on" type="button" data-tab="uav" role="tab">Drone / UAV</button>
        <button class="qf-tab" type="button" data-tab="it" role="tab">IT / technology</button>
      </div>

      <!-- UAV block -->
      <div id="qf-uavBlock" class="hidden">
        <div class="qf-section-head"><span class="qf-pill uav">Drone / UAV</span></div>
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
        <div class="qf-readout" id="qf-uavReadout" aria-live="polite">
          <div class="qf-rk" id="qf-uavRk">Indicative ballpark</div>
          <div class="qf-rv" id="qf-uavRv">&mdash;</div>
          <div class="qf-rn" id="qf-uavRn"></div>
        </div>
      </div>

      <!-- IT block -->
      <div id="qf-itBlock" class="hidden">
        <div class="qf-section-head"><span class="qf-pill it">IT / technology</span></div>
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
        <div class="qf-readout it hidden" id="qf-itReadout" aria-live="polite">
          <div class="qf-rk it" id="qf-itRk">Estimated monthly range</div>
          <div class="qf-rv"><span id="qf-itRvNum">&mdash;</span> <span class="qf-unit" id="qf-itUnit">/month</span></div>
          <div class="qf-rn" id="qf-itRn"></div>
        </div>
        <div class="qf-scopebox hidden" id="qf-itIntake">
          <b>One-time build? Let's scope it live.</b> One-time projects are quoted after a short <b>intake appointment</b> &mdash; so we get the details right instead of guessing a number. Nothing to enter here; just continue and pick a time in the next step.
        </div>
        <div class="qf-scopebox hidden" id="qf-itScopebox">
          <b>Not sure yet? No problem.</b> When it's a mix, the variables (headcount, existing kit, integrations) move the number too much for an honest instant figure &mdash; so instead of a made-up number, we'll prepare a <b>tailored quote</b> and confirm scope on a short call.
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

      <!-- contact capture (needed to prefill booking + route internal lead) -->
      <fieldset class="qf-contact" id="qf-contact">
        <legend class="qf-legend">Your details</legend>
        <div class="qf-grid2">
          <div class="qf-field"><label class="qf-l" for="qf-name">Your name</label>
            <input id="qf-name" class="qf-input" type="text" autocomplete="name"></div>
          <div class="qf-field"><label class="qf-l" for="qf-email">Email</label>
            <input id="qf-email" class="qf-input" type="email" autocomplete="email" required></div>
        </div>
        <div class="qf-grid2">
          <div class="qf-field"><label class="qf-l" for="qf-phone">Phone <span class="qf-opt">optional</span></label>
            <input id="qf-phone" class="qf-input" type="tel" autocomplete="tel"></div>
          <div class="qf-field"><label class="qf-l" for="qf-company">Company <span class="qf-opt">optional</span></label>
            <input id="qf-company" class="qf-input" type="text" autocomplete="organization"></div>
        </div>
        <div class="qf-hp" aria-hidden="true"><label>Company website<input type="text" tabindex="-1" autocomplete="off" id="qf-hp"></label></div>
        <p class="qf-cerr hidden" id="qf-cerr">Please add your name and a valid email so we can prepare your consultation.</p>
      </fieldset>

      <div class="qf-actions">
        <button class="qf-btn ghost" data-back="1" id="qf-back2">Back</button>
        <span class="qf-spacer"></span>
        <button class="qf-btn primary" id="qf-to3" type="button">Continue to scheduling</button>
      </div>
      <p class="qf-fineprint" id="qf-s2fine">Whatever we estimate here is a starting point for the conversation &mdash; a person always confirms the real number. Continue to book a free consultation and lock in your details.</p>
    </section>

    <!-- STEP 3 -->
    <section class="qf-pane hidden" data-pane="3">
      <p class="qf-eyebrow">Almost there</p>
      <h2 class="qf-h1" id="qf-s3title">Book your free consultation</h2>
      <p class="qf-lede" id="qf-s3lede">Pick a time that works and we'll come prepared with your project details in hand.</p>

      <div id="qf-schedWrap">
        <div class="qf-calshell">
          <div class="qf-calbar"><span class="qf-g"></span> book.uplinksync.com &middot; <span id="qf-calType">Consultation</span></div>
          <div id="qf-cal-inline" class="qf-calembed"></div>
          <div id="qf-cal-fallback" class="qf-calfallback hidden">
            <p>Your scheduler is ready in a new tab &mdash; your details and estimate come with it.</p>
            <a id="qf-cal-fallback-link" class="qf-btn primary" href="#" target="_blank" rel="noopener">Book your consultation &rarr;</a>
          </div>
        </div>
        <div class="qf-actions">
          <button class="qf-btn ghost" data-back="2">Back</button>
          <span class="qf-spacer"></span>
        </div>
        <p class="qf-fineprint">Your details and estimate travel with the booking so we arrive prepared. A person always confirms your final price &mdash; nothing here is a binding quote.</p>
      </div>

      <div id="qf-doneWrap" class="hidden">
        <div class="qf-confirm">
          <div class="qf-big">&#10003;</div>
          <h2 class="qf-h1">You're booked.</h2>
          <p class="qf-lede" style="margin-inline:auto">We'll send a calendar invite and a summary of what you told us. Talk soon.</p>
        </div>
      </div>
    </section>
  </div>
</div>

<style id="uls-qf-style">
  #uls-qf{--ink:#0F2440;--field:#0B1B33;--card:#FFFFFF;--line:#D8E0EA;--grey:#5B6675;--mute:#8A94A4;
    --teal:#1F7A8C;--teal-2:#2E9DB0;--teal-soft:#E6F2F4;--uav:#2E6E8E;--uav-soft:#E7F1F6;--it:#3A6A57;--it-soft:#E8F2ED;
    --good:#2F8F6B;--warn:#B7791F;--shadow:0 1px 2px rgba(15,36,64,.06),0 12px 34px -14px rgba(15,36,64,.28);--r:14px;--r-s:10px;
    max-width:720px;margin:0 auto;color:var(--ink);line-height:1.5;font-family:inherit;
    -webkit-font-smoothing:antialiased}
  @media (prefers-color-scheme:dark){
    #uls-qf{--ink:#EAF1F8;--field:#0A1626;--card:#101E32;--line:#22344C;--grey:#A9B4C4;--mute:#7C8A9C;
      --teal:#3FB0C6;--teal-2:#5AC6D9;--teal-soft:#12303A;--uav:#5AA6C6;--uav-soft:#122934;--it:#5FB08E;--it-soft:#122A22;
      --shadow:0 1px 2px rgba(0,0,0,.4),0 16px 40px -18px rgba(0,0,0,.7)}
  }
  #uls-qf *{box-sizing:border-box}
  #uls-qf .hidden{display:none!important}
  #uls-qf .qf-console{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden}
  #uls-qf .qf-stepper{display:flex;gap:2px;padding:14px 18px;border-bottom:1px solid var(--line)}
  #uls-qf .qf-step{flex:1;display:flex;align-items:center;gap:9px;font-size:.82rem;color:var(--mute);min-width:0}
  #uls-qf .qf-num{flex:0 0 auto;width:23px;height:23px;border-radius:50%;display:grid;place-items:center;
    border:1.5px solid var(--line);font-size:.74rem;font-weight:700;color:var(--mute);background:var(--card);transition:.25s}
  #uls-qf .qf-lbl{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
  #uls-qf .qf-step.on{color:var(--ink)} #uls-qf .qf-step.on .qf-num{border-color:var(--teal);color:#fff;background:var(--teal)}
  #uls-qf .qf-step.done .qf-num{border-color:var(--teal);color:var(--teal);background:var(--teal-soft)}
  #uls-qf .qf-bar{flex:1;height:1.5px;background:var(--line);margin:0 2px;border-radius:2px;align-self:center}
  @media(max-width:560px){#uls-qf .qf-lbl{display:none}#uls-qf .qf-bar{display:none}}
  #uls-qf .qf-pane{padding:clamp(20px,4.5vw,38px)}
  #uls-qf .qf-eyebrow{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;font-weight:800;color:var(--teal);margin:0 0 8px}
  #uls-qf .qf-h1{font-size:clamp(1.5rem,4vw,2.05rem);line-height:1.12;margin:0 0 10px;letter-spacing:-.02em;color:var(--ink)}
  #uls-qf .qf-lede{color:var(--grey);margin:0 0 24px;font-size:1.02rem;max-width:52ch}
  #uls-qf .qf-choices{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:520px){#uls-qf .qf-choices{grid-template-columns:1fr}}
  #uls-qf .qf-choice{position:relative;text-align:left;cursor:pointer;border:1.5px solid var(--line);background:var(--card);
    border-radius:var(--r);padding:18px 18px 16px;transition:.18s;display:flex;flex-direction:column;gap:8px;width:100%;font-family:inherit;color:var(--ink)}
  #uls-qf .qf-choice:hover{border-color:color-mix(in srgb,var(--teal) 55%,var(--line));transform:translateY(-2px)}
  #uls-qf .qf-choice:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
  #uls-qf .qf-choice .qf-ic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;color:#fff}
  #uls-qf .qf-choice.uav .qf-ic{background:linear-gradient(140deg,var(--uav),color-mix(in srgb,var(--uav) 60%,#000))}
  #uls-qf .qf-choice.it .qf-ic{background:linear-gradient(140deg,var(--it),color-mix(in srgb,var(--it) 60%,#000))}
  #uls-qf .qf-ch3{margin:2px 0 0;font-size:1.08rem;font-weight:700;letter-spacing:-.01em}
  #uls-qf .qf-cp{margin:0;color:var(--grey);font-size:.9rem}
  #uls-qf .qf-check{position:absolute;top:14px;right:14px;width:22px;height:22px;border-radius:50%;
    border:1.5px solid var(--line);display:grid;place-items:center;transition:.18s;background:transparent}
  #uls-qf .qf-choice[aria-pressed="true"]{border-color:var(--teal);background:linear-gradient(color-mix(in srgb,var(--teal-soft) 60%,transparent),transparent)}
  #uls-qf .qf-choice[aria-pressed="true"] .qf-check{background:var(--teal);border-color:var(--teal)}
  #uls-qf .qf-choice[aria-pressed="true"] .qf-check svg{opacity:1}
  #uls-qf .qf-check svg{opacity:0;transition:.15s}
  #uls-qf .qf-selhint{margin:16px 0 0;font-size:.84rem;color:var(--mute)}
  #uls-qf .qf-field{margin:0 0 15px}
  #uls-qf label.qf-l{display:block;font-weight:650;font-size:.92rem;margin:0 0 6px;color:var(--ink)}
  #uls-qf .qf-opt{color:var(--mute);font-weight:400}
  #uls-qf .qf-select,#uls-qf .qf-input{width:100%;font-family:inherit;font-size:16px;padding:11px 13px;border:1.5px solid var(--line);
    border-radius:var(--r-s);background:var(--card);color:var(--ink)}
  #uls-qf .qf-select:focus,#uls-qf .qf-input:focus{outline:2px solid var(--teal);outline-offset:1px;border-color:var(--teal)}
  #uls-qf .qf-check-row{display:flex;align-items:flex-start;gap:10px;padding:11px 13px;border:1.5px solid var(--line);
    border-radius:var(--r-s);cursor:pointer;margin:0 0 10px}
  #uls-qf .qf-check-row input{width:18px;height:18px;margin-top:1px;flex:0 0 auto}
  #uls-qf .qf-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:520px){#uls-qf .qf-grid2{grid-template-columns:1fr}}
  #uls-qf .qf-section-head{display:flex;align-items:center;gap:9px;margin:6px 0 14px}
  #uls-qf .qf-pill{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:4px 9px;border-radius:999px}
  #uls-qf .qf-pill.uav{background:var(--uav-soft);color:var(--uav)} #uls-qf .qf-pill.it{background:var(--it-soft);color:var(--it)}
  #uls-qf .qf-divider{height:1px;background:var(--line);margin:26px 0}
  #uls-qf .qf-readout{border:1.5px solid color-mix(in srgb,var(--uav) 40%,var(--line));background:var(--uav-soft);
    border-radius:var(--r);padding:18px 20px;margin:6px 0}
  #uls-qf .qf-rk{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--uav);font-weight:800}
  #uls-qf .qf-rv{font-size:2rem;font-weight:800;letter-spacing:-.02em;margin:4px 0 2px;font-variant-numeric:tabular-nums;color:var(--ink)}
  #uls-qf .qf-rn{font-size:.86rem;color:var(--grey)}
  #uls-qf .qf-readout.it{border-color:color-mix(in srgb,var(--it) 40%,var(--line));background:var(--it-soft)}
  #uls-qf .qf-rk.it{color:var(--it)}
  #uls-qf .qf-unit{font-size:.95rem;font-weight:700;color:var(--grey);letter-spacing:0;font-variant-numeric:normal}
  #uls-qf .qf-tabs{display:flex;gap:6px;background:var(--field);padding:5px;border-radius:var(--r-s);margin:0 0 22px}
  #uls-qf .qf-tab{flex:1;font-family:inherit;font-weight:700;font-size:.92rem;padding:10px 12px;border-radius:8px;border:0;cursor:pointer;background:transparent;color:#AAB6C6;transition:.15s}
  #uls-qf .qf-tab.on{background:var(--card);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.18)}
  #uls-qf .qf-tab:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
  #uls-qf .qf-engage{margin:0 0 15px}
  #uls-qf .qf-seg{display:inline-flex;gap:4px;background:var(--it-soft);padding:4px;border-radius:var(--r-s);border:1.5px solid color-mix(in srgb,var(--it) 30%,var(--line));width:100%}
  #uls-qf .qf-segbtn{flex:1;font-family:inherit;font-weight:700;font-size:.9rem;padding:9px 12px;border-radius:7px;border:0;cursor:pointer;background:transparent;color:var(--grey);transition:.15s}
  #uls-qf .qf-segbtn.on{background:var(--card);color:var(--it);box-shadow:0 1px 3px rgba(0,0,0,.15)}
  #uls-qf .qf-segbtn:focus-visible{outline:2px solid var(--it);outline-offset:2px}
  #uls-qf .qf-combo{border:1.5px solid var(--line);border-radius:var(--r);padding:16px 18px;margin:18px 0 0;background:var(--card)}
  #uls-qf .qf-comboHead{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;font-weight:800;color:var(--teal);margin:0 0 12px}
  #uls-qf .qf-comboGrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:520px){#uls-qf .qf-comboGrid{grid-template-columns:1fr}}
  #uls-qf .qf-comboCard{border:1.5px solid var(--line);border-radius:var(--r-s);padding:13px 15px}
  #uls-qf .qf-comboCard.uav{border-color:color-mix(in srgb,var(--uav) 35%,var(--line));background:var(--uav-soft)}
  #uls-qf .qf-comboCard.it{border-color:color-mix(in srgb,var(--it) 35%,var(--line));background:var(--it-soft)}
  #uls-qf .qf-comboK{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;font-weight:800;color:var(--grey)}
  #uls-qf .qf-comboV{font-size:1.3rem;font-weight:800;letter-spacing:-.01em;margin:3px 0 1px;font-variant-numeric:tabular-nums;color:var(--ink)}
  #uls-qf .qf-comboU{font-size:.78rem;color:var(--grey)}
  #uls-qf .qf-comboNote{font-size:.8rem;color:var(--mute);margin:12px 0 0}
  #uls-qf .qf-scopebox{border:1.5px dashed color-mix(in srgb,var(--it) 50%,var(--line));background:var(--it-soft);
    border-radius:var(--r);padding:16px 18px;color:var(--ink)}
  #uls-qf .qf-scopebox b{color:var(--it)}
  #uls-qf .qf-note{display:flex;gap:10px;background:color-mix(in srgb,var(--warn) 12%,transparent);color:var(--warn);
    border-left:3px solid var(--warn);border-radius:0 8px 8px 0;padding:11px 14px;font-size:.9rem;margin:14px 0}
  #uls-qf .qf-note span{color:var(--ink)}
  #uls-qf .qf-contact{border:1.5px solid var(--line);border-radius:var(--r);padding:16px 18px 4px;margin:18px 0 0}
  #uls-qf .qf-legend{font-weight:700;color:var(--ink);font-size:1.02rem;padding:0 6px}
  #uls-qf .qf-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
  #uls-qf .qf-cerr{color:#b3261e;font-size:.86rem;margin:2px 0 12px}
  @media (prefers-color-scheme:dark){#uls-qf .qf-cerr{color:#ff9a90}}
  #uls-qf .qf-actions{display:flex;gap:12px;margin-top:26px;flex-wrap:wrap;align-items:center}
  #uls-qf .qf-btn{font-family:inherit;font-weight:700;font-size:1rem;border-radius:var(--r-s);padding:13px 22px;cursor:pointer;border:1.5px solid transparent;transition:.16s;text-decoration:none;display:inline-block}
  #uls-qf .qf-btn.primary{background:var(--teal);color:#fff}#uls-qf .qf-btn.primary:hover{background:var(--teal-2)}
  #uls-qf .qf-btn.primary:disabled{opacity:.45;cursor:not-allowed}
  #uls-qf .qf-btn.ghost{background:transparent;color:var(--ink);border-color:var(--line)}#uls-qf .qf-btn.ghost:hover{border-color:var(--teal)}
  #uls-qf .qf-btn:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
  #uls-qf .qf-spacer{flex:1}
  #uls-qf .qf-fineprint{font-size:.78rem;color:var(--mute);margin:14px 0 0;max-width:56ch}
  #uls-qf .qf-calshell{border:1.5px solid var(--line);border-radius:var(--r);overflow:hidden}
  #uls-qf .qf-calbar{display:flex;align-items:center;gap:8px;padding:11px 15px;background:var(--field);color:#EAF1F8;font-size:.82rem}
  #uls-qf .qf-calbar .qf-g{width:9px;height:9px;border-radius:50%;background:var(--good)}
  #uls-qf .qf-calembed{min-height:600px;background:var(--card)}
  #uls-qf .qf-calembed iframe{width:100%!important;min-height:600px;border:0}
  #uls-qf .qf-calfallback{padding:26px 20px;text-align:center;color:var(--grey)}
  #uls-qf .qf-calfallback p{margin:0 0 14px}
  #uls-qf .qf-confirm{text-align:center;padding:14px 6px 4px}
  #uls-qf .qf-big{width:60px;height:60px;border-radius:50%;background:var(--teal-soft);color:var(--teal);
    display:grid;place-items:center;margin:0 auto 14px;font-size:1.7rem}
  @media (prefers-reduced-motion:no-preference){
    #uls-qf .qf-pane{animation:qffade .3s ease}
    @keyframes qffade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  }
</style>

<script>
(function(){
  "use strict";
  /* Rate model — reused VERBATIM from the live ULS_ESTIMATOR_CONFIG / Snippet #81 EMBEDDED (UPLAA-330).
     No new rates. §5 disclaimer verbatim (UPLAA-370). Prefers window.ULS_ESTIMATOR_CONFIG if present. */
  var EMBEDDED = {
    currency: "$",
    travel: { freeRadiusMi: 25, perMileOver: 0.75 },
    rush:   { standard: 1.00, rush: 1.30, same_day: 1.50 },
    disclaimer: "Estimate only — a starting figure based on typical projects, not a quote. Your firm price is confirmed by a UplinkSync team member and may differ with site conditions, access, and deliverables.",
    lines: {
      real_estate: { posture: "band", label: "Real-estate / property photography", base: 175, bandSpread: 0.35,
        options: [
          { id: "sqft", label: "Property size", type: "select", choices: [
            { v: "small",  label: "Under 2,000 sq ft (home/lot)", add: 0 },
            { v: "medium", label: "2,000-5,000 sq ft or acreage", add: 75 },
            { v: "large",  label: "Estate / commercial parcel",   add: 200 } ]},
          { id: "video", label: "Add cinematic video reel", type: "check", add: 150 },
          { id: "twilight", label: "Twilight / golden-hour session", type: "check", add: 90 } ] },
      mapping: { posture: "starting", label: "Mapping & surveying", base: 500, startingSpread: 0.40,
        options: [
          { id: "acres", label: "Approximate acreage", type: "select", choices: [
            { v: "s",  label: "Up to 20 acres",  add: 0 },
            { v: "m",  label: "20-100 acres",    add: 250 },
            { v: "l",  label: "Over 100 acres",  add: 750 } ]},
          { id: "deliverable", label: "Deliverable", type: "select", choices: [
            { v: "ortho", label: "2D orthomosaic map", add: 0 },
            { v: "model", label: "3D model / point cloud", add: 300 },
            { v: "both",  label: "Both", add: 450 } ]} ] },
      inspection: { posture: "starting", label: "Roof / structure inspection", base: 300, startingSpread: 0.40,
        options: [
          { id: "structures", label: "Number of structures", type: "select", choices: [
            { v: "1", label: "1 structure",  add: 0 },
            { v: "2", label: "2-3 structures", add: 150 },
            { v: "4", label: "4+ structures", add: 350 } ]},
          { id: "report",  label: "Formal written report", type: "check", add: 120 } ] },
      tower: { posture: "contact", label: "Tower / industrial / other" }
    }
  };

  var root = document.getElementById("uls-qf");
  if (!root || root.getAttribute("data-init") === "1") return;
  root.setAttribute("data-init", "1");
  var cfg = (window.ULS_ESTIMATOR_CONFIG && window.ULS_ESTIMATOR_CONFIG.lines) ? window.ULS_ESTIMATOR_CONFIG : EMBEDDED;
  if (cfg.lines.tower && !cfg.lines.tower.label) cfg.lines.tower.label = "Tower / industrial / other";

  var endpoint  = root.getAttribute("data-endpoint");
  var calOrigin = root.getAttribute("data-calorigin");
  var preset    = root.getAttribute("data-preset") || "";
  var $  = function(s){ return root.querySelector(s); };
  var $$ = function(s){ return Array.prototype.slice.call(root.querySelectorAll(s)); };
  var val = function(s){ var e=$(s); return e ? (e.value||"").trim() : ""; };
  var fmt = function(n){ return cfg.currency + Math.round(n).toLocaleString("en-US"); };
  var picks = { uav:false, it:false };

  var IT_AREAS = { managed:"Managed IT / support", automation:"Automation & AI", web:"Web development", mixed:"A mix / not sure yet" };
  var timingLabels = { standard:"Standard scheduling", rush:"Rush (expedited)", same_day:"Same-day" };

  /* IT rate model — UPLAA-337 IT branch, MONTHLY-SUBSCRIPTION framing (2026 SMB defaults).
     SEPARATE from the UAV EMBEDDED / §5 rate model above, which is untouched.
     All numbers are easily owner-adjustable here in one place. Every area is now a
     RECURRING /month subscription and shows an instant /month range:
       - managed    = per-user rate x users + per-server add (Managed IT / support).
       - automation = monthly retainer: base plan + tiered adds (Automation & AI).
       - web        = website-as-a-service: base plan + tiered adds (Web development).
       - mixed      = no instant number; scope on a call.
     Areas with engagement:true also offer a "One-time project" toggle that shows NO
     price and routes to a scoped intake appointment (the Step-3 cal.com consult).
     Customer-facing disclaimer reuses the SAME §5 cfg.disclaimer wording as UAV. */
  var IT_CFG = {
    managed: { posture:"perUser", unit:"/month", label:"Managed IT / support", spread:0.25, perServer:150, engagement:true,
      tiers:[
        { v:"basic",    label:"Help desk / basic support",       rate:60 },
        { v:"managed",  label:"Fully managed",                    rate:150 },
        { v:"security", label:"Managed + security / compliance",  rate:275 } ] },
    automation: { posture:"monthly", unit:"/month", label:"Automation & AI", spread:0.30, engagement:true,
      options:[
        { id:"atype", label:"What kind of automation plan?", type:"select", choices:[
          { v:"workflow", label:"Workflow automation partner (Zapier / Make / n8n)", add:500 },
          { v:"chatbot",  label:"AI assistant / chatbot — managed",                  add:750 },
          { v:"data",     label:"Data / systems integration — managed",              add:1000 },
          { v:"custom",   label:"Custom AI partner retainer",                         add:3000 } ]},
        { id:"complexity", label:"Complexity", type:"select", choices:[
          { v:"simple",   label:"Straightforward",                        add:0 },
          { v:"moderate", label:"Moderate (a few moving parts)",          add:500 },
          { v:"advanced", label:"Advanced (AI reasoning / many systems)", add:1500 } ]},
        { id:"processes", label:"How many processes / integrations maintained?", type:"select", choices:[
          { v:"1", label:"1",   add:0 },
          { v:"2", label:"2-3", add:400 },
          { v:"4", label:"4+",  add:1000 } ]} ] },
    web: { posture:"monthly", unit:"/month", label:"Web development", spread:0.30, engagement:true,
      options:[
        { id:"stype", label:"What kind of site plan?", type:"select", choices:[
          { v:"brochure", label:"Marketing / brochure site (host + maintain)", add:149 },
          { v:"ecom",     label:"E-commerce store — managed",                  add:349 },
          { v:"webapp",   label:"Web app / portal — managed",                  add:900 } ]},
        { id:"pages", label:"Approximate pages", type:"select", choices:[
          { v:"s", label:"Up to 5",  add:0 },
          { v:"m", label:"6-15",     add:50 },
          { v:"l", label:"16+",      add:150 } ]},
        { id:"feat_booking", label:"Booking / scheduling",             type:"check", add:50 },
        { id:"feat_crm",     label:"CRM / customer portal integration", type:"check", add:250 },
        { id:"feat_seo",     label:"Ongoing SEO & content plan",        type:"check", add:100 } ] },
    mixed: { posture:"contact", label:"A mix / not sure yet" }
  };

  /* ---- Step 1 ---- */
  $$(".qf-choice").forEach(function(b){
    b.addEventListener("click", function(){
      var k = b.getAttribute("data-pick"); picks[k] = !picks[k];
      b.setAttribute("aria-pressed", picks[k] ? "true" : "false");
      var any = picks.uav || picks.it;
      $("#qf-to2").disabled = !any;
      $("#qf-selhint").textContent = any ? (picks.uav && picks.it ? "Great — we'll cover both sides." : "Nice. You can add the other too.") : "Select at least one to continue.";
    });
  });
  $("#qf-to2").addEventListener("click", function(){ buildStep2(); go(2); });

  /* ---- Step 2: UAV ---- */
  function renderUavOpts(){
    var line = cfg.lines[$("#qf-uavLine").value], box = $("#qf-uavOpts"); box.innerHTML = "";
    var log = $("#qf-uavLogistics"), ro = $("#qf-uavReadout");
    if (line.posture === "contact"){
      log.classList.add("hidden"); ro.classList.add("hidden");
      box.innerHTML = '<div class="qf-scopebox"><b>Bespoke aerial work.</b> Tower, industrial and specialty jobs are scoped on a quick call — no instant number, we\'ll tailor it.</div>';
      return;
    }
    log.classList.remove("hidden"); ro.classList.remove("hidden");
    (line.options || []).forEach(function(o){
      var f = document.createElement("div"); f.className = "qf-field";
      if (o.type === "select"){
        var h = '<label class="qf-l">'+o.label+'</label><select class="qf-select" data-opt="'+o.id+'" data-optlabel="'+o.label+'">';
        o.choices.forEach(function(c){ h += '<option value="'+c.add+'" data-lbl="'+c.label+'">'+c.label+(c.add?" (+"+fmt(c.add)+")":"")+'</option>'; });
        h += '</select>'; f.innerHTML = h;
      } else {
        f.innerHTML = '<label class="qf-check-row"><input type="checkbox" data-opt="'+o.id+'" data-add="'+o.add+'" data-optlabel="'+o.label+'"><span>'+o.label+' <span class="qf-opt">(+'+fmt(o.add)+')</span></span></label>';
      }
      box.appendChild(f);
    });
    calcUav();
  }
  function computeUav(){
    var line = cfg.lines[$("#qf-uavLine").value];
    var answers = [];
    if (line.posture === "contact"){
      return { range:"", targetLabel:"", answers:answers, contact:true, serviceLabel:line.label };
    }
    var total = line.base;
    answers.push({ label: line.label + " (base)", value: fmt(line.base) });
    $$('#qf-uavOpts [data-opt]').forEach(function(el){
      if (el.tagName === "SELECT"){
        var o = el.options[el.selectedIndex];
        var add = parseFloat(el.value) || 0; total += add;
        answers.push({ label: el.getAttribute("data-optlabel"), value: o.getAttribute("data-lbl") + (add ? " (+"+fmt(add)+")" : "") });
      } else if (el.checked){
        var a = parseFloat(el.getAttribute("data-add")) || 0; total += a;
        answers.push({ label: el.getAttribute("data-optlabel"), value: "Yes (+"+fmt(a)+")" });
      }
    });
    var miles = parseFloat(val("#qf-uavMiles")) || 0;
    var timing = $("#qf-uavTiming").value || "standard";
    var overMi = Math.max(0, miles - cfg.travel.freeRadiusMi);
    var travelFee = overMi * cfg.travel.perMileOver;
    var rushMult = cfg.rush[timing] || 1;
    var withRush = (total + travelFee) * rushMult;
    if (travelFee > 0) answers.push({ label: "Travel ("+overMi+" mi beyond free radius)", value: "+"+fmt(travelFee) });
    if (rushMult > 1) answers.push({ label: "Timing multiplier", value: "x"+rushMult });
    var low, high, tlabel;
    if (line.posture === "band"){ low = withRush*(1-line.bandSpread); high = withRush*(1+line.bandSpread); tlabel = "Indicative ballpark"; }
    else { low = withRush; high = withRush*(1+(line.startingSpread||0.4)); tlabel = "Conservative starting range"; }
    return { range: fmt(low)+" – "+fmt(high), targetLabel: tlabel, answers: answers, contact:false, serviceLabel:line.label,
             timingLabel: timingLabels[timing]||"Standard scheduling", miles: (miles?String(miles):"0") };
  }
  function calcUav(){
    var line = cfg.lines[$("#qf-uavLine").value]; if (line.posture === "contact") return;
    var c = computeUav();
    $("#qf-uavRk").textContent = c.targetLabel;
    $("#qf-uavRv").textContent = c.range;
    $("#qf-uavRn").textContent = cfg.disclaimer || "";
  }

  /* ---- Step 2: IT — MONTHLY-subscription model with a Monthly / One-time toggle.
     Monthly = instant /month range (mirrors UAV UX). One-time = NO price shown,
     scoped on an intake appointment (routes to the Step-3 cal.com consult). ---- */
  var itEngagement = "monthly";
  function itArea(){ return IT_CFG[$("#qf-itLine").value]; }
  function renderItArea(){
    var area = itArea(), eng = $("#qf-itEngage");
    var ro = $("#qf-itReadout"), sb = $("#qf-itScopebox"), intake = $("#qf-itIntake");
    if (!area || area.posture === "contact"){
      eng.classList.add("hidden"); eng.innerHTML = "";
      $("#qf-itOpts").innerHTML = "";
      ro.classList.add("hidden"); intake.classList.add("hidden"); sb.classList.remove("hidden");
      calcCombo();
      return;
    }
    sb.classList.add("hidden");
    itEngagement = "monthly";
    if (area.engagement){
      eng.classList.remove("hidden");
      eng.innerHTML =
        '<div class="qf-seg" role="radiogroup" aria-label="Engagement type">'
        + '<button type="button" class="qf-segbtn on" data-eng="monthly" role="radio" aria-checked="true">Monthly plan</button>'
        + '<button type="button" class="qf-segbtn" data-eng="onetime" role="radio" aria-checked="false">One-time project</button>'
        + '</div>';
    } else { eng.classList.add("hidden"); eng.innerHTML = ""; }
    renderItMode();
  }
  function setEngagement(v){
    itEngagement = v;
    $$("#qf-itEngage .qf-segbtn").forEach(function(b){
      var on = b.getAttribute("data-eng") === v;
      b.classList.toggle("on", on); b.setAttribute("aria-checked", on ? "true" : "false");
    });
    renderItMode();
  }
  function renderItMode(){
    var area = itArea(), box = $("#qf-itOpts"), ro = $("#qf-itReadout"), intake = $("#qf-itIntake");
    box.innerHTML = "";
    if (itEngagement === "onetime"){
      ro.classList.add("hidden"); intake.classList.remove("hidden");
      calcCombo();
      return;
    }
    intake.classList.add("hidden"); ro.classList.remove("hidden");
    if (area.posture === "perUser"){
      var h = '<div class="qf-field"><label class="qf-l">Service level</label><select class="qf-select" data-it="tier" data-optlabel="Service level">';
      area.tiers.forEach(function(t){ h += '<option value="'+t.rate+'" data-lbl="'+t.label+'">'+t.label+' ('+fmt(t.rate)+'/user/mo)</option>'; });
      h += '</select></div>';
      h += '<div class="qf-grid2">';
      h += '<div class="qf-field"><label class="qf-l">How many users / workstations?</label><input class="qf-input" type="number" min="1" step="1" inputmode="numeric" value="5" data-it="users"></div>';
      h += '<div class="qf-field"><label class="qf-l">Servers to manage <span class="qf-opt">optional</span></label><input class="qf-input" type="number" min="0" step="1" inputmode="numeric" value="0" data-it="servers"></div>';
      h += '</div>';
      box.innerHTML = h;
    } else {
      (area.options || []).forEach(function(o){
        var f = document.createElement("div"); f.className = "qf-field";
        if (o.type === "select"){
          var hh = '<label class="qf-l">'+o.label+'</label><select class="qf-select" data-itopt="'+o.id+'" data-optlabel="'+o.label+'">';
          o.choices.forEach(function(c){ hh += '<option value="'+c.add+'" data-lbl="'+c.label+'">'+c.label+(c.add?" (+"+fmt(c.add)+"/mo)":"")+'</option>'; });
          hh += '</select>'; f.innerHTML = hh;
        } else {
          f.innerHTML = '<label class="qf-check-row"><input type="checkbox" data-itopt="'+o.id+'" data-add="'+o.add+'" data-optlabel="'+o.label+'"><span>'+o.label+' <span class="qf-opt">(+'+fmt(o.add)+'/mo)</span></span></label>';
        }
        box.appendChild(f);
      });
    }
    calcIt();
  }
  function computeIt(){
    var area = itArea(), answers = [];
    if (!area || area.posture === "contact"){
      return { mode:"contact", range:"", targetLabel:"", answers:answers, areaLabel:(area?area.label:"IT / technology") };
    }
    if (itEngagement === "onetime"){
      answers.push({ label:"Engagement", value:"One-time project — intake requested (no instant price)" });
      return { mode:"onetime", range:"", targetLabel:"One-time — scoped at intake", answers:answers, areaLabel:area.label };
    }
    if (area.posture === "perUser"){
      var tierEl = $('#qf-itOpts [data-it="tier"]');
      var rate  = tierEl ? (parseFloat(tierEl.value) || 0) : 0;
      var tlbl  = tierEl ? tierEl.options[tierEl.selectedIndex].getAttribute("data-lbl") : "";
      var users = Math.max(1, parseInt(val('#qf-itOpts [data-it="users"]'), 10) || 1);
      var servers = Math.max(0, parseInt(val('#qf-itOpts [data-it="servers"]'), 10) || 0);
      var monthly = rate * users + (area.perServer || 0) * servers;
      answers.push({ label:"Service level", value: tlbl + " ("+fmt(rate)+"/user/mo)" });
      answers.push({ label:"Users / workstations", value: String(users) });
      if (servers > 0) answers.push({ label:"Servers", value: servers + " (+"+fmt(area.perServer)+"/mo ea)" });
      var lo = monthly, hi = monthly * (1 + area.spread);
      return { mode:"monthly", range: fmt(lo)+" – "+fmt(hi), targetLabel:"Estimated monthly range",
               answers:answers, areaLabel:area.label };
    }
    var total = area.base || 0;
    $$('#qf-itOpts [data-itopt]').forEach(function(el){
      if (el.tagName === "SELECT"){
        var o = el.options[el.selectedIndex]; var add = parseFloat(el.value) || 0; total += add;
        answers.push({ label: el.getAttribute("data-optlabel"), value: o.getAttribute("data-lbl") + (add ? " (+"+fmt(add)+"/mo)" : "") });
      } else if (el.checked){
        var a = parseFloat(el.getAttribute("data-add")) || 0; total += a;
        answers.push({ label: el.getAttribute("data-optlabel"), value: "Yes (+"+fmt(a)+"/mo)" });
      }
    });
    var mlo = total, mhi = total * (1 + (area.spread || 0.30));
    return { mode:"monthly", range: fmt(mlo)+" – "+fmt(mhi), targetLabel:"Estimated monthly range",
             answers:answers, areaLabel:area.label };
  }
  function calcIt(){
    var area = itArea();
    if (!area || area.posture === "contact" || itEngagement === "onetime"){ calcCombo(); return; }
    var c = computeIt();
    $("#qf-itRk").textContent = c.targetLabel;
    $("#qf-itRvNum").textContent = c.range;
    $("#qf-itUnit").textContent = "/month";
    $("#qf-itRn").textContent = cfg.disclaimer || "";
    calcCombo();
  }
  function calcCombo(){
    if (!(picks.uav && picks.it)) return;
    var uc = computeUav();
    $("#qf-comboUav").textContent = uc.contact ? "Scoped on a call" : uc.range;
    $("#qf-comboUavU").textContent = uc.contact ? "bespoke / tower" : (uc.targetLabel + " · one-time");
    var ic = computeIt();
    if (ic.mode === "monthly"){
      $("#qf-comboIt").textContent = ic.range;
      $("#qf-comboItU").textContent = "Estimated monthly range · per month";
    } else if (ic.mode === "onetime"){
      $("#qf-comboIt").textContent = "Intake appointment";
      $("#qf-comboItU").textContent = "one-time — scoped on the call";
    } else {
      $("#qf-comboIt").textContent = "Scoped on a call";
      $("#qf-comboItU").textContent = "a mix — tailored quote";
    }
  }

  function buildStep2(){
    var both = picks.uav && picks.it;
    $("#qf-tabs").classList.toggle("hidden", !both);
    $("#qf-combo").classList.toggle("hidden", !both);
    if (both){
      setTab("uav");
    } else {
      $("#qf-uavBlock").classList.toggle("hidden", !picks.uav);
      $("#qf-itBlock").classList.toggle("hidden", !picks.it);
    }
    $("#qf-s2eyebrow").textContent = "Your instant estimate";
    $("#qf-s2title").textContent = "Tell us a little more";
    $("#qf-s2lede").textContent = both
      ? "Two tracks, one flow — switch tabs to answer a couple of quick questions for each. We'll show a starting range for both below."
      : "Answer a couple of quick questions and we'll show a starting range right away.";
    if (picks.uav) renderUavOpts();
    if (picks.it) renderItArea();
    if (both) calcCombo();
  }
  function setTab(which){
    $$("#qf-tabs .qf-tab").forEach(function(t){
      var on = t.getAttribute("data-tab") === which;
      t.classList.toggle("on", on); t.setAttribute("aria-selected", on ? "true" : "false");
    });
    $("#qf-uavBlock").classList.toggle("hidden", which !== "uav");
    $("#qf-itBlock").classList.toggle("hidden", which !== "it");
  }
  $("#qf-uavLine").addEventListener("change", renderUavOpts);
  $("#qf-itLine").addEventListener("change", renderItArea);
  $$("#qf-tabs .qf-tab").forEach(function(t){ t.addEventListener("click", function(){ setTab(t.getAttribute("data-tab")); }); });
  root.addEventListener("click", function(e){
    var seg = e.target.closest && e.target.closest("#qf-itEngage .qf-segbtn");
    if (seg){ setEngagement(seg.getAttribute("data-eng")); }
  });
  ["input","change"].forEach(function(ev){
    root.addEventListener(ev, function(e){
      if (!e.target.closest) return;
      if (e.target.closest("#qf-uavBlock")) calcUav();
      else if (e.target.closest("#qf-itBlock")) calcIt();
    });
  });
  $$('[data-back]').forEach(function(b){ b.addEventListener("click", function(){ go(parseInt(b.getAttribute("data-back"),10)); }); });

  /* ---- contact + lead ---- */
  function contactOk(){
    var name = val("#qf-name"), email = val("#qf-email");
    var ok = name.length > 0 && email.indexOf("@") > 0 && email.indexOf(".") > 0;
    $("#qf-cerr").classList.toggle("hidden", ok);
    if (!ok){ (name ? $("#qf-email") : $("#qf-name")).focus(); }
    return ok;
  }
  function itAreaLabel(){ return IT_AREAS[$("#qf-itLine").value] || "IT / technology"; }
  function itTargetPhrase(ic){
    if (ic.mode === "monthly") return ic.range + " /month (" + ic.targetLabel + ")";
    if (ic.mode === "onetime") return "one-time — intake requested (no instant price)";
    return "a mix (tailored quote)";
  }
  function buildLead(){
    var both = picks.uav && picks.it;
    var service, timingLabel = "n/a", miles = "n/a", target = "", targetLabel = "";
    var answers = [];
    var uc = picks.uav ? computeUav() : null;
    var ic = picks.it ? computeIt() : null;
    var uavTarget = "", itTarget = "";
    if (picks.uav){
      timingLabel = uc.contact ? "n/a (bespoke)" : uc.timingLabel;
      miles = uc.contact ? "n/a" : uc.miles;
      uavTarget = uc.contact ? "bespoke (scope on call)" : (uc.range + " (" + uc.targetLabel + ", one-time)");
      answers = answers.concat(uc.answers.map(function(a){ return { label:"UAV — "+a.label, value:a.value }; }));
    }
    if (picks.it){
      answers.push({ label:"IT — area", value: itAreaLabel() });
      itTarget = itTargetPhrase(ic);
      answers = answers.concat(ic.answers.map(function(a){ return { label:"IT — "+a.label, value:a.value }; }));
      answers.push({ label:"IT — timeline", value: val("#qf-itWhen") });
      var s = val("#qf-itScope"); if (s) answers.push({ label:"IT — notes", value: s });
    }
    if (both){
      service = "Drone/UAV ("+uc.serviceLabel+") + IT ("+itAreaLabel()+")";
      target = "UAV: " + uavTarget + " | IT: " + itTarget;
      targetLabel = "Combined (UAV one-time + IT " + (ic.mode === "monthly" ? "monthly" : (ic.mode === "onetime" ? "one-time intake" : "tailored")) + ")";
    } else if (picks.uav){
      service = "Drone/UAV — "+uc.serviceLabel;
      target = uc.contact ? "" : uc.range;
      targetLabel = uc.contact ? "" : uc.targetLabel;
    } else {
      service = "IT / technology — "+itAreaLabel();
      target = (ic.mode === "monthly") ? (ic.range + " /month") : "";
      targetLabel = (ic.mode === "monthly") ? ic.targetLabel : (ic.mode === "onetime" ? "One-time — intake requested" : "Tailored quote");
    }
    return { service:service, timingLabel:timingLabel, miles:miles, target:target, targetLabel:targetLabel, answers:answers };
  }
  function buildNotes(lead){
    var out = [];
    if (picks.uav){
      var uc = computeUav();
      out.push(uc.contact ? "UAV: bespoke / tower — scope on the call." : ("UAV ballpark: "+uc.range+" ("+uc.targetLabel+", one-time, indicative — NOT a quote)."));
    }
    if (picks.it){
      var ic = computeIt();
      if (ic.mode === "monthly") out.push("IT: "+itAreaLabel()+" — "+ic.range+" /month ("+ic.targetLabel+", indicative — NOT a quote); timeline "+val("#qf-itWhen")+".");
      else if (ic.mode === "onetime") out.push("IT: "+itAreaLabel()+" — ONE-TIME project, intake appointment requested (no instant price shown); timeline "+val("#qf-itWhen")+".");
      else out.push("IT: "+itAreaLabel()+" — a mix, tailored quote on the call; timeline "+val("#qf-itWhen")+".");
    }
    lead.answers.forEach(function(a){ out.push("  • "+a.label+": "+a.value); });
    out.push("(Submitted via guided quote flow — "+(picks.uav&&picks.it?"both":(picks.uav?"UAV":"IT"))+".)");
    return out.join("\n");
  }
  function fireLead(lead){
    try {
      var fd = new FormData();
      fd.append("action", "uls337_target_quote");
      fd.append("name", val("#qf-name"));
      fd.append("company", val("#qf-company"));
      fd.append("email", val("#qf-email"));
      fd.append("phone", val("#qf-phone"));
      fd.append("service_label", lead.service);
      fd.append("timing_label", lead.timingLabel);
      fd.append("miles", lead.miles);
      fd.append("target_quote", lead.target);
      fd.append("target_label", lead.targetLabel);
      fd.append("answers_json", JSON.stringify(lead.answers));
      fd.append("company_url", val("#qf-hp"));
      fetch(endpoint, { method:"POST", body:fd, credentials:"same-origin" });
    } catch(e){}
  }

  $("#qf-to3").addEventListener("click", function(){
    if (!contactOk()) return;
    var lead = buildLead();
    fireLead(lead);
    buildStep3(lead);
    go(3);
  });

  /* ---- Step 3: cal.com inline embed ---- */
  function loadCalOnce(){
    if (window.Cal && window.Cal.loaded) return;
    (function (C, A, L) {
      var p = function (a, ar) { a.q.push(ar); };
      var d = C.document;
      C.Cal = C.Cal || function () {
        var cal = C.Cal; var ar = arguments;
        if (!cal.loaded) { cal.ns = {}; cal.q = cal.q || []; d.head.appendChild(d.createElement("script")).src = A; cal.loaded = true; }
        if (ar[0] === L) {
          var api = function () { p(api, arguments); };
          var namespace = ar[1];
          api.q = api.q || [];
          if (typeof namespace === "string") { cal.ns[namespace] = cal.ns[namespace] || api; p(cal.ns[namespace], ar); p(cal, ["initNamespace", namespace]); }
          else p(cal, ar);
          return;
        }
        p(cal, ar);
      };
    })(window, calOrigin + "/embed/embed.js", "init");
    window.Cal("init", { origin: calOrigin });
    window.Cal("on", { action:"bookingSuccessful", callback: function(){
      $("#qf-schedWrap").classList.add("hidden");
      $("#qf-doneWrap").classList.remove("hidden");
    }});
  }
  function showFallback(calLink, cfgObj){
    var params = [];
    if (cfgObj.name)  params.push("name="  + encodeURIComponent(cfgObj.name));
    if (cfgObj.email) params.push("email=" + encodeURIComponent(cfgObj.email));
    if (cfgObj.notes) params.push("notes=" + encodeURIComponent(cfgObj.notes));
    var url = calOrigin + "/" + calLink + (params.length ? "?" + params.join("&") : "");
    var a = $("#qf-cal-fallback-link"); a.setAttribute("href", url);
    $("#qf-cal-inline").classList.add("hidden");
    $("#qf-cal-fallback").classList.remove("hidden");
  }
  function mountCal(calLink, cfgObj){
    var host = $("#qf-cal-inline");
    host.innerHTML = ""; host.classList.remove("hidden");
    $("#qf-cal-fallback").classList.add("hidden");
    var mounted = false;
    try {
      loadCalOnce();
      if (typeof window.Cal !== "function") throw new Error("no Cal");
      window.Cal("inline", { elementOrSelector: host, calLink: calLink, config: cfgObj });
      mounted = true;
    } catch(e){ mounted = false; }
    setTimeout(function(){
      if (!mounted || !host.querySelector("iframe")) showFallback(calLink, cfgObj);
    }, 4500);
  }
  function buildStep3(lead){
    var both = picks.uav && picks.it;
    var uavOnly = picks.uav && !picks.it;
    var calLink = uavOnly ? "team/uplinksync/uav-consult" : "team/uplinksync/it-consult";
    $("#qf-calType").textContent = uavOnly ? "UAV consultation" : (both ? "Scoping call" : "Discovery call");
    $("#qf-s3lede").textContent = uavOnly ? "Pick a slot that works — we'll have your ballpark on hand." : "Pick a time and we'll come prepared with everything you've told us.";
    var notes = buildNotes(lead);
    mountCal(calLink, { name: val("#qf-name"), email: val("#qf-email"), notes: notes, layout: "month_view" });
  }

  /* ---- stepper ---- */
  function go(n){
    $$(".qf-pane[data-pane]").forEach(function(p){ p.classList.toggle("hidden", p.getAttribute("data-pane") != String(n)); });
    $$("#qf-stepper .qf-step").forEach(function(s){ var i = parseInt(s.getAttribute("data-s"),10);
      s.classList.toggle("on", i === n); s.classList.toggle("done", i < n); });
    if (root.scrollIntoView) root.scrollIntoView({ behavior:"smooth", block:"start" });
  }

  /* ---- preset: skip step 1 ---- */
  if (preset === "uav" || preset === "it"){
    picks[preset] = true;
    var pb = root.querySelector('.qf-choice[data-pick="'+preset+'"]');
    if (pb) pb.setAttribute("aria-pressed","true");
    $("#qf-to2").disabled = false;
    var b2 = $("#qf-back2"); if (b2) b2.style.display = "none";
    buildStep2(); go(2);
  }
})();
</script>
<?php
	return ob_get_clean();
} );
