<?php

include_once '../lib/database.php';
include_once '../lib/image.functions.php';

OpenConnection();
$resize = false;
$thumb = false;
$width = 0;
$height = 0;

if (!empty($_GET["w"]) && !empty($_GET["h"])) {
	$width = intval($_GET["w"]);
	$height = intval($_GET["h"]);
	if ($width > 0 && $height > 0)
		$resize = true;
}

if (!empty($_GET["thumb"])) {
	$thumb = true;
}

if (isset($_GET["id"])) {
	$imageId = intval($_GET["id"]);
	$type = "image/jpeg";
	$data = "";
	if ($thumb) {
		$image = GetThumb($imageId);
		if (!empty($image['thumb'])) {
			$data = $image['thumb'];
		} else {
			$image = GetImage($imageId);
			if ($image) {
				$type = $image['image_type'];
				$data = $image['image'];
			}
		}
	} else {
		$image = GetImage($imageId);
		if ($image) {
			$type = $image['image_type'];
			$data = $image['image'];
		}
	}

	if (empty($data)) {
		if (extension_loaded("gd")) {
			$string = _("No image");
			$font  = 4;
			$width  = imagefontwidth($font) * strlen($string);
			$height = imagefontheight($font);
			$image = imagecreatetruecolor($width, $height);
			$white = imagecolorallocate($image, 255, 255, 255);
			$black = imagecolorallocate($image, 0, 0, 0);
			imagefill($image, 0, 0, $white);
			imagestring($image, $font, 0, 0, $string, $black);
			header("Content-type: image/png");
			imagepng($image);
			imagedestroy($image);
		} else {
			//empty image
			header("Content-type: image/gif");
			echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
		}
	} else {
		if ($resize && extension_loaded("gd")) {
			$org = imagecreatefromstring($data);
			$orgw = imagesx($org);
			$orgh = imagesy($org);
			$new = imagecreatetruecolor($width, $height);
			imagecopyresampled($new, $org, 0, 0, 0, 0, $width, $height, $orgw, $orgh);
			header("Content-type: image/jpeg");
			imageJPEG($new);
			imagedestroy($new);
			imagedestroy($org);
		} else {
			header("Content-type: " . $type);
			echo $data;
		}
	}
}


CloseConnection();
