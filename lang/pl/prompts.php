<?php 

// Patrones comunes para usar en la mayoría de las funciones
$TEMPLATE_DIALOG = " Napisz następną kwestię dialogową {$GLOBALS["HERIKA_NAME"]}."
    . " Bądź oryginalny, kreatywny i rzeczowy, korzystaj z własnych przemyśleń."
    . " Przejrzyj historię kontekstu, aby trzymać się tematu rozmowy i nie powtarzać wcześniejszych zdań ani sformułowań.";

$plDirectNarratorDialogue = !empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])
    || (($GLOBALS["gameRequest"][0] ?? '') === 'narrator_inputtext')
    || (($gameRequest[0] ?? '') === 'narrator_inputtext');
if ($plDirectNarratorDialogue) {
    $TEMPLATE_DIALOG .= " Odpowiadaj bezpośrednio do {$GLOBALS["PLAYER_NAME"]} wyłącznie mówionym dialogiem."
        . " Nie dodawaj narracji w trzeciej osobie, opisów sceny, didaskaliów ani tekstu w gwiazdkach.";
}

if ($plDirectNarratorDialogue) {
    $TEMPLATE_DIALOG .= " If an enabled narrator action matches the request, use it and keep the spoken line consistent with that action.";
}

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) && sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"]) > 0) {
    if ($GLOBALS["TTSFUNCTION"] == "azure") {
        $TEMPLATE_DIALOG .= "(opcjonalny sposób mówienia z tej listy [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";
    }
}

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION = "(Check #ACTIONS section to choose an appropiate action for this character if needed)";
} else {
    $TEMPLATE_ACTION = "";
}

$PROMPTS = array(
    "location" => [
        "cue" => ["(Prowadź rozmowę jako {$GLOBALS["HERIKA_NAME"]})"], // dar paso a
        "player_request" => ["{$gameRequest[3]} Co wiesz o tym miejscu?"]  // requerimiento
    ],

    "book" => [
        "cue" => ["(Zauważ, że pomimo złej pamięci, {$GLOBALS["HERIKA_NAME"]} jest w stanie zapamiętać całe książki)"],
        "player_request" => ["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, podsumuj krótko tę książkę: "]  // requerimiento

    ],

    "combatend" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} komentuje ostatnie starcie w walce) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} śmieje się z stylu walki {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} komentuje broń {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} podziwia styl walki {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG"
        ],
        "extra" => ["force_tokens_max" => "50", "dontuse" => (time() % 5 != 0)]   // 20% szansy
    ],

    "quest" => [
        "cue" => ["$TEMPLATE_DIALOG"],
        "player_request" => "{$GLOBALS["HERIKA_NAME"]}, co powinniśmy zrobić w związku z tą misją?"
    ],

    "bleedout" => [
        "cue" => ["{$GLOBALS["HERIKA_NAME"]} skarży się na prawie przegraną, $TEMPLATE_DIALOG"]
    ],

    "goodmorning" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} komentuje drzemkę {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG"],
        "player_request" => ["(budząc się po śnie). ahhhh  "]
    ],

    "inputtext" => [
        "cue" => (function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })() // Sugestia jest implikowana

    ],
    "narrator_inputtext" => [
        "cue" => (function () use ($TEMPLATE_ACTION) {
            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })()
    ],
    "inputtext_s" => [
        "cue" => (function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })(), // Sugestia jest implikowana
        "extra" => ["mood" => "whispering"]
    ],
    "afterfunc" => [
        "extra" => [],
        "cue" => [
            "default" => "{$GLOBALS["HERIKA_NAME"]} rozmawia z {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG",
            "TakeASeat" => "({$GLOBALS["HERIKA_NAME"]} rozmawia o miejscu do siedzenia)$TEMPLATE_DIALOG",
            "GetDateTime" => "({$GLOBALS["HERIKA_NAME"]} odpowiada aktualną datą i godziną w krótkim zdaniu)$TEMPLATE_DIALOG",
            "MoveTo" => "({$GLOBALS["HERIKA_NAME"]} komentuje cel przemieszczenia)$TEMPLATE_DIALOG"
        ]
    ],
    "lockpicked" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} komentuje przedmiot otwarty za pomocą wytrychu) $TEMPLATE_DIALOG"],
        "player_request" => ["({$GLOBALS["PLAYER_NAME"]} otworzył {$gameRequest[3]})"],
        "extra" => ["mood" => "whispering"]
    ],
    "afterattack" => [
        "cue" => ["(interpretuj {$GLOBALS["HERIKA_NAME"]}, ona krzyczy frazę bojową) $TEMPLATE_DIALOG"]
    ],
// Jak inputtext, ale bez części wywoływania funkcji. Prawdopodobnie używane w skryptach papyrus
    "chatnf" => [
        "cue" => ["$TEMPLATE_DIALOG"] // Sugestia jest implikowana
    ],
    "instruction" => [
        "cue" => ["{$gameRequest[3]} {$GLOBALS["TEMPLATE_DIALOG"]} POSTAĆ MUSI WYKONAĆ INSTRUKCJĘ NARRATORA"],
        "player_request" => ["Narrator: {$gameRequest[3]}"]
    ],
    "suggestion" => [
        "cue" => ["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request" => ["Narrator: {$gameRequest[3]}"]
    ],
    "diary" => [
        "cue" => ["Proszę, zapisz w swoim osobistym dzienniku krótkie podsumowanie ostatniego dialogu i wydarzeń {$GLOBALS["PLAYER_NAME"]} i {$GLOBALS["HERIKA_NAME"]} opisanych powyżej. Pisz tylko jako {$GLOBALS["HERIKA_NAME"]}."],
        "extra" => ["force_tokens_max" => 0]
    ],

);

?>
