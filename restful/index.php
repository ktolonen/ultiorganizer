<?php 
include '../lib/database.php';
include $include_prefix.'ext/localization.php';
include $include_prefix.'lib/restful.functions.php';
include $include_prefix.'lib/yui.functions.php';

restHeader(_("Ultiorganizer"), yuiLoad(array("connection")));
?>
<div id='seasons'></div>
<script type='text/javascript' src='<?php global $include_prefix; echo $include_prefix ?>script/restful/ultiorganizer.js'></script>
<script type="text/javascript">
//<![CDATA[
var callback = function(seasonarray) {
	var targetDiv = document.getElementById('seasons');
	var ul = document.createElementNS("http://www.w3.org/1999/xhtml", 'ul');
	for (var i=0; i<seasonarray.length; i++) {
		var li = document.createElementNS("http://www.w3.org/1999/xhtml", 'li');
		li.appendChild(seasonarray[i].getLinkNS("http://www.w3.org/1999/xhtml"));
		ul.appendChild(li);
	}
	targetDiv.appendChild(ul);
}

ultiorganizer.restful.getSeasonArray('seasons/', false, callback);

//]]>
</script>
<?php 
restFooter();
?>