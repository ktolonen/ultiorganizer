<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/configuration.functions.php';
include_once 'lib/translation.functions.php';

$LAYOUT_ID = TRANSLATIONS;
$locales = getAvailableLocalizations();
$title = _("Translations");

function translationLocaleKey($locale)
{
    return str_replace(".", "_", $locale);
}

function translationTextLength($text)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen((string) $text);
    }

    return strlen((string) $text);
}

function translationRowsByKey($translationRows)
{
    $rows = [];
    foreach ($translationRows as $translation) {
        $key = $translation['translation_key'];
        if (!isset($rows[$key])) {
            $rows[$key] = [
                'key' => $key,
                'values' => [],
            ];
        }
        $rows[$key]['values'][$translation['locale']] = $translation['translation'];
    }

    return $rows;
}

function translationSelectedKey($rows)
{
    $selected = '';
    if (isset($_POST['selected_key'])) {
        $selected = $_POST['selected_key'];
    } elseif (isset($_GET['selected_key'])) {
        $selected = $_GET['selected_key'];
    }

    if ($selected !== '' && isset($rows[$selected])) {
        return $selected;
    }

    $keys = array_keys($rows);
    if (isset($keys[0])) {
        return $keys[0];
    }

    return '';
}

function translationAddMode()
{
    if (isset($_GET['new_key'])) {
        return true;
    }

    return isset($_POST['editor_mode']) && $_POST['editor_mode'] === 'add';
}

function translationPageUrl($selectedKey = '')
{
    $url = '?view=admin/translations';
    if ($selectedKey !== '') {
        $url .= '&amp;selected_key=' . urlencode($selectedKey);
    }

    return $url;
}

function translationValidateKey($key, &$errors)
{
    $key = trim((string) $key);
    if ($key === '') {
        $errors[] = _("Translation key is required.");
        return false;
    }
    if (translationTextLength($key) > 50) {
        $errors[] = sprintf(_("Translation key is too long: %s"), $key);
        return false;
    }

    return true;
}

function translationValidateValue($value, $key, &$errors)
{
    if (translationTextLength($value) > 100) {
        $errors[] = sprintf(_("Translation text is too long for key: %s"), $key);
        return false;
    }

    return true;
}

function translationReadLocaleValues($locales, $fieldPrefix)
{
    $translations = [];
    foreach ($locales as $locale => $name) {
        $localeKey = translationLocaleKey($locale);
        $field = $fieldPrefix . $localeKey;
        $translations[$localeKey] = isset($_POST[$field]) ? (string) $_POST[$field] : '';
    }

    return $translations;
}

function translationValidateValues($translations, $key, &$errors)
{
    $valid = true;
    foreach ($translations as $value) {
        if (!translationValidateValue($value, $key, $errors)) {
            $valid = false;
        }
    }

    return $valid;
}

function translationRenderKeyOptions($rows, $selectedKey)
{
    $html = '';
    foreach ($rows as $key => $row) {
        $selected = $key === $selectedKey ? " selected='selected'" : "";
        $html .= "<option value='" . utf8entities($key) . "'" . $selected . ">" . utf8entities($key) . "</option>\n";
    }

    return $html;
}

function translationBuildBulkBlock($rows, $locales)
{
    $lines = [];
    foreach ($rows as $row) {
        $lines[] = '--- ' . $row['key'];
        foreach ($locales as $locale => $name) {
            $localeKey = translationLocaleKey($locale);
            $lines[] = $locale . ': ' . ($row['values'][$localeKey] ?? '');
        }
        $lines[] = '';
    }

    return implode("\n", $lines);
}

function translationParseBulkText($text, $locales)
{
    $items = [];
    $localeMap = [];
    foreach ($locales as $locale => $name) {
        $localeMap[$locale] = translationLocaleKey($locale);
        $localeMap[translationLocaleKey($locale)] = translationLocaleKey($locale);
    }

    $currentKey = null;
    $lines = preg_split("/\r\n|\n|\r/", (string) $text);
    foreach ($lines as $line) {
        if (strpos($line, '--- ') === 0) {
            $currentKey = trim(substr($line, 4));
            if ($currentKey !== '' && !isset($items[$currentKey])) {
                $items[$currentKey] = [];
            }
            continue;
        }

        if ($currentKey === null || $currentKey === '') {
            continue;
        }

        $separator = strpos($line, ':');
        if ($separator === false) {
            continue;
        }

        $locale = trim(substr($line, 0, $separator));
        if (!isset($localeMap[$locale])) {
            continue;
        }

        $items[$currentKey][$localeMap[$locale]] = ltrim(substr($line, $separator + 1));
    }

    return $items;
}

