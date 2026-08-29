<?php


/* STT entry point */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."logger.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."visual_context.php");

if (isset($_GET["format"]) && $_GET["format"]=="png")
    $finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_img_".md5($_FILES["file"]["tmp_name"]).".png";
else
    $finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_img_".md5($_FILES["file"]["tmp_name"]).".bmp";


if (!$_FILES["file"]["tmp_name"]) {
    Logger::error("ITT error, no data given: ".print_r($_POST,true));
    die("ITT error, no data given");
    
}
@copy($_FILES["file"]["tmp_name"] ,$finalName);


if (chimRuntimeBindActiveProfileFromRequest() !== null) {
    $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
}

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

    if (!isset($_GET["format"]) || (!$_GET["format"]=="png"))
        imageflip($imageTmp, IMG_FLIP_VERTICAL);

    // quality is a value from 0 (worst) to 100 (best)
    //imagepng($imageTmp, $outputImage, $quality);
    imagejpeg($imageTmp, $outputImage, $GLOBALS["FEATURES"]["MISC"]["ITT_QUALITY"]);
    imagedestroy($imageTmp);

    return 1;
}

if (isset($_GET["format"]) && $_GET["format"]=="png")
    $finalNameJpeg=strtr($finalName,[".png"=>".jpg","_img_"=>"_img_p_"]);
else
    $finalNameJpeg=strtr($finalName,[".bmp"=>".jpg","_img_"=>"_img_p_"]);

Logger::info("Saving $finalName to $finalNameJpeg");
convertImage($finalName,$finalNameJpeg,9);
@unlink($finalName);


$db=$GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;
$location=DataLastKnownLocation();
$hints="";
$actorCandidates = chimVisualActorCandidates($_GET['visual_actor_candidates'] ?? null);
//$charactersArray=implode(",",DataPosibleInspectTargets(true));

if ($actorCandidates) {
    $hints .= chimBuildVisualActorCandidateHints($actorCandidates);
} elseif (isset($_GET["vc"])) {
    $sanitize=explode(",",$_GET["vc"]);
    $vc=[];
    foreach ($sanitize as $name) {
        $name = preg_replace('/\s+/u', ' ', chimVisualContextText($name, 120)) ?? '';
        if (!empty($name))
           $vc[]=ucfirst($name);
    }

    if ($vc) {
        $hints.="Possible nearby character candidates (not proof they are visible): ".implode(",",$vc)."\n";
    }
}
if (!$actorCandidates && isset($_GET["fg"])) {
    $foreground = preg_replace('/\s+/u', ' ', chimVisualContextText($_GET['fg'], 120)) ?? '';
    if ($foreground !== '') {
        $hints.="Crosshair target candidate (name only if the image supports it): {$foreground}.\n";
    }
}

$hints.="Location: $location";

require_once($path."itt/itt-{$GLOBALS["ITTFUNCTION"]}.php");

$description = trim(strval(itt($finalNameJpeg, $hints)));
$galleryDirectory = __DIR__.DIRECTORY_SEPARATOR."data/pictures/gallery/";
$visualLocation = $_GET['visual_location'] ?? $location;
$gameTs = DataLastKnownGameTS();
$gameDate = $gameTs > 0 ? convert_gamets2skyrim_long_date($gameTs) : '';
@mkdir($galleryDirectory, 0777, true);
$galleryFilename = chimVisualContextGalleryFilename($visualLocation, $gameDate, 'jpg');
$galleryPath = $galleryDirectory.$galleryFilename;
$filenameStem = pathinfo($galleryFilename, PATHINFO_FILENAME);
$filenameExtension = pathinfo($galleryFilename, PATHINFO_EXTENSION);
$filenameCounter = 2;
while (file_exists($galleryPath)) {
    $galleryFilename = $filenameStem . '__' . $filenameCounter . '.' . $filenameExtension;
    $galleryPath = $galleryDirectory.$galleryFilename;
    $filenameCounter++;
}
if (@rename($finalNameJpeg, $galleryPath)) {
    $visualType = chimVisualContextSubjectType($_GET['visual_type'] ?? 'scene');
    $subjectName = chimVisualContextText($_GET['visual_name'] ?? ($_GET['fg'] ?? ''), 300);
    $subjectKey = chimVisualContextText($_GET['visual_key'] ?? '', 500);
    chimVisualContextStore([
        'subject_type' => $visualType,
        'subject_key' => $subjectKey,
        'subject_name' => $subjectName,
        'plugin' => $_GET['visual_plugin'] ?? '',
        'baseid' => $_GET['visual_baseid'] ?? '',
        'refid' => $_GET['visual_refid'] ?? '',
        'cell_id' => $_GET['visual_cell'] ?? '',
        'location_name' => $visualLocation,
        'image_path' => 'data/pictures/gallery/' . basename($galleryPath),
        'image_sha256' => hash_file('sha256', $galleryPath) ?: '',
        'description' => $description,
        'perspective' => $_GET['visual_perspective'] ?? 'first_person',
        'provider' => $GLOBALS['ITTFUNCTION'] ?? '',
        'model' => $GLOBALS['ITT']['model'] ?? '',
        'metadata' => [
            'visible_characters' => $vc ?? [],
            'foreground' => chimVisualContextText($_GET['fg'] ?? '', 300),
            'actor_candidates' => $actorCandidates,
        ],
    ]);
}

echo $description;


?>
