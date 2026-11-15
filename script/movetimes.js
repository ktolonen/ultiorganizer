/*
 * Packs the transfer-time matrix into one field on submit. A matrix of N
 * fields posts N*N inputs, and PHP drops everything past max_input_vars.
 */
(function () {
  var form = document.getElementById("movetimesform");
  if (!form) {
    return;
  }
  form.addEventListener("submit", function () {
    var rows = [];
    var i = 0;
    var j;
    var row;
    var input;
    while (document.getElementById("move" + i + "_0")) {
      row = [];
      j = 0;
      input = document.getElementById("move" + i + "_" + j);
      while (input) {
        row.push(input.value.replace(/[^0-9]/g, ""));
        input.removeAttribute("name");
        j++;
        input = document.getElementById("move" + i + "_" + j);
      }
      rows.push(row.join(","));
      i++;
    }
    document.getElementById("movetimes").value = rows.join(";");
  });
})();
