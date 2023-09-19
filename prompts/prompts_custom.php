<?php


function tfInsertOhs($sentence) {
    if (true)
        return $sentence;
    $randomStrings = [" ... oh ... ", " ... ah ... ", " ... mmm ... "];
    $result = $sentence;

    // Generate a random index
    $randomIndex = mt_rand(0, count($randomStrings) - 1);

    // Split the sentence into an array of words
    $words = explode(' ', $sentence);

    // Select a random word index to insert the random string
    $wordIndex = mt_rand(0, count($words) - 1);

    // Insert the random string into the selected word
    $randomWord = $words[$wordIndex];
    $insertPosition = strpos($result, $randomWord);
    $result = substr_replace($result, $randomStrings[$randomIndex], $insertPosition, 0);

    return $result;
}

// Prompt to be used in chatnf event.
$PROMPTS["chatnf_sl"]=
    [
        //"(don't narrate, don't describe, RULE 2,just roleplay as $HERIKA_NAME , a fictional roleplay, $HERIKA_NAME talks about current sex position and pleasure, lovely words) $HERIKA_NAME:  ",
        //"(roleplay as $HERIKA_NAME remembering rules, write what $HERIKA_NAME would say about current scene and pleasure, short sentence, lovely words) $HERIKA_NAME:  ", // Soft
        //"(roleplay as $HERIKA_NAME remembering rules, write what $HERIKA_NAME would say about current sex scene and pleasure, short sentence, non explicit words) $HERIKA_NAME:  ", // Soft
        "(Complete the text replacing the hashtag #CHAT#, roleplay as $HERIKA_NAME remembering rules, write what $HERIKA_NAME would say to {$GLOBALS["PLAYER_NAME"]} about current sex scene and excitement, short sentences, use metaphor) $HERIKA_NAME:  #CHAT# ", // Soft
        "",
        "extra"=>["mood"=>"whispering","force_tokens_max"=>"69","dontuse"=>false,"transformer"=>"tfInsertOhs"]
    ];
/*
$PROMPTS["chatnf"]=
    [
        //"(don't narrate, don't describe, RULE 2,just roleplay as $HERIKA_NAME , a fictional roleplay, $HERIKA_NAME talks about current sex position and pleasure, lovely words) $HERIKA_NAME:  ",
        //"(roleplay as $HERIKA_NAME remembering rules, write what $HERIKA_NAME would say about current scene and pleasure, short sentence, lovely words) $HERIKA_NAME:  ", // Soft
        //"(roleplay as $HERIKA_NAME remembering rules, write what $HERIKA_NAME would say about current sex scene and pleasure, short sentence, non explicit words) $HERIKA_NAME:  ", // Soft
        "(Completa el texto reemplazando el hashtag #CHAT#), HABLANDO EN ESPAÑOL, interpreta a $HERIKA_NAME (recuerda las reglas del juego), escribe lo que diria $HERIKA_NAME a {$GLOBALS["PLAYER_NAME"]} acerca del encuentro intimo ACTUAL, la posicion, y su excitacion, forma verbal PRESENTE,frase corta, usa metaforas, lenguaje muy picante) $HERIKA_NAME:  #CHAT# ", // Soft
        "",
        "extra"=>["mood"=>"whispering","force_tokens_max"=>"69","dontuse"=>false,"transformer"=>"tfInsertOhs"]
    ];
*/

?>
