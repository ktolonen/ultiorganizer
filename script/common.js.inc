<script type="text/javascript">
<!--
/* exported checkAll, clearAll, setId, changeseason, confirmLeave, menu_toggle, navigateBack */
function checkAll(field)
{
  var form = document.getElementById(field);

  for (var i=1; i < form.elements.length; i++)
  {
    form.elements[i].checked = !form.elements[i].checked;
  }
}

function clearAll(field)
{
  var form = document.getElementById(field);

  for (var i=1; i < form.elements.length; i++)
  {
    form.elements[i].checked = false;
  }
}

function setId(id)
{
  var input = document.getElementById("hiddenDeleteId");
  input.value = id;
}

function changeseason(id){
  var sid = encodeURIComponent(id);
  window.location.href = "index.php?view=games&season=" + sid + "&selseason=" + sid;
}

/* Navigate to the URL held in the element's data-href attribute. The URL is read
   via getAttribute (already HTML-decoded by the browser) and is never parsed as
   JavaScript, so it cannot inject script. Block non-http(s) schemes (e.g.
   javascript:) so a crafted return URL cannot execute. */
function navigateBack(el)
{
  var url = el.getAttribute("data-href") || "";
  var scheme = url.match(/^\s*([a-z][a-z0-9+.-]*):/i);
  if (scheme && !/^https?$/i.test(scheme[1])) {
    return;
  }
  window.location.href = url;
}

function confirmLeave(element, confirm, text) {
  if (element) {
    if (!element.classList.contains("highlight"))
      element.classList.add("highlight");
  }
  if (confirm)
    window.onbeforeunload = function() {
      return text==null?"":text;
    }
  else
    window.onbeforeunload = null;
}

function menu_toggle() {
  var x = document.getElementById('menu_left') || document.querySelector('.menu_left');
  if (!x) {
    return;
  }
  x.style.display = '';
  if (x.classList.contains('menu_open')) {
    x.classList.remove('menu_open');
  } else {
    x.classList.add('menu_open');
  }
}

//-->
</script>
