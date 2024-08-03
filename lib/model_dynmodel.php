<?php

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR ;

function removeAndReturnNext(&$array, $value) {
    $key = array_search($value, $array);
    
    if ($key !== false) {
        array_splice($array, $key, 1);
        
        if (isset($array[$key])) {
            return $array[$key];
        }
    }
    
    return current($array);
}

function DMgetCurrentModel() {
    
    $lprof=isset($GLOBALS["active_profile"])?$GLOBALS["active_profile"]:"";
    
    $file=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."CurrentModel_{$lprof}.json";
    if (!file_exists($file)) {
        DMsetCurrentModel($GLOBALS["CONNECTORS"][0]);
    }

    $cmj=file_get_contents($file);
    
    return json_decode($cmj,true);

}

function DMsetCurrentModel($model) {

    $lprof=isset($GLOBALS["active_profile"])?$GLOBALS["active_profile"]:"";

        
    $file=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."CurrentModel_{$lprof}.json";

    $cmj=file_put_contents($file,json_encode($model));

}

function DMtoggleModel() {
    $cm=DMgetCurrentModel();
    $arrayCopy=$GLOBALS["CONNECTORS"];

    $nextModel=removeAndReturnNext($arrayCopy,$cm);

    DMsetCurrentModel($nextModel);
    return $nextModel;
}


?>
