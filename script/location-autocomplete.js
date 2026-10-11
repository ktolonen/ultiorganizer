/* exported attachLocationSuggestions */
function attachLocationSuggestions() {
  var input = document.getElementById("locationName");
  var selectedId = document.getElementById("location");
  if (!input || !selectedId) {
    return;
  }
  var list = document.createElement("ul");
  list.id = "locationSuggestions";
  list.className = "location-suggestions";
  list.setAttribute("role", "listbox");
  list.setAttribute("aria-label", input.getAttribute("aria-label"));
  list.hidden = true;
  input.parentNode.appendChild(list);
  input.setAttribute("role", "combobox");
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-controls", list.id);
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("autocomplete", "off");
  var results = [];
  var active = -1;
  var timer;
  var request;
  var generation = 0;

  function close() {
    list.hidden = true;
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
    active = -1;
  }

  function cancel() {
    generation += 1;
    window.clearTimeout(timer);
    if (request) {
      request.abort();
    }
    close();
  }

  function select(index) {
    var result = results[index];
    if (result) {
      input.value = result.name;
      selectedId.value = result.id;
      cancel();
    }
  }

  function search() {
    cancel();
    var currentGeneration = generation;
    var query = input.value;
    if (!query.trim()) {
      return;
    }
    timer = window.setTimeout(function () {
      var xhr = new XMLHttpRequest();
      request = xhr;
      xhr.open("GET", "ext/locationtxt.php?query=" + encodeURIComponent(query));
      xhr.onload = function () {
        if (currentGeneration !== generation || xhr.status !== 200 || document.activeElement !== input) {
          return;
        }
        results = [];
        list.textContent = "";
        xhr.responseText.split("\n").forEach(function (line) {
          var fields = line.replace(/\r$/, "").split("\t");
          if (fields.length !== 3 || !/^\d+$/.test(fields[2])) {
            return;
          }
          var index = results.length;
          results.push({ name: fields[0], id: fields[2] });
          var option = document.createElement("li");
          option.id = list.id + index;
          option.setAttribute("role", "option");
          option.setAttribute("aria-selected", "false");
          option.setAttribute("data-index", index);
          option.textContent = fields[0] + " / " + fields[1];
          list.appendChild(option);
        });
        list.hidden = results.length === 0;
        input.setAttribute("aria-expanded", String(results.length > 0));
      };
      xhr.send();
    }, 200);
  }

  input.addEventListener("input", function () {
    selectedId.value = "";
    search();
  });
  input.addEventListener("focus", search);
  input.addEventListener("blur", cancel);
  input.addEventListener("keydown", function (event) {
    var key = event.keyCode;
    if (key === 27) {
      cancel();
      return;
    }
    if (list.hidden) {
      return;
    }
    if (key === 38 || key === 40) {
      event.preventDefault();
      if (active >= 0) {
        list.children[active].setAttribute("aria-selected", "false");
      }
      active = active < 0 ? (key === 40 ? 0 : results.length - 1) :
        (active + (key === 40 ? 1 : results.length - 1)) % results.length;
      var option = list.children[active];
      option.setAttribute("aria-selected", "true");
      input.setAttribute("aria-activedescendant", option.id);
      option.scrollIntoView({ block: "nearest" });
    } else if (key === 13 || key === 9) {
      if (active >= 0) {
        select(active);
        if (key === 13) {
          event.preventDefault();
        }
      } else {
        cancel();
      }
    }
  });
  list.addEventListener("mousedown", function (event) {
    event.preventDefault();
  });
  list.addEventListener("click", function (event) {
    var index = event.target.getAttribute("data-index");
    if (index !== null) {
      select(Number(index));
    }
  });
}
