/* exported attachTranslationSuggestions */
function attachTranslationSuggestions(inputId) {
  var input = document.getElementById(inputId);
  if (!input) {
    return;
  }
  var list = document.createElement("datalist");
  list.id = inputId + "Suggestions";
  input.parentNode.appendChild(list);
  input.setAttribute("list", list.id);
  var timer;
  var request;
  var generation = 0;

  input.addEventListener("input", function () {
    generation += 1;
    var currentGeneration = generation;
    window.clearTimeout(timer);
    if (request) {
      request.abort();
    }
    list.textContent = "";
    var query = input.value;
    if (!query.trim()) {
      return;
    }
    timer = window.setTimeout(function () {
      request = new XMLHttpRequest();
      request.open("GET", "ext/autocompletetranslationtxt.php?query=" + encodeURIComponent(query));
      request.onload = function () {
        if (currentGeneration !== generation || request.status !== 200) {
          return;
        }
        request.responseText.split("\n").forEach(function (line) {
          var fields = line.replace(/\r$/, "").split("\t");
          if (fields.length < 3 || !fields[1]) {
            return;
          }
          var option = document.createElement("option");
          option.value = fields[1];
          option.label = fields[0] + ": " + fields.slice(2).join("\t");
          list.appendChild(option);
        });
      };
      request.send();
    }, 200);
  });
}
