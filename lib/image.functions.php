<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

function GetImage($imageId)
{
    $query = sprintf(
        "
		SELECT image, image_type
		FROM uo_image 
		WHERE image_id=%d",
        (int) $imageId,
    );

    $result = DBQueryToRow($query);
    return $result;
}

function GetThumb($imageId)
{
    $query = sprintf(
        "
		SELECT thumb
		FROM uo_image 
		WHERE image_id=%d",
        (int) $imageId,
    );

    $result = DBQueryToRow($query);
    return $result;
}

function ImageInfo($imageId)
{
    $query = sprintf(
        "
		SELECT image_type, image_width, image_height, thumb_height, thumb_width, image_size
		FROM uo_image 
		WHERE image_id=%d",
        (int) $imageId,
    );

    $result = DBQueryToRow($query);
    return $result;
}

function RemoveImage($imageId)
{
    if (isSuperAdmin()) {
        $query = sprintf(
            "DELETE FROM uo_image WHERE image_id=%d",
            (int) $imageId,
        );

        $result = DBQuery($query);
        return $result;
    } else {
        die('Insufficient rights to remove image');
    }
}

function CanProcessImages()
{
    return function_exists('imagejpeg')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled');
}

function CanReadImageType($type)
{
    switch ((int) $type) {
        case 1:
            return function_exists('imagecreatefromgif');
        case 2:
            return function_exists('imagecreatefromjpeg');
        case 3:
            return function_exists('imagecreatefrompng');
        default:
            return false;
    }
}

function WriteJpegImage($image, $fileDst)
{
    $dir = dirname($fileDst);
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $tmp = tempnam($dir, basename($fileDst) . '.');
    if ($tmp === false) {
        return false;
    }

    if (!imagejpeg($image, $tmp)) {
        unlink($tmp);
        return false;
    }

    if (!rename($tmp, $fileDst)) {
        unlink($tmp);
        return false;
    }

    return true;
}

function ConvertToJpeg($file_src, $file_dst)
{
    if (!CanProcessImages()) {
        return false;
    }

    $imageInfo = getimagesize($file_src);
    if ($imageInfo === false) {
        return false;
    }
    list($w_src, $h_src, $type) = $imageInfo;
    if (!CanReadImageType($type)) {
        return false;
    }

    $img_src = false;
    switch ($type) {
        case 1:   //   gif -> jpg
            $img_src = imagecreatefromgif($file_src);
            break;

        case 2:   //   jpeg -> jpg
            $img_src = imagecreatefromjpeg($file_src);
            break;

        case 3:  //   png -> jpg
            $img_src = imagecreatefrompng($file_src);
            break;
    }

    if (!$img_src) {
        return false;
    }

    $result = WriteJpegImage($img_src, $file_dst);
    return $result;
}

function CreateThumb($file_src, $file_dst, $w_dst, $h_dst)
{
    if (!CanProcessImages()) {
        return false;
    }

    $w_dst = (int) $w_dst;
    $h_dst = (int) $h_dst;
    if ($w_dst <= 0 || $h_dst <= 0) {
        return false;
    }

    $imageInfo = getimagesize($file_src);
    if ($imageInfo === false) {
        return false;
    }
    list($w_src, $h_src, $type) = $imageInfo;
    if ($w_src <= 0 || $h_src <= 0) {
        return false;
    }
    if (!CanReadImageType($type)) {
        return false;
    }

    // create new dimensions, keeping aspect ratio
    $ratio = $w_src / $h_src;
    if ($w_dst / $h_dst > $ratio) {
        $w_dst = max(1, (int) floor($h_dst * $ratio));
    } else {
        $h_dst = max(1, (int) floor($w_dst / $ratio));
    }
    $img_src = false;
    switch ($type) {
        case 1:   //   gif -> jpg
            $img_src = imagecreatefromgif($file_src);
            break;

        case 2:   //   jpeg -> jpg
            $img_src = imagecreatefromjpeg($file_src);
            break;

        case 3:  //   png -> jpg
            $img_src = imagecreatefrompng($file_src);
            break;
    }

    if (!$img_src) {
        return false;
    }

    $img_dst = imagecreatetruecolor($w_dst, $h_dst);  //  resample
    if (!$img_dst) {
        return false;
    }

    imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $w_dst, $h_dst, $w_src, $h_src);
    $result = WriteJpegImage($img_dst, $file_dst);

    return $result;
}
