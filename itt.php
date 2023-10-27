<?php


/* STT entry point */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_img_".md5($_FILES["file"]["tmp_name"]).".bmp";


if (!$_FILES["file"]["tmp_name"]) {
    error_log("ITT error, no data given");
    die("ITT error, no data given");
    
}
@copy($_FILES["file"]["tmp_name"] ,$finalName);


function convertImage($originalImage, $outputImage, $quality)
{
    // jpg, png, gif or bmp?
    $exploded = explode('.',$originalImage);
    $ext = $exploded[count($exploded) - 1]; 

    if (preg_match('/jpg|jpeg/i',$ext))
        $imageTmp=imagecreatefromjpeg($originalImage);
    else if (preg_match('/png/i',$ext))
        $imageTmp=imagecreatefrompng($originalImage);
    else if (preg_match('/gif/i',$ext))
        $imageTmp=imagecreatefromgif($originalImage);
    else if (preg_match('/bmp/i',$ext))
        $imageTmp=imagecreatefrombmp($originalImage);
    else
        return 0;

    imageflip($imageTmp, IMG_FLIP_VERTICAL);

    // quality is a value from 0 (worst) to 100 (best)
    imagejpeg($imageTmp, $outputImage, $quality);

    imagedestroy($imageTmp);

    return 1;
}

$finalNameJpeg=strtr($finalName,[".bmp"=>".jpg"]);

convertImage($finalName,$finalNameJpeg,90);
@unlink($finalName);


 
$db=new sql();
$location=DataLastKnownLocation();
$charactersArray=implode(",",DataPosibleInspectTargets(true));
$hints="{$_GET["hints"]}. Location: $location. Posible characters: {$GLOBALS["HERIKA_NAME"]},{$GLOBALS["PLAYER_NAME"]}. Other Posible characters: $charactersArray.";
//$hints="{$_GET["hints"]}. Location: $location. Posible characters: {$GLOBALS["HERIKA_NAME"]},{$GLOBALS["PLAYER_NAME"]}. ";


require_once($path."itt/itt-llamacpp.php");

echo itt($finalNameJpeg,$hints);


?>

