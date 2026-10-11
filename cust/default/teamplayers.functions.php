<?php
require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

if (!isset($teamId)) {
    $teamId = isset($_GET['team']) ? intval($_GET['team']) : 0;
} else {
    $teamId = intval($teamId);
}

if (is_file('cust/' . CUSTOMIZATIONS . '/players.php')) {
    $datasource = 'cust/' . CUSTOMIZATIONS . '/players.php';
} else {
    $datasource = 'cust/default/players.php';
}
$memberColumns = [
    ['key' => 'ProfileId', 'label' => _("Profile ID"), 'sort' => 'number'],
];
if (CUSTOMIZATIONS == "slkl") {
    $memberColumns[] = ['key' => 'AccreditationId', 'label' => _("Member ID"), 'sort' => 'number'];
}
$memberColumns[] = ['key' => 'Jersey', 'label' => _("#"), 'sort' => 'number'];
$memberColumns[] = ['key' => 'Firstname', 'label' => _("First name")];
$memberColumns[] = ['key' => 'Lastname', 'label' => _("Last name")];
$memberColumns[] = ['key' => 'BirthDate', 'label' => _("Date of birth"), 'sort' => 'date'];
$memberColumns[] = ['key' => 'Team', 'label' => _("Prev. Team")];
$memberColumns[] = ['key' => 'Event', 'label' => _("Prev. Event")];
?>
<script type="text/javascript" src="script/tablesort/tablesort.min.js"></script>
<script type="text/javascript" src="script/tablesort/sorts/tablesort.number.min.js"></script>
<link rel="stylesheet" type="text/css" href="script/tablesort/tablesort.css" />
<style type="text/css">
	#selectaccreditation_id {
		max-width: calc(100vw - 32px);
	}

	#selectaccreditation_id::backdrop {
		background-color: rgb(0 0 0 / 30%);
	}

	#accreditation_idgrid th::after {
		border-color: currentcolor transparent;
	}

	#accreditation_idgrid tbody tr {
		cursor: pointer;
	}

	#accreditation_idgrid tbody tr:hover {
		background-color: var(--highlight, #A6C7E9);
		font-weight: bold;
	}
</style>

<script type="text/javascript">
	var memberColumns = <?php echo json_encode($memberColumns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	var memberSort = null;
	var memberRequest = 0;

	function memberValue(node, key) {
		var el = node.getElementsByTagName(key)[0];
		return el ? el.textContent : "";
	}

	function memberCell(row, column, value) {
		var cell = row.insertCell(-1);
		if (column.sort === "date") {
			// The datasource writes day.month.year; 1.1.1970 and 1.1.1971 mean unknown.
			var parts = value.split(".");
			if (value === "01.01.1970" || value === "01.01.1971" || parts.length !== 3) {
				value = "";
				cell.setAttribute("data-sort", "");
			} else {
				cell.setAttribute("data-sort", parts[2] + parts[1] + parts[0]);
			}
		}
		cell.textContent = value;
	}

	function showMembers(members) {
		var table = document.getElementById("accreditation_idgrid");
		var head = table.tHead.rows[0];
		var body = table.tBodies[0];
		var i, j;
		if (head.cells.length === 0) {
			for (i = 0; i < memberColumns.length; i++) {
				var th = document.createElement("th");
				th.textContent = memberColumns[i].label;
				if (memberColumns[i].sort === "number") {
					th.setAttribute("data-sort-method", "number");
				}
				head.appendChild(th);
			}
		}
		body.innerHTML = "";
		for (i = 0; i < members.length; i++) {
			var row = body.insertRow(-1);
			row.memberData = {};
			for (j = 0; j < memberColumns.length; j++) {
				memberCell(row, memberColumns[j], memberValue(members[i], memberColumns[j].key));
			}
			row.memberData.ProfileId = memberValue(members[i], "ProfileId");
			row.memberData.AccreditationId = memberValue(members[i], "AccreditationId");
			row.memberData.Firstname = memberValue(members[i], "Firstname");
			row.memberData.Lastname = memberValue(members[i], "Lastname");
			row.memberData.Jersey = memberValue(members[i], "Jersey");
		}
		document.getElementById("accreditation_idempty").hidden = members.length > 0;
		if (memberSort) {
			memberSort.refresh();
		} else {
			memberSort = new Tablesort(table);
		}
	}

	function checkProfileId(playerId) {
		document.getElementById('dialogPlayerId').value = playerId;
		document.getElementById('dialogaccreditation_id').value = "";
		var firstname = document.getElementById('firstname' + playerId).value;
		var lastname = document.getElementById('lastname' + playerId).value;
		if (firstname.length < 2 && lastname.length < 2) {
			alert("<?php echo _("Name must be at least 2 letters long!"); ?>");
			return;
		}
		var request = ++memberRequest;
		var url = "<?php echo $datasource; ?>?firstname=" + encodeURIComponent(firstname) +
			"&lastname=" + encodeURIComponent(lastname) + "&team=<?php echo $teamId; ?>";
		showMembers([]);
		document.getElementById("accreditation_idempty").hidden = true;
		document.getElementById("selectaccreditation_id").showModal();
		fetch(url, {
			credentials: "same-origin"
		}).then(function(response) {
			return response.text();
		}).then(function(text) {
			if (request !== memberRequest) {
				return;
			}
			var xml = new DOMParser().parseFromString(text, "text/xml");
			showMembers(xml.getElementsByTagName("Member"));
		}).catch(function() {
			if (request === memberRequest) {
				showMembers([]);
			}
		});
	}

	function handleSubmit(member) {
		var playerId = document.getElementById('dialogPlayerId').value;
		var numberField = document.getElementById('number' + playerId);
		var existingNumber = "";
		if (numberField && numberField.value) {
			existingNumber = ("" + numberField.value).replace(/\s+/g, '');
		}
		var profileJersey = member.Jersey.replace(/\s+/g, '');

		document.getElementById('profileId' + playerId).value = member.ProfileId;
		document.getElementById('accrId' + playerId).value = member.AccreditationId;
		document.getElementById('firstname' + playerId).value = member.Firstname;
		document.getElementById('lastname' + playerId).value = member.Lastname;

		// Keep existing number untouched. Only fill from profile if empty.
		if (existingNumber.length === 0 && profileJersey.length > 0 && numberField) {
			numberField.value = profileJersey;
		}

		if (playerId == 0) {
			document.getElementById("add").value = "<?php echo _("Confirm"); ?>";
			document.getElementById("save").disabled = false;
			document.getElementById("cancel").disabled = false;
		} else {
			document.getElementById('showAccrId' + playerId).textContent = member.AccreditationId;
		}
		handleCancel();
		// After closing: a viewer without edit rights has no playerEdited field,
		// so this throws, and it must not leave the dialog stuck open.
		if (playerId != 0) {
			ChgPlayer(playerId);
		}
		return false;
	}

	function handleCancel() {
		document.getElementById("selectaccreditation_id").close();
		return false;
	}

	document.addEventListener("DOMContentLoaded", function() {
		var table = document.getElementById("accreditation_idgrid");
		if (!table) {
			return;
		}
		// Escape, the Close button and a picked row all close the dialog; drop any pending search.
		document.getElementById("selectaccreditation_id").addEventListener("close", function() {
			memberRequest++;
		});
		table.tBodies[0].addEventListener("click", function(e) {
			var row = e.target.closest("tr");
			if (row && row.memberData) {
				handleSubmit(row.memberData);
			}
		});
	});
</script>
