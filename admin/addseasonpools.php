<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
$LAYOUT_ID = ADDSEASONPOOLS;
$html = "";
$template=0;
$addmore=false;

$poolId = intval($_GET["pool"]);
$info = PoolInfo($poolId);
$season = $info['season'];
$seriesId = $info['series']; 	
	
//pool parameters
$pp = array(
	"name"=>"",
	"ordering"=>"A",
	"visible"=>"0",
	"continuingpool"=>"0",
	"placementpool"=>"0",
	"teams"=>"0",
	"mvgames"=>"0",
	"timeoutlen"=>"0",
	"halftime"=>"0",
	"winningscore"=>"0",
	"timecap"=>"0",
	"timeslot"=>"0",
	"scorecap"=>"0",
	"played"=>"0",
	"addscore"=>"0",
	"halftimescore"=>"0",
	"timeouts"=>"0",
	"timeoutsper"=>"game",
	"timeoutsovertime"=>"0",
	"timeoutstimecap"=>"0",
	"betweenpointslen"=>"0",
	"series"=>$seriesId,
	"type"=>"0",
    "playoff_template"=>"",
	"color"=>"ffffff",
    "forfeitscore"=>"0",
	"forfeitagainst"=>"0",
	"drawsallowed"=>"0");

//process itself on submit
if(!empty($_POST['add'])) {
	if(!empty($_POST['name'])) {
		$ordering='A';
		if(!empty($_POST['ordering'])){
			$ordering=$_POST['ordering'];
		}
		$template = $_POST['template'];
		$poolId = PoolFromPoolTemplate($seriesId, $_POST['name'], $ordering, $template);
		$html .= "<p>". _("Pool added"). ": " .utf8entities(U_($_POST['name'])). "</p>";
		$html .= "<hr/>";
		$addmore = true;
	} else {
		$html .= "<p class='warning'>"._("Pool name is mandatory!")."</p>";
	}
}

if(!empty($_POST['save'])) {
	$ok=true;
	$pp['name']=$_POST['name'];
	$pp['series']=$seriesId;
	/*$pp['teams']=intval($_POST['teams']);*/
	$pp['timeoutlen']=intval($_POST['timeoutlength']);
	$pp['halftime']=intval($_POST['halftimelength']);
	$pp['winningscore']=intval($_POST['gameto']);
	$pp['timecap']=intval($_POST['timecap']);
	$pp['timeslot']=intval($_POST['timeslot']);
	$pp['scorecap']=intval($_POST['pointcap']);
	$pp['addscore']=intval($_POST['extrapoint']);
	$pp['halftimescore']=intval($_POST['halftimepoint']);
	$pp['timeouts']=intval($_POST['timeouts']);
	$pp['timeoutsper']=$_POST['timeoutsfor'];
	$pp['timeoutsovertime']=intval($_POST['timeoutsOnOvertime']);
	$pp['timeoutstimecap']=intval($_POST['timeoutsOnOvertime']);
	$pp['betweenpointslen']=intval($_POST['timebetweenPoints']);
	$pp['type']=intval($_POST['type']);
	if (empty($_POST['playoff_template']))
		$pp['playoff_template'] = NULL;
	else
		$pp['playoff_template']=$_POST['playoff_template'];
	$comment=$_POST['comment'];
	$pp['ordering']=$_POST['ordering'];
	$pp['mvgames']=intval($_POST['mvgames']);
	$pp['color']=$_POST['color'];
	$pp['forfeitscore']=intval($_POST['forfeitscore']);
	$pp['forfeitagainst']=intval($_POST['forfeitagainst']);
	
	if(!empty($_POST['visible']))
		$pp['visible']=1;
	else
		$pp['visible']=0;

	if(!empty($_POST['played']))
		$pp['played']=1;
	else
		$pp['played']=0;

	if(!empty($_POST['continuationserie']))
		$pp['continuingpool']=1;
	else
		$pp['continuingpool']=0;
		
	if(!empty($_POST['placementpool']))
		$pp['placementpool']=1;
	else
		$pp['placementpool']=0;
		
	if(!empty($_POST['drawsallowed']))
		$pp['drawsallowed']=1;
	else
		$pp['drawsallowed']=0;
		
	if($ok){
		SetPoolDetails($poolId,$pp, $comment);
		session_write_close();
		header("location:?view=admin/seasonpools&season=$season");
	}
}
if ($poolId) {
	$info = PoolInfo($poolId);
	
	$pp['name']=$info['name'];
	$pp['teams']=$info['teams'];
	$pp['timeoutlen']=$info['timeoutlen'];
	$pp['halftime']=$info['halftime'];
	$pp['winningscore']=$info['winningscore'];
	$pp['timecap']=$info['timecap'];
	$pp['timeslot']=$info['timeslot'];
	$pp['scorecap']=$info['scorecap'];
	$pp['addscore']=$info['addscore'];
	$pp['halftimescore']=$info['halftimescore'];
	$pp['timeouts']=$info['timeouts'];
	$pp['timeoutsper']=$info['timeoutsper'];
	$pp['timeoutsovertime']=$info['timeoutsovertime'];
	$pp['timeoutstimecap']=$info['timeoutstimecap'];
	$pp['betweenpointslen']=$info['betweenpointslen'];
	$pp['continuingpool']=$info['continuingpool'];
	$pp['placementpool']=$info['placementpool'];
	$pp['played']=$info['played'];
	$pp['visible']=$info['visible'];
	$pp['series']=$info['series'];
	$pp['type']=$info['type'];
	$pp['playoff_template']=$info['playoff_template'];
	$pp['ordering']=$info['ordering'];
	$pp['mvgames']=$info['mvgames'];
	$pp['color']=$info['color'];
	$pp['forfeitagainst']=$info['forfeitagainst'];
	$pp['forfeitscore']=$info['forfeitscore'];
	$pp['drawsallowed']=$info['drawsallowed'];
}
$title = _("Edit");
	
