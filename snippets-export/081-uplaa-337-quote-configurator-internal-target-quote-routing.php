<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 81
 * name  : UPLAA-337 Quote configurator (internal target-quote routing)
 * scope : global
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/* UPLAA-337 configurator — admin-ajax routing variant (REST is auth-restricted site-wide).
 *
 * Owner-approved (UPLAA-308, 2026-07-25) P4 quote configurator WITH the mandated
 * spec change: customer input produces an INTERNAL "target quote" routed to a
 * HUMAN (Doug) for review. NEVER shown to the customer as a firm/binding price,
 * NEVER auto-binding. Customer sees ONLY a soft acknowledgement.
 *
 * Math source: reuses the live ULS_ESTIMATOR_CONFIG rate model (UPLAA-330)
 * verbatim — NO new rates. Prefers window.ULS_ESTIMATOR_CONFIG if present.
 * Routing: wp_mail to dirwin@uplinksync.com (existing owner-notification inbox)
 * via admin-ajax (REST is auth-restricted site-wide). Final destination is an
 * OWNER decision (see UPLAA-337). Reversible: deactivate + trash request-quote page.
 */

/* 1) admin-ajax handler (public + logged-in): action=uls337_target_quote -> internal email */
add_action( 'wp_ajax_nopriv_uls337_target_quote', 'uls337_handle_target_quote' );
add_action( 'wp_ajax_uls337_target_quote', 'uls337_handle_target_quote' );

function uls337_handle_target_quote() {
	$p = wp_unslash( $_POST );

	// Honeypot: silently accept and drop bots.
	if ( ! empty( $p['company_url'] ) ) {
		wp_send_json_success( array( 'ok' => true ) );
	}

	$email = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => 'A valid email is required.' ), 400 );
	}

	$name       = sanitize_text_field( $p['name'] ?? '' );
	$phone      = sanitize_text_field( $p['phone'] ?? '' );
	$company    = sanitize_text_field( $p['company'] ?? '' );
	$service    = sanitize_text_field( $p['service_label'] ?? '' );
	$timing     = sanitize_text_field( $p['timing_label'] ?? '' );
	$miles      = sanitize_text_field( $p['miles'] ?? '' );
	$target     = sanitize_text_field( $p['target_quote'] ?? '' );
	$target_lbl = sanitize_text_field( $p['target_label'] ?? '' );

	$answers_raw = array();
	if ( ! empty( $p['answers_json'] ) ) {
		$decoded = json_decode( $p['answers_json'], true );
		if ( is_array( $decoded ) ) {
			$answers_raw = $decoded;
		}
	}
	$answers_lines = array();
	foreach ( $answers_raw as $row ) {
		$lbl = sanitize_text_field( is_array( $row ) ? ( $row['label'] ?? '' ) : '' );
		$val = sanitize_text_field( is_array( $row ) ? ( $row['value'] ?? '' ) : '' );
		if ( $lbl !== '' ) {
			$answers_lines[] = '  - ' . $lbl . ': ' . $val;
		}
	}
	$answers_txt = $answers_lines ? implode( "\n", $answers_lines ) : '  (none)';

	if ( $target === '' ) {
		$target_display = '(contact-only line — no computed target; scope by call)';
	} else {
		$target_display = ( $target_lbl ? $target_lbl . ': ' : '' ) . $target;
	}

	$body  = "INTERNAL TARGET QUOTE — for human review, NOT sent to the customer.\n";
	$body .= "The customer only saw a soft acknowledgement. Review and follow up with a tailored quote.\n\n";
	$body .= "----- Contact -----\n";
	$body .= "Name:    {$name}\n";
	$body .= "Company: {$company}\n";
	$body .= "Email:   {$email}\n";
	$body .= "Phone:   {$phone}\n\n";
	$body .= "----- Project -----\n";
	$body .= "Service line: {$service}\n";
	$body .= "Timing:       {$timing}\n";
	$body .= "Round-trip travel: {$miles} mi\n";
	$body .= "Answers:\n{$answers_txt}\n\n";
	$body .= "----- Computed target (indicative, non-binding) -----\n";
	$body .= "{$target_display}\n\n";
	$body .= "Rate model: ULS_ESTIMATOR_CONFIG (UPLAA-330). Internal starting point only,\n";
	$body .= "not a quote and not shown to the customer. Confirm the firm price after review.\n\n";
	$body .= "-- \nUPLAA-337 configurator on " . get_bloginfo( 'name' ) . " (" . home_url( '/' ) . ")\n";

	$subject_who = $company !== '' ? $company : ( $name !== '' ? $name : $email );
	$subject     = '[UplinkSync] INTERNAL target quote — review & follow up — ' . $subject_who;
	$headers     = array(
		'From: UplinkSync Website <webmaster@uplinksync.com>',
		'Reply-To: ' . $email,
	);

	$sent = wp_mail( 'dirwin@uplinksync.com', $subject, $body, $headers );
	if ( ! $sent ) {
		error_log( '[UPLAA-337] wp_mail failed for target-quote lead: ' . $email );
	}

	wp_send_json_success( array( 'ok' => true ) );
}

