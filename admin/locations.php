<?php
include_once 'menufunctions.php';
include_once 'lib/location.functions.php';
include_once 'lib/configuration.functions.php';

if(!empty($_GET["season"])) {
	$season = $_GET["season"];
}

if (isset($_POST['save']) && isset($_POST['id'])) {
	$id = $_POST['id'];
	$name = _("Not named");
	$address = " ";
	$info = array();
	$fields = "1";
	$indoor = "0";
	$lat = "0";
	$lng = "0";
	if (isset($_POST['name'])) $name = $_POST['name']; 
	if (isset($_POST['address'])) $address = $_POST['address']; 
	foreach ($locales as $locale => $locname) {
		$locale = str_replace(".", "_", $locale);
		if (isset($_POST['info_'.$locale])) $info[$locale] = $_POST['info_'.$locale];
	} 
	if (isset($_POST['fields'])) $fields = $_POST['fields'];
	if (isset($_POST['indoor'])) $indoor = "1"; 
	if (isset($_POST['lat'])) $lat = $_POST['lat']; 
	if (isset($_POST['lng'])) $lng = $_POST['lng'];
	SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season);
}

if (isset($_POST['add'])) {
	$name = _("Not named");
	$address = " ";
	$info = array();
	$fields = "1";
	$indoor = "0";
	$lat = "0";
	$lng = "0";
	if (isset($_POST['name'])) $name = $_POST['name']; 
	if (isset($_POST['address'])) $address = $_POST['address']; 
	foreach ($locales as $locale => $locname) {
		$locale = str_replace(".", "_", $locale);
		if (isset($_POST['info_'.$locale])) $info[$locale] = $_POST['info_'.$locale];
	} 
	if (isset($_POST['fields'])) $fields = $_POST['fields']; 
	if (isset($_POST['indoor'])) $indoor = "1"; 
	if (isset($_POST['lat'])) $lat = $_POST['lat']; 
	if (isset($_POST['lng'])) $lng = $_POST['lng'];
	$newId = AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season);
	header('location: ?view=admin/locations&Location='.$newId."&amp;season=".$season);
	exit();
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
	$id = $_POST['id'];
	RemoveLocation($id);
	header('location: ?view=admin/locations');
	exit();
}