//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "slider", "colorpicker", "datasource", "autocomplete"));

?>
<style type="text/css"> 
 #colorcontainer { position: relative; padding: 6px; background-color: #eeeeee; width: 300px; height:180px; } 
</style> 
<script type="text/javascript">

(function() {
    var Event = YAHOO.util.Event, picker;

    Event.onDOMReady(function() {
            picker = new YAHOO.widget.ColorPicker("colorcontainer", {
                    showhsvcontrols: false,
                    showhexcontrols: true,
                    showhexsummary: false,
                    showrgbcontrols: false,
                    showwebsafe: false,
					images: {
						PICKER_THUMB: "styles/yui/colorpicker/assets/picker_thumb.png",
						HUE_THUMB: "styles/yui/colorpicker/assets/hue_thumb.png"
    				}
                });
            picker.setValue([<?php 
            echo hexdec(substr($pp['color'], 0, 2)).", ";
            echo hexdec(substr($pp['color'], 2, 2)).", ";
            echo hexdec(substr($pp['color'], 4, 2));
            ?>], true);
			var onRgbChange = function(o) {
				var val = picker.get("hex");
				YAHOO.util.Dom.get('color').value = val;  
				var btn = YAHOO.util.Dom.get('showcolor');
				YAHOO.util.Dom.setStyle(btn, "background-color", "#" + val);
			}
			
			//subscribe to the rgbChange event;
			picker.on("rgbChange", onRgbChange);

			var handleColorButton = function() {
				var containerDiv = YAHOO.util.Dom.get("colorcontainer"); 
				if(containerDiv.style.display == "none"){
					YAHOO.util.Dom.setStyle(containerDiv, "display", "block");
				} else {
					YAHOO.util.Dom.setStyle(containerDiv, "display", "none");
				}
			}
		    YAHOO.util.Event.addListener("showcolor", "click", handleColorButton);
			
        });
})();

</script>

<?php 
$setFocus = "onload=\"document.getElementById('name').focus();\"";
pageTopHeadClose($title, false, $setFocus);

leftMenu($LAYOUT_ID);
contentStart();

echo $html;

