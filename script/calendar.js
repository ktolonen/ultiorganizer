(function () {
  var DATE_FORMAT = "j.n.Y";

  function calendarLocale() {
    var language = document.documentElement.lang.split(/[-_]/)[0];
    var locale = flatpickr.l10ns[language] || flatpickr.l10ns.default;
    var result = {};
    Object.keys(locale).forEach(function (key) {
      result[key] = locale[key];
    });
    result.firstDayOfWeek = 1;
    return result;
  }

  function initializeCalendar(input) {
    var button = document.getElementById(input.getAttribute("data-calendar-button"));
    if (!button) {
      return;
    }
    // flatpickr is bound to a detached input so it never parses or rewrites
    // what the user types; only a picked date is written to the form field.
    var calendar = flatpickr(document.createElement("input"), {
      clickOpens: false,
      dateFormat: DATE_FORMAT,
      disableMobile: true,
      ignoredFocusElements: [button, input],
      locale: calendarLocale(),
      positionElement: input,
      onChange: function (dates, dateStr) {
        if (dates.length) {
          input.value = dateStr;
        }
      }
    });
    input.addEventListener("input", function () {
      calendar.close();
    });
    button.addEventListener("click", function () {
      if (calendar.isOpen) {
        calendar.close();
        return;
      }
      var fallback = document.getElementById(input.getAttribute("data-calendar-fallback"));
      var date = calendar.parseDate(input.value, DATE_FORMAT);
      if (input.value && date && !isNaN(date.getTime())) {
        calendar.setDate(date, false);
      } else {
        calendar.clear(false);
        date = fallback && fallback.value ? calendar.parseDate(fallback.value, DATE_FORMAT) : undefined;
        if (date && !isNaN(date.getTime())) {
          calendar.jumpToDate(date);
        }
      }
      calendar.open();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var inputs = document.querySelectorAll("[data-calendar-button]");
    Array.prototype.forEach.call(inputs, initializeCalendar);
  });
}());
