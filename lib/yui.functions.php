<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/yuiloader/phploader/loader.php';

function yuiLoad($libs)
{
    $loader = new YAHOO_util_Loader("2.8.0r4");
    global $styles_prefix;
    global $include_prefix;
    if (!isset($styles_prefix)) {
        $styles_prefix = $include_prefix;
    }
    $loader->base = $styles_prefix . "script/yui/";
    foreach ($libs as $lib) {
        $loader->loadSingle($lib);
    }
    $tags = $loader->tags();
    if (in_array("autocomplete", $libs, true)) {
        // AutoComplete writes each suggestion into innerHTML, and the stock
        // formatResult returns the matched name unescaped.
        $tags .= "<script type=\"text/javascript\">
YAHOO.widget.AutoComplete.escapeHtml = function (value) {
  var el = document.createElement(\"div\");
  el.appendChild(document.createTextNode(value === null || value === undefined ? \"\" : String(value)));
  return el.innerHTML;
};
YAHOO.widget.AutoComplete.prototype.formatResult = function (oResultData, sQuery, sResultMatch) {
  return YAHOO.widget.AutoComplete.escapeHtml(sResultMatch);
};
</script>\n";
    }
    return $tags;
}
