<?php 

// Patrones comunes para usar en la mayoría de las funciones
$TEMPLATE_DIALOG = "interpretuj {$GLOBALS["HERIKA_NAME"]} uzupełniając dialog {$GLOBALS["HERIKA_NAME"]} używając tego formatu '{$GLOBALS["HERIKA_NAME"]}: (opcjonalny stan ducha z tej listy [" . implode(",", (@is_array($GLOBALS["AZURETTS_CONF"]["validMoods"])) ? $GLOBALS["AZURETTS_CONF"]["validMoods"] : array()) . "]) ...'";

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION = "wywołaj funkcję aby sterować {$GLOBALS["HERIKA_NAME"]} lub";
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

    "bored" => [
        "cue" => [
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz lub żart na temat obecnej lokalizacji) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat aktualnej pogody) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat aktualnego czasu i daty) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat ostatniego wydarzenia) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat mema ze Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat któregoś z Bogów w Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat polityki Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat historycznego wydarzenia ze świata Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat książki ze świata Elder Scrolls) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz zaczynający się od: Kiedyś musiałem... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz zaczynający się od: Czy słyszałeś co się stało w... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz zaczynający się od: Mądry człowiek z Akaviru kiedyś mi powiedział... )$TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} robi przypadkowy komentarz na temat aktualnej relacji/przyjaźni z {$GLOBALS["PLAYER_NAME"]})$TEMPLATE_DIALOG"
        ]
    ],

    "goodmorning" => [
        "cue" => ["({$GLOBALS["HERIKA_NAME"]} komentuje drzemkę {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG"],
        "player_request" => ["(budząc się po śnie). ahhhh  "]
    ],

    "inputtext" => [
        "cue" => ["$TEMPLATE_ACTION $TEMPLATE_DIALOG "] // Sugestia jest implikowana

    ],
    "inputtext_s" => [
        "cue" => ["$TEMPLATE_ACTION $TEMPLATE_DIALOG"], // Sugestia jest implikowana
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
    "diary" => [
        "cue" => ["Proszę, zapisz w swoim osobistym dzienniku krótkie podsumowanie ostatniego dialogu i wydarzeń {$GLOBALS["PLAYER_NAME"]} i {$GLOBALS["HERIKA_NAME"]} opisanych powyżej. Pisz tylko jako {$GLOBALS["HERIKA_NAME"]}."],
        "extra" => ["force_tokens_max" => 0]
    ],

);

?>