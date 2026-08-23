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

/**
 * Upper bounds for decoding an uploaded image.
 *
 * The upload cap applies to the compressed file, but imagecreatefrom*()
 * allocates roughly four bytes per pixel of the decoded image, so a small file
 * can advertise dimensions large enough to exhaust a worker. These bounds allow
 * any ordinary camera or phone photo (typically 12 megapixels) while keeping
 * the decoded allocation comfortably inside a 128 MB memory limit.
 */
const MAX_IMAGE_DIMENSION = 8000;
const MAX_IMAGE_PIXELS = 16000000;

/**
 * Returns true when an image of these dimensions is safe to decode.
 *
 * Call it with the dimensions getimagesize() reports, which are read from the
 * header without decoding the pixel data.
 */
function CanDecodeImageSize($width, $height)
{
    $width = (int) $width;
    $height = (int) $height;

    if ($width <= 0 || $height <= 0) {
        return false;
    }
    if ($width > MAX_IMAGE_DIMENSION || $height > MAX_IMAGE_DIMENSION) {
        return false;
    }

    return ($width * $height) <= MAX_IMAGE_PIXELS;
}

/**
 * Returns the reason an uploaded image cannot be decoded, or "" when it can.
 *
 * The decode helpers only report failure as a boolean, so an image that is
 * merely too large would otherwise reach the caller as a generic processing
 * error with no hint that scaling it down is the fix.
 */
function ImageSizeError($file_src)
{
    $imageInfo = getimagesize($file_src);
    if ($imageInfo === false) {
        return "";
    }

    if (CanDecodeImageSize($imageInfo[0], $imageInfo[1])) {
        return "";
    }

    return sprintf(
        _("Image resolution is too large. The maximum is %1\$d x %2\$d pixels and %3\$d megapixels."),
        MAX_IMAGE_DIMENSION,
        MAX_IMAGE_DIMENSION,
        (int) (MAX_IMAGE_PIXELS / 1000000),
    );
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

    // tempnam() creates the file 0600 and rename() keeps that mode, which
    // hides the image from a web server running as another user.
    chmod($fileDst, 0644);

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
    // Bounded before the decode: getimagesize() reads the header only, while
    // imagecreatefrom*() below allocates the whole decoded bitmap.
    if (!CanDecodeImageSize($w_src, $h_src)) {
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
    // Bounded before the decode: getimagesize() reads the header only, while
    // imagecreatefrom*() below allocates the whole decoded bitmap. The 5 MB
    // upload cap constrains the compressed file, not this.
    if (!CanDecodeImageSize($w_src, $h_src)) {
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
