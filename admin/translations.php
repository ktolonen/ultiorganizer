<?php
include_once 'lib/translation.functions.php';

$LAYOUT_ID = TRANSLATIONS;

$title = _("Translations");	

//process itself if remove button was pressed
if(!empty($_POST['remove_x'])) {
	$key = $_POST['hiddenDeleteKey'];
	RemoveTranslation($key);
}

//process itself if add button was pressed
if(!empty($_POST['add'])) {
	$translations = array();
	if (isset($_POST["addkey"])) {
		foreach ($locales as $locale => $name) {
			$locale = str_replace(".", "_", $locale);
			if (isset($_POST['add'.$locale])) {
				$translations[$locale] = $_POST['add'.$locale];
			} else {
				$translations[$locale] = '';
			}
		}
		AddTranslation($_POST['addkey'], $translations);
		loadDBTranslations(getSessionLocale());
	}
}

if (!empty($_POST['save'])) {
	for ($i=0; $i<count($_POST['translationEdited']); $i++) {
		if ($_POST['translationEdited'][$i] == "yes") {
			$key = $_POST['translationKey'][$i];
			$translations = array();		
			foreach ($locales as $locale => $name) {
				$locale = str_replace(".", "_", $locale);
				if (isset($_POST[$locale.$i])) {
					$translations[$locale] = $_POST[$locale.$i];
				} else {
					$translations[$locale] = '';
				}
			}
			SetTranslation($key, $translations);
			loadDBTranslations(getSessionLocale());
		}
	}
}

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setKey(id) {
	var input = document.getElementById("hiddenDeleteKey");
	input.value = id;
}
	
function ChgTranslation(index) {
	YAHOO.util.Dom.get('translationEdited' + index).value = 'yes';
	YAHOO.util.Dom.get("save").disabled = false;
	YAHOO.util.Dom.get("cancel").disabled = false;
}

function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '')
	}
//-->
</script>
<?php
if (is_file('cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php')) {
	include_once 'cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php';
}
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//help
$help = "<p>"._("Modify translations").":</p>
	<ol>
		<li> "._("The translations are used to localize all user-provided text fields")." </li>
		<li> "._("The key is substitituted with the text in the user-selected locale")." </li>
		<li> "._("The key is case insensitive")." </li>
	</ol>";

onPageHelpAvailable($help);

//content
echo "<h2>"._("Translations")."</h2>\n";
if (hasTranslationRight()) {

	echo "<form method='post' action='?view=admin/translations'>\n";
	echo "<table border='0' cellpadding='2' width='100%'>\n";
	
	echo "<tr>
	<th>"._("Key")."</th>\n";
	foreach ($locales as $locale => $name) {
		echo "<th>".$name."</th>\n";
	}
	echo "<th>&nbsp;</th></tr>\n";

	$translations = Translations();
	$i = 0;
	$translation = mysqli_fetch_assoc($translations);
	while($translation) {
	  $tkey = $translation['translation_key'];
	  $values = array();
	  while($translations && $translation['translation_key'] == $tkey) {
	    $values[$translation['locale']]=$translation['translation'];
	    $translation = mysqli_fetch_assoc($translations);
	  }
		echo "<tr>\n<td>".utf8entities($tkey);
		echo "<input type='hidden' id='translationEdited".$i."' name='translationEdited[]' value='no'/>\n";
		echo "<input type='hidden' name='translationKey[]' value='".utf8entities($tkey)."'/>\n";
		echo "</td>\n";
		foreach ($locales as $locale => $name) {
		  $locale = str_replace('.', "_", $locale);
		  $value = isset($values[$locale])?$values[$locale]:"";
	      echo "<td><input type='text' size='25' maxlength='50' onkeypress=\"ChgTranslation(".$i.")\" value='".utf8entities($value)."' name='".$locale.$i."'/></td>";
		}
		echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setKey('".$tkey."');\"/></td></tr>";
		$i++;
	}
	echo "<tr>\n<td>";
	echo "<input type='text' name='addkey' value=''/>\n";
	echo "</td>\n";
	foreach ($locales as $locale => $name) {
		$locale = str_replace(".", "_", $locale); 
		echo "<td><input type='text' size='25' maxlength='50' value='' name='add".$locale."'/></td>";
	}
	echo "<td class='center'><input class='button' type='submit' name='add' value='"._("Add")."'/></td></tr>";
	
	echo "</table>\n";
	echo "<p><input type='hidden' id='hiddenDeleteKey' name='hiddenDeleteKey'/>";
	echo "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='"._("Save")."'/>";
	echo "<input disabled='disabled' id='cancel' class='button' name='cancel' type='submit' value='"._("Cancel")."'/>";
	echo "</p></form>\n";
}


//common end
contentEnd();
pageEnd();

?>
