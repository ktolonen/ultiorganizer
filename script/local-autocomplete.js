/* exported attachLocalSuggestions */
function attachLocalSuggestions(inputId, values, listId) {
  var input = document.getElementById(inputId);
  if (!input) {
    return;
  }
  var list = document.getElementById(listId);
  if (!list) {
    list = document.createElement("datalist");
    list.id = listId;
    values.forEach(function (value) {
      var option = document.createElement("option");
      option.value = value;
      list.appendChild(option);
    });
    document.body.appendChild(list);
  }
  input.setAttribute("list", listId);
}
