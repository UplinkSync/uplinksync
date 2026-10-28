/* Licence tier picker: radio cards drive Woo's (visually hidden) pa_licence select. */
(function ($) {
  $(function () {
    $(".variations_form").each(function () {
      var $f = $(this), $sel = $f.find('select[name="attribute_pa_licence"]');
      if (!$sel.length) { return; }
      $f.addClass("uls-lic-form");
      $sel.attr({ "aria-hidden": "true", tabindex: "-1" });
      var $r = $f.find('.uls-lic-picker input[type="radio"]');
      function sync() {
        var v = $sel.val() || "personal";
        $r.each(function () { this.checked = this.value === v; $(this).closest(".uls-lic-opt").toggleClass("is-on", this.checked); });
      }
      $r.on("change", function () { $sel.val(this.value).trigger("change"); sync(); });
      $sel.on("change", sync);
      if (!$sel.val()) { $sel.val("personal").trigger("change"); }
      sync();
    });
  });
})(jQuery);
