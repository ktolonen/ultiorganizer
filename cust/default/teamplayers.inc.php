<div class="yui-skin-sam">
<div id="selectaccreditation_id" class="yui-pre-content">
	<div class="hd"><?php echo _("Choose best match"); ?></div>
	<div class="bd">
		<form method='post' action='?view=user/teamplayers&amp;team=<?php echo $_GET['team']; ?>'>
			<div>
			<input type='hidden' name='playerId' id='dialogPlayerId' value=''/>
			<input type='hidden' name='firstname' id='dialogFirstName' value=''/>
			<input type='hidden' name='lastname' id='dialogLastName' value=''/>
			<input type='hidden' name='playerId' id='dialogaccreditation_id' value=''/></div>
			<div id="accreditation_idgrid"></div>
			<div>
			<hr/>
			<input type='submit' class='button' value="<?php echo _("Close"); ?>" onclick="javascript:handleCancel();"/></div>
		</form>
	</div>
</div>
</div>
