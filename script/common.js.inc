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
	var url = location.href;
	
	var param = "selseason";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + id + '$2');
    }else{
        
		if(location.href.search("view=") !=-1){
			url = url + '&' + param + "=" + id;
		}else{
			url = url.substring(0,url.lastIndexOf('/'));
			url = url + "/index.php?"+ param + "=" + id; 
		}
	}
	location.href=url;
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
};


//-->
</script>