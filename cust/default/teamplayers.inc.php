<?php
require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);
?>
<dialog id="selectaccreditation_id">
	<h3><?php echo _("Choose best match"); ?></h3>
	<form method="dialog">
		<div>
			<input type='hidden' name='playerId' id='dialogPlayerId' value='' />
			<input type='hidden' name='playerId' id='dialogaccreditation_id' value='' />
		</div>
		<table id="accreditation_idgrid" class="admintable">
			<thead>
				<tr></tr>
			</thead>
			<tbody></tbody>
		</table>
		<p id="accreditation_idempty" hidden="hidden"><?php echo _("No players matched the search."); ?></p>
		<p id="accreditation_iderror" hidden="hidden"><?php echo _("Player search failed. Please try again."); ?></p>
		<div>
			<hr />
			<input type='submit' class='button' value="<?php echo _("Close"); ?>" />
		</div>
	</form>
</dialog>
