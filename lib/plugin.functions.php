<?php
function GetPluginList($category, $type = "", $format = "")
{
	global $include_prefix;
	$plugins = array();

	if ($handle = opendir($include_prefix . 'plugins/')) {
		while (false !== ($file = readdir($handle))) {
			$fullfile = $include_prefix . 'plugins/' . $file;
			if ($file != "." && $file != ".." && is_readable($fullfile) && is_file($fullfile)) {
				$file = $fullfile;
				$pluginf = fopen($file, "r") or die;
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
				$ini = parse_ini_string($inidata);

				if (
					$ini['category'] == $category
					&& (empty($type) || $type == $ini['type'])
					&& (empty($format) || $type == $ini['format'])
				) {
					$file = substr($file, 0, -4); //remove file extension
					$plugins[] = array('file' => $file, 'title' => $ini['title'], 'description' => $ini['description']);
				}
			}
		}
		closedir($handle);
	}
	return $plugins;
}
