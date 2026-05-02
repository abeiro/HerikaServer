<?php




$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"]=$path;

$enginePath=$GLOBALS["ENGINE_PATH"];

require_once($path . "lib/runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);
require_once($path . "lib/model_dynmodel.php");
require_once($path . "lib/data_functions.php");
require_once($path . "lib/chat_helper_functions.php");
require_once($path . "lib/logger.php");

$db = $GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

if (isset($_GET["format"]) && $_GET["format"]=="png")
    $finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_img_".md5($_FILES["file"]["tmp_name"]).".png";
else
    $finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_img_".md5($_FILES["file"]["tmp_name"]).".bmp";


if (!$_FILES["file"]["tmp_name"]) {
    Logger::error("ITT error, no data given: ".print_r($_POST,true));
    die("ITT error, no data given");
    
}
@copy($_FILES["file"]["tmp_name"] ,$finalName);



function convertImage($originalImage, $outputImage, $quality)
{
    // Detectar extensión
    $exploded = explode('.', $originalImage);
    $ext = strtolower(end($exploded));

    if (preg_match('/jpg|jpeg/i', $ext))
        $imageTmp = imagecreatefromjpeg($originalImage);
    else if (preg_match('/png/i', $ext))
        $imageTmp = imagecreatefrompng($originalImage);
    else if (preg_match('/gif/i', $ext))
        $imageTmp = imagecreatefromgif($originalImage);
    else if (preg_match('/bmp/i', $ext))
        $imageTmp = imagecreatefrombmp($originalImage);
    else
        return 0;

    // Si no es PNG, voltear verticalmente
    if (!isset($_GET["format"]) || ($_GET["format"] != "png")) {
        imageflip($imageTmp, IMG_FLIP_VERTICAL);
    }

    // Obtener dimensiones originales
    $src_w = imagesx($imageTmp);
    $src_h = imagesy($imageTmp);

    // Relación de aspecto deseada (512x768 = 2:3)
    $target_w = 512;
    $target_h = 768;
    $target_ratio = $target_w / $target_h; // ≈ 0.666...

    $src_ratio = $src_w / $src_h;

    // Determinar área de crop centrada
    if ($src_ratio > $target_ratio) {
        // Imagen más ancha -> recortar lados
        $new_h = $src_h;
        $new_w = intval($src_h * $target_ratio);
    } else {
        // Imagen más alta -> recortar arriba y abajo
        $new_w = $src_w;
        $new_h = intval($src_w / $target_ratio);
    }

    $src_x = intval(($src_w - $new_w) / 2);
    $src_y = intval(($src_h - $new_h) / 2);

    // Crear lienzo de destino
    $crop = imagecreatetruecolor($target_w, $target_h);

    // Copiar y redimensionar desde el centro
    imagecopyresampled(
        $crop,
        $imageTmp,
        0, 0,            // destino (x, y)
        $src_x, $src_y,  // origen (x, y)
        $target_w, $target_h, // dimensiones destino
        $new_w, $new_h   // dimensiones del recorte
    );

    // Guardar en JPEG con calidad indicada
    imagejpeg($crop, $outputImage, $GLOBALS["FEATURES"]["MISC"]["ITT_QUALITY"]);

    // Liberar memoria
    imagedestroy($imageTmp);
    imagedestroy($crop);

    return 1;
}


if (isset($_GET["format"]) && $_GET["format"]=="png")
    $finalNameJpeg=strtr($finalName,[".png"=>".jpg","_img_"=>"_img_p_"]);
else
    $finalNameJpeg=strtr($finalName,[".bmp"=>".jpg","_img_"=>"_img_p_"]);


Logger::info("Saving $finalName to $finalNameJpeg");
convertImage($finalName,$finalNameJpeg,9);
@unlink($finalName);

$npcMaster=new npcMaster();
$npcData=$npcMaster->getByName($_GET["fg"]);
if (!$npcData) {
    Logger::error("Noc NPC provided <{$_GET["fg"]}>");    
    die();
}

@mkdir("{$GLOBALS["ENGINE_PATH"]}/data/pictures/profile/",0777,true);
@copy($finalNameJpeg,"{$GLOBALS["ENGINE_PATH"]}/data/pictures/profile/{$npcData["refid"]}.jpg");
@copy($finalNameJpeg,"{$GLOBALS["ENGINE_PATH"]}/data/pictures/gallery/{$npcData["refid"]}.jpg");

 


?>
