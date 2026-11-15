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
    var minutes;
    while (document.getElementById("move" + i + "_0")) {
      row = [];
      j = 0;
      input = document.getElementById("move" + i + "_" + j);
      while (input) {
        // The same integer the server's (int) cast reads from an unpacked post.
        minutes = parseInt(input.value, 10);
        row.push(isNaN(minutes) ? "0" : String(minutes));
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