//if poolId is empty, then add new pool	
if(!$poolId || $addmore) {
	echo "<h2>"._("Add pool")."</h2>\n";	
	echo "<form method='post' action='?view=admin/addseasonpools&amp;season=$season&amp;series=$seriesId'>";
	echo "<table cellpadding='2'>
			<tr>
			<td class='infocell'>"._("Name").":</td>
			<td>".TranslatedField("name", $pp['name'])."</td>
			</tr>\n";
	echo "<tr>
			<td class='infocell'>"._("Order")." (A,B,C,D ...):</td>
			<td><input class='input' id='ordering' name='ordering' value='".utf8entities($pp['ordering'])."'/></td>
		</tr>\n";
	echo "<tr>
			<td class='infocell'>"._("Template").":</td>
			<td><select class='dropdown' name='template'>";

	$templates = PoolTemplates();
		
	foreach($templates as $row) {
		if($template==$row['template_id']){
			echo "<option class='dropdown' selected='selected' value='".utf8entities($row['template_id'])."'>". utf8entities($row['name']) ."</option>";
		}else{
			echo "<option class='dropdown' value='".utf8entities($row['template_id'])."'>". utf8entities($row['name']) ."</option>";
		}
	}
	echo "</select></td>
		</tr>";
	
	echo "</table>
		  <p><input class='button' name='add' type='submit' value='"._("Add")."'/>
		  <input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>
		  </form>";
} else {	
	echo "<h2>"._("Edit pool").":</h2>\n";	
	echo "<form method='post' action='?view=admin/addseasonpools&amp;pool=$poolId&amp;season=$season'>";
	
	echo "<table cellpadding='2'>
		<tr>
			<td class='infocell'>"._("Name").":</td>
			<td>".TranslatedField("name", $pp['name'])."</td>
		</tr>\n";
			
	$seriesname = SeriesName($pp['series']);
	echo "<tr><td class='infocell'>"._("Division").":</td>
			<td><input class='input' id='series' name='series' disabled='disabled' value='".utf8entities($seriesname)."'/></td>
			<td></td></tr>";
	
	echo "<tr><td class='infocell'>"._("Order")." (A,B,C,D ...):</td>
		<td><input class='input' id='ordering' name='ordering' value='".utf8entities($pp['ordering'])."'/></td></tr>";
/*		
	echo "<tr><td class='infocell'>"._("Teams").":</td>
			<td><input class='input' id='teams' name='teams' size='5' value='".utf8entities($pp['teams'])."'/></td>
			<td></td></tr>";
*/			

	echo "<tr><td class='infocell'>"._("Type").":</td><td>";
	
	echo "<select class='dropdown' name='type'>";
	if($pp['type']=="1")
		echo "<option class='dropdown' selected='selected' value='1'>"._("Round Robin")."</option>";
	else
		echo "<option class='dropdown' value='1'>"._("Division")."</option>";
			
	if($pp['type']=="2")
		echo "<option class='dropdown' selected='selected' value='2'>"._("Play-off")."</option>";
	else
		echo "<option class='dropdown' value='2'>"._("Play-off")."</option>";
				
	if($pp['type']=="3")
		echo "<option class='dropdown' selected='selected' value='3'>"._("Swissdraw")."</option>";
	else
		echo "<option class='dropdown' value='3'>"._("Swissdraw")."</option>";

	if($pp['type']=="4")
		echo "<option class='dropdown' selected='selected' value='4'>"._("Crossmatch")."</option>";
	else
		echo "<option class='dropdown' value='4'>"._("Crossmatch")."</option>";
	
	echo "<tr><td class='infocell'>"._("Special playoff template").":</td>
		<td><input class='input' id='playoff_template' name='playoff_template' value='".utf8entities($pp['playoff_template'])."'/></td></tr>";
		
	echo "</select></td></tr>";
		echo "<tr><td class='infocell'>"._("Move games").":</td><td>";
	
	echo "<select class='dropdown' name='mvgames'>";
	if($pp['mvgames']=="0")
		echo "<option class='dropdown' selected='selected' value='0'>"._("All")."</option>";
	else
		echo "<option class='dropdown' value='0'>"._("All")."</option>";

	if($pp['mvgames']=="1")
		echo "<option class='dropdown' selected='selected' value='1'>"._("Nothing")."</option>";
	else
		echo "<option class='dropdown' value='1'>"._("Nothing")."</option>";
		
	if($pp['mvgames']=="2")
		echo "<option class='dropdown' selected='selected' value='2'>"._("Mutual")."</option>";
	else
		echo "<option class='dropdown' value='2'>"._("Mutual")."</option>";
		
	echo "</select></td></tr>";
	
	echo "<tr><td class='infocell'>"._("Visible").":</td>";
	
	$frompool = PoolGetMoveFrom($info['pool_id'],1);
	$frompoolinfo = PoolInfo($frompool['frompool']);
// CS: Sometimes you want to change the visibility setting in Swissdraw
	if(rtrim($frompoolinfo['ordering'],"0..9")==rtrim($pp['ordering'],"0..9")){ // Playoff or Swissdraw
	    echo "<td><input class='input' disabled='disabled' type='checkbox' id='visible' name='visible'/></td>";
	}else{
    	if(intval($pp['visible']))
    		echo "<td><input class='input' type='checkbox' id='visible' name='visible' checked='checked'/></td>";
    	else
    		echo "<td><input class='input' type='checkbox' id='visible' name='visible' /></td>";
	}
	echo "<td></td></tr>";

	echo "<tr><td class='infocell'>"._("Played").":</td>";
	if(intval($pp['played']))
		echo "<td><input class='input' type='checkbox' id='played' name='played' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='played' name='played' /></td>";
	echo "<td></td></tr>";
	
				
	echo "<tr><td class='infocell'>"._("Continuing pool").":</td>";
	if(rtrim($frompoolinfo['ordering'],"0..9")==rtrim($pp['ordering'],"0..9")){ // Playoff or Swissdraw
	   		echo "<td><input class='input' disabled='disabled' type='checkbox' id='continuationserie' name='continuationserie' checked='checked'/></td>";
	}else{
    	if(intval($pp['continuingpool']))
    		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' checked='checked'/></td>";
    	else
    		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' /></td>";
	} 
   echo "<td></td></tr>";
        	
	
	echo "<tr><td class='infocell'>"._("Placement pool").":</td>";
	if(intval($pp['placementpool']))
		echo "<td><input class='input' type='checkbox' id='placementpool' name='placementpool' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='placementpool' name='placementpool' /></td>";
	echo "<td></td></tr>";
	
	if(intval($pp['continuingpool'])) {
		echo "<tr><td class='infocell'>"._("Initial pools").":</td>
			<td><a href='?view=admin/poolmoves&amp;season=$season&amp;series=".$pp['series']."&amp;pool=".$poolId."'>"._("select")."</a></td>
			<td></td></tr>";
	}
	echo "<tr><td class='infocell'>"._("Color").":</td>";
	echo "<td><input class='input' type='hidden' id='color' name='color' value='".utf8entities($pp['color'])."'/>\n";
	echo "<button type='button' id='showcolor' class='button' style='background-color:#".$pp['color']."'>"._("Select")."</button></td>";
	echo "<td></td></tr>";
	
	$comment = CommentRaw(3, $poolId);
	echo "<tr><td class='infocell' style='vertical-align:top'>".htmlentities(_("Comment (you can use <p>, </i>, and <br> tags)")).":</td>
		<td><textarea class='input' rows='10' cols='70' id='comment' name='comment'>".htmlentities($comment)."</textarea></td></tr>";
	
	
	echo "</table>";
	echo "<div class='yui-skin-sam' id='colorcontainer' style='display:none'></div>";
	
	echo "<h2>"._("Teams").":</h2>";	
	
		
	$teams = PoolTeams($poolId);
	if(count($teams)) {
		echo "<table width='75%' cellpadding='4'><tr><th>"._("Name")."</th><th>"._("Club")."</th></tr>\n";
			
		foreach($teams as $team){
			echo "<tr>";
			echo "<td>".utf8entities($team['name'])."</td>";
			echo "<td>".utf8entities($team['clubname'])."</td>";
			echo "</tr>\n";	
		}
		echo "</table>";
	} else {
		echo "<p>"._("No teams")."</p>";
	}
	//echo "<p><input class='button' name='add' type='button' value='"._("Valitse ...")."' onclick=\"window.location.href='?view=admin/serieteams&amp;Serie=$seriesId&amp;season=$season'\"/></p>";	
	
	echo "<h2>"._("Rules")." "._("(from the selected template)").":</h2>";
	
	echo "<table cellpadding='2'>";
		
	echo "<tr><td class='infocell'>"._("Game points").":</td>
			<td><input class='input' id='gameto' name='gameto' value='".utf8entities($pp['winningscore'])."'/></td>
			<td></td></tr>

		<tr><td class='infocell'>"._("Half-time").":</td>
			<td><input class='input' id='halftimelength' name='halftimelength' value='".utf8entities($pp['halftime'])."'/></td>
			<td>"._("minutes")."</td></tr>		

		<tr><td class='infocell'>"._("Half-time at point").":</td>
			<td><input class='input' id='halftimepoint' name='halftimepoint' value='".utf8entities($pp['halftimescore'])."'/></td>
			<td></td></tr>		
			
		<tr><td class='infocell'>"._("Time cap").":</td>
			<td><input class='input' id='timecap' name='timecap' value='".utf8entities($pp['timecap'])."'/></td>
			<td>"._("minutes")."</td></tr>		
		
		<tr><td class='infocell'>"._("Time slot").":</td>
			<td><input class='input' id='timeslot' name='timeslot' value='".utf8entities($pp['timeslot'])."'/></td>
			<td>"._("minutes")."</td></tr>		
			
		<tr><td class='infocell'>"._("Point cap").":</td>
			<td><input class='input' id='pointcap' name='pointcap' value='".utf8entities($pp['scorecap'])."'/></td>
			<td>"._("points")."</td></tr>

		<tr><td class='infocell'>"._("Additional points after time cap").":</td>
			<td><input class='input' id='extrapoint' name='extrapoint' value='".utf8entities($pp['addscore'])."'/></td>
			<td>"._("points")."</td></tr>

			
		<tr><td class='infocell'>"._("Time between points").":</td>
			<td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='".utf8entities($pp['betweenpointslen'])."'/></td>
			<td>"._("seconds")."</td></tr>
			
		<tr><td class='infocell'>"._("Time-outs").":</td>
			<td><input class='input' id='timeouts' name='timeouts' value='".utf8entities($pp['timeouts'])."'/></td>
			<td>
			<select class='dropdown' name='timeoutsfor'>";
			if($pp['timeoutsper']=="game" || $pp['timeoutsper']=="")
				echo "<option class='dropdown' selected='selected' value='game'>"._("per game")."</option>";
			else
				echo "<option class='dropdown' value='game'>"._("per game")."</option>";
			
			if($pp['timeoutsper']=="half")	
				echo "<option class='dropdown' selected='selected' value='half'>"._("per half")."</option>";
			else 
				echo "<option class='dropdown' value='half'>"._("per half")."</option>";
				
	echo "	</select>
			</td></tr>

		<tr><td class='infocell'>"._("Time-out duration").":</td>
			<td><input class='input' id='timeoutlength' name='timeoutlength' value='".utf8entities($pp['timeoutlen'])."'/></td>
			<td>"._("seconds")."</td></tr>

		<tr><td class='infocell'>"._("Time-outs in overtime").":</td>
			<td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='".utf8entities($pp['timeoutsovertime'])."'/></td>
			<td>"._("per team")."</td></tr>		";

	


	echo "
		<tr><td class='infocell'>"._("Forfeit/BYE against").":</td>
			<td><input class='input' id='forfeitagainst' name='forfeitagainst' value='".utf8entities($pp['forfeitagainst'])."'/></td>
			<td>"._("points for the team giving up / BYE")."</td></tr>
	
		<tr><td class='infocell'>"._("Forfeit/BYE score").":</td>
			<td><input class='input' id='forfeitscore' name='forfeitscore' value='".utf8entities($pp['forfeitscore'])."'/></td>
			<td>"._("points for their remaining opponent")."</td></tr>

		";
	
	echo "<tr><td class='infocell'>"._("Draws allowed").":</td>";
	if(intval($pp['drawsallowed']))
		echo "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' /></td>";
	echo "<td></td></tr>";
	
	
	
	echo "</table>";

	echo "<p><input class='button' name='save' type='submit' value='"._("Save")."'/>";
	echo "<input class='button' type='button' name='back'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>";
	echo "</form>\n";
	}
echo TranslationScript("name");
contentEnd();
pageEnd();
?>
