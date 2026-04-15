<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

function GetPluginList($category, $type = "", $format = "")
{
	global $include_prefix;
	$plugins = array();

	if ($handle = opendir($include_prefix . 'plugins/')) {
		while (false !== ($file = readdir($handle))) {
			$fullfile = $include_prefix . 'plugins/' . $file;
			if (
				$file != "."
				&& $file != ".."
				&& is_readable($fullfile)
				&& is_file($fullfile)
				&& pathinfo($fullfile, PATHINFO_EXTENSION) === 'php'
			) {
				$pluginf = fopen($fullfile, "r");
				if ($pluginf === false) {
					continue;
				}
				$inidata = "";
				$readdata = false;

				while (!feof($pluginf)) {
					$line = fgets($pluginf);
					if (trim($line) == "<!--") {
						$readdata = true;
						continue;
					} else if (trim($line) == "-->") {
						$readdata = false;
						break;
					}
					if ($readdata) {
						$inidata .= $line;
					}
				}
				fclose($pluginf);

				if (empty($inidata)) {
					continue;
				}
				$ini = @parse_ini_string($inidata, false, INI_SCANNER_RAW);
				if (
					!is_array($ini)
					|| empty($ini['category'])
					|| empty($ini['type'])
					|| empty($ini['format'])
					|| !isset($ini['title'])
					|| !isset($ini['description'])
				) {
					continue;
				}

				if (
					$ini['category'] == $category
					&& (empty($type) || $type == $ini['type'])
					&& (empty($format) || $format == $ini['format'])
				) {
					$plugins[] = array(
						'file' => substr($fullfile, 0, -4), //remove file extension
						'title' => $ini['title'],
						'description' => $ini['description']
					);
				}
			}
		}
		closedir($handle);
	}
	sort($plugins); // sort plugins by filename (alphabetically)
	return $plugins;
}
