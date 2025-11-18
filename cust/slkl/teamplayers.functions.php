<?php
include_once 'lib/yui.functions.php';

// Ensure $teamId is always defined before using it in the JS snippet below.
if (!isset($teamId)) {
	$teamId = isset($_GET['team']) ? intval($_GET['team']) : 0;
} else {
	$teamId = intval($teamId);
}

echo yuiLoad(array("utilities", "datasource", "datatable", "dragdrop", "container"));
?>
<link rel="stylesheet" type="text/css" href="script/yui/container/assets/container.css" />
<link rel="stylesheet" type="text/css" href="script/yui/datatable/assets/datatable-core.css" />
<style type="text/css">
	/* custom styles for this example */
	.yui-skin-sam .yui-dt-body {
		cursor: pointer;
	}

	/* when rows are selectable */
</style>

<script type="text/javascript">
	document.documentElement.className = "yui-pe";

	var dialog;

	function urlencode(str) {
		return escape(str).replace('+', '%2B').replace('%20', '+').replace('*', '%2A').replace('/', '%2F').replace('@', '%40');
	}

	function checkAccrId(playerId) {
		YAHOO.util.Dom.get('dialogPlayerId').value = playerId;
		YAHOO.util.Dom.get('dialogaccreditation_id').value = "";
		firstname = YAHOO.util.Dom.get('firstname' + playerId).value;
		lastname = YAHOO.util.Dom.get('lastname' + playerId).value;
		if (firstname.length < 2 && lastname.length < 2) {
			alert("<?php echo _("Name must be at least 2 letters long!"); ?>");
		} else {
			memberTable.initializeTable();
			memberDataSource.sendRequest("firstname=" + urlencode(firstname) + "&lastname=" + urlencode(lastname) + "&team=<?php echo $teamId; ?>", oCallback);
			dialog.show();
		}
	}

	function handleSubmit(oRecord) {
		var playerId = YAHOO.util.Dom.get('dialogPlayerId').value;

		var accrId = oRecord.getData("memberId");
		var firstname = oRecord.getData("Firstname");
		var lastname = oRecord.getData("Lastname");

		if (accrId == "") accrId = "<?php echo _("Search"); ?> ...";
		//YAHOO.util.Dom.get('showAccrId' + playerId).innerHTML = accrId;
		YAHOO.util.Dom.get('accrId' + playerId).value = accrId;
		YAHOO.util.Dom.get('firstname' + playerId).value = firstname;
		YAHOO.util.Dom.get('lastname' + playerId).value = lastname;
		YAHOO.util.Dom.get('number' + playerId).disabled = false;
		//	YAHOO.util.Dom.get('playerEdited' + playerId).value = 'yes';
		//	YAHOO.util.Dom.get("save").disabled = false;
		YAHOO.util.Dom.get("add").value = "<?php echo _("Confirm"); ?>";
		YAHOO.util.Dom.get("cancel").disabled = false;
		dialog.hide();
		return false;
	}

	function handleCancel() {
		dialog.hide();
		return false;
	};


	YAHOO.util.Event.addListener(window, "load", function() {
		var handleCancel = function() {
			this.cancel();
		};
		// Remove progressively enhanced content class, just before creating the module
		YAHOO.util.Dom.removeClass("selectaccreditation_id", "yui-pe-content");
		dialog = new YAHOO.widget.Dialog("selectaccreditation_id", {
			fixedcenter: false,
			visible: false,
			constraintoviewport: false
		});
		dialog.render();

	});

	var memberTable;
	var oCallback;
	var memberDataSource;

	function memberSelected(memberId) {
		YAHOO.util.Dom.get('dialogaccreditation_id').value = memberId;
	}

	YAHOO.util.Event.addListener(window, "load", function() {
		var AcrrIdLoader = function() {


			var formatBDate = function(elCell, oRecord, oColumn, oData) {
				elCell.innerHTML = YAHOO.util.Date.format(oData, {
					format: "%e.%m.%Y"
				}, "fi");
			}

			var memberColumnDefs = [{
					key: "memberId",
					label: "<?php echo _("Accr. Id"); ?>",
					sortable: true
				},
				{
					key: "Firstname",
					label: "<?php echo _("First name"); ?>",
					sortable: true
				},
				{
					key: "Lastname",
					label: "<?php echo _("Last name"); ?>",
					sortable: true
				},
				{
					key: "MembershipYear",
					label: "<?php echo _("Membership"); ?>",
					sortable: true
				},
				{
					key: "LicenseYear",
					label: "<?php echo _("License"); ?>",
					sortable: true
				},
				{
					key: "BirthDate",
					label: "<?php echo _("Date of birth"); ?>",
					formatter: formatBDate,
					sortable: true
				}
			];


			memberDataSource = new YAHOO.util.DataSource("cust/slkl/jasenet.php?");
			memberDataSource.connMethodPost = false;
			memberDataSource.responseType = YAHOO.util.DataSource.TYPE_XML;
			memberDataSource.responseSchema = {
				resultNode: "Member",
				fields: [{
						key: "memberId",
						parser: "number"
					},
					{
						key: "Firstname"
					},
					{
						key: "Lastname"
					},
					{
						key: "MembershipYear",
						parser: "number"
					},
					{
						key: "LicenseYear",
						parser: "number"
					},
					{
						key: "BirthDate"
					}
				]
			};
			memberTable = new YAHOO.widget.DataTable("accreditation_idgrid", memberColumnDefs, memberDataSource, {
				initialLoad: false,
				selectionMode: "single"
			});

			oCallback = {
				success: memberTable.onDataReturnInitializeTable,
				failure: memberTable.onDataReturnInitializeTable,
				scope: memberTable,
				argument: memberTable.getState() // data payload that will be returned to the callback function 
			};

			//highlight
			var onEventHighlightRow = function(oArgs) {
				var elRow = memberTable.getTrEl(oArgs.target);
				if (elRow) {
					var oRecord = memberTable.getRecord(elRow);
					YAHOO.util.Dom.addClass(elRow, "selectable_highlight");
				}
			};

			//un-highlight
			var onEventUnhighlightRow = function(oArgs) {
				var elRow = memberTable.getTrEl(oArgs.target);
				if (elRow) {
					var oRecord = memberTable.getRecord(elRow);
					YAHOO.util.Dom.removeClass(elRow, "selectable_highlight");
				}
			};

			//select
			var onEventSelectRow = function(oArgs) {
				var elRow = memberTable.getTrEl(oArgs.target);
				if (elRow) {
					var oRecord = memberTable.getRecord(elRow);
					handleSubmit(oRecord);
				}
			};

			memberTable.subscribe("rowMouseoverEvent", onEventHighlightRow);
			memberTable.subscribe("rowMouseoutEvent", onEventUnhighlightRow);
			memberTable.subscribe("rowClickEvent", onEventSelectRow);

			return {
				oDS: memberDataSource,
				oDT: memberTable
			};
		}();
	});
</script>
