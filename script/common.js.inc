<script type="text/javascript">
<!--
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

function confirmLeave(element, confirm, text) {
  if (element) {
    if (!element.className.contains("highlight"))
		element.className += "highlight";
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
