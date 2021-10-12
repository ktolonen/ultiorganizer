<?php
include_once $include_prefix . 'lib/yuiloader/phploader/loader.php';

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
	return $loader->tags();
}