function translationPreviewBulkItems($items, $rows, $locales)
{
    $preview = [];
    $group = 0;
    foreach ($items as $key => $translations) {
        $group++;
        if (!isset($rows[$key])) {
            $preview[] = [
                'key' => $key,
                'group' => $group,
                'locale' => '',
                'language' => '',
                'current' => '',
                'translation' => '',
                'status' => _("Unknown key"),
            ];
            continue;
        }

        foreach ($locales as $locale => $language) {
            $localeKey = translationLocaleKey($locale);
            if (!array_key_exists($localeKey, $translations)) {
                $preview[] = [
                    'key' => $key,
                    'group' => $group,
                    'locale' => $locale,
                    'language' => $language,
                    'current' => $rows[$key]['values'][$localeKey] ?? '',
                    'translation' => '',
                    'status' => _("Missing from block"),
                ];
                continue;
            }

            $current = $rows[$key]['values'][$localeKey] ?? '';
            $translation = $translations[$localeKey];
            if ($current === $translation) {
                $status = _("Unchanged");
            } elseif ($translation === '') {
                $status = _("Clear");
            } else {
                $status = _("Update");
            }

            $preview[] = [
                'key' => $key,
                'group' => $group,
                'locale' => $locale,
                'language' => $language,
                'current' => $current,
                'translation' => $translation,
                'status' => $status,
            ];
        }
    }

    return $preview;
}

$messages = [];
$errors = [];
$bulkText = isset($_POST['bulk_text']) ? (string) $_POST['bulk_text'] : '';
$translationRows = hasTranslationRight() ? translationRowsByKey(Translations()) : [];
$selectedKey = translationSelectedKey($translationRows);
$addMode = translationAddMode() || $selectedKey === '';

if (hasTranslationRight()) {
    if (isset($_POST['remove'])) {
        $key = $_POST['original_key'] ?? '';
        if (translationValidateKey($key, $errors)) {
            RemoveTranslation($key);
            loadDBTranslations(getSessionLocale());
            $messages[] = _("Translation removed.");
            $translationRows = translationRowsByKey(Translations());
            $selectedKey = translationSelectedKey($translationRows);
            $addMode = $selectedKey === '';
        }
    }

    if (isset($_POST['save'])) {
        $originalKey = (string) ($_POST['original_key'] ?? '');
        $key = $originalKey === '' ? trim((string) ($_POST['edit_key'] ?? '')) : $originalKey;
        $translations = translationReadLocaleValues($locales, 'translation_');
        $keyAvailable = !($originalKey === '' && isset($translationRows[$key]));
        if (!$keyAvailable) {
            $errors[] = _("Translation key already exists.");
        }
        if ($keyAvailable && translationValidateKey($key, $errors) && translationValidateValues($translations, $key, $errors)) {
            if ($originalKey === '') {
                AddTranslation($key, $translations);
                $messages[] = _("Translation added.");
            } else {
                SetTranslation($key, $translations);
                $messages[] = _("Translations saved.");
            }
            loadDBTranslations(getSessionLocale());
            $translationRows = translationRowsByKey(Translations());
            $selectedKey = $key;
            $addMode = false;
        }
    }
}

if (hasTranslationRight() && isset($_POST['bulk_apply'])) {
    $items = translationParseBulkText($bulkText, $locales);
    $applied = 0;
    foreach ($items as $key => $translations) {
        if (!isset($translationRows[$key])) {
            continue;
        }
        if (!translationValidateKey($key, $errors) || !translationValidateValues($translations, $key, $errors)) {
            continue;
        }

        SetTranslation($key, $translations);
        $applied += count($translations);
    }

    if ($applied > 0) {
        loadDBTranslations(getSessionLocale());
        $messages[] = sprintf(_("%d bulk translation values applied."), $applied);
        $translationRows = translationRowsByKey(Translations());
        $bulkText = '';
    }
}

$bulkPreview = [];
if (hasTranslationRight() && isset($_POST['bulk_preview'])) {
    $bulkPreview = translationPreviewBulkItems(translationParseBulkText($bulkText, $locales), $translationRows, $locales);
} elseif ($bulkText === '') {
    $bulkText = translationBuildBulkBlock($translationRows, $locales);
}

pageTopHeadOpen($title);
?>
<script type="text/javascript">
	function markTranslationChanged() {
		var saveButton = document.getElementById('save');
		var cancelButton = document.getElementById('cancel');
		if (saveButton) {
			saveButton.disabled = false;
		}
		if (cancelButton) {
			cancelButton.disabled = false;
		}
	}
