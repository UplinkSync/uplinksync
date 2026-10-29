/* Licence tier picker: radio cards drive Woo's (visually hidden) pa_licence select; the add-to-cart
   button names the chosen tier's price ("Add to cart · $79"). Coming-soon tiers are not inputs. */
(function ($) {
  $(function () {
    $(".variations_form").each(function () {
      var $f = $(this), $sel = $f.find('select[name="attribute_pa_licence"]');
      if (!$sel.length) { return; }
      $f.addClass("uls-lic-form");
      $sel.attr({ "aria-hidden": "true", tabindex: "-1" });
      var $r = $f.find('.uls-lic-picker input[type="radio"]');
      var $btn = $f.find(".single_add_to_cart_button");
      var base = $.trim($btn.text()).split(" · ")[0];
      function sync() {
        var v = $sel.val() || "personal", price = "";
        $r.each(function () {
          this.checked = this.value === v;
          $(this).closest(".uls-lic-opt").toggleClass("is-on", this.checked);
          if (this.checked) { price = $.trim($(this).closest(".uls-lic-opt").find(".uls-lic-opt__price").text()); }
        });
        if (price && base) { $btn.text(base + " · " + price); }
      }
      $r.on("change", function () { $sel.val(this.value).trigger("change"); sync(); });
      $sel.on("change", sync);
      if (!$sel.val() && $r.filter(":checked").length) { $sel.val($r.filter(":checked").val()).trigger("change"); }
      sync();
    });
  });
})(jQuery);
