<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");

function convertHashtagsToSentence($input) {
    // Step 1: Remove the hashtags
    if (!$input)
        return;
    $input = str_replace('#', '', $input);
    
    // Step 2: Split the words based on spaces
    $words = explode(' ', $input);
    
    // Step 3: Add spaces in camel-cased words
    foreach ($words as &$word) {
        // Add space between lowercase and uppercase letters (e.g., DragonAttack -> Dragon Attack)
        $word = preg_replace('/([a-z])([A-Z])/', '$1 $2', $word);
    }
    
    // Step 4: Join the words with commas
    return $words;
}

function convertToHashtag($input) {
    // Step 1: Capitalize the first letter of each word
    $input = ucwords(strtolower($input));
    
    // Step 2: Remove spaces between words
    $input = str_replace(' ', '', $input);
    
    // Step 3: Prepend a hashtag
    return '#' . $input;
}

$db = new sql();
$terms=[];
$rawTags=$db->fetchAll('SELECT tags FROM "public"."memory_summary"');
foreach ($rawTags as $tagcol) {

    $localterms=convertHashtagsToSentence($tagcol["tags"]);
    if (is_array($localterms))
        foreach ($localterms as $term) {
            $terms[$term]=$term;
        }
}
foreach ($terms as $term) {
    echo "-".convertToHashtag($term)."\\n";
}


?>


