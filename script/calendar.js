(function () {
  function initializeCalendar(input) {
    var button = document.getElementById(input.getAttribute("data-calendar-button"));
    if (!button) {
      return;
    }
    var language = document.documentElement.lang.split(/[-_]/)[0];
    var locale = flatpickr.l10ns[language] || flatpickr.l10ns.default;
    var calendarLocale = {};
    Object.keys(locale).forEach(function (key) {
      calendarLocale[key] = locale[key];
    });
    calendarLocale.firstDayOfWeek = 1;
    var calendar = flatpickr(input, {
      allowInput: true,
      clickOpens: false,
      dateFormat: "j.n.Y",
      disableMobile: true,
      ignoredFocusElements: [button],
      locale: calendarLocale
    });
    button.addEventListener("click", function () {
      if (calendar.isOpen) {
        calendar.close();
        return;
      }
      var fallback = document.getElementById(input.getAttribute("data-calendar-fallback"));
      var value = input.value || (fallback && fallback.value);
      if (value) {
        calendar.jumpToDate(value);
      }
      calendar.open();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var inputs = document.querySelectorAll("[data-calendar-button]");
    Array.prototype.forEach.call(inputs, initializeCalendar);
  });
}());
