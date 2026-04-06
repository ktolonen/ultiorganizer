<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';

$LAYOUT_ID = USERS;
$title = _("Users");
$message = "";

if (hasEditUsersRight()) {
	if (isset($_POST['deleteuser'])) {
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				if (!empty($_POST['registerrequest'])) {
					DeleteRegisterRequest(urldecode($userid));
				} else {
					DeleteUser(urldecode($userid));
				}
			}
		}
	} elseif (isset($_POST['resetpassword'])) {
		if (IsEmailDisabled()) {
			$message = "<p class='warning'>" . _("Password reset email is unavailable. Open the user information page to set a new password manually.") . "</p>";
		} elseif (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				UserResetPassword(urldecode($userid));
			}
		}
	}
}
pageTopHeadOpen($title);
?>
<script type="text/javascript">
	function checkAll(field) {
		var form = document.getElementById(field);

		for (var i = 1; i < form.elements.length; i++) {
			form.elements[i].checked = !form.elements[i].checked;
		}
	}
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$target = "view=admin/users";
//content
echo "<p><a href='?view=admin/adduser'>" . _("Add new user") . "</a></p>";
echo "<h2>" . $title . "</h2>";
echo $message;
if (hasEditUsersRight()) {
	$actions = array('deleteuser' => _("Delete"));
	if (!IsEmailDisabled()) {
		$actions = array('resetpassword' => _("Reset password"), 'deleteuser' => _("Delete"));
	}
	echo SearchUser($target, array(), $actions);
}

contentEnd();
pageEnd();
?>
