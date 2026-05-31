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
    } elseif (isset($_POST['deleteeventroles'])) {
        if (isset($_POST['users'])) {
            $deletedRoles = DeleteSelectedUsersEventRoles($_POST['users']);
            if ($deletedRoles > 0) {
                $message = "<p>" . sprintf(_("Deleted %d event access rights."), $deletedRoles) . "</p>";
            } else {
                $message = "<p>" . _("No event access rights found.") . "</p>";
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

	function confirmDeleteUsers() {
		var form = document.getElementById('users');
		var confirmMessage;

		if (!form) {
			return true;
		}

		if (form.querySelectorAll('input[name="users[]"]:checked').length === 0) {
			return true;
		}

		if (form.querySelector('input[name="registerrequest"]')) {
			confirmMessage = '<?php echo addslashes(_("Are you sure you want to delete the selected registration requests?")); ?>';
		} else {
			confirmMessage = '<?php echo addslashes(_("Are you sure you want to delete the selected users?")); ?>';
		}

		return confirm(confirmMessage);
	}

	function confirmDeleteEventAccessRights() {
		var form = document.getElementById('users');

		if (!form || form.querySelectorAll('input[name="users[]"]:checked').length === 0) {
			return true;
		}

		return confirm('<?php echo addslashes(_("Delete event access rights from the selected users?")); ?>');
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
    $actions = ['deleteeventroles' => _("Delete event access rights"), 'deleteuser' => _("Delete")];
    if (!IsEmailDisabled()) {
        $actions = ['resetpassword' => _("Reset password"), 'deleteeventroles' => _("Delete event access rights"), 'deleteuser' => _("Delete")];
    }
    echo SearchUser($target, [], $actions);
    echo "<script type='text/javascript'>
		(function() {
			var deleteButton = document.querySelector(\"#users input[name='deleteuser']\");
			var deleteEventRolesButton = document.querySelector(\"#users input[name='deleteeventroles']\");

			if (deleteButton) {
				deleteButton.onclick = confirmDeleteUsers;
			}
			if (deleteEventRolesButton) {
				deleteEventRolesButton.onclick = confirmDeleteEventAccessRights;
			}
		})();
	</script>";
}

contentEnd();
pageEnd();
?>
