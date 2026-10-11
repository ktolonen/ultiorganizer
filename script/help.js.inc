<script type="text/javascript">
function handleHelpButton(e) {
  e.preventDefault();
  var helpText = document.getElementById("helptext");
  helpText.style.display = helpText.style.display === "none" ? "" : "none";
}

document.addEventListener("DOMContentLoaded", function () {
  var helpText = document.getElementById("helptext");
  var helpButton = document.getElementById("helpbutton");
  if (!helpText || !helpButton) {
    return;
  }
  helpText.style.display = "none";
  helpButton.addEventListener("click", handleHelpButton);
});
</script>
