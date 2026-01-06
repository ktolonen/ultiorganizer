<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/api.functions.php';
include_once 'lib/season.functions.php';

$LAYOUT_ID = APITOKENS;
$title = _("API Tokens");

$createdToken = null;
$errors = array();

if (isSuperAdmin()) {
  if (!empty($_POST['create_token'])) {
    $label = trim((string)($_POST['label'] ?? ''));
    $scopeType = trim((string)($_POST['scope_type'] ?? ''));
    $scopeId = trim((string)($_POST['scope_id'] ?? ''));

    $allowedScopes = array('installation', 'season', 'user');
    if (!in_array($scopeType, $allowedScopes, true)) {
      $errors[] = _("Invalid scope type.");
    }

    if ($scopeType === 'season') {
      if ($scopeId === '') {
        $errors[] = _("Season id is required for season scope.");
      } elseif (!SeasonExists($scopeId)) {
        $errors[] = _("Season not found.");
      }
    } elseif ($scopeType === 'user') {
      if ($scopeId === '') {
        $errors[] = _("User id is required for user scope.");
      } else {
        $userExists = DBQueryToValue(
          "SELECT userid FROM uo_users WHERE userid='" . DBEscapeString($scopeId) . "'"
        );
        if ($userExists === -1 || $userExists === null || $userExists === false) {
          $errors[] = _("User not found.");
        }
      }
    } else {
      $scopeId = '';
    }

    if (empty($errors)) {
      $createdToken = ApiTokenCreate($label, $scopeType, $scopeId);
    }
  } elseif (!empty($_POST['revoke_token'])) {
    $tokenId = (int)($_POST['token_id'] ?? 0);
    if ($tokenId > 0) {
      ApiTokenSetRevoked($tokenId, 1);
    }
  } elseif (!empty($_POST['restore_token'])) {
    $tokenId = (int)($_POST['token_id'] ?? 0);
    if ($tokenId > 0) {
      ApiTokenSetRevoked($tokenId, 0);
    }
  } elseif (!empty($_POST['delete_x']) && !empty($_POST['hiddenDeleteId'])) {
    $tokenId = (int)$_POST['hiddenDeleteId'];
    if ($tokenId > 0) {
      ApiTokenDelete($tokenId);
    }
  }
}

pageTopHeadOpen($title);
?>
<script type="text/javascript">
  function toggleScopeId() {
    var scopeSelect = document.querySelector("select[name='scope_type']");
    var scopeInput = document.querySelector("input[name='scope_id']");
    if (!scopeSelect || !scopeInput) {
      return;
    }
    if (scopeSelect.value === 'installation') {
      scopeInput.value = '';
      scopeInput.disabled = true;
    } else {
      scopeInput.disabled = false;
    }
  }
  function showToken(button, tokenId) {
    var row = document.getElementById('token_row_' + tokenId);
    var valueCell = document.getElementById('token_value_' + tokenId);
    if (!row || !valueCell) {
      return;
    }
    var token = button.getAttribute('data-token') || '';
    valueCell.textContent = token;
    if (row.style.display === 'table-row') {
      row.style.display = 'none';
      button.value = '<?php echo _("Show"); ?>';
    } else {
      row.style.display = 'table-row';
      button.value = '<?php echo _("Hide"); ?>';
    }
  }
  function setDeleteId(tokenId) {
    var input = document.getElementById('hiddenDeleteId');
    if (!input) {
      return false;
    }
    var answer = confirm('<?php echo _("Delete this token permanently?"); ?>');
    if (answer) {
      input.value = tokenId;
      return true;
    }
    input.value = '';
    return false;
  }
  document.addEventListener('DOMContentLoaded', toggleScopeId);
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

echo "<h2>" . $title . "</h2>";

if (!isSuperAdmin()) {
  echo "<p>" . _("Insufficient rights.") . "</p>";
  contentEnd();
  pageEnd();
  return;
}

if (!empty($errors)) {
  echo "<div class='warning'><ul>";
  foreach ($errors as $error) {
    echo "<li>" . utf8entities($error) . "</li>";
  }
  echo "</ul></div>";
}

