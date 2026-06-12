<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);
require_once __DIR__ . '/translation.functions.php';

function TimekeeperActionDefinitions()
{
    return [
        'betweenpoints' => [
            'label' => _("Start of point"),
        ],
        'timeout' => [
            'label' => _("Timeout"),
        ],
        'halftime' => [
            'label' => _("Halftime"),
        ],
        'halfstart' => [
            'label' => _("Start of game"),
        ],
        'dispute' => [
            'label' => _("Call or discussion"),
        ],
        'discretrieval' => [
            'label' => _("Disc retrieval"),
        ],
    ];
}

function TimekeeperActionSignalGroups()
{
    return [
        'betweenpoints' => ['label' => _("Start of point")],
        'timeout' => ['label' => _("Timeout")],
        'timeoutbeforepull' => ['label' => _("Timeout before pull")],
        'halftime' => ['label' => _("Halftime")],
        'halfstart' => ['label' => _("Start of game")],
        'dispute' => ['label' => _("Call or discussion")],
        'discretrieval' => ['label' => _("Disc retrieval")],
    ];
}

function TimekeeperTemplateCapFields()
{
    return [
        'half_time_cap' => ['label' => _("Halftime cap"), 'default' => 55, 'unit' => _("minutes")],
        'time_cap' => ['label' => _("Time cap"), 'default' => 100, 'unit' => _("minutes")],
    ];
}

function TimekeeperTemplateCapDefaults()
{
    $defaults = [];
    foreach (TimekeeperTemplateCapFields() as $fieldId => $field) {
        $defaults[$fieldId] = (int) $field['default'];
    }
    return $defaults;
}

function TimekeeperTemplateCapsFromRow($row)
{
    $caps = [];
    foreach (TimekeeperTemplateCapFields() as $fieldId => $field) {
        $caps[$fieldId] = isset($row[$fieldId]) ? (int) $row[$fieldId] : (int) $field['default'];
    }
    return $caps;
}

function TimekeeperNormalizeTemplateCaps($params)
{
    $caps = [];
    foreach (TimekeeperTemplateCapFields() as $fieldId => $field) {
        $value = isset($params[$fieldId]) ? (int) $params[$fieldId] : (int) $field['default'];
        $caps[$fieldId] = max(0, $value);
    }
    return $caps;
}

function TimekeeperTemplateRows()
{
    return DBQueryToArray(
        "SELECT t.*, COALESCE(s.signal_count, 0) AS signal_count
        FROM uo_timekeeper_template t
        LEFT JOIN (
            SELECT template_id, COUNT(signal_id) AS signal_count
            FROM uo_timekeeper_template_signal
            GROUP BY template_id
        ) s ON (t.template_id = s.template_id)
        ORDER BY t.name ASC",
        true,
    );
}

function TimekeeperTemplateInfo($templateId)
{
    return DBQueryToRow(sprintf(
        "SELECT * FROM uo_timekeeper_template WHERE template_id=%d",
        (int) $templateId,
    ), true);
}

function TimekeeperDefaultTemplateId()
{
    $row = DBQueryToRow("SELECT template_id FROM uo_timekeeper_template WHERE is_default=1 ORDER BY template_id ASC LIMIT 1", true);
    if ($row) {
        return (int) $row['template_id'];
    }

    $row = DBQueryToRow("SELECT template_id FROM uo_timekeeper_template ORDER BY is_system DESC, template_id ASC LIMIT 1", true);
    return $row ? (int) $row['template_id'] : 0;
}

function TimekeeperTemplateSignalRows($templateId)
{
    return DBQueryToArray(sprintf(
        "SELECT signal_id, action_key, signal_time, signal_text
        FROM uo_timekeeper_template_signal
        WHERE template_id=%d
        ORDER BY action_key ASC, signal_time ASC, signal_id ASC",
        (int) $templateId,
    ), true);
}

function TimekeeperTemplateSignalsByAction($templateId)
{
    $signals = [];
    foreach (TimekeeperActionSignalGroups() as $actionKey => $definition) {
        $signals[$actionKey] = [];
    }
    foreach (TimekeeperTemplateSignalRows($templateId) as $row) {
        $actionKey = $row['action_key'];
        if (!isset($signals[$actionKey])) {
            $signals[$actionKey] = [];
        }
        $signals[$actionKey][] = [
            'id' => (int) $row['signal_id'],
            'time' => (int) $row['signal_time'],
            'text' => U_($row['signal_text']),
        ];
    }
    return $signals;
}

