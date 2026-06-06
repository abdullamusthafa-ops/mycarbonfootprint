<?php


error_reporting(0);


$crop_x=100;
$crop_y=100;
$crop_width=400;
$crop_height=400;


if(empty($_POST["screenshot_data"])){ exit; }


$screenshot_data=$_POST["screenshot_data"];


$s=getimagesize($screenshot_data);
//if(empty($s) || $s[2]!=3){ exit; } // NO PNG


$screenshot_data=str_replace('data:image/jpeg;base64,','',$screenshot_data);
$screenshot_data=str_replace(' ','+',$screenshot_data);
$screenshot_data=base64_decode($screenshot_data);
$p=imagecreatefromstring($screenshot_data);
$d=imagecreatetruecolor($crop_width,$crop_height);
$image = imagecopyresampled($d,$p,0,0,$crop_x,$crop_y,$crop_width,$crop_height,$crop_width,$crop_height);
// echo $p;
$filename = date('Y-m-d_H-i-s', time()).'.jpeg';
$image = imagepng($p, 'Screenshots/'.$filename);

// header("Cache-Control:public");
// header("Content-Description:file transfer");
// header("Content-Disposition:attachment;filename=image.png");
// header("Content-Transfer-Encoding:binary");
// header("Content-Type:binary/octet-stream");


echo json_encode(array('image' => $filename));exit;
// imagepng($d);
//imagebmp($d);
imagedestroy($p);
imagedestroy($d);

?>
