/* UplinkSync estimator — ported from DB Code Snippets #82 (guided flow) + #84 (modal).
 * Steps 1-2: questions, rate maths, lead hand-off (snippet #81 uls337_target_quote) are the
 * live snippet #82 logic, unchanged; rates arrive in window.ULS_QF_BOOT (PHP config, pinned by
 * tests/test-estimator-pricing.php). Step 3: native calendar via same-origin WP REST
 * (uplinksync/v1/slots + /book) — no Cal.com embed SDK, no third-party request from the
 * browser. Visitor-supplied text is only ever written with textContent. */
(function () {
  "use strict";
  /* Runs after the DOM is parsed even if an optimiser strips `defer` (the modal markup is
     printed in wp_footer AFTER the script tag). */
  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", init); } else { init(); }
  function init() {
  var BOOT = window.ULS_QF_BOOT || {};

  /* ======================= Modal (snippet #84) ======================= */
  var modal = document.getElementById("uls-qmodal");
  var cssState = 0; // 0 none, 1 loading, 2 ready
  var cssWaiters = [];
  function cssReady() {
    cssState = 2;
    var w = cssWaiters.slice(); cssWaiters = [];
    w.forEach(function (f) { f(); });
  }
  function ensureCss(cb) {
    if (cb) { cssWaiters.push(cb); }
    if (cssState === 2) { cssReady(); return; }
    if (cssState === 1) { return; }
    if (document.querySelector('link[href*="uplinksync-estimator/estimator.css"]') || !BOOT.css) { cssReady(); return; }
    cssState = 1;
    var l = document.createElement("link");
    l.rel = "stylesheet"; l.href = BOOT.css;
    l.onload = cssReady; l.onerror = cssReady;
    document.head.appendChild(l);
    setTimeout(function () { if (cssState !== 2) { cssReady(); } }, 2000);
  }

  var flowStart = null; // set by the flow below: flowStart(mode, intent)
  /* Entry points. Quote/estimate triggers (verbatim rules from snippet #84) open the estimator;
     every booking trigger — [data-uls-book-open] CTAs and any book.uplinksync.com link —
     opens its native "Book a call" mode (one booking UI site-wide). The link hrefs stay real
     links: the no-JS fallback. data-uls-book-direct marks links that must stay plain. */
  var match = function (t) {
    if (!t || !t.closest) { return null; }
    var el = t.closest('a[href*="request-quote"],a[href*="#uls-estimator"],.est-trigger,.uls-est-trigger,.uls-consult-trigger,[data-uls-book-open],a[href^="https://book.uplinksync.com/"]');
    if (!el || el.hasAttribute("data-uls-book-direct")) { return null; }
    if (modal && modal.contains(el)) { return null; }
    var href = (el.getAttribute && el.getAttribute("href")) || "";
    if (href.indexOf("request-quote") !== -1 || href.indexOf("#uls-estimator") !== -1) { return { el: el, mode: "quote" }; }
    if (el.classList && (el.classList.contains("est-trigger") || el.classList.contains("uls-est-trigger"))) { return { el: el, mode: "quote" }; }
    if ((el.textContent || "").toLowerCase().indexOf("estimate") !== -1) { return { el: el, mode: "quote" }; }
    if (el.hasAttribute("data-uls-book-open") || el.classList.contains("uls-consult-trigger") || /^https:\/\/book\.uplinksync\.com\//.test(href)) {
      var intent = el.getAttribute("data-uls-book-intent") || (/uav|drone/i.test(href) ? "uav" : "");
      return { el: el, mode: "book", intent: intent === "it" || intent === "uav" ? intent : "" };
    }
    return null;
  };

  if (modal) {
    var dlg = modal.querySelector(".uls-qmodal__dlg");
    var scroller = modal.querySelector(".uls-qmodal__scroll");
    var lastFocus = null;
    var foci = function () {
      return Array.prototype.slice.call(
        dlg.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')
      ).filter(function (el) { return !el.closest(".hidden") && (el.offsetWidth || el.offsetHeight || el.getClientRects().length); });
    };
    var openM = function (trigger, mode, intent) {
      lastFocus = trigger || document.activeElement;
      ensureCss(function () {
        if (flowStart) { flowStart(mode || "quote", intent || ""); }
        modal.hidden = false;
        document.body.classList.add("uls-qmodal-open");
        if (scroller) { scroller.scrollTop = 0; }
        var f = foci();
        (f[1] || f[0] || dlg).focus(); // f[0] is the close button; start on the first control.
      });
    };
    var closeM = function () {
      if (modal.hidden) { return; }
      modal.hidden = true;
      document.body.classList.remove("uls-qmodal-open");
      if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    };
    /* Capture phase + stopImmediatePropagation => intercept BEFORE estimate-book.js and the
       booking-CTAs chooser ever see the click. */
    document.addEventListener("click", function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
      var m = match(e.target);
      if (!m) { return; }
      e.preventDefault();
      if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
      openM(m.el, m.mode, m.intent);
    }, true);
    ["pointerover", "focusin", "touchstart"].forEach(function (ev) {
      document.addEventListener(ev, function (e) { if (cssState === 0 && match(e.target)) { ensureCss(); } }, { passive: true, capture: true });
    });
    modal.addEventListener("click", function (e) {
      if (e.target.closest && e.target.closest("[data-uqm-close]")) { e.preventDefault(); closeM(); }
    });
    document.addEventListener("keydown", function (e) {
      if (modal.hidden) { return; }
      if (e.key === "Escape" || e.keyCode === 27) { e.preventDefault(); closeM(); return; }
      if (e.key === "Tab" || e.keyCode === 9) {
        var f = foci(); if (!f.length) { e.preventDefault(); return; }
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        else if (!dlg.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      }
    });
  }

  /* ======================= Flow (snippet #82) ======================= */
  var root = document.getElementById("uls-qf");
  if (!root || root.getAttribute("data-init") === "1") { return; }
  root.setAttribute("data-init", "1");

  /* Rate model — UAV "EMBEDDED" (UPLAA-330) + IT_CFG, served from PHP config, no new rates.
     Prefers window.ULS_ESTIMATOR_CONFIG if present (unchanged behaviour). */
  var EMBEDDED = BOOT.uav;
  var cfg = (window.ULS_ESTIMATOR_CONFIG && window.ULS_ESTIMATOR_CONFIG.lines) ? window.ULS_ESTIMATOR_CONFIG : EMBEDDED;
  if (!cfg || !cfg.lines) { return; }
  if (cfg.lines.tower && !cfg.lines.tower.label) { cfg.lines.tower.label = "Tower / industrial / other"; }
  var IT_CFG = BOOT.it || {};

  var endpoint = root.getAttribute("data-endpoint");
  var preset   = root.getAttribute("data-preset") || "";
  var inModal  = !!(modal && modal.contains(root));
  var $  = function (s) { return root.querySelector(s); };
  var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };
  var val = function (s) { var e = $(s); return e ? (e.value || "").trim() : ""; };
  var fmt = function (n) { return cfg.currency + Math.round(n).toLocaleString("en-US"); };
  var picks = { uav: false, it: false };
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var IT_AREAS = { managed: "Managed IT / support", automation: "Automation & AI", web: "Web development", mixed: "A mix / not sure yet" };
  var timingLabels = { standard: "Standard scheduling", rush: "Rush (expedited)", same_day: "Same-day" };

  /* ---- Step 1 ---- */
  function anyPick() { return picks.uav || picks.it; }
  $$(".qf-choice").forEach(function (b) {
    b.addEventListener("click", function () {
      var k = b.getAttribute("data-pick"); picks[k] = !picks[k];
      b.setAttribute("aria-pressed", picks[k] ? "true" : "false");
      var any = anyPick();
      $("#qf-to2").setAttribute("aria-disabled", any ? "false" : "true");
      $("#qf-selhint").textContent = any ? (picks.uav && picks.it ? "Great — we'll cover both sides." : "Nice. You can add the other too.") : "Select at least one to continue.";
    });
  });
  $("#qf-to2").addEventListener("click", function () {
    if (!anyPick()) { $("#qf-selhint").textContent = "Select at least one to continue."; return; }
    buildStep2(); go(2);
  });

  /* ---- Step 2: UAV ---- */
  function renderUavOpts() {
    var line = cfg.lines[$("#qf-uavLine").value], box = $("#qf-uavOpts"); box.innerHTML = "";
    var log = $("#qf-uavLogistics"), ro = $("#qf-uavReadout"), contact = $("#qf-uavContact");
    if (line.posture === "contact") {
      log.classList.add("hidden"); ro.classList.add("hidden"); contact.classList.remove("hidden");
      calcCombo(); syncDetails();
      return;
    }
    log.classList.remove("hidden"); ro.classList.remove("hidden"); contact.classList.add("hidden");
    (line.options || []).forEach(function (o) {
      var f = document.createElement("div"); f.className = "qf-field";
      var fid = "qf-uo-" + o.id;
      if (o.type === "select") {
        var h = '<label class="qf-l" for="' + fid + '">' + o.label + '</label><select class="qf-select" id="' + fid + '" data-opt="' + o.id + '" data-optlabel="' + o.label + '">';
        o.choices.forEach(function (c) { h += '<option value="' + c.add + '" data-v="' + c.v + '" data-lbl="' + c.label + '">' + c.label + (c.add ? " (+" + fmt(c.add) + ")" : "") + '</option>'; });
        h += '</select>'; f.innerHTML = h;
      } else {
        f.innerHTML = '<label class="qf-check-row"><input type="checkbox" data-opt="' + o.id + '" data-add="' + o.add + '" data-optlabel="' + o.label + '"><span>' + o.label + ' <span class="qf-opt">(+' + fmt(o.add) + ')</span></span></label>';
      }
      box.appendChild(f);
    });
    calcUav(); syncDetails();
  }
  function computeUav() {
    var line = cfg.lines[$("#qf-uavLine").value];
    var answers = [];
    if (line.posture === "contact") {
      return { range: "", targetLabel: "", answers: answers, contact: true, serviceLabel: line.label };
    }
    var total = line.base;
    answers.push({ label: line.label + " (base)", value: fmt(line.base) });
    $$('#qf-uavOpts [data-opt]').forEach(function (el) {
      if (el.tagName === "SELECT") {
        var o = el.options[el.selectedIndex];
        var add = parseFloat(el.value) || 0; total += add;
        answers.push({ label: el.getAttribute("data-optlabel"), value: o.getAttribute("data-lbl") + (add ? " (+" + fmt(add) + ")" : "") });
      } else if (el.checked) {
        var a = parseFloat(el.getAttribute("data-add")) || 0; total += a;
        answers.push({ label: el.getAttribute("data-optlabel"), value: "Yes (+" + fmt(a) + ")" });
      }
    });
    var miles = parseFloat(val("#qf-uavMiles")) || 0;
    var timing = $("#qf-uavTiming").value || "standard";
    var overMi = Math.max(0, miles - cfg.travel.freeRadiusMi);
    var travelFee = overMi * cfg.travel.perMileOver;
    var rushMult = cfg.rush[timing] || 1;
    var withRush = (total + travelFee) * rushMult;
    if (travelFee > 0) { answers.push({ label: "Travel (" + overMi + " mi beyond free radius)", value: "+" + fmt(travelFee) }); }
    if (rushMult > 1) { answers.push({ label: "Timing multiplier", value: "x" + rushMult }); }
    var low, high, tlabel;
    if (line.posture === "band") { low = withRush * (1 - line.bandSpread); high = withRush * (1 + line.bandSpread); tlabel = "Indicative ballpark"; }
    else { low = withRush; high = withRush * (1 + (line.startingSpread || 0.4)); tlabel = "Conservative starting range"; }
    return { range: fmt(low) + " – " + fmt(high), targetLabel: tlabel, answers: answers, contact: false, serviceLabel: line.label,
             timingLabel: timingLabels[timing] || "Standard scheduling", miles: (miles ? String(miles) : "0") };
  }
  function calcUav() {
    var line = cfg.lines[$("#qf-uavLine").value]; if (line.posture === "contact") { calcCombo(); return; }
    var c = computeUav();
    $("#qf-uavRk").textContent = c.targetLabel;
    $("#qf-uavRv").textContent = c.range;
    $("#qf-uavRn").textContent = cfg.disclaimer || "";
    calcCombo();
  }

  /* ---- Step 2: IT — monthly model with a Monthly / One-time toggle (unchanged). ---- */
  var itEngagement = "monthly";
  function itArea() { return IT_CFG[$("#qf-itLine").value]; }
  function renderItArea() {
    var area = itArea(), eng = $("#qf-itEngage");
    var ro = $("#qf-itReadout"), sb = $("#qf-itScopebox"), intake = $("#qf-itIntake");
    if (!area || area.posture === "contact") {
      eng.classList.add("hidden"); eng.innerHTML = "";
      $("#qf-itOpts").innerHTML = "";
      ro.classList.add("hidden"); intake.classList.add("hidden"); sb.classList.remove("hidden");
      calcCombo(); syncDetails();
      return;
    }
    sb.classList.add("hidden");
    itEngagement = "monthly";
    if (area.engagement) {
      eng.classList.remove("hidden");
      eng.innerHTML =
        '<div class="qf-seg" role="radiogroup" aria-label="Engagement type">'
        + '<button type="button" class="qf-segbtn on" data-eng="monthly" role="radio" aria-checked="true">Monthly plan</button>'
        + '<button type="button" class="qf-segbtn" data-eng="onetime" role="radio" aria-checked="false">One-time project</button>'
        + '</div>';
    } else { eng.classList.add("hidden"); eng.innerHTML = ""; }
    renderItMode();
  }
  function setEngagement(v) {
    itEngagement = v;
    $$("#qf-itEngage .qf-segbtn").forEach(function (b) {
      var on = b.getAttribute("data-eng") === v;
      b.classList.toggle("on", on); b.setAttribute("aria-checked", on ? "true" : "false");
    });
    renderItMode();
  }
  function renderItMode() {
    var area = itArea(), box = $("#qf-itOpts"), ro = $("#qf-itReadout"), intake = $("#qf-itIntake");
    box.innerHTML = "";
    if (itEngagement === "onetime") {
      ro.classList.add("hidden"); intake.classList.remove("hidden");
      calcCombo(); syncDetails();
      return;
    }
    intake.classList.add("hidden"); ro.classList.remove("hidden");
    if (area.posture === "perUser") {
      var h = '<div class="qf-field"><label class="qf-l" for="qf-it-tier">Service level</label><select class="qf-select" id="qf-it-tier" data-it="tier" data-optlabel="Service level">';
      area.tiers.forEach(function (t) { h += '<option value="' + t.rate + '" data-v="' + t.v + '" data-lbl="' + t.label + '">' + t.label + ' (' + fmt(t.rate) + '/user/mo)</option>'; });
      h += '</select></div>';
      h += '<div class="qf-grid2">';
      h += '<div class="qf-field"><label class="qf-l" for="qf-it-users">How many users / workstations?</label><input class="qf-input" id="qf-it-users" type="number" min="1" step="1" inputmode="numeric" value="5" data-it="users"></div>';
      h += '<div class="qf-field"><label class="qf-l" for="qf-it-servers">Servers to manage <span class="qf-opt">optional</span></label><input class="qf-input" id="qf-it-servers" type="number" min="0" step="1" inputmode="numeric" value="0" data-it="servers"></div>';
      h += '</div>';
      box.innerHTML = h;
    } else {
      (area.options || []).forEach(function (o) {
        var f = document.createElement("div"); f.className = "qf-field";
        var fid = "qf-io-" + o.id;
        if (o.type === "select") {
          var hh = '<label class="qf-l" for="' + fid + '">' + o.label + '</label><select class="qf-select" id="' + fid + '" data-itopt="' + o.id + '" data-optlabel="' + o.label + '">';
          o.choices.forEach(function (c) { hh += '<option value="' + c.add + '" data-v="' + c.v + '" data-lbl="' + c.label + '">' + c.label + (c.add ? " (+" + fmt(c.add) + "/mo)" : "") + '</option>'; });
          hh += '</select>'; f.innerHTML = hh;
        } else {
          f.innerHTML = '<label class="qf-check-row"><input type="checkbox" data-itopt="' + o.id + '" data-add="' + o.add + '" data-optlabel="' + o.label + '"><span>' + o.label + ' <span class="qf-opt">(+' + fmt(o.add) + '/mo)</span></span></label>';
        }
        box.appendChild(f);
      });
    }
    calcIt(); syncDetails();
  }
  function computeIt() {
    var area = itArea(), answers = [];
    if (!area || area.posture === "contact") {
      return { mode: "contact", range: "", targetLabel: "", answers: answers, areaLabel: (area ? area.label : "IT / technology") };
    }
    if (itEngagement === "onetime") {
      answers.push({ label: "Engagement", value: "One-time project — intake requested (no instant price)" });
      return { mode: "onetime", range: "", targetLabel: "One-time — scoped at intake", answers: answers, areaLabel: area.label };
    }
    if (area.posture === "perUser") {
      var tierEl = $('#qf-itOpts [data-it="tier"]');
      var rate  = tierEl ? (parseFloat(tierEl.value) || 0) : 0;
      var tlbl  = tierEl ? tierEl.options[tierEl.selectedIndex].getAttribute("data-lbl") : "";
      var users = Math.max(1, parseInt(val('#qf-itOpts [data-it="users"]'), 10) || 1);
      var servers = Math.max(0, parseInt(val('#qf-itOpts [data-it="servers"]'), 10) || 0);
      var monthly = rate * users + (area.perServer || 0) * servers;
      answers.push({ label: "Service level", value: tlbl + " (" + fmt(rate) + "/user/mo)" });
      answers.push({ label: "Users / workstations", value: String(users) });
      if (servers > 0) { answers.push({ label: "Servers", value: servers + " (+" + fmt(area.perServer) + "/mo ea)" }); }
      var lo = monthly, hi = monthly * (1 + area.spread);
      return { mode: "monthly", range: fmt(lo) + " – " + fmt(hi), targetLabel: "Estimated monthly range",
               answers: answers, areaLabel: area.label };
    }
    var total = area.base || 0;
    $$('#qf-itOpts [data-itopt]').forEach(function (el) {
      if (el.tagName === "SELECT") {
        var o = el.options[el.selectedIndex]; var add = parseFloat(el.value) || 0; total += add;
        answers.push({ label: el.getAttribute("data-optlabel"), value: o.getAttribute("data-lbl") + (add ? " (+" + fmt(add) + "/mo)" : "") });
      } else if (el.checked) {
        var a = parseFloat(el.getAttribute("data-add")) || 0; total += a;
        answers.push({ label: el.getAttribute("data-optlabel"), value: "Yes (+" + fmt(a) + "/mo)" });
      }
    });
    var mlo = total, mhi = total * (1 + (area.spread || 0.30));
    return { mode: "monthly", range: fmt(mlo) + " – " + fmt(mhi), targetLabel: "Estimated monthly range",
             answers: answers, areaLabel: area.label };
  }
  function calcIt() {
    var area = itArea();
    if (!area || area.posture === "contact" || itEngagement === "onetime") { calcCombo(); return; }
    var c = computeIt();
    $("#qf-itRk").textContent = c.targetLabel;
    $("#qf-itRvNum").textContent = c.range;
    $("#qf-itUnit").textContent = "/month";
    $("#qf-itRn").textContent = cfg.disclaimer || "";
    calcCombo();
  }
  function calcCombo() {
    refreshMini();
    if (!(picks.uav && picks.it)) { return; }
    var uc = computeUav();
    $("#qf-comboUav").textContent = uc.contact ? "Scoped on a call" : uc.range;
    $("#qf-comboUavU").textContent = uc.contact ? "bespoke / tower" : (uc.targetLabel + " · one-time");
    var ic = computeIt();
    if (ic.mode === "monthly") {
      $("#qf-comboIt").textContent = ic.range;
      $("#qf-comboItU").textContent = "Estimated monthly range · per month";
    } else if (ic.mode === "onetime") {
      $("#qf-comboIt").textContent = "Intake appointment";
      $("#qf-comboItU").textContent = "one-time — scoped on the call";
    } else {
      $("#qf-comboIt").textContent = "Scoped on a call";
      $("#qf-comboItU").textContent = "a mix — tailored quote";
    }
  }
  /* Short estimate text (mobile strip, step-3 summary, confirmation). */
  function estimateParts() {
    var out = [], tight = function (r) { return r.replace(/ – /, "\u2060–\u2060"); }; // word joiners: never break inside a range
    if (picks.uav) { var uc = computeUav(); out.push(uc.contact ? "Drone: scoped on a call" : "Drone " + tight(uc.range)); }
    if (picks.it) {
      var ic = computeIt();
      out.push(ic.mode === "monthly" ? "IT " + tight(ic.range) + "/mo" : (ic.mode === "onetime" ? "IT: intake appointment" : "IT: tailored quote"));
    }
    return out;
  }
  function refreshMini() {
    var m = $("#qf-mini"); if (!m) { return; }
    var p = estimateParts();
    m.textContent = "";
    if (!p.length) { return; }
    var k = document.createElement("span"); k.textContent = "Your estimate"; m.appendChild(k);
    var b = document.createElement("b"); b.textContent = p.join(" · "); m.appendChild(b);
  }

  /* Event used for booking: UAV-only -> uav-consult; IT-only or both -> it-consult (as before). */
  function bookEvent() { return (picks.uav && !picks.it) ? "uav" : "it"; }
  /* Pre-select the booking fields Cal.com requires (visitor can change them). */
  function syncDetails() {
    var ev = bookEvent();
    $("#qf-goalWrap").classList.toggle("hidden", ev !== "it");
    $("#qf-siteWrap").classList.toggle("hidden", ev !== "uav");
    if (ev === "it" && !$("#qf-goal").getAttribute("data-touched")) {
      var area = $("#qf-itLine").value, tierEl = $('#qf-itOpts [data-it="tier"]');
      var tier = tierEl ? tierEl.options[tierEl.selectedIndex].getAttribute("data-v") : "";
      $("#qf-goal").value = area === "managed" ? (tier === "security" ? "Security" : "Systems / help-desk support") : (area === "web" ? "New website" : "Other");
    }
    if (ev === "uav" && !$("#qf-siteType").getAttribute("data-touched")) {
      var line = $("#qf-uavLine").value, sel = $("#qf-uavOpts select"), ch = sel ? sel.options[sel.selectedIndex].getAttribute("data-v") : "";
      var st = "Other";
      if (line === "real_estate") { st = ch === "large" ? "Commercial building" : "Residential / roof"; }
      else if (line === "mapping") { st = "Land / acreage (mapping)"; }
      else if (line === "inspection") { st = (!ch || ch === "1") ? "Residential / roof" : "Commercial building"; }
      $("#qf-siteType").value = st;
    }
  }
  ["qf-goal", "qf-siteType"].forEach(function (id) {
    var el = $("#" + id); if (el) { el.addEventListener("change", function () { el.setAttribute("data-touched", "1"); }); }
  });

  function syncSide() {
    $$("[data-side]").forEach(function (s) {
      var blk = $(s.getAttribute("data-side") === "uav" ? "#qf-uavBlock" : "#qf-itBlock");
      s.classList.toggle("hidden", blk.classList.contains("hidden"));
    });
  }
  function buildStep2() {
    var both = picks.uav && picks.it;
    $("#qf-tabs").classList.toggle("hidden", !both);
    $("#qf-combo").classList.toggle("hidden", !both);
    if (both) {
      setTab("uav");
    } else {
      $("#qf-uavBlock").classList.toggle("hidden", !picks.uav);
      $("#qf-itBlock").classList.toggle("hidden", !picks.it);
      syncSide();
    }
    $("#qf-s2eyebrow").textContent = "Your instant estimate";
    $("#qf-s2title").textContent = "Tell us a little more";
    $("#qf-s2lede").textContent = both
      ? "Two tracks, one flow — switch tabs to answer a couple of quick questions for each. We'll show a starting range for both below."
      : "Answer a couple of quick questions and we'll show a starting range right away.";
    if (picks.uav) { renderUavOpts(); }
    if (picks.it) { renderItArea(); }
    if (both) { calcCombo(); }
    syncDetails(); refreshMini();
  }
  function setTab(which) {
    $$("#qf-tabs .qf-tab").forEach(function (t) {
      var on = t.getAttribute("data-tab") === which;
      t.classList.toggle("on", on); t.setAttribute("aria-selected", on ? "true" : "false");
    });
    $("#qf-uavBlock").classList.toggle("hidden", which !== "uav");
    $("#qf-itBlock").classList.toggle("hidden", which !== "it");
    syncSide();
  }
  $("#qf-uavLine").addEventListener("change", renderUavOpts);
  $("#qf-itLine").addEventListener("change", renderItArea);
  $$("#qf-tabs .qf-tab").forEach(function (t) { t.addEventListener("click", function () { setTab(t.getAttribute("data-tab")); }); });
  root.addEventListener("click", function (e) {
    var seg = e.target.closest && e.target.closest("#qf-itEngage .qf-segbtn");
    if (seg) { setEngagement(seg.getAttribute("data-eng")); }
  });
  ["input", "change"].forEach(function (ev) {
    root.addEventListener(ev, function (e) {
      if (!e.target.closest) { return; }
      if (e.target.closest("#qf-uavBlock")) { calcUav(); syncDetails(); }
      else if (e.target.closest("#qf-itBlock")) { calcIt(); syncDetails(); }
    });
  });
  $$("[data-back]").forEach(function (b) { b.addEventListener("click", function () { var t = parseInt(b.getAttribute("data-back"), 10); go(mode === "book" && t === 2 ? 1 : t); }); });

  /* ---- contact + lead ---- */
  function fieldErr(el, msg) {
    $("#qf-cerrText").textContent = msg;
    $("#qf-cerr").classList.remove("hidden");
    if (el) { el.setAttribute("aria-invalid", "true"); el.focus(); }
    return false;
  }
  ["#qf-name", "#qf-email", "#qf-site"].forEach(function (sel) {
    $(sel).addEventListener("input", function () {
      this.setAttribute("aria-invalid", "false");
      if (!$("#qf-cerr").classList.contains("hidden") && val("#qf-name") && val("#qf-email").indexOf("@") > 0) { $("#qf-cerr").classList.add("hidden"); }
    });
  });
  function contactOk() {
    ["#qf-name", "#qf-email", "#qf-site"].forEach(function (s) { $(s).setAttribute("aria-invalid", "false"); });
    var name = val("#qf-name"), email = val("#qf-email");
    var ok = name.length > 0 && email.indexOf("@") > 0 && email.indexOf(".") > 0;
    if (!ok) { return fieldErr(name ? $("#qf-email") : $("#qf-name"), "Please add your name and a valid email so we can prepare your consultation."); }
    if (bookEvent() === "uav" && !val("#qf-site")) { return fieldErr($("#qf-site"), "Where is the site? A street address or the nearest town is enough."); }
    $("#qf-cerr").classList.add("hidden");
    return true;
  }
  function itAreaLabel() { return IT_AREAS[$("#qf-itLine").value] || "IT / technology"; }
  function itTargetPhrase(ic) {
    if (ic.mode === "monthly") { return ic.range + " /month (" + ic.targetLabel + ")"; }
    if (ic.mode === "onetime") { return "one-time — intake requested (no instant price)"; }
    return "a mix (tailored quote)";
  }
  function buildLead() {
    var both = picks.uav && picks.it;
    var service, timingLabel = "n/a", miles = "n/a", target = "", targetLabel = "";
    var answers = [];
    var uc = picks.uav ? computeUav() : null;
    var ic = picks.it ? computeIt() : null;
    var uavTarget = "", itTarget = "";
    if (picks.uav) {
      timingLabel = uc.contact ? "n/a (bespoke)" : uc.timingLabel;
      miles = uc.contact ? "n/a" : uc.miles;
      uavTarget = uc.contact ? "bespoke (scope on call)" : (uc.range + " (" + uc.targetLabel + ", one-time)");
      answers = answers.concat(uc.answers.map(function (a) { return { label: "UAV — " + a.label, value: a.value }; }));
    }
    if (picks.it) {
      answers.push({ label: "IT — area", value: itAreaLabel() });
      itTarget = itTargetPhrase(ic);
      answers = answers.concat(ic.answers.map(function (a) { return { label: "IT — " + a.label, value: a.value }; }));
      answers.push({ label: "IT — timeline", value: val("#qf-itWhen") });
      var s = val("#qf-itScope"); if (s) { answers.push({ label: "IT — notes", value: s }); }
    }
    if (both) {
      service = "Drone/UAV (" + uc.serviceLabel + ") + IT (" + itAreaLabel() + ")";
      target = "UAV: " + uavTarget + " | IT: " + itTarget;
      targetLabel = "Combined (UAV one-time + IT " + (ic.mode === "monthly" ? "monthly" : (ic.mode === "onetime" ? "one-time intake" : "tailored")) + ")";
    } else if (picks.uav) {
      service = "Drone/UAV — " + uc.serviceLabel;
      target = uc.contact ? "" : uc.range;
      targetLabel = uc.contact ? "" : uc.targetLabel;
    } else {
      service = "IT / technology — " + itAreaLabel();
      target = (ic.mode === "monthly") ? (ic.range + " /month") : "";
      targetLabel = (ic.mode === "monthly") ? ic.targetLabel : (ic.mode === "onetime" ? "One-time — intake requested" : "Tailored quote");
    }
    return { service: service, timingLabel: timingLabel, miles: miles, target: target, targetLabel: targetLabel, answers: answers };
  }
  function buildNotes(lead) {
    var out = [];
    if (picks.uav) {
      var uc = computeUav();
      out.push(uc.contact ? "UAV: bespoke / tower — scope on the call." : ("UAV ballpark: " + uc.range + " (" + uc.targetLabel + ", one-time, indicative — NOT a quote)."));
    }
    if (picks.it) {
      var ic = computeIt();
      if (ic.mode === "monthly") { out.push("IT: " + itAreaLabel() + " — " + ic.range + " /month (" + ic.targetLabel + ", indicative — NOT a quote); timeline " + val("#qf-itWhen") + "."); }
      else if (ic.mode === "onetime") { out.push("IT: " + itAreaLabel() + " — ONE-TIME project, intake appointment requested (no instant price shown); timeline " + val("#qf-itWhen") + "."); }
      else { out.push("IT: " + itAreaLabel() + " — a mix, tailored quote on the call; timeline " + val("#qf-itWhen") + "."); }
    }
    lead.answers.forEach(function (a) { out.push("  • " + a.label + ": " + a.value); });
    out.push("(Submitted via guided quote flow — " + (picks.uav && picks.it ? "both" : (picks.uav ? "UAV" : "IT")) + ".)");
    return out.join("\n");
  }
  function fireLead(lead) {
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
      fd.append("company_url", trapVal());
      fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin" });
    } catch (e) {}
  }

  function trapVal() { var t = root.querySelector("[data-qf-trap]"); return t ? (t.value || "").trim() : ""; }
  var S3 = { all: null, lead: null, notes: "", event: "it", tz: "", y: 0, m: 0, days: {}, day: "", slot: "", nonce: "", reqSeq: 0, busy: false };
  $("#qf-to3").addEventListener("click", function () {
    if (!contactOk()) { return; }
    var lead = buildLead();
    fireLead(lead);
    buildStep3(lead);
    go(3);
  });

  /* ======================= Step 3: native picker ======================= */
  function browserTz() {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC"; } catch (e) { return "UTC"; }
  }
  function dfmt(opts, d) {
    try { return new Intl.DateTimeFormat("en-US", Object.assign({ timeZone: S3.tz }, opts)).format(d); }
    catch (e) { return new Intl.DateTimeFormat("en-US", opts).format(d); }
  }
  function tzName() {
    var n = "";
    try {
      var parts = new Intl.DateTimeFormat("en-US", { timeZone: S3.tz, timeZoneName: "longGeneric" }).formatToParts(new Date());
      parts.forEach(function (p) { if (p.type === "timeZoneName") { n = p.value; } });
    } catch (e) {}
    return n;
  }
  function todayYmd() {
    try { return new Intl.DateTimeFormat("en-CA", { timeZone: S3.tz, year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date()); }
    catch (e) { return new Date().toISOString().slice(0, 10); }
  }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  /* Last bookable local day: today + 120 days (the server clamps its window to the same horizon). */
  function horizonYmd() { var p = todayYmd().split("-"); var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + 120)); return d.toISOString().slice(0, 10); }
  /* Group the server's UTC instants into the visitor's local days (time zone never leaves the browser). */
  function groupDays(list) {
    var out = {}, f;
    try { f = new Intl.DateTimeFormat("en-CA", { timeZone: S3.tz, year: "numeric", month: "2-digit", day: "2-digit" }); } catch (e) { f = null; }
    (list || []).forEach(function (iso) {
      var k = f ? f.format(new Date(iso)) : iso.slice(0, 10);
      if (S3.range && (k < S3.range.start || k > S3.range.end)) { return; } // UTC window spills into neighbouring days
      (out[k] = out[k] || []).push(iso);
    });
    return out;
  }
  function ymd(y, m, d) { return y + "-" + pad(m + 1) + "-" + pad(d); }
  function dayDate(s) { var p = s.split("-"); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2], 12)); }
  function dayLabel(s, opts) { return new Intl.DateTimeFormat("en-US", Object.assign({ timeZone: "UTC" }, opts)).format(dayDate(s)); }
  function timeLabel(iso) { return dfmt({ hour: "numeric", minute: "2-digit" }, new Date(iso)).replace(" AM", " am").replace(" PM", " pm"); }
  /* Call length as Cal.com reports it (GET /slots lengthMinutes) — never hard-coded. */
  function lengthMin() { return S3.len || 30; }

  function fallbackUrl() {
    // No name / e-mail / notes in the URL: no personal data in query strings to a third party.
    return (BOOT.fallback && BOOT.fallback[S3.event]) || "https://book.uplinksync.com/team/uplinksync/it-consult";
  }
  function showState(which) {
    ["#qf-stLoading", "#qf-stEmpty", "#qf-cal-fallback"].forEach(function (s) { $(s).classList.add("hidden"); });
    $("#qf-slotList").classList.toggle("hidden", which !== "slots");
    $("#qf-slotCol").setAttribute("aria-busy", which === "loading" ? "true" : "false");
    if (which === "loading") { $("#qf-stLoading").classList.remove("hidden"); }
    if (which === "empty") { $("#qf-stEmpty").classList.remove("hidden"); }
    if (which === "error") {
      $("#qf-cal-fallback-link").setAttribute("href", fallbackUrl());
      $("#qf-cal-fallback").classList.remove("hidden");
    }
  }
  function showError(msg) {
    $("#qf-errText").textContent = msg || "we couldn't load the calendar just now. Your estimate and details are saved.";
    $("#qf-dayTitle").textContent = "Book another way";
    $("#qf-daySub").textContent = "";
    showState("error");
  }
  function setConfirm(enabled) {
    $("#qf-confirm").setAttribute("aria-disabled", enabled ? "false" : "true");
    $("#qf-selNote").textContent = enabled
      ? dfmt({ weekday: "short", month: "short", day: "numeric" }, new Date(S3.slot)) + " · " + timeLabel(S3.slot) + " " + (tzName() || S3.tz)
      : "Pick a day and a time.";
  }

  function monthRange() {
    var first = ymd(S3.y, S3.m, 1);
    var lastD = new Date(Date.UTC(S3.y, S3.m + 1, 0)).getUTCDate();
    var t = todayYmd(), last = ymd(S3.y, S3.m, lastD), h = horizonYmd();
    return { start: first < t ? t : first, end: last > h ? h : last };
  }
  function renderMonth() {
    var t = todayYmd(), tp = t.split("-");
    $("#qf-month").textContent = new Intl.DateTimeFormat("en-US", { month: "long", year: "numeric", timeZone: "UTC" }).format(new Date(Date.UTC(S3.y, S3.m, 1, 12)));
    $("#qf-prev").disabled = (S3.y < +tp[0]) || (S3.y === +tp[0] && S3.m <= +tp[1] - 1);
    var nm = S3.m === 11 ? ymd(S3.y + 1, 0, 1) : ymd(S3.y, S3.m + 1, 1);
    $("#qf-next").disabled = nm > horizonYmd();
    var tb = $("#qf-days"); tb.textContent = "";
    var lead = new Date(Date.UTC(S3.y, S3.m, 1)).getUTCDay();
    var lastD = new Date(Date.UTC(S3.y, S3.m + 1, 0)).getUTCDate();
    var tr = document.createElement("tr");
    for (var i = 0; i < lead; i++) { tr.appendChild(document.createElement("td")); }
    for (var d = 1; d <= lastD; d++) {
      var key = ymd(S3.y, S3.m, d), td = document.createElement("td"), b = document.createElement("button");
      var has = !!(S3.days[key] && S3.days[key].length);
      b.type = "button"; b.className = "qf-day" + (has ? " av" : "") + (key === t ? " today" : "") + (key === S3.day ? " sel" : "");
      b.textContent = String(d);
      b.setAttribute("data-day", key);
      b.setAttribute("aria-label", dayLabel(key, { month: "long", day: "numeric", year: "numeric" }) + (has ? ", available" : ", no times"));
      if (key === S3.day) { b.setAttribute("aria-pressed", "true"); }
      if (!has) { b.disabled = true; }
      td.appendChild(b); tr.appendChild(td);
      if ((lead + d) % 7 === 0) { tb.appendChild(tr); tr = document.createElement("tr"); }
    }
    if (tr.children.length) { while (tr.children.length < 7) { tr.appendChild(document.createElement("td")); } tb.appendChild(tr); }
  }
  function firstAvailable(after) {
    var ks = Object.keys(S3.days).sort();
    for (var i = 0; i < ks.length; i++) { if ((!after || ks[i] > after) && S3.days[ks[i]].length) { return ks[i]; } }
    return "";
  }
  function selectDay(key) {
    S3.day = key; S3.slot = ""; setConfirm(false);
    renderMonth();
    var list = S3.days[key] || [];
    $("#qf-dayTitle").textContent = dayLabel(key, { weekday: "long", month: "long", day: "numeric" });
    if (!list.length) {
      $("#qf-daySub").textContent = "";
      $("#qf-emptyTitle").textContent = "No times on " + dayLabel(key, { weekday: "long", month: "short", day: "numeric" });
      var nx = firstAvailable(key);
      $("#qf-nextAvail").textContent = nx ? "Next available: " + dayLabel(nx, { weekday: "short", month: "short", day: "numeric" }) : "Try next month";
      $("#qf-nextAvail").setAttribute("data-next", nx || "");
      showState("empty");
      return;
    }
    $("#qf-daySub").textContent = list.length + (list.length === 1 ? " time" : " times") + " available" + (S3.len ? " · " + S3.len + " minutes each" : "");
    var box = $("#qf-slotList"); box.textContent = "";
    list.forEach(function (iso) {
      var b = document.createElement("button");
      b.type = "button"; b.className = "qf-slot"; b.setAttribute("aria-pressed", "false"); b.setAttribute("data-iso", iso);
      b.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>';
      b.appendChild(document.createTextNode(timeLabel(iso)));
      box.appendChild(b);
    });
    showState("slots");
  }
  function loadMonth(pick, auto) {
    var seq = ++S3.reqSeq, r = monthRange();
    S3.range = r; S3.all = null;
    S3.days = {}; S3.day = ""; S3.slot = ""; setConfirm(false);
    $("#qf-slotList").textContent = "";
    renderMonth();
    $("#qf-dayTitle").textContent = "Finding open times…";
    $("#qf-daySub").textContent = "";
    showState("loading");
    var ctl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctl) { ctl.abort(); } }, 12000);
    var url = (BOOT.rest || "/wp-json/uplinksync/v1/") + "slots?event=" + encodeURIComponent(S3.event) + "&start=" + r.start + "&end=" + r.end;
    fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" }, signal: ctl ? ctl.signal : undefined })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
      .then(function (j) {
        clearTimeout(timer);
        if (seq !== S3.reqSeq) { return; }
        if (!j || !j.ok || !j.slots) { showError(j && (j.error === "rate_limited" || j.error === "busy") ? "the calendar is busy just now. Your estimate and details are saved." : ""); return; }
        S3.all = j.slots; S3.days = groupDays(S3.all); if (j.nonce) { S3.nonce = j.nonce; }
        if (j.lengthMinutes) { S3.len = j.lengthMinutes; paintEyebrow(); }
        renderMonth();
        var f = (pick && S3.days[pick] && S3.days[pick].length) ? pick : firstAvailable("");
        if (f) { selectDay(f); return; }
        // Nothing left this month (e.g. its last days): look one month ahead once, automatically.
        if (auto && !$("#qf-next").disabled) { shiftMonth(1, false); return; }
        $("#qf-dayTitle").textContent = "";
        $("#qf-emptyTitle").textContent = "No times left in " + $("#qf-month").textContent;
        $("#qf-nextAvail").textContent = "Try next month"; $("#qf-nextAvail").setAttribute("data-next", "");
        showState("empty");
      })
      .catch(function () { clearTimeout(timer); if (seq === S3.reqSeq) { showError(""); } });
  }
  function shiftMonth(delta, auto) { var t = S3.y * 12 + S3.m + delta; S3.y = Math.floor(t / 12); S3.m = t % 12; loadMonth("", auto); }

  $("#qf-prev").addEventListener("click", function () { shiftMonth(-1); });
  $("#qf-next").addEventListener("click", function () { shiftMonth(1); });
  $("#qf-days").addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest(".qf-day.av"); if (b) { selectDay(b.getAttribute("data-day")); }
  });
  $("#qf-slotList").addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest(".qf-slot"); if (!b) { return; }
    $$("#qf-slotList .qf-slot").forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
    S3.slot = b.getAttribute("data-iso"); setConfirm(true);
  });
  $("#qf-nextAvail").addEventListener("click", function () {
    var nx = this.getAttribute("data-next");
    if (nx) { selectDay(nx); } else if (!$("#qf-next").disabled) { shiftMonth(1); }
  });
  $("#qf-tzChange").addEventListener("click", function () {
    var pick = $("#qf-tzPick"), sel = $("#qf-tz"), open = pick.classList.contains("hidden");
    if (open && !sel.options.length) {
      var zones = [];
      try { zones = Intl.supportedValuesOf("timeZone"); } catch (e) {}
      if (!zones.length) { zones = ["UTC"]; }
      if (zones.indexOf(S3.tz) === -1) { zones.unshift(S3.tz); }
      zones.forEach(function (z) { var o = document.createElement("option"); o.value = z; o.textContent = z.replace(/_/g, " "); sel.appendChild(o); });
    }
    sel.value = S3.tz;
    pick.classList.toggle("hidden", !open);
    this.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) { sel.focus(); }
  });
  $("#qf-tz").addEventListener("change", function () {
    S3.tz = this.value; paintTz();
    if (S3.all) { S3.days = groupDays(S3.all); S3.slot = ""; setConfirm(false); renderMonth(); var f = (S3.day && S3.days[S3.day]) ? S3.day : firstAvailable(""); if (f) { selectDay(f); } else { loadMonth(""); } }
    else { loadMonth(S3.day); }
  });
  function paintTz() { var n = tzName(); $("#qf-tzName").textContent = n ? n + " (" + S3.tz + ")" : S3.tz; }

  function fname() { return mode === "book" ? val("#qf-bname") : val("#qf-name"); }
  function femail() { return mode === "book" ? val("#qf-bemail") : val("#qf-email"); }
  function paintEyebrow() {
    var len = S3.len ? S3.len + "-minute " : "";
    if (mode === "book") { $("#qf-s3eyebrow").textContent = "Free " + len + "consultation"; return; }
    var both = picks.uav && picks.it, uavOnly = picks.uav && !picks.it;
    $("#qf-s3eyebrow").textContent = "Almost there · free " + len + (uavOnly ? "UAV consultation" : (both ? "scoping call" : "discovery call"));
  }
  function buildStep3(lead) {
    var uavOnly = picks.uav && !picks.it;
    S3.lead = lead; S3.notes = buildNotes(lead); S3.event = bookEvent();
    $("#qf-s3title").textContent = "Book your free consultation";
    $("#qf-s3lede").textContent = uavOnly ? "Pick a slot that works — we'll have your ballpark on hand." : "Pick a time and we'll come prepared with everything you've told us.";
    $("#qf-sumEst").textContent = estimateParts().join(" · ");
    $("#qf-sumEstRow").classList.remove("hidden");
    $("#qf-s3fine").textContent = "Your details and estimate travel with the booking so we arrive prepared. A person always confirms your final price \u2014 nothing here is a binding quote.";
    startCalendar();
  }
  function buildStep3Book() {
    S3.lead = null; S3.event = bIntent;
    S3.notes = (val("#qf-bnotes") ? val("#qf-bnotes") + "\n\n" : "") + "(Booked via \u201cBook a call\u201d on the website \u2014 no estimate.)";
    $("#qf-s3title").textContent = "Pick a time that suits you";
    $("#qf-s3lede").textContent = "You'll talk to the person who does the work. We'll send a calendar invite straight away.";
    $("#qf-sumEstRow").classList.add("hidden");
    $("#qf-s3fine").textContent = "Your details travel with the booking so we arrive prepared. Cal.com sends the calendar invite.";
    startCalendar();
  }
  function startCalendar() {
    if (!S3.tz) { S3.tz = browserTz(); }
    paintEyebrow();
    $("#qf-sumName").textContent = fname();
    $("#qf-sumEmail").textContent = femail();
    $("#qf-schedWrap").classList.remove("hidden");
    $("#qf-doneWrap").classList.add("hidden");
    paintTz();
    var tp = todayYmd().split("-");
    if (!S3.y) { S3.y = +tp[0]; S3.m = +tp[1] - 1; }
    loadMonth(S3.day, !S3.day);
  }

  /* ---- Confirm booking ---- */
  function icsStamp(d) { return d.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, ""); }
  function showDone(start, end) {
    var s = new Date(start), e = end ? new Date(end) : new Date(s.getTime() + lengthMin() * 60000);
    $("#qf-doneEmail").textContent = femail();
    $("#qf-doneEstRow").classList.toggle("hidden", mode === "book");
    $("#qf-doneWhen").textContent = dfmt({ weekday: "long", month: "long", day: "numeric" }, s) + " · " + timeLabel(s.toISOString()) + " – " + timeLabel(e.toISOString());
    $("#qf-doneTz").textContent = tzName() || S3.tz;
    $("#qf-doneEst").textContent = estimateParts().join(" · ");
    var title = "UplinkSync free consultation", details = "Video call with Doug, UplinkSync. The calendar invite from Cal.com has the join link.";
    $("#qf-gcal").setAttribute("href", "https://calendar.google.com/calendar/render?action=TEMPLATE&text=" + encodeURIComponent(title) + "&dates=" + icsStamp(s) + "/" + icsStamp(e) + "&details=" + encodeURIComponent(details));
    try {
      var ics = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//UplinkSync//Estimator//EN", "BEGIN:VEVENT",
        "UID:" + icsStamp(s) + "-" + Math.random().toString(36).slice(2) + "@uplinksync.com", "DTSTAMP:" + icsStamp(new Date()),
        "DTSTART:" + icsStamp(s), "DTEND:" + icsStamp(e), "SUMMARY:" + title, "DESCRIPTION:" + details, "END:VEVENT", "END:VCALENDAR"].join("\r\n");
      $("#qf-ics").setAttribute("href", URL.createObjectURL(new Blob([ics], { type: "text/calendar" })));
    } catch (err) { $("#qf-ics").classList.add("hidden"); }
    $("#qf-schedWrap").classList.add("hidden");
    $("#qf-doneWrap").classList.remove("hidden");
    $$("#qf-stepper .qf-step").forEach(function (x) { x.classList.remove("on"); x.classList.add("done"); x.removeAttribute("aria-current"); });
    $$("#qf-stepper .qf-bar").forEach(function (x) { x.classList.add("done"); });
    if (modal) { var d = modal.querySelector(".uls-qmodal__dlg"); if (d) { d.classList.remove("wide"); } }
    var h = $("#qf-doneTitle"); if (h) { h.focus(); }
  }
  $("#qf-confirm").addEventListener("click", function () {
    var btn = this;
    if (btn.getAttribute("aria-disabled") === "true" || S3.busy) { if (!S3.slot) { $("#qf-selNote").textContent = "Pick a day and a time first."; } return; }
    S3.busy = true; btn.setAttribute("aria-busy", "true"); btn.textContent = "Booking…";
    var uavSel = $("#qf-uavOpts [data-opt='video']"), rep = $("#qf-uavOpts [data-opt='report']");
    var body = mode === "book" ? {
      event: S3.event, start: S3.slot, timeZone: S3.tz,
      name: fname(), email: femail(), notes: S3.notes, hp: trapVal(),
      it: { goal: $("#qf-bgoal").value, setup: val("#qf-bsetup") },
      uav: { site: val("#qf-bsite"), siteType: $("#qf-bsiteType").value, deliverables: bDeliverables() }
    } : {
      event: S3.event, start: S3.slot, timeZone: S3.tz,
      name: val("#qf-name"), email: val("#qf-email"), notes: S3.notes, hp: trapVal(),
      it: { goal: $("#qf-goal").value, setup: val("#qf-itScope") ? val("#qf-itScope") + "\n\n" + S3.notes : S3.notes },
      uav: { site: val("#qf-site"), siteType: $("#qf-siteType").value, line: $("#qf-uavLine").value, video: !!(uavSel && uavSel.checked), report: !!(rep && rep.checked) }
    };
    var ctl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctl) { ctl.abort(); } }, 20000);
    var done = function () { clearTimeout(timer); S3.busy = false; btn.removeAttribute("aria-busy"); btn.textContent = "Confirm booking"; };
    fetch((BOOT.rest || "/wp-json/uplinksync/v1/") + "book", {
      method: "POST", credentials: "same-origin", signal: ctl ? ctl.signal : undefined,
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-ULS-Nonce": S3.nonce || "" },
      body: JSON.stringify(body)
    })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }).then(function (j) { return { status: res.status, j: j }; }); })
      .then(function (r) {
        done();
        if (r.j && r.j.ok) { showDone(r.j.start || S3.slot, r.j.end); return; }
        if (r.status === 409 && r.j && r.j.error === "in_progress") { $("#qf-selNote").textContent = "Already sent — check your inbox for the confirmation."; return; }
        if (r.status === 409) { $("#qf-selNote").textContent = "That time was just taken — please pick another."; loadMonth(S3.day); return; }
        showError(r.status === 429 ? "too many booking attempts just now. Your estimate and details are saved." : "we couldn't confirm that booking. Nothing was booked — your estimate and details are saved.");
        setConfirm(false);
      })
      .catch(function () { done(); showError("we couldn't reach the calendar. Nothing was booked — your estimate and details are saved."); setConfirm(false); });
  });
  if (!modal || !modal.contains(root)) {
    $("#qf-done").addEventListener("click", function () { window.location.href = "/"; });
  }

  /* ---- stepper ---- */
  function paneKey(n) { return (n === 1 && mode === "book") ? "b" : String(n); }
  function go(n) {
    var key = paneKey(n);
    $$(".qf-pane[data-pane]").forEach(function (p) { p.classList.toggle("hidden", p.getAttribute("data-pane") !== key); });
    var steps = $$("#qf-stepper .qf-step"), bars = $$("#qf-stepper .qf-bar");
    steps.forEach(function (s) {
      var i = parseInt(s.getAttribute("data-s"), 10);
      s.classList.toggle("on", i === n); s.classList.toggle("done", i < n);
      if (i === n) { s.setAttribute("aria-current", "step"); } else { s.removeAttribute("aria-current"); }
    });
    bars.forEach(function (b, i) { b.classList.toggle("done", i + 1 < n); });
    if (inModal) {
      var d = modal.querySelector(".uls-qmodal__dlg"); if (d) { d.classList.toggle("wide", mode === "book" ? n === 3 : n > 1); }
      var sc = modal.querySelector(".uls-qmodal__scroll"); if (sc) { sc.scrollTop = 0; }
    } else if (root.scrollIntoView) {
      root.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
    }
    var h = $('.qf-pane[data-pane="' + key + '"] .qf-h1');
    if (h) { h.setAttribute("tabindex", "-1"); try { h.focus({ preventScroll: true }); } catch (e) { h.focus(); } }
  }

  /* ======================= "Book a call" mode ======================= */
  var mode = "quote", bIntent = "";
  function setMode(m) {
    mode = m;
    root.classList.toggle("qf-mode-book", m === "book");
    var l = $('#qf-stepper .qf-step[data-s="1"] .qf-lbl');
    if (l) { l.textContent = l.getAttribute(m === "book" ? "data-book" : "data-quote"); }
    var n3 = $('#qf-stepper .qf-step[data-s="3"] .qf-n');
    if (n3) { n3.textContent = m === "book" ? "2" : "3"; } // two visible steps in book mode
  }
  function bpick(v) {
    bIntent = v;
    $$("[data-bpick]").forEach(function (b) {
      var on = b.getAttribute("data-bpick") === v;
      b.setAttribute("aria-pressed", on ? "true" : "false"); b.setAttribute("aria-checked", on ? "true" : "false");
    });
    $("#qf-bIt").classList.toggle("hidden", v !== "it");
    $("#qf-bUav").classList.toggle("hidden", v !== "uav");
  }
  function bDeliverables() {
    return $$('input[name="qf-bdeliv"]:checked').map(function (c) { return c.value; });
  }
  $$("[data-bpick]").forEach(function (b) { b.addEventListener("click", function () { bpick(b.getAttribute("data-bpick")); $("#qf-berr").classList.add("hidden"); }); });
  function bErr(el, msg) {
    $("#qf-berrText").textContent = msg; $("#qf-berr").classList.remove("hidden");
    if (el) { if (el.setAttribute && el.tagName !== "FIELDSET") { el.setAttribute("aria-invalid", "true"); } (el.querySelector && el.tagName === "FIELDSET" ? el.querySelector("input") : el).focus(); }
    return false;
  }
  function bookOk() {
    ["#qf-bname", "#qf-bemail", "#qf-bsetup", "#qf-bsite"].forEach(function (s) { $(s).setAttribute("aria-invalid", "false"); });
    if (!bIntent) { return bErr($('[data-bpick="it"]'), "Pick what the call is about."); }
    if (!val("#qf-bname")) { return bErr($("#qf-bname"), "Please add your name."); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val("#qf-bemail"))) { return bErr($("#qf-bemail"), "Please add an email we can send the invite to."); }
    if (bIntent === "it" && !val("#qf-bsetup")) { return bErr($("#qf-bsetup"), "A line about your current setup helps us come prepared."); }
    if (bIntent === "uav" && !val("#qf-bsite")) { return bErr($("#qf-bsite"), "Where is the site? A street address or the nearest town is enough."); }
    if (bIntent === "uav" && !bDeliverables().length) { return bErr($("#qf-bdeliv"), "Choose at least one deliverable."); }
    $("#qf-berr").classList.add("hidden");
    return true;
  }
  $("#qf-bto3").addEventListener("click", function () { if (bookOk()) { buildStep3Book(); go(3); } });
  $("#qf-toQuote").addEventListener("click", function () {
    if (!val("#qf-name") && val("#qf-bname")) { $("#qf-name").value = val("#qf-bname"); }
    if (!val("#qf-email") && val("#qf-bemail")) { $("#qf-email").value = val("#qf-bemail"); }
    setMode("quote"); go(1);
  });
  function visiblePane() { var p = root.querySelector(".qf-pane:not(.hidden)"); return p ? p.getAttribute("data-pane") : ""; }
  function isDone() { return !$("#qf-doneWrap").classList.contains("hidden") && visiblePane() === "3"; }
  flowStart = function (m, intent) {
    if (m === "book") {
      var resume = mode === "book" && !isDone() && (visiblePane() === "b" || visiblePane() === "3");
      setMode("book");
      if (intent) { bpick(intent); }
      if (!resume) { go(1); }
    } else if (mode !== "quote" || isDone()) {
      setMode("quote"); go(1);
    }
  };
  if (!modal) {
    /* Inline /request-quote/ page: booking links switch this flow to "Book a call" in place. */
    document.addEventListener("click", function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
      var m = match(e.target);
      if (!m || m.mode !== "book" || root.contains(m.el)) { return; }
      e.preventDefault();
      if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
      flowStart("book", m.intent);
    }, true);
  }

  /* ---- preset: skip step 1 ---- */
  if (preset === "uav" || preset === "it") {
    picks[preset] = true;
    var pb = root.querySelector('.qf-choice[data-pick="' + preset + '"]');
    if (pb) { pb.setAttribute("aria-pressed", "true"); }
    $("#qf-to2").setAttribute("aria-disabled", "false");
    var b2 = $("#qf-back2"); if (b2) { b2.style.display = "none"; }
    buildStep2(); go(2);
  }
  }
})();
