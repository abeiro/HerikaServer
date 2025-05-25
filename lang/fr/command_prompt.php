<?php

$COMMAND_PROMPT = "
N'écrivez pas de récits.
";

$COMMAND_PROMPT_FUNCTIONS="Utilisez # ACTIONS  si votre personnage doit effectuer une action.";
/*
$COMMAND_PROMPT_FUNCTIONS = "
Utilise des appels d'outils pour contrôler les actions de {$GLOBALS["HERIKA_NAME"]}.
Utilise les appels d'outils si {$GLOBALS["PLAYER_NAME"]} commande quelque chose.
N'effectue des actions et des appels d'outils que si votre personnage le juge nécessaire ou doit le faire, même si cela contredit les demandes de {$GLOBALS["PLAYER_NAME"]}.
";
*/

$COMMAND_PROMPT_ENFORCE_ACTIONS="Choisisit une action cohérente à obéir {$GLOBALS["PLAYER_NAME"]}.";

$DIALOGUE_TARGET="(En train de parler avec {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="";

$RESPONSE_OK_NOTED="D'accord, noté.";

$ERROR_OPENAI="Je ne t'ai pas entendu, peux-tu répéter ?";                           // Dites quelque chose de logique, car cette réponse sera incluse dans le prochain appel. que cette réponse sera incluse dans le prochain appel.
$ERROR_OPENAI_REQLIMIT="Tais-toi, j'ai un souvenir, donne-moi une minute";    // Dites quelque chose de logique, car cette réponse sera incluse dans le prochain appel.
$ERROR_OPENAI_POLICY="Je n'arrive pas à penser clairement maintenant...";               // Dites quelque chose de logique, car cette réponse sera incluse dans le prochain appel.



?>