if (!empty($createdToken)) {
  echo "<div class='info'>";
  echo "<p>" . _("Token created.") . "</p>";
  echo "<p><code>" . $createdToken['token'] . "</code></p>";
  echo "</div>";
}

echo "<h3>" . _("Create token") . "</h3>";
echo "<form method='post' action='?view=admin/apitokens'>";
echo "<table border='0' cellpadding='4' cellspacing='0'>";
echo "<tr><td>" . _("Label") . "</td>";
echo "<td><input type='text' name='label' size='30'/></td></tr>";
echo "<tr><td>" . _("Scope type") . "</td>";
echo "<td><select name='scope_type' onchange='toggleScopeId()'>";
echo "<option value='installation'>" . _("Installation") . "</option>";
echo "<option value='season'>" . _("Season") . "</option>";
echo "<option value='user'>" . _("User") . "</option>";
echo "</select></td></tr>";
echo "<tr><td>" . _("Scope id") . "</td>";
echo "<td><input type='text' name='scope_id' size='20'/></td></tr>";
echo "</table>";
echo "<p><input type='submit' name='create_token' value='" . _("Create") . "'/></p>";
echo "</form>";

$tokens = ApiTokenList();
echo "<h3>" . _("Existing tokens") . "</h3>";
if (empty($tokens)) {
  echo "<p>" . _("No tokens created.") . "</p>";
} else {
  echo "<table style='white-space: nowrap;width:100%' border='0' cellpadding='4'>\n";
  echo "<tr>";
  echo "<th>" . _("Id") . "</th>";
  echo "<th></th>";
  echo "<th>" . _("Label") . "</th>";
  echo "<th>" . _("Scope") . "</th>";
  echo "<th>" . _("Scope id") . "</th>";
  echo "<th>" . _("Created") . "</th>";
  echo "<th>" . _("Last used") . "</th>";
  echo "<th>" . _("Status") . "</th>";
  echo "<th>" . _("Operations") . "</th>";
  echo "<th></th>";
  echo "</tr>\n";

  foreach ($tokens as $token) {
    $status = !empty($token['revoked']) ? _("Revoked") : _("Active");
    echo "<tr>";
    echo "<td>" . (int)$token['token_id'] . "</td>";
    $tokenId = (int)$token['token_id'];
    $tokenValue = htmlspecialchars((string)$token['token_value'], ENT_QUOTES);
    echo "<td>";
    echo "<input type='button' value='" . _("Show") . "' data-token='" . $tokenValue . "' onclick='showToken(this, " . $tokenId . ")'/>";
    echo "</td>";
    echo "<td>" . utf8entities($token['label']) . "</td>";
    echo "<td>" . utf8entities($token['scope_type']) . "</td>";
    echo "<td>" . utf8entities($token['scope_id']) . "</td>";
    echo "<td>" . utf8entities($token['created_at']) . "</td>";
    echo "<td>" . utf8entities($token['last_used']) . "</td>";
    echo "<td>" . $status . "</td>";
    echo "<td>";
    echo "<form method='post' action='?view=admin/apitokens' style='display:inline;'>";
    echo "<input type='hidden' name='token_id' value='" . (int)$token['token_id'] . "'/>";
    if (!empty($token['revoked'])) {
      echo "<input type='submit' name='restore_token' value='" . _("Restore") . "'/>";
    } else {
      echo "<input type='submit' name='revoke_token' value='" . _("Revoke") . "'/>";
    }
    echo "</form>";
    echo "</td>";
    echo "<td class='center'>";
    echo "<form method='post' action='?view=admin/apitokens' style='display:inline;'>";
    echo "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='delete' value='" . _("X") . "' onclick='return setDeleteId(" . (int)$token['token_id'] . ");'/>";
    echo "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>";
    echo "</form>";
    echo "</td>";
    echo "</tr>\n";
    echo "<tr id='token_row_" . $tokenId . "' style='display:none;'>";
    echo "<td colspan='10'><code id='token_value_" . $tokenId . "'></code></td>";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

contentEnd();
pageEnd();
