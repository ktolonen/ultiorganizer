<?php 

function GetImage($imageId){
	$query = sprintf("
		SELECT image, image_type
		FROM uo_image 
		WHERE image_id='%s'",
		DBEscapeString($imageId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysqli_fetch_assoc($result);
}	

function GetThumb($imageId){
	$query = sprintf("
		SELECT thumb
		FROM uo_image 
		WHERE image_id='%s'",
		DBEscapeString($imageId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysqli_fetch_assoc($result);
}

function ImageInfo($imageId){
	$query = sprintf("
		SELECT image_type, image_width, image_height, thumb_height, thumb_width, image_size
		FROM uo_image 
		WHERE image_id='%s'",
		DBEscapeString($imageId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysqli_fetch_assoc($result);
}

function RemoveImage($imageId){
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_image WHERE image_id='%s'",
					DBEscapeString($profile['image_id']));
					
		$result = mysql_query($query);
		return $result;
	} else { die('Insufficient rights to remove image'); }	
}

function ConvertToJpeg($file_src, $file_dst){

	if(is_file($file_dst)){
		unlink($file_dst);//  remove old images if present
	}

   list($w_src, $h_src, $type) = getimagesize($file_src);
   
  switch ($type){
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
	
   imagejpeg($img_src, $file_dst);    //  save new image
   imagedestroy($img_src);       
  }

function CreateThumb($file_src, $file_dst, $w_dst, $h_dst) {
	
	if(is_file($file_dst)){
		unlink($file_dst);//  remove old images if present
	}
	
	list($w_src, $h_src, $type) = getimagesize($file_src);
	
	// create new dimensions, keeping aspect ratio
	$ratio = $w_src/$h_src;
	if ($w_dst/$h_dst > $ratio) {
		$w_dst = floor($h_dst*$ratio);
	} else {
		$h_dst = floor($w_dst/$ratio);
	}
	switch ($type){
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

	$img_dst = imagecreatetruecolor($w_dst, $h_dst);  //  resample

	imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $w_dst, $h_dst, $w_src, $h_src);
	imagejpeg($img_dst, $file_dst);    //  save new image

	imagedestroy($img_src);       
	imagedestroy($img_dst);
}
?>