</script>
<style>
	.translation-toolbar,
	.translation-add-form {
		display: flex;
		flex-wrap: wrap;
		gap: 6px 10px;
		align-items: end;
		margin: 8px 5px;
	}

	.translation-toolbar label,
	.translation-add-form label,
	.translation-bulk label {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}

	.translation-toolbar select {
		max-width: 420px;
	}

	.translation-summary {
		margin: 8px 5px;
	}

	.translation-grid {
		width: 100%;
		table-layout: fixed;
	}

	.translation-grid th,
	.translation-grid td {
		padding: 4px;
		vertical-align: top;
	}

	.translation-preview th,
	.translation-preview td {
		border: 1px solid #c8c8c8;
	}

	.translation-preview .translation-group-even td {
		background: #f0f0f0;
	}

	.translation-preview .translation-group-odd td {
		background: #ffffff;
	}

	.translation-preview .translation-changed-value {
		font-weight: bold;
	}

	.translation-language {
		width: 22%;
	}

	.translation-locale {
		width: 18%;
	}

	.translation-value {
		width: 60%;
	}

	.translation-preview-key {
		width: 20%;
		word-break: break-word;
	}

	.translation-grid input[type='text'],
	.translation-bulk textarea {
		box-sizing: border-box;
		width: 100%;
	}

	.translation-message {
		margin: 8px 5px;
		padding: 5px;
		background: #eef6ee;
		border: 1px solid #8bb98b;
	}

	.translation-error {
		margin: 8px 5px;
		padding: 5px;
		background: #fff0f0;
		border: 1px solid #cc7777;
	}

	.translation-bulk {
		margin: 12px 5px;
	}

	@media (max-width: 700px) {
		.translation-grid {
			table-layout: auto;
		}
	}
</style>
<?php
if (is_file('cust/' . CUSTOMIZATIONS . '/teamplayers.functions.php')) {
    include_once 'cust/' . CUSTOMIZATIONS . '/teamplayers.functions.php';
}
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$help = "<p>" . _("Modify translations") . ":</p>
	<ol>
		<li> " . _("The translations are used to localize all user-provided text fields.") . " </li>
		<li> " . _("Select a key and edit each supported language on its own row.") . " </li>
		<li> " . _("Use the bulk translation area to copy all keys and all languages to an AI assistant and preview the pasted result before applying it.") . " </li>
	</ol>";

echo onPageHelpAvailable($help);
echo "<h2>" . _("Translations") . "</h2>\n";

if (!hasTranslationRight()) {
    echo "<p>" . _("Insufficient user rights") . "</p>";
    contentEnd();
    pageEnd();
    return;
}

foreach ($messages as $message) {
    echo "<p class='translation-message'>" . utf8entities($message) . "</p>\n";
}
foreach ($errors as $error) {
    echo "<p class='translation-error'>" . utf8entities($error) . "</p>\n";
}

$pageUrl = translationPageUrl($selectedKey);
$deleteConfirm = utf8entities(json_encode(_("Delete this translation key?"), JSON_HEX_APOS | JSON_HEX_QUOT));
$bulkApplyConfirm = utf8entities(json_encode(_("Apply the bulk translations from the text area?"), JSON_HEX_APOS | JSON_HEX_QUOT));

echo "<form class='translation-toolbar' method='get' action='?'>\n";
echo "<input type='hidden' name='view' value='admin/translations'/>\n";
echo "<label>" . _("Translation key") . "<select class='dropdown' name='selected_key'>\n";
echo translationRenderKeyOptions($translationRows, $selectedKey);
echo "</select></label>\n";
echo "<input class='button' type='submit' value='" . _("Edit") . "'/>\n";
echo "<button class='button' type='submit' name='new_key' value='1'>" . _("Add new") . "</button>\n";
echo "</form>\n";

echo "<p class='translation-summary'>" . sprintf(_("%d translation keys available."), count($translationRows)) . "</p>\n";

echo "<h3>" . ($addMode ? _("Add translation key") : _("Edit translation key")) . "</h3>\n";
$selectedValues = $addMode && isset($_POST['save']) ? translationReadLocaleValues($locales, 'translation_') : [];
if (!$addMode) {
    $selectedValues = $translationRows[$selectedKey]['values'] ?? [];
}
$editorMode = $addMode ? 'add' : 'edit';
$originalKey = $addMode ? '' : $selectedKey;
$editorKeyValue = $addMode ? (string) ($_POST['edit_key'] ?? '') : $selectedKey;
$saveDisabled = $addMode ? '' : " disabled='disabled'";
$saveLabel = $addMode ? _("Add") : _("Save");