//common page
$title = _("Game locations");
$LAYOUT_ID = LOCATIONS;
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
?>
<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=<?php echo GetGoogleMapsAPIKey(); ?>" type="text/javascript"></script>
<script type="text/javascript">
    //<![CDATA[
    var map;
    var geocoder;

    function load() {
      if (GBrowserIsCompatible()) {
        geocoder = new GClientGeocoder();
        map = new GMap2(document.getElementById('map'));
        map.addControl(new GSmallMapControl());
        map.addControl(new GMapTypeControl());
        map.setCenter(new GLatLng(62, 25), 4);
      }
<?php
      
if (isset($_POST['id']) || isset($_GET['location'])) {
	if (isset($_POST['id'])) {
		$id = $_POST['id'];
	} else {
		$id = $_GET['location'];
	}
	echo "		var searchUrl = 'ext/locationxml.php?id=".$id."';\n";
	echo "		searchLocations(searchUrl);\n";
}

?>
    }

    function searchLatLng() {
        var address = document.getElementById('address').value;
        geocoder.getLatLng(address, function(point) {
          if (!point) {
            alert(document.getElementById('address').value + ' <?php echo _("not found");?>');
          } else {
        	var sidebar = document.getElementById('sidebar');
  			map.clearOverlays();
        	sidebar.innerHTML = '';		
        	var placeid = document.getElementById('place_id');
            var name = document.getElementById('name').value;
            var address = document.getElementById('address').value;
<?php
foreach ($locales as $locale => $locname) {
	$locale = str_replace(".", "_", $locale); 
	echo "\t\tvar info_".$locale." = document.getElementById('info_".$locale."').value;\n";
}

?>            var fields = document.getElementById('fields').value;
            var indoor = '<?php echo _("outdoors"); ?>';
      		if (document.getElementById('indoor').checked) {
          		indoor == '<?php echo _("indoors"); ?>'; 
      		}
            var marker = createMarker(point, name, address, ''<?php
            		foreach ($locales as $locale => $locname) { 
            	echo ", info_".str_replace(".", "_", $locale);
            }
            ?>, fields, indoor);
            var bounds = new GLatLngBounds();
            bounds.extend(point);
            map.addOverlay(marker);
            map.setCenter(bounds.getCenter(), map.getBoundsZoomLevel(bounds));
            var sidebarEntry = createSidebarEntry(marker, placeid, name, address<?php
            		foreach ($locales as $locale => $locname) { 
            	echo ", info_".str_replace(".", "_", $locale);
            }
            ?>, fields, indoor);
            sidebar.appendChild(sidebarEntry);
            GEvent.trigger(marker, 'click');
        	document.getElementById('lat').value = point.y;
            document.getElementById('lng').value = point.x;
        	document.getElementById('lat_disabled').value = point.y;
            document.getElementById('lng_disabled').value = point.x;
          }
        });
      }

	function seachByNameOrAddress() {
		var search = document.getElementById('search').value;
    	var searchUrl = 'ext/locationxml.php?search=' + search;
    	searchLocations(searchUrl);
	}

    function searchLocations(searchUrl) {
     GDownloadUrl(searchUrl, function(data) {
       var xml = GXml.parse(data);
       var markers = xml.documentElement.getElementsByTagName('marker');
       map.clearOverlays();

       var sidebar = document.getElementById('sidebar');
       sidebar.innerHTML = '';
       if (markers.length == 0) {
         sidebar.innerHTML = 'No results found.';
         map.setCenter(new GLatLng(62, 25), 4);
         return;
       }

       var bounds = new GLatLngBounds();
       for (var i = 0; i < markers.length; i++) {
         var placeid = markers[i].getAttribute('id');
         var name = markers[i].getAttribute('name');
         var address = markers[i].getAttribute('address');
         var info = markers[i].getAttribute('info');
<?php
	 foreach ($locales as $locale => $locname) {
	 	$locale = str_replace(".", "_", $locale);
         echo "\t\tvar info_".$locale." = markers[i].getAttribute('info_".$locale."');\n";
	 }
?>
         var fields = markers[i].getAttribute('fields');
		
         var indoorBit = markers[i].getAttribute('indoor');
		 var indoor = '<?php echo _("outdoors"); ?>';
		 if (indoorBit == '1') {
			 indoor = '<?php echo _("indoors"); ?>';
		 }
         var point = new GLatLng(parseFloat(markers[i].getAttribute('lat')),
                                 parseFloat(markers[i].getAttribute('lng')));
         
         var marker = createMarker(point, name, address, info<?php
        		 foreach ($locales as $locale => $locname) { 
        		echo ", info_".str_replace(".", "_", $locale);
        	}
        	?>, fields, indoor);
         map.addOverlay(marker);
         var sidebarEntry = createSidebarEntry(marker, placeid, name, address<?php
foreach ($locales as $locale => $locname) { 
	echo ", info_".str_replace(".", "_", $locale);
}
?>, fields, indoor);
         sidebar.appendChild(sidebarEntry);
         bounds.extend(point);
       }
       map.setCenter(bounds.getCenter(), map.getBoundsZoomLevel(bounds));
     });
   }

    function createMarker(point, name, address, info<?php
    		foreach ($locales as $locale => $locname) { 
    	echo ", info_".str_replace(".", "_", $locale);
    }
    ?>, fields, indoor) {
      var marker = new GMarker(point, {draggable: true});
      var html = '<b>' + name + '</b> ' + address + '<br/>' + info + '<br/>' + <?php 

    	      echo "'"._("Fields")." ' + fields + '(' + indoor + ')'"
    	      
    	      ?>;
      GEvent.addListener(marker, 'click', function() {
        marker.openInfoWindowHtml(html);
      });
      GEvent.addListener(marker, "dragend", function() {
          var point = marker.getLatLng();
          document.getElementById('lat').value = point.y;
          document.getElementById('lng').value = point.x;
	      document.getElementById('lat_disabled').value = point.y;
          document.getElementById('lng_disabled').value = point.x;
      });
    	      
      return marker;
    }