function TimekeeperTemplatesForClient()
{
    $templates = [];
    foreach (TimekeeperTemplateRows() as $row) {
        $templateId = (int) $row['template_id'];
        $templates[$templateId] = [
            'id' => $templateId,
            'name' => U_($row['name']),
            'caps' => TimekeeperTemplateCapsFromRow($row),
            'signals' => TimekeeperTemplateSignalsByAction($templateId),
        ];
    }
    return [
        'defaultTemplateId' => TimekeeperDefaultTemplateId(),
        'templates' => $templates,
    ];
}

function TimekeeperRequireSuperAdmin($action)
{
    if (!isSuperAdmin()) {
        die('Insufficient rights to ' . $action . ' Timekeeper template.');
    }
}

function TimekeeperNormalizeTemplateSignals($rows)
{
    $validActions = TimekeeperActionSignalGroups();
    $normalized = [];
    foreach ($rows as $row) {
        if (
            !isset($row['action_key'], $row['signal_time'], $row['signal_text'])
            || !isset($validActions[$row['action_key']])
            || trim((string) $row['signal_text']) === ''
        ) {
            continue;
        }
        $normalized[] = [
            'action_key' => $row['action_key'],
            'signal_time' => max(0, (int) $row['signal_time']),
            'signal_text' => trim((string) $row['signal_text']),
        ];
    }
    return $normalized;
}

function SetTimekeeperTemplateSignals($templateId, $signals)
{
    TimekeeperRequireSuperAdmin('edit');
    DBQuery(sprintf("DELETE FROM uo_timekeeper_template_signal WHERE template_id=%d", (int) $templateId));
    foreach (TimekeeperNormalizeTemplateSignals($signals) as $signal) {
        RegisterTranslationKey($signal['signal_text']);
        DBQuery(sprintf(
            "INSERT INTO uo_timekeeper_template_signal (template_id, action_key, signal_time, signal_text)
            VALUES (%d, '%s', %d, '%s')",
            (int) $templateId,
            DBEscapeString($signal['action_key']),
            (int) $signal['signal_time'],
            DBEscapeString($signal['signal_text']),
        ));
    }
}

function AddTimekeeperTemplate($params, $signals)
{
    TimekeeperRequireSuperAdmin('add');
    $caps = TimekeeperNormalizeTemplateCaps($params);
    $templateId = DBQueryInsert(sprintf(
        "INSERT INTO uo_timekeeper_template (name, is_default, is_system, half_time_cap, time_cap) VALUES ('%s', 0, 0, %d, %d)",
        DBEscapeString($params['name']),
        (int) $caps['half_time_cap'],
        (int) $caps['time_cap'],
    ));
    SetTimekeeperTemplateSignals($templateId, $signals);
    return $templateId;
}

function SetTimekeeperTemplate($templateId, $params, $signals)
{
    TimekeeperRequireSuperAdmin('edit');

    if (!TimekeeperTemplateInfo($templateId)) {
        die('Timekeeper template not found.');
    }

    $caps = TimekeeperNormalizeTemplateCaps($params);
    DBQuery(sprintf(
        "UPDATE uo_timekeeper_template SET name='%s', half_time_cap=%d, time_cap=%d WHERE template_id=%d",
        DBEscapeString($params['name']),
        (int) $caps['half_time_cap'],
        (int) $caps['time_cap'],
        (int) $templateId,
    ));
    SetTimekeeperTemplateSignals($templateId, $signals);
}

function DeleteTimekeeperTemplate($templateId)
{
    TimekeeperRequireSuperAdmin('delete');

    $info = TimekeeperTemplateInfo($templateId);
    if (!$info) {
        return;
    }
    if ((int) $info['is_default']) {
        die('Default timekeeper template cannot be deleted.');
    }

    DBQuery(sprintf("DELETE FROM uo_timekeeper_template WHERE template_id=%d", (int) $templateId));
}

function SetDefaultTimekeeperTemplate($templateId)
{
    TimekeeperRequireSuperAdmin('set default');

    if (!TimekeeperTemplateInfo($templateId)) {
        die('Timekeeper template not found.');
    }

    DBQuery("UPDATE uo_timekeeper_template SET is_default=0");
    DBQuery(sprintf(
        "UPDATE uo_timekeeper_template SET is_default=1 WHERE template_id=%d",
        (int) $templateId,
    ));
}
