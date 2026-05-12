function checkAll(field)
{
  var form = document.getElementById(field);

  for (var i=1; i < form.elements.length; i++)
  {
    form.elements[i].checked = !form.elements[i].checked;
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