<?php 
global $locales;
echo "\tfunction createSidebarEntry(marker, placeid, name, address";
foreach ($locales as $locale => $locname) { 
	echo ", info_".str_replace(".", "_", $locale);
}
echo ", fields, indoor) {\n";
?>
      var div = document.createElement('div');
      div.id = 'place' + placeid;
      var html = '<b>' + name + '</b> ' + address + '<br/>' + <?php 

      echo "'"._("Fields")." ' + fields + ' (' + indoor + ')'";
      
      ?>;
      div.innerHTML = html;
      div.style.cursor = 'pointer';
      div.style.marginBottom = '5px'; 
      GEvent.addDomListener(div, 'click', function() {
        GEvent.trigger(marker, 'click');

        document.getElementById('place_id').value = placeid;
        document.getElementById('name').value = name;
        document.getElementById('address').value = address;
<?php 
foreach ($locales as $locale => $locname) {
	$locale = str_replace(".", "_", $locale);
    echo "\t\tdocument.getElementById('info_".$locale."').value = info_".$locale.";\n";
}
?>
        document.getElementById('fields').value = fields;
		if (indoor == '<?php echo _("outdoors"); ?>') {
			document.getElementById('indoor').checked = false;
		} else {
			document.getElementById('indoor').checked = true;
		}
        var point = marker.getLatLng();
        document.getElementById('lat').value = point.y;
        document.getElementById('lng').value = point.x;
    	document.getElementById('lat_disabled').value = point.y;
        document.getElementById('lng_disabled').value = point.x;

        document.getElementById('save').name = 'save';
        document.getElementById('save').value = '<?php echo _("Save"); ?>';
        document.getElementById('delete').name = 'delete';
        document.getElementById('delete').value = '<?php echo _("Delete"); ?>';
        
        document.getElementById('editPlace').style.visibility = 'visible';
        
      });
      GEvent.addDomListener(div, 'mouseover', function() {
        div.style.backgroundColor = '#eee';
      });
      GEvent.addDomListener(div, 'mouseout', function() {
        div.style.backgroundColor = '#fff';
      });
      return div;
    }
    function addLocation() {
        document.getElementById('place_id').value = '';
        document.getElementById('name').value = '';
        document.getElementById('address').value = '';
<?php
foreach ($locales as $locale => $locname) {
	$locale = str_replace(".", "_", $locale);
	echo "\t\tdocument.getElementById('info_".$locale."').value = '';\n";
}
?>        document.getElementById('fields').value = '1';
		document.getElementById('indoor').checked = false;
        document.getElementById('lat').value = '0';
        document.getElementById('lng').value = '0';
    	document.getElementById('lat_disabled').value = '0';
        document.getElementById('lng_disabled').value = '0';
        document.getElementById('save').name = 'add';
        document.getElementById('save').value = html_entity_decode('<?php echo _("Add"); ?>');;
        document.getElementById('delete').name = 'cancel';
        document.getElementById('delete').value = html_entity_decode('<?php echo _("Cancel"); ?>');;
        document.getElementById('editPlace').style.visibility = 'visible';
    }

    function html_entity_decode(str) {
    	var ta=document.createElement("textarea");
   		ta.innerHTML=str.replace(/</g,"&lt;").replace(/>/g,"&gt;");
    	return ta.value;
    }
    	    
    //]]>

  </script>
<?php 
pageTopHeadClose($title, false, "onload=\"load()\" onunload=\"GUnload()\"");
leftMenu($LAYOUT_ID);
contentStart();
?>
    <?php echo _("Name or address")?>: <input type="text" id="search"/>


    <input type="button" onclick="seachByNameOrAddress()" value="<?php echo _("Search"); ?>"/>
    <input type="button" onclick="addLocation()" value="<?php echo _("Add"); ?>"/>
    <br/>    
    <br/>
<div style="width:600px; font-family:Arial, 
sans-serif; font-size:11px; border:1px solid black">
  <table> 
    <tbody> 
      <tr id="cm_mapTR">

        <td style="width:200px; valign:top"> <div id="sidebar" style="overflow: auto; height: 400px; font-size: 11px; color: #000"></div>

        </td>
        <td> <div id="map" style="overflow: hidden; width:400px; height:400px"></div> </td>

      </tr> 
    </tbody>
  </table>
</div> 
<div id='editPlace' style='visibility: hidden'>
  <form method='post' action='?view=admin/locations&season=<?php echo $season ?>'>
  <div>
  <input type='hidden' name='id' id='place_id'/>
  <input type='hidden' name='lat' id='lat'/>
  <input type='hidden' name='lng' id='lng'/>
  </div>
  <table>
  	<tbody>
  	<tr><th><?php echo _("Name")?>:</th><td>
	<?php echo TranslatedField("name", ""); ?>
	</td></tr>
  	<tr><th><?php echo _("Address")?>:</th><td><input type='text' name='address' id='address' size='30' />&nbsp;<a href="javascript:searchLatLng();"><?php echo _("Fetch coordinates") ?></a></td></tr>
<?php 
	foreach ($locales as $locale => $name) {
		$locale = str_replace(".", "_", $locale);
		echo "<tr><th>"._("Info")." (".$name.")";
  		echo ":</th><td><textarea name='info_".$locale."' id='info_".$locale."' rows='3' cols='18'></textarea></td></tr>"; 
	}
  	?>
	<tr><th><?php echo _("Fields")?>:</th><td><input type='text' name='fields' id='fields'/></td></tr>
	<tr><th><?php echo _("Indoor pitch")?>:</th><td><input type='checkbox' name='indoor' id='indoor'/></td></tr>
	<tr><th><?php echo _("Longitude")?>:</th><td><input type='text' name='lng' id='lng_disabled' disabled='disabled'/></td></tr>
	<tr><th><?php echo _("Latitude")?>:</th><td><input type='text' name='lat' id='lat_disabled' disabled='disabled'/></td></tr>
  	</tbody>
  </table>
  <p>
  <input type='submit' id='save' name='save' value='<?php echo _("Save"); ?>'/>
  <input type='submit' id='delete' name='delete' value='<?php echo _("Delete"); ?>'/>
  </p>
  </form>
</div>
<?php 
echo TranslationScript("name");
contentEnd();
pageEnd();
?>