/* 2) Shortcode: [uls_quote_configurator] */
add_shortcode( 'uls_quote_configurator', function () {
	$endpoint = esc_url_raw( admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
<div class="uls-qc" id="uls-qc" data-endpoint="<?php echo esc_attr( $endpoint ); ?>">
  <form class="uls-qc__form" novalidate>
    <p class="uls-qc__eyebrow">Free, no-obligation</p>
    <h2 class="uls-qc__title">Tell us about your project</h2>
    <p class="uls-qc__lede">Answer a few quick questions and a UplinkSync specialist will follow up with a tailored quote — usually within one business day.</p>

    <div class="uls-qc__field">
      <label class="uls-qc__label" for="uls-qc-line">What kind of project is this?</label>
      <select id="uls-qc-line" class="uls-qc__select" data-role="line" required>
        <option value="">Select a service…</option>
      </select>
    </div>

    <div class="uls-qc__group" data-role="options" hidden></div>

    <div class="uls-qc__group" data-role="logistics" hidden>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-miles">Approx. round-trip travel to the site (miles)</label>
        <input id="uls-qc-miles" class="uls-qc__input" type="number" min="0" step="1" inputmode="numeric" placeholder="e.g. 30" data-role="miles">
      </div>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-timing">How soon do you need it?</label>
        <select id="uls-qc-timing" class="uls-qc__select" data-role="timing">
          <option value="standard">Standard scheduling</option>
          <option value="rush">Rush (expedited)</option>
          <option value="same_day">Same-day</option>
        </select>
      </div>
    </div>

    <div class="uls-qc__group" data-role="contactonly" hidden>
      <p class="uls-qc__note">This kind of work is scoped with a quick call. Share your details below and we'll reach out to plan it with you.</p>
    </div>

    <fieldset class="uls-qc__group" data-role="contact" hidden>
      <legend class="uls-qc__legend">Where should we send your tailored quote?</legend>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-name">Your name</label>
        <input id="uls-qc-name" class="uls-qc__input" type="text" autocomplete="name" data-role="name">
      </div>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-company">Company <span class="uls-qc__opt">(optional)</span></label>
        <input id="uls-qc-company" class="uls-qc__input" type="text" autocomplete="organization" data-role="company">
      </div>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-email">Email</label>
        <input id="uls-qc-email" class="uls-qc__input" type="email" autocomplete="email" required data-role="email">
      </div>
      <div class="uls-qc__field">
        <label class="uls-qc__label" for="uls-qc-phone">Phone <span class="uls-qc__opt">(optional)</span></label>
        <input id="uls-qc-phone" class="uls-qc__input" type="tel" autocomplete="tel" data-role="phone">
      </div>
      <div class="uls-qc__hp" aria-hidden="true">
        <label>Company website<input type="text" tabindex="-1" autocomplete="off" data-role="hp"></label>
      </div>
      <button type="submit" class="uls-qc__submit" data-role="submit">Request my tailored quote</button>
      <p class="uls-qc__fineprint">No obligation. We never show or charge a price automatically — a person prepares and confirms your quote.</p>
    </fieldset>

    <div class="uls-qc__ack" data-role="ack" role="status" aria-live="polite" hidden>
      <div class="uls-qc__ackicon" aria-hidden="true">✓</div>
      <h3 class="uls-qc__acktitle">Thanks — we're on it.</h3>
      <p class="uls-qc__ackbody">Your details are with our team. A UplinkSync specialist will follow up within one business day with a tailored quote for your project. No obligation.</p>
    </div>
    <div class="uls-qc__err" data-role="err" role="alert" aria-live="assertive" hidden>
      Something went wrong sending your request. Please email us at <a href="mailto:hello@uplinksync.com">hello@uplinksync.com</a> and we'll take care of it.
    </div>
  </form>
</div>

<style id="uls-qc-style">
  #uls-qc{--qc-navy:#0F2440;--qc-accent:#1f7a8c;--qc-line:#d6dde6;--qc-grey:#5b6675;max-width:640px;margin:0 auto;font-family:inherit}
  #uls-qc *{box-sizing:border-box}
  .uls-qc__form{background:#fff;border:1px solid var(--qc-line);border-radius:16px;padding:clamp(20px,4vw,36px)}
  .uls-qc__eyebrow{margin:0 0 6px;font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--qc-accent)}
  .uls-qc__title{margin:0 0 8px;font-size:clamp(1.4rem,3.5vw,1.9rem);color:var(--qc-navy);line-height:1.2}
  .uls-qc__lede{margin:0 0 22px;color:var(--qc-grey);font-size:1rem;line-height:1.5}
  .uls-qc__field{margin:0 0 16px}
  .uls-qc__group{margin:0 0 4px;padding:0;border:0}
  .uls-qc__legend{font-weight:700;color:var(--qc-navy);font-size:1.05rem;margin:6px 0 12px;padding:0}
  .uls-qc__label{display:block;margin:0 0 6px;font-weight:600;color:var(--qc-navy);font-size:.95rem}
  .uls-qc__opt{font-weight:400;color:var(--qc-grey)}
  .uls-qc__input,.uls-qc__select{width:100%;font-size:16px;padding:12px 14px;border:1px solid var(--qc-line);border-radius:10px;background:#fff;color:#12233b;line-height:1.3}
  .uls-qc__input:focus,.uls-qc__select:focus{outline:2px solid var(--qc-accent);outline-offset:1px;border-color:var(--qc-accent)}
  .uls-qc__check{display:flex;align-items:flex-start;gap:10px;font-size:.95rem;color:#12233b;margin:0 0 6px;cursor:pointer}
  .uls-qc__check input{width:18px;height:18px;margin-top:2px;flex:0 0 auto}
  .uls-qc__note{margin:0 0 8px;color:var(--qc-grey);font-size:.95rem;line-height:1.5;background:#f4f7fa;border-left:3px solid var(--qc-accent);padding:10px 14px;border-radius:0 8px 8px 0}
  .uls-qc__submit{width:100%;margin-top:8px;font-size:1rem;font-weight:700;color:#fff;background:var(--qc-navy);border:0;border-radius:10px;padding:14px 20px;cursor:pointer;transition:background .15s}
  .uls-qc__submit:hover{background:#163156}
  .uls-qc__submit:disabled{opacity:.6;cursor:default}
  .uls-qc__submit:focus-visible{outline:2px solid var(--qc-accent);outline-offset:2px}
  .uls-qc__fineprint{margin:12px 0 0;font-size:.8rem;color:var(--qc-grey);line-height:1.5}
  .uls-qc__hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
  .uls-qc__ack{text-align:center;padding:12px 4px}
  .uls-qc__ackicon{width:56px;height:56px;line-height:56px;margin:0 auto 14px;border-radius:50%;background:#e6f4f1;color:var(--qc-accent);font-size:1.7rem;font-weight:700}
  .uls-qc__acktitle{margin:0 0 8px;color:var(--qc-navy);font-size:1.4rem}
  .uls-qc__ackbody{margin:0 auto;max-width:440px;color:var(--qc-grey);line-height:1.6}
  .uls-qc__err{margin-top:14px;padding:12px 14px;border-radius:10px;background:#fdecec;color:#8a1c1c;font-size:.95rem}
  .uls-qc__err a{color:#8a1c1c;text-decoration:underline}
</style>

<script>
(function(){
  "use strict";
  var EMBEDDED = {
    currency: "$",
    travel: { freeRadiusMi: 25, perMileOver: 0.75 },
    rush:   { standard: 1.00, rush: 1.30, same_day: 1.50 },
    disclaimer: "Estimate only — a starting figure based on typical projects, not a quote. Your firm price is confirmed by a UplinkSync team member and may differ with site conditions, access, and deliverables.",
    lines: {
      real_estate: {
        posture: "band",
        label: "Real-estate / property photography",
        base: 175, bandSpread: 0.35,
        options: [
          { id: "sqft", label: "Property size", type: "select", choices: [
            { v: "small",  label: "Under 2,000 sq ft (home/lot)", add: 0 },
            { v: "medium", label: "2,000-5,000 sq ft or acreage", add: 75 },
            { v: "large",  label: "Estate / commercial parcel",   add: 200 }
          ]},
          { id: "video", label: "Add cinematic video reel", type: "check", add: 150 },
          { id: "twilight", label: "Twilight / golden-hour session", type: "check", add: 90 }
        ]
      },
      mapping: {
        posture: "starting",
        label: "Mapping & surveying",
        base: 500, startingSpread: 0.40,
        options: [
          { id: "acres", label: "Approximate acreage", type: "select", choices: [
            { v: "s",  label: "Up to 20 acres",  add: 0 },
            { v: "m",  label: "20-100 acres",    add: 250 },
            { v: "l",  label: "Over 100 acres",  add: 750 }
          ]},
          { id: "deliverable", label: "Deliverable", type: "select", choices: [
            { v: "ortho", label: "2D orthomosaic map", add: 0 },
            { v: "model", label: "3D model / point cloud", add: 300 },
            { v: "both",  label: "Both", add: 450 }
          ]}
        ]
      },
      inspection: {
        posture: "starting",
        label: "Roof / structure inspection",
        base: 300, startingSpread: 0.40,
        options: [
          { id: "structures", label: "Number of structures", type: "select", choices: [
            { v: "1", label: "1 structure",  add: 0 },
            { v: "2", label: "2-3 structures", add: 150 },
            { v: "4", label: "4+ structures", add: 350 }
          ]},
          { id: "report",  label: "Formal written report", type: "check", add: 120 }
        ]
      },
      tower: { posture: "contact", label: "Tower / industrial / other" }
    }
  };

  var root = document.getElementById("uls-qc");
  if (!root) return;
  var cfg = (window.ULS_ESTIMATOR_CONFIG && window.ULS_ESTIMATOR_CONFIG.lines) ? window.ULS_ESTIMATOR_CONFIG : EMBEDDED;
  if (cfg.lines.tower && !cfg.lines.tower.label) cfg.lines.tower.label = "Tower / industrial / other";

  var endpoint = root.getAttribute("data-endpoint");
  var form      = root.querySelector(".uls-qc__form");
  var lineSel   = root.querySelector('[data-role="line"]');
  var optsBox   = root.querySelector('[data-role="options"]');
  var logistics = root.querySelector('[data-role="logistics"]');
  var contactOnly = root.querySelector('[data-role="contactonly"]');
  var contactBox  = root.querySelector('[data-role="contact"]');
  var ackBox    = root.querySelector('[data-role="ack"]');
  var errBox    = root.querySelector('[data-role="err"]');
  var submitBtn = root.querySelector('[data-role="submit"]');

  function show(el){ if(el) el.hidden = false; }
  function hide(el){ if(el) el.hidden = true; }
  var fmt = function(n){ return cfg.currency + Math.round(n).toLocaleString("en-US"); };

  Object.keys(cfg.lines).forEach(function(key){
    var line = cfg.lines[key];
    var o = document.createElement("option");
    o.value = key;
    o.textContent = line.label || key;
    lineSel.appendChild(o);
  });

  function renderOptions(lineKey){
    var line = cfg.lines[lineKey];
    optsBox.innerHTML = "";
    if (!line || !line.options) return;
    line.options.forEach(function(opt){
      var field = document.createElement("div");
      field.className = "uls-qc__field";
      if (opt.type === "select"){
        var html = '<label class="uls-qc__label" for="uls-qc-opt-'+opt.id+'">'+opt.label+'</label>';
        html += '<select id="uls-qc-opt-'+opt.id+'" class="uls-qc__select" data-opt="'+opt.id+'" data-optlabel="'+opt.label+'">';
        opt.choices.forEach(function(c){ html += '<option value="'+c.v+'" data-add="'+c.add+'">'+c.label+'</option>'; });
        html += '</select>';
        field.innerHTML = html;
      } else if (opt.type === "check"){
        field.innerHTML = '<label class="uls-qc__check"><input type="checkbox" data-opt="'+opt.id+'" data-add="'+opt.add+'" data-optlabel="'+opt.label+'"> <span>'+opt.label+'</span></label>';
      }
      optsBox.appendChild(field);
    });
  }

  function computeTarget(lineKey){
    var line = cfg.lines[lineKey];
    var answers = [];
    if (line.posture === "contact"){
      return { target: "", targetLabel: "", answers: answers };
    }
    var total = line.base;
    answers.push({ label: line.label + " (base)", value: fmt(line.base) });
    optsBox.querySelectorAll("[data-opt]").forEach(function(el){
      if (el.tagName === "SELECT"){
        var o = el.options[el.selectedIndex];
        var add = parseFloat(o.getAttribute("data-add")) || 0;
        total += add;
        answers.push({ label: el.getAttribute("data-optlabel"), value: o.textContent + (add ? " (+"+fmt(add)+")" : "") });
      } else if (el.type === "checkbox"){
        var a = parseFloat(el.getAttribute("data-add")) || 0;
        if (el.checked){ total += a; answers.push({ label: el.getAttribute("data-optlabel"), value: "Yes (+"+fmt(a)+")" }); }
      }
    });
    var miles = parseFloat(root.querySelector('[data-role="miles"]').value) || 0;
    var timing = root.querySelector('[data-role="timing"]').value || "standard";
    var overMi = Math.max(0, miles - cfg.travel.freeRadiusMi);
    var travelFee = overMi * cfg.travel.perMileOver;
    var rushMult = cfg.rush[timing] || 1;
    var withTravel = total + travelFee;
    var withRush = withTravel * rushMult;
    if (travelFee > 0) answers.push({ label: "Travel ("+overMi+" mi beyond free radius)", value: "+"+fmt(travelFee) });
    if (rushMult > 1) answers.push({ label: "Timing multiplier", value: "x"+rushMult });

    var low, high, tlabel;
    if (line.posture === "band"){
      low = withRush * (1 - line.bandSpread);
      high = withRush * (1 + line.bandSpread);
      tlabel = "Indicative target band";
    } else {
      low = withRush;
      high = withRush * (1 + (line.startingSpread || 0.4));
      tlabel = "Conservative target range";
    }
    return { target: fmt(low) + "-" + fmt(high), targetLabel: tlabel, answers: answers };
  }

  lineSel.addEventListener("change", function(){
    var key = lineSel.value;
    hide(ackBox); hide(errBox);
    if (!key){ hide(optsBox); hide(logistics); hide(contactOnly); hide(contactBox); return; }
    if (cfg.lines[key].posture === "contact"){
      hide(optsBox); hide(logistics);
      show(contactOnly); show(contactBox);
      return;
    }
    renderOptions(key);
    show(optsBox); show(logistics); hide(contactOnly); show(contactBox);
  });

  var timingLabels = { standard: "Standard scheduling", rush: "Rush (expedited)", same_day: "Same-day" };

  form.addEventListener("submit", function(ev){
    ev.preventDefault();
    hide(errBox);
    var hp = root.querySelector('[data-role="hp"]');
    if (hp && hp.value){ show(ackBox); form.reset(); hide(contactBox); hide(optsBox); hide(logistics); hide(contactOnly); return; }
    var key = lineSel.value;
    if (!key){ lineSel.focus(); return; }
    var emailEl = root.querySelector('[data-role="email"]');
    var email = (emailEl.value || "").trim();
    if (!email || email.indexOf("@") < 1){ emailEl.focus(); return; }

    var line = cfg.lines[key];
    var computed = computeTarget(key);
    var milesEl = root.querySelector('[data-role="miles"]');
    var timingEl = root.querySelector('[data-role="timing"]');

    var fd = new FormData();
    fd.append("action", "uls337_target_quote");
    fd.append("name", (root.querySelector('[data-role="name"]').value || "").trim());
    fd.append("company", (root.querySelector('[data-role="company"]').value || "").trim());
    fd.append("email", email);
    fd.append("phone", (root.querySelector('[data-role="phone"]').value || "").trim());
    fd.append("service_label", line.label || key);
    fd.append("timing_label", (line.posture === "contact") ? "n/a (contact-only)" : (timingLabels[timingEl ? timingEl.value : "standard"] || "Standard scheduling"));
    fd.append("miles", (line.posture === "contact") ? "n/a" : ((milesEl && milesEl.value) ? milesEl.value : "0"));
    fd.append("target_quote", computed.target);
    fd.append("target_label", computed.targetLabel);
    fd.append("answers_json", JSON.stringify(computed.answers));
    fd.append("company_url", hp ? hp.value : "");

    submitBtn.disabled = true;
    submitBtn.textContent = "Sending…";
    fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function(r){ return r.ok ? r.json() : Promise.reject(r); })
      .then(function(d){
        if (!d || !d.success) { throw new Error("bad"); }
        hide(optsBox); hide(logistics); hide(contactOnly); hide(contactBox);
        show(ackBox);
        ackBox.scrollIntoView({ behavior: "smooth", block: "center" });
      })
      .catch(function(){
        submitBtn.disabled = false;
        submitBtn.textContent = "Request my tailored quote";
        show(errBox);
      });
  });
})();
</script>
<?php
	return ob_get_clean();
} );
