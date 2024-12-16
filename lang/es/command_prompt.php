<?php

$COMMAND_PROMPT = "
No escribas narraciones.
";

$COMMAND_PROMPT_FUNCTIONS="Usa # ACTIONS  si tu personaje necesita realizar una acción.";
/*
$COMMAND_PROMPT_FUNCTIONS = "
Usa llamadas a herramientas para controlar las acciones de {$GLOBALS["HERIKA_NAME"]}.
Usa llamadas a herramientas si {$GLOBALS["PLAYER_NAME"]} ordena algo.
Solo realiza acciones y llamadas a herramientas si tu personaje lo encuentra necesario o debe hacerlo, incluso si contradice las peticiones de {$GLOBALS["PLAYER_NAME"]}.
";
*/

$COMMAND_PROMPT_ENFORCE_ACTIONS="Elige una accion coherente para obedecer a {$GLOBALS["PLAYER_NAME"]}.";

$DIALOGUE_TARGET="(Hablando con {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="";

$RESPONSE_OK_NOTED="De acuerdo, anotado.";

$ERROR_OPENAI="No te escuché, ¿puedes repetirlo?";                           // Di algo lógico, ya que esta respuesta se incluirá en la siguiente llamada.
$ERROR_OPENAI_REQLIMIT="Guarda silencio, estoy teniendo un recuerdo, dame un minuto";    // Di algo lógico, ya que esta respuesta se incluirá en la siguiente llamada.
$ERROR_OPENAI_POLICY="No puedo pensar con claridad ahora...";               // Di algo lógico, ya que esta respuesta se incluirá en la siguiente llamada.



?>