echo "<form method='post' action='" . $pageUrl . "'>\n";
echo "<input type='hidden' name='editor_mode' value='" . $editorMode . "'/>\n";
echo "<input type='hidden' name='original_key' value='" . utf8entities($originalKey) . "'/>\n";
if ($addMode) {
    echo "<p><label>" . _("Key") . " <input class='input' type='text' name='edit_key' maxlength='50' value='" . utf8entities($editorKeyValue) . "'/></label></p>\n";
} else {
    echo "<p><strong>" . _("Key") . ":</strong> " . utf8entities($editorKeyValue) . "</p>\n";
}
echo "<table class='translation-grid'>\n";
echo "<tr><th class='translation-language'>" . _("Language") . "</th><th class='translation-locale'>" . _("Locale") . "</th><th class='translation-value'>" . _("Translation") . "</th></tr>\n";
foreach ($locales as $locale => $name) {
    $localeKey = translationLocaleKey($locale);
    $value = $selectedValues[$localeKey] ?? '';
    echo "<tr>";
    echo "<td>" . utf8entities($name) . "</td>";
    echo "<td>" . utf8entities($locale) . "</td>";
    echo "<td><input class='input' type='text' name='translation_" . utf8entities($localeKey) . "' maxlength='100' value='" . utf8entities($value) . "' oninput='markTranslationChanged()'/></td>";
    echo "</tr>\n";
}
echo "</table>\n";
echo "<p>";
echo "<input" . $saveDisabled . " id='save' class='button' name='save' type='submit' value='" . $saveLabel . "'/> ";
if ($addMode) {
    echo "<input class='button' type='button' name='cancel' value='" . _("Cancel") . "' onclick=\"window.location.href='?view=admin/translations'\"/> ";
} else {
    echo "<input disabled='disabled' id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/> ";
    echo "<button class='button' type='submit' name='remove' value='1' onclick='return confirm(" . $deleteConfirm . ")'>" . _("Delete") . "</button>";
}
echo "</p></form>\n";

echo "<h3>" . _("Bulk translation") . "</h3>\n";
echo "<form class='translation-bulk' method='post' action='" . $pageUrl . "'>\n";
echo "<input type='hidden' name='selected_key' value='" . utf8entities($selectedKey) . "'/>\n";
echo "<p>" . _("Copy the block below to an AI assistant, update the language values, then paste the result back here for preview.") . "</p>\n";
echo "<label>" . _("Bulk translation block") . "<textarea name='bulk_text' rows='18'>" . utf8entities($bulkText) . "</textarea></label>\n";
echo "<p><input class='button' type='submit' name='bulk_preview' value='" . _("Preview bulk translations") . "'/></p>\n";

if (!empty($bulkPreview)) {
    echo "<h3>" . _("Bulk preview") . "</h3>\n";
    echo "<table class='translation-grid translation-preview'>\n";
    echo "<tr><th class='translation-preview-key'>" . _("Key") . "</th><th>" . _("New translation") . "</th><th>" . _("Current translation") . "</th><th>" . _("Language") . "</th></tr>\n";
    foreach ($bulkPreview as $preview) {
        $groupClass = $preview['group'] % 2 === 0 ? 'translation-group-even' : 'translation-group-odd';
        $statusClass = 'translation-row-unchanged';
        if ($preview['status'] === _("Update")) {
            $statusClass = 'translation-row-update';
        } elseif ($preview['status'] === _("Clear")) {
            $statusClass = 'translation-row-clear';
        } elseif ($preview['status'] === _("Missing from block")) {
            $statusClass = 'translation-row-missing';
        } elseif ($preview['status'] === _("Unknown key")) {
            $statusClass = 'translation-row-unknown';
        }
        $valueClass = $statusClass === 'translation-row-unchanged' ? '' : " class='translation-changed-value'";

        echo "<tr class='" . $groupClass . " " . $statusClass . "'>";
        echo "<td>" . utf8entities($preview['key']) . "</td>";
        echo "<td" . $valueClass . ">" . utf8entities($preview['translation']) . "</td>";
        echo "<td>" . utf8entities($preview['current']) . "</td>";
        echo "<td>" . utf8entities($preview['language']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    echo "<p><input class='button' type='submit' name='bulk_apply' value='" . _("Apply bulk translations") . "' onclick='return confirm(" . $bulkApplyConfirm . ")'/></p>\n";
}

echo "</form>\n";

contentEnd();
pageEnd();

?>
